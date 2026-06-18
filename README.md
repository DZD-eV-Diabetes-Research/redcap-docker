
# redcap-docker

Yet another try to containerize [REDCap](https://www.project-redcap.org/) — but with a focus on **automated, hands-off deployments**.

**Status**: Usable - beta  
**Maintainer**: Tim Bleimehl, DZD  
**Docker image**: [dzdde/redcap-docker](https://hub.docker.com/r/dzdde/redcap-docker)  
**Source**: [github.com/DZD-eV-Diabetes-Research/redcap-docker](https://github.com/DZD-eV-Diabetes-Research/redcap-docker)


> [!IMPORTANT]
> If you find this tool useful and decide to test it, we would greatly appreciate your feedback. So far, we have only limited data on its usage in other environments. Feel free to leave your feedback in the [GitHub repository issues](
https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues)

---

- [redcap-docker](#redcap-docker)
  - [Disclaimer](#disclaimer)
  - [About \& Motivation](#about--motivation)
  - [Features](#features)
  - [Security](#security)
  - [Quick Start](#quick-start)
    - [Option A — Auto-install via community portal](#option-a--auto-install-via-community-portal)
    - [Option B — Mount your own REDCap files](#option-b--mount-your-own-redcap-files)
  - [Container Reference](#container-reference)
    - [Environment Variables](#environment-variables)
      - [REDCap application settings](#redcap-application-settings)
      - [Email](#email)
      - [Cron mode](#cron-mode)
    - [Volumes \& Paths](#volumes--paths)
      - [REDCap file repository](#redcap-file-repository)
    - [User Provisioning](#user-provisioning)
    - [File Ownership](#file-ownership)
  - [Updates \& Upgrades](#updates--upgrades)
  - [Beta Channel](#beta-channel)
  - [Contributing \& Development](#contributing--development)
  - [Example Setups](#example-setups)
  - [Roadmap](#roadmap)

---

## Disclaimer

This is not an official REDCap project. We are an institutional partner of the REDCap Consortium, but beyond that we have no connection to REDCap - we are simple REDCap users.

This project does **not** distribute REDCap and never will. It is a wrapper to help deploy REDCap. You still need to provide your own licensed copy of REDCap, or a valid login to the REDCap community portal to download it.

> [!CAUTION]
> We are not responsible for any data loss or damage that may occur from the use of this container image. Use it at your own risk. **Make backups.**

---

## About & Motivation

Our infrastructure emphasises automation and reproducibility through containerization. While excellent solutions like [Andy's redcap-docker-compose](https://github.com/123andy/redcap-docker-compose) exist and were a great help when building this repo, we could not adapt them to our environment without manual intervention during setup.

This project is our attempt to containerize REDCap in a way that allows deploying a fresh instance with **zero manual steps**.

**Target audience**

- **IT Operators** — professionals with container operations knowledge who need to deploy and manage REDCap instances.
- **REDCap Admins** — users familiar with Docker or Podman who need local REDCap instances for testing or experimentation.

---

## Features

- 🧪 **[BETA](#beta-channel)**  **Auto-install/upgrade/update** Set `REDCAP_VERSION` and your community portal credentials and the container downloads and installs REDCap on first boot, no manual file copying required
- 🧪 **[BETA](#beta-channel)** **Manual upgrade/update-wizard** (`redcap-upgrade`) Checks for updates, downloads from the REDCap community portal, runs SQL migrations, creates a database backup, and rolls back. All from a single command inside the running container. See [Updates & Upgrades](#updates--upgrades).
- 🧪 **[BETA](#beta-channel)** **Security-hardened by default** — read-only webroot, HTTP security headers, PHP hardening, Docker Secrets support. REDCap's browser-based Easy Upgrade is disabled by default (see [Security](#security)).
- 🧪 **[BETA](#beta-channel)** **Automated testing** We run an automated testing rig, to validate security and functionality before every change and/or release (see [Testing](tests/README.md)).
- Database and application configuration entirely via environment variables
- Automated DB schema installation . No need to manually run install SQL scripts
- Simple mail setup via environment variables (`msmtprc`-based)
- Automated routine tasks such as deactivating the default admin (optional)
- "Cron mode": run the same image as the REDCap cron job manager
- User provisioning via environment variables and/or YAML files


---

## Security

The container ships with several hardening defaults:

- **Read-only webroot** — the `www-data` process cannot write PHP files into the webroot, closing off the main vector for persistent backdoor injection. REDCap's built-in browser-based upgrade ("Easy Upgrade") is **disabled by default** for this reason. The container's own `redcap-upgrade` command and `REDCAP_AUTO_UPGRADE` are unaffected and remain the recommended upgrade path.
- **HTTP security headers** — `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` — sent on every response.
- **PHP hardening** — `expose_php = Off`, `display_errors = Off`.
- **Docker Secrets support** — sensitive variables (`DB_PASSWORD`, `DB_SALT`, `REDCAP_COMMUNITY_PASSWORD`) can be loaded from files via `_FILE` variants to avoid plaintext env vars.
- **File integrity checker** — `redcap-integrity-check` compares the running installation against a canonical download to detect injected or modified PHP files.

Full details, configuration options, and production hardening guidance: [SECURITY.md](SECURITY.md)

---

## Quick Start

> This guide assumes basic familiarity with `docker` and `docker compose` and that both are installed.

There are two ways to get REDCap files into the container. Choose the one that fits your setup.

### Option A — Auto-install via community portal

The recommended path for new deployments. The container downloads REDCap directly from the community portal on first boot.

**Requirements:** a valid REDCap community portal account.

```yaml
# docker-compose.yaml
services:
  redcap:
    image: dzdde/redcap-docker
    environment:
      # --- REDCap source ---
      REDCAP_VERSION: "14.9.5"
      REDCAP_COMMUNITY_USER: your_community_username
      REDCAP_COMMUNITY_PASSWORD: your_community_password

      # --- Database ---
      DB_HOSTNAME: db
      DB_NAME: redcap
      DB_USERNAME: redcap
      DB_PASSWORD: redcap123
      DB_SALT: changeme_use_a_random_string

      # --- REDCap base URL ---
      RCCONF_redcap_base_url: "http://localhost"
    volumes:
      - ./data/redcap:/var/www/html   # REDCap files persist here
      - ./data/backups:/opt/redcap-docker/backups
    ports:
      - "80:80"
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mysql:lts
    environment:
      MYSQL_DATABASE: redcap
      MYSQL_USER: redcap
      MYSQL_PASSWORD: redcap123
      MYSQL_ROOT_PASSWORD: redcaproot123
    volumes:
      - ./data/db:/var/lib/mysql
    healthcheck:
      test: "/usr/bin/mysql -u $$MYSQL_USER -p$$MYSQL_PASSWORD $$MYSQL_DATABASE -e 'SHOW TABLES;'"
      interval: 5s
      timeout: 3s
      retries: 5
```

```bash
docker compose up -d
docker compose logs -f redcap   # watch the install progress
```

On first boot the container downloads the REDCap zip, extracts it, and runs the install SQL. Subsequent boots skip the download — the files are already on the volume.

> [!TIP]
> Set `REDCAP_AUTO_UPGRADE=true` to also allow automatic upgrades on boot when `REDCAP_VERSION` is bumped. Without this flag the container will log a warning and continue with the existing version, requiring a manual `redcap-upgrade` run.

### Option B — Mount your own REDCap files

If you prefer to manage the REDCap source yourself, mount it at the document root. No community portal credentials are needed.

```yaml
services:
  redcap:
    image: dzdde/redcap-docker
    environment:
      DB_HOSTNAME: db
      DB_NAME: redcap
      DB_USERNAME: redcap
      DB_PASSWORD: redcap123
      DB_SALT: changeme_use_a_random_string
      RCCONF_redcap_base_url: "http://localhost"
    volumes:
      - ./data/redcap:/var/www/html   # must contain redcap_vX.X.X/ directory
    ports:
      - "80:80"
```

Place your REDCap source (the `redcap_vX.X.X/` directory) inside `./data/redcap/` before starting the container.

See the [minimal example](examples/local_instance_basic) for a complete working compose file.

---

## Container Reference

### Environment Variables

See [config_vars_list.md](config_vars_list.md) for the full variable reference.

| Category | Key variables |
|---|---|
| **REDCap source** | `REDCAP_VERSION`, `REDCAP_AUTO_UPGRADE`, `REDCAP_COMMUNITY_USER`, `REDCAP_COMMUNITY_PASSWORD` |
| **Database** | `DB_HOSTNAME`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SALT` |
| **Installation** | `REDCAP_INSTALL_ENABLE`, `REDCAP_INSTALL_SQL_SCRIPT_PATH` |
| **Application** | Prefix any `redcap_config` key with `RCCONF_` — e.g. `RCCONF_auth_meth_global=table` |
| **Email** | Prefix any msmtp config key with `MSMTP_` — e.g. `MSMTP_host=mail.example.com` |
| **Cron** | `CRON_MODE`, `CRON_INTERVAL`, `CRON_RUN_JOB_ON_START` |
| **User provisioning** | `USER_PROV`, `USER_PROV_FILE_DIR`, `USER_PROV_OVERWRITE_EXISTING` |
| **Upgrades** | `REDCAP_UPGRADE_BACKUP_DIR`, `REDCAP_UPGRADE_BACKUP_DB_USER` |
| **Security** | `REDCAP_EASY_UPGRADE_ENABLE` (default `false`), `REDCAP_INTEGRITY_CHECK_ON_BOOT` (default `false`), `*_FILE` secret variants — see [SECURITY.md](SECURITY.md) |
| **Server** | `SERVER_NAME`, `APACHE_DOCUMENT_ROOT`, `WWW_DATA_UID`, `WWW_DATA_GID` |

#### REDCap application settings

Any key from the `redcap_config` database table can be set at startup by prefixing it with `RCCONF_`:

```env
RCCONF_auth_meth_global=table
RCCONF_redcap_base_url=https://redcap.example.com
RCCONF_institution=My Institution
```

See [config_vars_list.md — REDCap Application Config vars](config_vars_list.md#redcap-application-config-vars) for the full list.

#### Email

Mail is sent via `msmtp`. Prefix any msmtp config directive with `MSMTP_`:

```env
MSMTP_host=mail.example.com
MSMTP_port=587
MSMTP_auth=on
MSMTP_user=redcap@example.com
MSMTP_password=secret
MSMTP_tls=on
MSMTP_tls_starttls=on
RCCONF_from_email=redcap@example.com
```

See [config_vars_list.md — MSMTP](config_vars_list.md#msmtp) for all options.

#### Cron mode

Run the REDCap cron process in a separate container using the same image:

```yaml
services:
  redcap-cron:
    image: dzdde/redcap-docker
    environment:
      CRON_MODE: "true"
      CRON_INTERVAL: "*/5 * * * *"
      # same DB_* and REDCAP_* vars as the web container
```

See the [cron example](examples/instance_with_cron) for a full setup.

---

### Volumes & Paths

| Path inside container | Purpose | Env var override |
|---|---|---|
| `/var/www/html` | REDCap PHP files (Apache document root) | `APACHE_DOCUMENT_ROOT` |
| `/config/php/custom_inis` | Drop `.ini` files here to extend PHP config | `PHP_INI_SCAN_DIR` |
| `/config/apache/custom.virtualhost` | Custom Apache virtual-host directives | — |
| `/config/redcap/install/install.sql` | Optional custom install SQL script | `REDCAP_INSTALL_SQL_SCRIPT_PATH` |
| `/opt/redcap-docker/users` | User provisioning YAML/JSON files | `USER_PROV_FILE_DIR` |
| `/opt/redcap-docker/sql_scripts_run_once` | SQL scripts to run once at boot (e.g. manual upgrade scripts) | `AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION` |
| `/opt/redcap-docker/backups` | Pre-upgrade database backups | `REDCAP_UPGRADE_BACKUP_DIR` |

> [!TIP]
> Mount `/opt/redcap-docker/backups` to the host so backups survive container restarts:
> ```yaml
> volumes:
>   - ./backups:/opt/redcap-docker/backups
> ```

#### REDCap file repository

By default user-uploaded files live inside the document root at `/var/www/html/edocs`. To move them outside the web root (recommended):

```env
RCCONF_edoc_path=/data
```

And add a volume mount for `/data`. See the [custom edocs example](examples/local_instance_custom_edocs).

---

### User Provisioning

Pre-populate REDCap with table-based users on container boot, via environment variables or YAML/JSON files.

- Full documentation: [USER_PROV.md](USER_PROV.md)
- Working example: [docker compose example](examples/local_instance_with_user_prov)

```env
# Simplest form — inline JSON
USER_PROV='[{"username":"admin","user_firstname":"Admin","user_lastname":"User","user_email":"admin@example.com","password":"secret","super_user":1}]'
```

> [!IMPORTANT]
> User provisioning only makes sense when table-based authentication is enabled:
> ```env
> RCCONF_auth_meth_global=table
> ```

---

### File Ownership

By default the Apache process runs as `www-data` (UID/GID `33`/`33`). Override with:

```env
WWW_DATA_UID=1000
WWW_DATA_GID=1000
```

---

## Updates & Upgrades

See [REDCAP_UPGRADE.md](REDCAP_UPGRADE.md) for complete documentation.

**Quick reference:**

| Method | When to use |
|---|---|
| Bump `REDCAP_VERSION` + `REDCAP_AUTO_UPGRADE=true` | Fully automated, GitOps-style |
| `REDCAP_VERSION=latest-lts` / `latest-std` (+ `REDCAP_AUTO_UPGRADE=true`) | Always track the newest LTS/STD release. ⚠️ Unattended and **not recommended** — see warning below |
| `docker compose exec redcap redcap-upgrade` | Manual in-container upgrade with interactive wizard |
| `redcap-upgrade --version X.X.X` | Scripted non-interactive upgrade |
| Mount SQL script to `/opt/redcap-docker/sql_scripts_run_once` | Manual fallback, no portal credentials needed |

```bash
# Interactive upgrade wizard
docker compose exec -it redcap redcap-upgrade

# Interactive install wizard (first boot, no REDCAP_VERSION set)
docker compose exec -it redcap redcap-install
```

> [!WARNING]
> `REDCAP_VERSION` also accepts the symbolic values `latest-lts` / `latest-std`, resolved against the community portal on every boot. Combined with `REDCAP_AUTO_UPGRADE=true` this gives a fully unattended, always-current instance — but it applies untested releases and their schema migrations automatically on every reboot. **This is uncharted territory and not currently recommended.** Both flags must be set deliberately; for production, pin an explicit version. See [REDCAP_UPGRADE.md — Tracking the latest version](REDCAP_UPGRADE.md#tracking-the-latest-version-latest-lts--latest-std).

---

## Beta Channel

Some features are released as beta before they land in the stable image. To try them, use the `beta` tag:

```yaml
services:
  redcap:
    image: dzdde/redcap-docker:beta
```

Beta images are published automatically when a pre-release is created on GitHub. They may contain bugs — please [report issues](https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues).

---

## Contributing & Development

See [DEV_README.md](DEV_README.md) for the project structure, where to add new features, and how to run the integration tests.

> [!WARNING]
> **The integration tests download REDCap from the community portal.** You need to provide a Community portal login.Each full run downloads exactly 2 versions. Do not run the full suite repeatedly in a short period — automated downloads may conflict with the REDCap Consortium's usage policies. Run targeted tests during development and reserve full runs for pre-release verification.

---

## Example Setups

If you are more of a hands-on learner; Under [`examples/README.md`](examples/README.md) you can find examplary setups with some documentation.

## Roadmap

**Planned**
- Update user admin privileges for existing users (including external users via LDAP or OAuth2)

**Ideas under consideration**
- Project provisioning
- Automated data exports

Feedback and contributions are welcome — [open an issue](https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues) to start the conversation.
