
# REDCap Updates & Upgrades

- [REDCap Updates \& Upgrades](#redcap-updates--upgrades)
  - [Environment Update](#environment-update)
  - [REDCap Application Update](#redcap-application-update)
    - [Method 1 — Automatic via REDCAP\_VERSION](#method-1--automatic-via-redcap_version)
      - [Tracking the latest version (latest-lts / latest-std)](#tracking-the-latest-version-latest-lts--latest-std)
    - [Method 2 — Upgrader Script (redcap-upgrade)](#method-2--upgrader-script-redcap-upgrade)
      - [Interactive wizard](#interactive-wizard)
      - [Non-interactive (scripted) upgrade](#non-interactive-scripted-upgrade)
      - [Using a locally pre-downloaded zip](#using-a-locally-pre-downloaded-zip)
      - [Branch switching (LTS ↔ STD)](#branch-switching-lts--std)
      - [Preview without changes](#preview-without-changes)
      - [Backup and rollback](#backup-and-rollback)
      - [All options](#all-options)
    - [Method 3 — Fully manual update](#method-3--fully-manual-update)

---

There are two distinct kinds of updates:

| Kind | What changes | How |
|---|---|---|
| **Environment** | OS, PHP, ImageMagick, other system packages | Pull new Docker image |
| **Application** | REDCap PHP files and database schema | One of the methods below |

> [!NOTE]
> **REDCap's browser-based "Easy Upgrade" is disabled by default** (`REDCAP_EASY_UPGRADE_ENABLE=false`).
> Easy Upgrade requires the web process to write PHP files into the webroot, which is a security risk on production servers.
> The upgrade methods documented here — `REDCAP_AUTO_UPGRADE`, `redcap-upgrade`, and the SQL script method — do **not** require Easy Upgrade to be enabled and are the recommended upgrade paths.
> See [SECURITY.md — Read-only webroot and Easy Upgrade](SECURITY.md#read-only-webroot-and-easy-upgrade) for details.

---

## Environment Update

Pull the latest image and restart:

```bash
docker compose pull
docker compose down && docker compose up -d
```

Check [Docker Hub tags](https://hub.docker.com/r/dzdde/redcap-docker/tags) or [GitHub releases](https://github.com/DZD-eV-Diabetes-Research/redcap-docker/releases) for what changed.

---

## REDCap Application Update

### Method 1 — Automatic via REDCAP_VERSION

> [!WARNING]
> **This feature is currently in BETA.**
> It has been tested on a limited number of REDCap versions and MySQL configurations.
> Always verify the post-upgrade backup before deleting it, and keep the old version
> directory until you are confident the upgrade succeeded.
> Please report any issues at https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues

The fully automated path. Declare the desired version in your compose file and the container reconciles the state on every boot.

```yaml
services:
  redcap:
    environment:
      REDCAP_VERSION: "14.9.5"
      REDCAP_AUTO_UPGRADE: "true"           # required to enable auto-upgrade
      REDCAP_COMMUNITY_USER: your_username
      REDCAP_COMMUNITY_PASSWORD: your_password
```

**Behaviour table:**

| Installed vs desired | `REDCAP_AUTO_UPGRADE` | Action |
|---|---|---|
| Nothing installed | any | Download and install on first boot |
| Same version | any | Skip — nothing to do |
| Older installed | `true` | Auto-upgrade on boot |
| Older installed | `false` (default) | Log warning, continue with existing version |
| Newer installed | any | Skip — never auto-downgrade |

> [!TIP]
> The conservative default (`REDCAP_AUTO_UPGRADE=false`) means bumping `REDCAP_VERSION` in your compose file logs a warning but does **not** upgrade automatically. This lets you review before upgrading — either set the flag or run `redcap-upgrade` manually.

> [!NOTE]
> When auto-upgrading, a pre-upgrade database backup is always created automatically. See [Backup and rollback](#backup-and-rollback).

#### Tracking the latest version (`latest-lts` / `latest-std`)

Instead of a pinned version number, `REDCAP_VERSION` accepts two symbolic values:

| Value | Resolves to |
|---|---|
| `latest-lts` | Newest **LTS** (long-term support) release on the community portal |
| `latest-std` | Newest **STD** (standard) release on the community portal |

The value is resolved against the community portal **on every boot** (the version-listing endpoint is public — no credentials are needed to resolve, only to download). The resolved concrete version then flows through the exact same decision table as a pinned version:

```yaml
services:
  redcap:
    environment:
      REDCAP_VERSION: "latest-lts"
      REDCAP_AUTO_UPGRADE: "true"           # required for unattended upgrades
      REDCAP_COMMUNITY_USER: your_username
      REDCAP_COMMUNITY_PASSWORD: your_password
```

- `latest-lts` **alone** (no `REDCAP_AUTO_UPGRADE`) installs the newest LTS on first boot, and on later boots only **logs a warning** if a newer release exists — it does not upgrade.
- `latest-lts` **+** `REDCAP_AUTO_UPGRADE=true` is fully automated: it resolves the newest release and upgrades on every reboot.

> [!WARNING]
> ## ⚠️ Uncharted waters — not currently recommended
>
> Combining a `latest-*` value with `REDCAP_AUTO_UPGRADE=true` means your instance will **download and apply the newest REDCap release — including its database schema migrations — automatically on every container reboot, with no human review of a version you have not tested.**
>
> This is powerful but risky:
> - A reboot could pull a REDCap release that breaks your plugins, hooks, or workflows.
> - Schema migrations run unattended; a failed migration mid-upgrade auto-rolls back, but recovery is still disruptive.
> - Your deployment is no longer reproducible — two identical reboots on different days can land on different versions.
>
> **We do not recommend this combination at this time.** It exists for users who explicitly want hands-off, always-current instances and accept the risk. Both `REDCAP_VERSION=latest-*` and `REDCAP_AUTO_UPGRADE=true` must be set **deliberately** — neither is a default. For production, **pin an explicit version** (e.g. `REDCAP_VERSION: "16.0.34"`) and upgrade on a reviewed schedule. **Always keep verified backups.**

---

### Method 2 — Upgrader Script (redcap-upgrade)

> [!WARNING]
> **This feature is currently in BETA.**
> It has been tested on a limited number of REDCap versions and MySQL configurations.
> Always verify the post-upgrade backup before deleting it, and keep the old version
> directory until you are confident the upgrade succeeded.
> Please report any issues at https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues

The container ships a built-in upgrade command that handles the full upgrade process in-place — no container restart required.

It:
1. Creates a **compressed database backup** before touching anything.
2. Takes REDCap **offline** in the database.
3. Downloads the target version zip from the REDCap community portal (or uses a locally provided zip).
4. Extracts the new `redcap_vX.X.X/` directory.
5. Runs all relevant **SQL upgrade scripts** found in the zip.
6. Installs the new version directory into the document root.
7. Updates the `redcap_version` record in the database.
8. Keeps the old version directory in place (needed for a clean rollback).
9. Brings REDCap back **online** automatically.

#### Interactive wizard

The easiest way to upgrade. Call `redcap-upgrade` with no arguments while attached to a terminal:

```bash
docker compose exec -it redcap redcap-upgrade
```

The wizard fetches the available versions list (no credentials needed for that part) and walks you through the rest:

```
=== REDCap Upgrade Wizard ===

Installed version: 16.0.15

── Available Updates ─────────────────────────────────────────
Fetching available versions... found 33
Current branch  : lts

LTS versions:
  16.0.16  (2026-03-05)
  ...
  16.0.30  (2026-05-14)  <-- recommended (your branch, latest)

STD versions:
  16.1.1  (2026-02-12)
  ...
  17.0.8  (2026-05-14)

Which version would you like to install [16.0.30]:

── Community Portal Credentials ──────────────────────────────
Username: (set via environment)
Password: (set via environment)

── Upgrade Plan ──────────────────────────────────────────────
  From           : 16.0.15
  To             : 16.0.30
  Source         : community portal download
  Database backup: /opt/redcap-docker/backups
  Offline mode   : yes (automatic)
  Keep old dir   : yes (for rollback)

Start upgrade? [Y/n]:
```

During download a live progress bar is shown:

```
  [===============>             ]  54%   27.8 /  51.3 MB    4.2 MB/s
```

> [!NOTE]
> The `-it` flag on `docker compose exec` is required. Without it Docker does not allocate a terminal and the wizard will not start.

#### Non-interactive (scripted) upgrade

Set credentials once in your `docker-compose.yaml`:

```yaml
services:
  redcap:
    environment:
      REDCAP_COMMUNITY_USER: your_community_username
      REDCAP_COMMUNITY_PASSWORD: your_community_password
```

Then trigger the upgrade from the host:

```bash
docker compose exec redcap redcap-upgrade --version 14.9.5
```

#### Using a locally pre-downloaded zip

For servers without outbound internet access:

```bash
# Copy the zip to the REDCap data volume (already mounted)
cp ~/Downloads/redcap_v14.9.5.zip ./data/redcap/

# Run the upgrade pointing at the local file
docker compose exec redcap redcap-upgrade --zip /var/www/html/redcap_v14.9.5.zip
```

#### Branch switching (LTS ↔ STD)

REDCap releases on two tracks:

| Branch | Description |
|---|---|
| **LTS** | Long-term support — fewer, stability-focused releases |
| **STD** | Standard — more frequent releases with new features |

When you are already on the latest version of your current branch, the wizard detects this and asks whether you want to switch to a newer branch:

```
You are already on the latest LTS version (16.0.30).

STD versions:
  16.1.1  (2026-02-12)
  ...
  17.0.8  (2026-05-14)  <-- latest

Note: switching branches is a one-way upgrade — you cannot go back to LTS without a rollback.

Switch to a newer branch? [y/N]:
```

> [!WARNING]
> Switching from LTS to STD is effectively a one-way change. Use `--rollback` if you need to revert.

#### Preview without changes

```bash
docker compose exec redcap redcap-upgrade --version 14.9.5 --dry-run
```

#### Backup and rollback

The upgrader creates a compressed database dump **before every upgrade** in `/opt/redcap-docker/backups/` (configurable via `REDCAP_UPGRADE_BACKUP_DIR` or `--backup-dir`).

> [!TIP]
> Mount the backup directory on the host so backups survive container restarts:
> ```yaml
> volumes:
>   - ./backups:/opt/redcap-docker/backups
> ```

If the SQL step fails mid-upgrade the database is **automatically restored** from the backup. The old version PHP directory is always kept so a rollback restores both layers cleanly.

To manually roll back after a completed upgrade:

```bash
docker compose exec redcap redcap-upgrade \
    --rollback /opt/redcap-docker/backups/redcap_backup_20260518_143022_from_14.8.0_to_14.9.5.sql.gz
```

Once satisfied, clean up manually:

```bash
rm -rf /var/www/html/redcap_v14.8.0
rm /opt/redcap-docker/backups/redcap_backup_20260518_143022_from_14.8.0_to_14.9.5.sql.gz
```

#### All options

```
--version <X.X.X>         Version to download from the community portal.
                          Also accepts 'latest-lts' / 'latest-std' to resolve
                          the newest version on that branch from the portal.
--zip <path>              Use a local zip instead of downloading.
--community-user          Community portal username (or REDCAP_COMMUNITY_USER env var).
--community-password      Community portal password (or REDCAP_COMMUNITY_PASSWORD env var).
--no-backup               Skip the pre-upgrade database backup (disables auto-rollback).
--backup-dir <path>       Backup directory (env: REDCAP_UPGRADE_BACKUP_DIR).
--backup-db-user <user>   Elevated DB user for the backup (e.g. root).
--backup-db-password <p>  Password for --backup-db-user.
--rollback <file>         Restore a backup and remove the version installed after it.
--dry-run                 Show what would happen without making changes.
--keep-old                Keep the previous version directory (default when backup is on).
--no-sql                  Skip SQL upgrade scripts (file extraction only).
--no-offline              Do not toggle the REDCap offline flag during upgrade.
--help                    Show full help text.
```

> [!NOTE]
> The upgrader sets REDCap to offline mode for the duration of the upgrade and restores it to online on completion (even on failure).

> [!IMPORTANT]
> SQL upgrade scripts are only run for versions strictly newer than the currently installed version. If you skip multiple minor versions, make sure the zip for your target version includes all intermediate SQL files (REDCap normally ships them all in each release).

---

### Method 3 — Fully manual update

Use this when you have no community portal credentials, prefer full control, or the other methods are not an option.

**Step 1 — Download the new REDCap version**

Log in to the [REDCap community portal](https://redcap.vumc.org/community/) and download the zip for the target version (e.g. `redcap14.9.5.zip`).

**Step 2 — Extract and copy the files to your volume**

The REDCap volume mount on the host (e.g. `./data/redcap`) is the container's document root. Extract the new version directory there alongside the existing one:

```bash
unzip redcap14.9.5.zip -d ./data/redcap/
```

After extraction you should have both the old and new version directories on disk:

```
./data/redcap/
  redcap_v14.8.0/    ← old version, keep until you are satisfied
  redcap_v14.9.5/    ← new version
  database.php
```

**Step 3 — Run any required SQL upgrade scripts**

Some REDCap versions ship a SQL upgrade script that must be executed against the database. Check the REDCap upgrade page inside your running instance — it will tell you if a SQL script is required.

If one is needed, copy it into a directory that is mounted as the run-once SQL location:

```yaml
# docker-compose.yaml
services:
  redcap:
    volumes:
      - ./data/redcap:/var/www/html
      - ./sql_scripts:/opt/redcap-docker/sql_scripts_run_once  # add this
```

```bash
# Place the SQL file from the REDCap zip into that directory
cp ./data/redcap/redcap_v14.9.5/Resources/sql/upgrade_redcap_v14.9.5.sql ./sql_scripts/
```

Then restart the container. The script is executed automatically on boot and tracked by hash so it never runs twice:

```bash
docker compose down && docker compose up -d
docker compose logs redcap | grep "RUN CUSTOM BOOT SQLS"
# redcap-1  | [RUN CUSTOM BOOT SQLS] Try run file: '...upgrade_redcap_v14.9.5.sql'
```

> [!TIP]
> You can keep the `sql_scripts_run_once` volume mount permanently. Scripts are tracked by hash and will never execute again on subsequent restarts.

**Step 4 — Clean up the old version**

Once you have verified the new version is working correctly, remove the old version directory from the host volume:

```bash
rm -rf ./data/redcap/redcap_v14.8.0
```
