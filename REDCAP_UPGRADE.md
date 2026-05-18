
- [How to Update / Upgrade](#how-to-update--upgrade)
  - [Environment Update](#environment-update)
    - [Docker Compose](#docker-compose)
    - [Docker](#docker)
  - [REDCap Application Update](#redcap-application-update)
    - [Introduction](#introduction)
    - [Server-Side Upgrader (recommended)](#server-side-upgrader-recommended)
      - [Interactive wizard](#interactive-wizard)
      - [Branch switching](#branch-switching)
      - [Non-interactive (scripted) upgrade](#non-interactive-scripted-upgrade)
      - [Using a locally pre-downloaded zip](#using-a-locally-pre-downloaded-zip)
      - [Preview without changes](#preview-without-changes)
      - [Backup and rollback](#backup-and-rollback)
      - [All options](#all-options)
    - [Manual Updates](#manual-updates)
    - [Mount /opt/redcap-docker/sql\_scripts\_run\_once](#mount-optredcap-dockersql_scripts_run_once)
    - [Update](#update)


# How to Update / Upgrade

There are two kinds of updates:

- **Environment** – the operating system, PHP runtime, and third-party modules (e.g., ImageMagick).  
- **Application** – the REDCap PHP files.  

Both require different update/upgrade procedures.


## Environment Update

This process is straightforward because everything happens when rebuilding the Docker image.

Simply pull the latest Docker image from Docker Hub.  

You can check for new releases here:  
- https://hub.docker.com/r/dzdde/redcap-docker/tags  
- https://github.com/DZD-eV-Diabetes-Research/redcap-docker/releases  

### Docker Compose

```bash
docker compose pull
````

Restart REDCap:

```bash
docker compose down && docker compose up -d
```

### Docker

If you’re using plain Docker, run:

```bash
docker pull dzdde/redcap-docker
```

Then restart your container.

## REDCap Application Update

### Introduction



We ~~recommend~~ using the **Easy Upgrade** feature, available since REDCap version **8.6.0**.
This automates the update process and is fully compatible with this container.

> [!WARNING]  
> Update Feb 2026: The REDCap consortium does not endorse the **Easy Upgrade** anymore. See https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues/4 for a possible future solution  
> The following manual is still working but be aware of the risks!


From the 8.6.0 Changelog:

> Administrators may now upgrade to a more recent version of REDCap in an easier and more automated fashion with just a couple of clicks. The Easy Upgrade process (if fully enabled) allows REDCap administrators to upgrade REDCap using only the REDCap user interface in the Control Center (i.e., direct access to the web server or database server is not required).

However, there is one caveat:

> If a particular upgrade cannot complete via the Easy Upgrade process because it requires REDCap to be taken offline before executing the SQL upgrade script, the Easy Upgrade feature will still auto-download the REDCap upgrade file but redirect the administrator to the Upgrade Module to complete the upgrade manually. This occurs only in a small minority of upgrades.

### Server-Side Upgrader (recommended)

> [!WARNING]
> **This feature is currently in BETA.**
> It has been tested on a limited number of REDCap versions and MySQL configurations.
> Always verify the post-upgrade backup before deleting it, and keep the old version
> directory until you are confident the upgrade succeeded.
> Please report any issues at https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues

The container ships with a built-in upgrade command (`redcap-upgrade`) that handles
the full upgrade process in-place — no container restart required.

It:
1. Creates a **compressed database backup** before touching anything.
2. Takes REDCap **offline** in the database.
3. Downloads the target version zip from the REDCap community portal (or uses a
   locally provided zip).
4. Extracts the new `redcap_vX.X.X/` directory.
5. Runs all relevant **SQL upgrade scripts** found in the zip.
6. Installs the new version directory into the document root.
7. Updates the `redcap_version` record in the database.
8. Keeps the old version directory in place (needed for a clean rollback).
9. Brings REDCap back **online** automatically.

#### Interactive wizard

The easiest way to upgrade is to call `redcap-upgrade` with **no arguments**.
If stdin is a terminal, the wizard starts automatically:

```bash
docker compose exec -it redcap redcap-upgrade
```

The wizard will fetch available versions from the REDCap community portal (no
credentials needed for the version list) and show them grouped by release branch:

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

During the download a live progress bar shows the download size and speed:

```
  [===============>             ]  54%   27.8 /  51.3 MB    4.2 MB/s
```

> [!NOTE]
> The `-it` flag on `docker compose exec` is required for the wizard to
> receive keyboard input. Without it, Docker does not allocate a terminal
> and the wizard will not start.

#### Branch switching

REDCap releases on two parallel tracks:

| Branch | Description |
|--------|-------------|
| **LTS** | Long-term support — fewer, stability-focused releases |
| **STD** | Standard — more frequent releases with new features |

When you are already on the **latest version of your current branch**, the wizard
detects this and asks whether you want to switch to a newer branch instead of
silently recommending a version from a different track:

```
You are already on the latest LTS version (16.0.30).

STD versions:
  16.1.1  (2026-02-12)
  ...
  17.0.8  (2026-05-14)  <-- latest

Note: switching branches is a one-way upgrade — you cannot go back to LTS without a rollback.

Switch to a newer branch? [y/N]:
```

The default answer is **N** — pressing Enter exits without making any changes.
If you confirm, the wizard continues normally with the latest version of the
other branch as the default selection.

> [!WARNING]
> Switching from LTS to STD is effectively a one-way change. REDCap does not
> support downgrading between major tracks without a full database restore.
> Use the `--rollback` option if you need to revert after a branch switch.

#### Non-interactive (scripted) upgrade

Supply your community portal credentials once via your `docker-compose.yaml`:

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

If your server has no outbound internet access, copy the zip onto the volume mount
first, then point the upgrader at it:

```bash
# Copy the zip into the REDCap data directory (which is already mounted)
cp ~/Downloads/redcap_v14.9.5.zip ./data/redcap/redcap/

# Run the upgrade
docker compose exec redcap redcap-upgrade --zip /var/www/html/redcap_v14.9.5.zip
```

#### Preview without changes

```bash
docker compose exec redcap redcap-upgrade --version 14.9.5 --dry-run
```

#### Backup and rollback

The upgrader creates a compressed database dump **before every upgrade** and
stores it in `/opt/redcap-docker/backups/` (configurable via
`REDCAP_UPGRADE_BACKUP_DIR` or `--backup-dir`).

> [!TIP]
> Mount the backup directory on the host so backups survive container restarts:
> ```yaml
> volumes:
>   - ./backups:/opt/redcap-docker/backups
> ```

If the SQL step fails mid-upgrade, the database is **automatically restored**
from the backup. The old version PHP directory is always kept alongside the new
one so a rollback restores both layers cleanly.

To manually roll back after a completed upgrade:

```bash
docker compose exec redcap redcap-upgrade \
    --rollback /opt/redcap-docker/backups/redcap_backup_20260518_143022_from_14.8.0_to_14.9.5.sql.gz
```

This restores the database and removes the newly installed `redcap_v14.9.5/`
directory. The old `redcap_v14.8.0/` directory is still in place and becomes
active again automatically.

Once you are satisfied with the upgrade, clean up manually:

```bash
# Inside the container (or via exec):
rm -rf /var/www/html/redcap_v14.8.0
rm /opt/redcap-docker/backups/redcap_backup_20260518_143022_from_14.8.0_to_14.9.5.sql.gz
```

#### All options

```
--version <X.X.X>         Version to download from the community portal.
--zip <path>              Use a local zip instead of downloading.
--community-user          Community portal username (or REDCAP_COMMUNITY_USER env var).
--community-password      Community portal password (or REDCAP_COMMUNITY_PASSWORD env var).
--no-backup               Skip the pre-upgrade database backup (disables auto-rollback).
--backup-dir <path>       Backup directory (env: REDCAP_UPGRADE_BACKUP_DIR).
--rollback <file>         Restore a backup and remove the version installed after it.
--dry-run                 Show what would happen without making changes.
--keep-old                Keep the previous version directory (default when backup is on).
--no-sql                  Skip SQL upgrade scripts (file extraction only).
--no-offline              Do not toggle the REDCap offline flag during upgrade.
--help                    Show full help text.
```

> [!NOTE]
> The upgrader sets REDCap to offline mode for the duration of the upgrade and
> restores it to online on completion (even on failure).

> [!IMPORTANT]
> SQL upgrade scripts shipped inside the zip are only run for versions strictly
> newer than the currently installed version. If you are skipping multiple minor
> versions, make sure the zip for your target version includes all intermediate
> SQL files (REDCap normally ships them all in each release).

---

### Manual Updates

If you do not want to use the integrated updater you can also run your sql scripts by yourself if needed.

In this case, you’ll see a message like this:

![manual-update-ahead](/img/notice-pain-full-update.png)

Fortunately, this container includes a feature that makes the process easier.
By using the environment variable [`AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION`](/config_vars_list.md#run-custom-or-upgrade-sql-scripts-at-boot), you can simply drop the required SQL script into a directory, and it will be executed automatically at boot.

The upgrader will then look like this:

![upgrader-with-script](/img/upgrader-with-script.png)

You can use **Option B** – download the SQL file and place it in a directory next to your `docker-compose.yaml` file.

### Mount /opt/redcap-docker/sql_scripts_run_once

For example, let’s say the SQL file is named `redcap_upgrade_150012.sql` and you move it to `./sql_scripts`.

Make sure you mount this directory to `/opt/redcap-docker/sql_scripts_run_once` inside the container.

Your REDCap container volumes may look like this:

```yaml
services:
  redcap:
    [...]
    volumes:
      # Mount the REDCap source/PHP scripts
      - ./data/redcap:/var/www/html
      # Mount SQL scripts to run once at boot (useful for manual updates)
      - ./sql_scripts:/opt/redcap-docker/sql_scripts_run_once
    [...]
```

> [!TIP]
> You can keep this mount permanently. The system tracks which SQL scripts have already run and won’t execute them again.

### Update

If the updater instructs you to do so, set your REDCap instance to **offline mode** in the settings.

Then restart the container with:

```bash
docker compose down && docker compose pull && docker compose up -d
```

Check the logs with:

```bash
docker compose logs
```

You should see an entry like:

```
redcap-1       | [RUN CUSTOM BOOT SQLS] Try run file: '/opt/redcap-docker/sql_scripts_run_once/tmp/redcap_upgrade_150012.sql'
```

If no error messages appear, everything is fine.
Now return to the "Easy Upgrade" page in REDCap and follow any remaining instructions.

> [!IMPORTANT]
> Don’t forget to switch your REDCap system back from **offline** to **online** in the Control Center.

