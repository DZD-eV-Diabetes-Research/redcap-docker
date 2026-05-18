
# redcap-docker

Yet another try to containerize [REDCap](https://www.project-redcap.org/) — but with a focus on **automated, hands-off deployments**.

**Status**: Work in progress · beta  
**Maintainer**: Tim Bleimehl, DZD  
**Docker image**: [dzdde/redcap-docker](https://hub.docker.com/r/dzdde/redcap-docker)  
**Source**: [github.com/DZD-eV-Diabetes-Research/redcap-docker](https://github.com/DZD-eV-Diabetes-Research/redcap-docker)

---

- [redcap-docker](#redcap-docker)
  - [Disclaimer](#disclaimer)
  - [About \& Motivation](#about--motivation)
    - [Target Audience](#target-audience)
  - [Features](#features)
  - [Quick Start](#quick-start)
  - [Container Reference](#container-reference)
    - [Environment Variables](#environment-variables)
      - [REDCap Application Configuration](#redcap-application-configuration)
      - [Email Configuration](#email-configuration)
      - [Cron Mode](#cron-mode)
    - [Volumes \& Paths](#volumes--paths)
      - [REDCap PHP scripts (Apache document root)](#redcap-php-scripts-apache-document-root)
      - [Custom PHP INI configuration](#custom-php-ini-configuration)
      - [Custom Apache virtual host directives](#custom-apache-virtual-host-directives)
      - [Custom install SQL script *(optional)*](#custom-install-sql-script-optional)
      - [User provisioning files](#user-provisioning-files)
      - [REDCap file repository](#redcap-file-repository)
    - [User Provisioning](#user-provisioning)
    - [File Ownership](#file-ownership)
  - [REDCap Updates \& Upgrades](#redcap-updates--upgrades)
  - [Roadmap](#roadmap)

---

## Disclaimer

This is not an official REDCap project. We are an institutional partner of the REDCap Consortium, but beyond that we have no connection to REDCap — we are simply REDCap users.

This project does **not** distribute REDCap and never will. It is a wrapper to help deploy REDCap. You still need to provide your own licensed copy of REDCap.

> [!CAUTION]
> We are not responsible for any data loss or damage that may occur from the use of this container image. Use it at your own risk. **Make backups.**

---

## About & Motivation

Our infrastructure emphasises automation and reproducibility through containerization. While excellent solutions like [Andy's redcap-docker-compose](https://github.com/123andy/redcap-docker-compose) exist and were a great help when building this repo, we could not adapt them to our environment without manual intervention during setup.

This project is our attempt to containerize REDCap in a way that allows deploying a fresh instance with **zero manual steps**.

### Target Audience

- **IT Operators** — professionals with container operations knowledge who need to deploy and manage REDCap instances.
- **REDCap Admins** — users familiar with Docker or Podman who need local REDCap instances for testing or experimentation.

---

## Features

- Automated installation — no need to manually copy or run REDCap-generated SQL scripts
- Database and application configuration via environment variables
- Simple mail setup via environment variables (`msmtprc`-based)
- Automated routine tasks such as deactivating the default admin (optional)
- "Cron mode": run the same image as the REDCap cron job manager
- User provisioning via environment variables and/or YAML files
- 🧪 **[BETA]** Built-in server-side upgrader (`redcap-upgrade`) as a replacement for the deprecated **Easy Upgrade**. It checks for updates, downloads from the REDCap community portal, runs SQL migrations, creates a database backup, and rolls back. All from a single command inside the running container. See [REDCap Updates & Upgrades](#redcap-updates--upgrades).

---

## Quick Start

> This guide assumes basic familiarity with `docker` and `docker compose` and that both are installed.

> [!IMPORTANT]
> **Batteries not included.** Due to REDCap's licensing, you must provide the REDCap source code (PHP scripts) yourself.

See the [minimal docker compose example](examples/local_instance_basic) for a working starting point you can bring up right away.

---

## Container Reference

### Environment Variables

See [config_vars_list.md](config_vars_list.md) for the full list of available variables.

#### REDCap Application Configuration

REDCap application settings can be configured via environment variables. Prefix any key from the `redcap_config` database table with `RCCONF_` to set it at startup.

Example: `RCCONF_auth_meth_global=table`

See [REDCap application env vars](config_vars_list.md#redcap-application-config-vars) for a list of available options.

#### Email Configuration

See [config_vars_list.md — msmtp](config_vars_list.md#msmtp) for all mail-related variables.

#### Cron Mode

You can use the same Docker image to run the REDCap cron process. Set the environment variable `CRON_MODE=true`.

> **Note:** You still need a separate container running the REDCap web server.

- See [config_vars_list.md — cron](config_vars_list.md#cron) for all cron-related variables.
- See the [cron example compose](examples/instance_with_cron) for a full setup alongside a REDCap web instance.

---

### Volumes & Paths

#### REDCap PHP scripts (Apache document root)

The REDCap PHP scripts must be placed in:

```
/var/www/html
```

This path can be changed via the `APACHE_DOCUMENT_ROOT` environment variable.

#### Custom PHP INI configuration

Drop any `.ini` files into this directory to extend the PHP configuration. See the [PHP ini reference](https://www.php.net/manual/en/ini.list.php) for all available settings.

```
/config/php/custom_inis
```

Configurable via the `PHP_INI_SCAN_DIR` environment variable.

#### Custom Apache virtual host directives

```
/config/apache/custom.virtualhost
```

#### Custom install SQL script *(optional)*

You can supply the REDCap installation SQL script generated by `http(s)://<yourredcapdomain>/install.php`:

```
/config/redcap/install/install.sql
```

If this file exists and the `redcap_config` table is not yet present in the database, the script will be executed at startup. If no file is provided, a generic version is derived from the REDCap sources you supply.

Configurable via the `REDCAP_INSTALL_SQL_SCRIPT_PATH` environment variable.

#### User provisioning files

```
/opt/redcap-docker/users
```

This directory is scanned for user data to provision at startup. See [User Provisioning](#user-provisioning) for details.

#### REDCap file repository

By default, user-uploaded files are stored in the document root at `/var/www/html/edocs`. As recommended by the REDCap documentation, you may want to move this outside the web root.

Set `RCCONF_edoc_path` to any path of your choice (e.g. `RCCONF_edoc_path=/data`) and mount that path into the container.

See the [custom edocs compose example](examples/local_instance_custom_edocs) for a working setup.

---

### User Provisioning

The container can pre-populate your REDCap instance with table-based users. The simplest way is to supply user data via the `USER_PROV` environment variable as JSON, but file-based provisioning is also supported.

- Full documentation: [USER_PROV.md](USER_PROV.md)
- Environment variable reference: [config_vars_list.md — user provisioning](config_vars_list.md#user-provisioning)
- Working example: [docker compose example](examples/local_instance_with_user_prov)

> [!IMPORTANT]
> When using user provisioning you will almost certainly also want to set the [authentication method](config_vars_list.md#rcconf_auth_meth_global) to `table`:
> ```
> RCCONF_auth_meth_global=table
> ```

---

### File Ownership

If the default UID/GID of the container's internal Apache user does not suit your environment, see [`WWW_DATA_UID` / `WWW_DATA_GID`](config_vars_list.md) for how to override it.

---

## REDCap Updates & Upgrades

> 🧪 **BETA** — The container ships a built-in upgrade command. Run it without arguments for an interactive wizard, or pass `--version X.X.X` for scripted upgrades.

```bash
docker compose exec -it redcap redcap-upgrade
```

See [REDCAP_UPGRADE.md](REDCAP_UPGRADE.md) for full documentation, including manual fallback procedures.

---

## Roadmap

**Planned**
- Update user admin privileges for existing users (including external users via LDAP or OAuth2)

**Ideas under consideration**
- Project provisioning
- Automated data exports

Feedback and contributions are welcome — [open an issue](https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues) to start the conversation.
