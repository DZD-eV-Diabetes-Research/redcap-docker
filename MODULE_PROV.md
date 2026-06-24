# DZD Docker REDCap External Module Provisioning

- [DZD Docker REDCap External Module Provisioning](#dzd-docker-redcap-external-module-provisioning)
- [What this does](#what-this-does)
- [Enable or disable module provisioning](#enable-or-disable-module-provisioning)
- [Install-only vs. enable (the default policy)](#install-only-vs-enable-the-default-policy)
- [Module data structure/model](#module-data-structuremodel)
  - [Sources](#sources)
    - [`github` — a GitHub release/tag](#github--a-github-releasetag)
    - [`url` — an arbitrary zip](#url--an-arbitrary-zip)
    - [`local` — already mounted into `/modules`](#local--already-mounted-into-modules)
    - [`repo` — the REDCap central repository](#repo--the-redcap-central-repository)
  - [Common fields](#common-fields)
- [Providing module data](#providing-module-data)
  - [Option 1 — JSON list in an environment variable](#option-1--json-list-in-an-environment-variable)
  - [Option 2 — Indexed environment variables](#option-2--indexed-environment-variables)
  - [Option 3 — YAML or JSON files](#option-3--yaml-or-json-files)
- [How it works under the hood](#how-it-works-under-the-hood)
- [Updating modules & `latest`](#updating-modules--latest)
- [Security considerations](#security-considerations)
- [Environment variable reference](#environment-variable-reference)

# What this does

This container image can make [REDCap external modules](https://github.com/vanderbilt-redcap/external-module-framework-docs/blob/main/README.md)
available — and optionally enable and configure them — automatically on
container boot. No manual file copying, no clicking through the *External
Modules* control center.

It mirrors the [user provisioning](USER_PROV.md) feature: you describe the
modules you want via environment variables and/or YAML/JSON files, and the
container takes care of the rest on startup.

> [!IMPORTANT]
> **With the default security hardening, REDCap's built-in web UI module
> installer does not work.** The read-only webroot prevents the `www-data` web
> process from writing into `/modules`, so the Control Center actions
> *"View modules available in the REDCap Repo" → Download* and
> *"Upload a module ZIP"* will fail. This is by design — the same hardening that
> disables REDCap's browser-based "Easy Upgrade" (see [SECURITY.md](SECURITY.md)).
>
> This module provisioning feature is the supported replacement: it places module
> files during container startup while it still runs as `root`, then re-applies
> the read-only webroot permissions. If you specifically want the web UI installer
> back, set `REDCAP_EASY_UPGRADE_ENABLE=true` (not recommended for production).

# Enable or disable module provisioning

Set `ENABLE_MODULE_PROV` to `true` or `false` (default `false`) to turn the
feature on or off.

```env
ENABLE_MODULE_PROV=true
```

Provisioning only runs once REDCap is installed (the `redcap_config` table
exists). On a brand-new instance that is being auto-installed, it runs on the
same boot, after the install step.

# Install-only vs. enable (the default policy)

By default a provisioned module is **installed only**: its files are placed in
`/modules` so it shows up as *available* in the External Modules control
center, but it is **not** system-enabled. An administrator can then enable it
from the UI.

The global default is controlled by `MODULE_PROV_DEFAULT_ENABLE` (default
`false` = install-only):

```env
MODULE_PROV_DEFAULT_ENABLE=false   # install only, admin enables manually (default)
MODULE_PROV_DEFAULT_ENABLE=true    # system-enable every provisioned module
```

Each module entry can override the global policy with its own `enabled` field.
Specifying `projects` implies an enable (a module must be system-enabled before
it can be enabled for a project).

| `MODULE_PROV_DEFAULT_ENABLE` | entry `enabled` | result |
| --- | --- | --- |
| `false` | _unset_ | install only |
| `false` | `true` | system-enabled |
| `true` | _unset_ | system-enabled |
| `true` | `false` | install only |

# Module data structure/model

Each module is described by an object. The required fields depend on the
`source`.

## Sources

### `github` — a GitHub release/tag

Downloads a release (or tag/branch) zip straight from a GitHub repository.

| field | required | description |
| --- | --- | --- |
| `source` | yes | `github` |
| `repo` | yes | `owner/name` slug, e.g. `vanderbilt-redcap/redcap-banner` |
| `version` | no | release tag (a leading `v` is optional). Set to `latest` or omit to always use the **latest** release (see [Updating modules & `latest`](#updating-modules--latest)). |
| `prefix` | no | module directory prefix. Derived from the repo name when omitted. |
| `github_token` | no | token for private repos / higher API rate limits |

```yaml
source: github
repo: vanderbilt-redcap/survey-ui-tweaks
version: 1.4.2          # pin a release; use "latest" (or omit) to auto-update
prefix: survey_ui_tweaks # optional; derived from repo name otherwise
enabled: true
```

> [!TIP]
> Pin an explicit `version` for reproducible deployments. `latest` changes over
> time and pulls in whatever the upstream maintainer last released — convenient,
> but see the security note in [Updating modules & `latest`](#updating-modules--latest).

### `url` — an arbitrary zip

Downloads a module zip from any `https://` URL (self-hosted, private registry,
artifact storage, …). Because the folder name cannot be derived from an
arbitrary archive, you **must** provide both `prefix` and `version`.

| field | required | description |
| --- | --- | --- |
| `source` | yes | `url` |
| `url` | yes | direct https link to the module zip |
| `prefix` | yes | module directory prefix |
| `version` | yes | module version |

```yaml
source: url
url: https://files.example.org/modules/my_module-2.0.0.zip
prefix: my_module
version: 2.0.0
```

### `local` — already mounted into `/modules`

Use this when you bind-mount a prepared module directory. The container only
enables/configures it; it does not fetch anything.

| field | required | description |
| --- | --- | --- |
| `source` | yes | `local` |
| `prefix` | yes | module directory prefix already present in `/modules` |
| `version` | no | specific version; the highest present is chosen when omitted |

```yaml
source: local
prefix: my_inhouse_module
version: 1.0.0   # optional
enabled: true
```

Mount the module so it lands at `<webroot>/modules/<prefix>_v<version>/`, e.g.
`./my_module:/var/www/html/modules/my_inhouse_module_v1.0.0`.

> [!NOTE]
> REDCap requires the module directory name to follow `<prefix>_v<version>` — the
> version part **must** carry a leading `v` (e.g. `_v1.0.0`). A directory without
> it (e.g. `_1.0.0`) is ignored by REDCap. You give `version` here without the
> `v`; the container matches it against the `v`-prefixed directory.

### `repo` — the REDCap central repository

Downloads a module published in the official REDCap repository, using REDCap's
own downloader. Requires outbound internet access to the REDCap community
servers (`https://redcap.vumc.org/consortium/modules/`). It does **not** require
your community login — the download only sends your `institution` and server
name. Modules are identified by their **numeric** module id.

| field | required | description |
| --- | --- | --- |
| `source` | yes | `repo` |
| `module_id` | yes | numeric id of the module in the REDCap central repository |

```yaml
source: repo
module_id: 1234
enabled: true
```

#### How to find a module's `module_id`

The id is **not** shown in the repo's web page text — it lives in the
"Download" button. Pick whichever method is convenient:

**Option A — from a REDCap Control Center (browser)**

1. Go to *Control Center → External Modules → "View modules available in the
   REDCap Repo"*.
2. Find your module, right-click its **Download** button → *Inspect*.
3. The button's `onclick` reads `downloadModule('<module_id>', ...)` — the first
   argument is the id.

> Under this container's hardened webroot the Download button itself does not
> work (that's the whole point of this feature), but the id is still readable in
> the page markup.

**Option B — from the consortium repo (no REDCap needed)**

The public module list embeds the same `downloadModule('<id>', …)` calls. Grep
for your module's directory name (e.g. `popup_alerts_v1.1.1`):

```bash
curl -s https://redcap.vumc.org/consortium/modules/index.php \
  | grep -oE "downloadModule\('[0-9]+','[^']*','popup_alerts_v1\.1\.1'\)"
# downloadModule('2919','Popup+Alerts+%28popup_alerts_v1.1.1%29','popup_alerts_v1.1.1')
#                  ^^^^ module_id
```

The first number is the `module_id` (here `2919`). The third argument confirms
the exact `<prefix>_v<version>` you will get.

## Common fields

These apply to every source:

| field | description |
| --- | --- |
| `enabled` | `true`/`false` — override the global `MODULE_PROV_DEFAULT_ENABLE` policy for this module |
| `projects` | list of project ids to enable the module for (implies a system enable) |
| `settings` | object of system-level settings to apply after enabling (`key: value`) |
| `title` | cosmetic label used only in log output |

```yaml
source: github
repo: org/cool-module
version: 3.1.0
enabled: true
projects: [12, 34]
settings:
  api_url: https://api.example.org
  timeout_seconds: 30
```

# Providing module data

There are three ways to provide module data — identical in spirit to user
provisioning.

## Option 1 — JSON list in an environment variable

A one-line JSON list (or single object) in `MODULE_PROV`:

```env
MODULE_PROV='[{"source":"github","repo":"org/mod-a","version":"1.0.0","enabled":true},{"source":"repo","module_id":1234}]'
```

## Option 2 — Indexed environment variables

Append `_` + a sequential number to `MODULE_PROV` to keep entries readable:

```env
MODULE_PROV_1='{"source":"github","repo":"org/mod-a","version":"1.0.0","enabled":true}'
MODULE_PROV_2='{"source":"repo","module_id":1234}'
```

## Option 3 — YAML or JSON files

For anything beyond a couple of modules, use files. The container scans the
directory in `MODULE_PROV_FILE_DIR` (default `/opt/redcap-docker/modules`) for
`.yaml`, `.yml` and `.json` files. Each file has a root key `REDCapModuleList`
containing a list of module objects.

**YAML file example:**

```yaml
REDCapModuleList:
  - source: github
    repo: vanderbilt-redcap/survey-ui-tweaks
    version: 1.4.2
    enabled: true
  - source: local
    prefix: my_inhouse_module
    version: 1.0.0
    enabled: true
    projects: [1]
  - source: repo
    module_id: 1234
```

**JSON file example:**

```json
{
  "REDCapModuleList": [
    {
      "source": "github",
      "repo": "vanderbilt-redcap/survey-ui-tweaks",
      "version": "1.4.2",
      "enabled": true
    },
    {
      "source": "url",
      "url": "https://files.example.org/modules/my_module-2.0.0.zip",
      "prefix": "my_module",
      "version": "2.0.0"
    }
  ]
}
```

Mount your file directory into the container, e.g.:

```yaml
volumes:
  - ./modules:/opt/redcap-docker/modules:ro
```

# How it works under the hood

1. The startup script `140_module_provisioning.php` collects all module specs
   from environment variables and files.
2. For `github`, `url` and `local` sources it acquires the files and places
   them at `<webroot>/modules/<prefix>_v<version>/`, re-applying the read-only
   webroot ownership/permissions so Apache can read them.
3. It then hands a task list to a child process
   (`php_helpers/redcap_module_enabler.php`) that bootstraps REDCap and uses
   REDCap's **own External Module framework API** to download `repo` modules
   and to enable/configure modules. Using the framework API (rather than raw
   SQL) gives correct config-default settings, cron-job registration,
   PHP/REDCap compatibility checks and properly typed setting storage.

Operations are idempotent: a module already present in `/modules` is left
untouched, and a module already system-enabled at the requested version is not
re-enabled.

# Updating modules & `latest`

Provisioning re-runs on **every container start**, so it doubles as an update
mechanism:

- **Pinned version (`version: 1.4.2`)** — bump the value and restart the
  container. The new version is downloaded and the module is switched over to it.
- **`version: latest` (or omitted)** — on every boot the newest release is
  resolved and, if it is newer than what is enabled, downloaded and switched to
  automatically. This is the "always keep it updated" mode.

How a version switch works: when a module is already enabled at a different
version, the container first clears the old enabled version (a database-only
disable that preserves your system settings and project-level enablement), then
enables the new one via REDCap's framework — so config-default settings and cron
jobs are re-initialised for the new version. Project enablements and any
settings you set previously are kept.

Notes and limits:

- Updates happen **at container start**, not while running. A running instance is
  not auto-updated until it restarts.
- `latest` is supported for **`github`** (newest GitHub release) and, by
  omitting `version`, for **`local`** (highest `_v<version>` directory mounted).
  It is **not** supported for `url` (an arbitrary zip has no version to resolve)
  or `repo` (central-repo `module_id`s are version-specific — bump the id to
  update).
- Old version directories are left in `/modules` after an update (they are simply
  ignored by REDCap once disabled); clean them up manually if disk usage matters.

> [!CAUTION]
> `latest` runs whatever the maintainer most recently released, **unreviewed**,
> inside your webroot, and a release made between two restarts will be picked up
> automatically. For production, pin explicit versions and update deliberately.

# Security considerations

> [!CAUTION]
> Provisioning a module executes third-party PHP inside your REDCap webroot.
> Only provision modules you trust.

- **Pin versions.** Prefer explicit `version`/tags over `latest`, and prefer
  `repo`/`local` (reviewed, mounted) sources for production.
- **Use tokens via secrets.** Supply `github_token` / `MODULE_PROV_GITHUB_TOKEN`
  through Docker Secrets rather than plaintext env vars where possible.
- Provisioning honours the hardened webroot: after placing files the container
  restores the read-only permissions, so the running web process still cannot
  write PHP into the webroot.

See [SECURITY.md](SECURITY.md) for the container's overall hardening model.

# Environment variable reference

| Variable | Default | Description |
| --- | --- | --- |
| `ENABLE_MODULE_PROV` | `false` | Master switch for the feature |
| `MODULE_PROV_DEFAULT_ENABLE` | `false` | Global policy: `false` = install only, `true` = system-enable provisioned modules |
| `MODULE_PROV` | _unset_ | JSON list (or single object) of module specs |
| `MODULE_PROV_1` … `MODULE_PROV_N` | _unset_ | Indexed single-object module specs |
| `MODULE_PROV_FILE_DIR` | `/opt/redcap-docker/modules` | Directory scanned for `.yaml`/`.yml`/`.json` module files |
| `MODULE_PROV_GITHUB_TOKEN` | _unset_ | Default GitHub token for `github`/`url` downloads |

See [examples/instance_with_modules](examples/instance_with_modules) for a
working `docker compose` setup.
