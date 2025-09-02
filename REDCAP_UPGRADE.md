
- [How to Update / Upgrade](#how-to-update--upgrade)
  - [Environment Update](#environment-update)
    - [Docker Compose](#docker-compose)
    - [Docker](#docker)
  - [REDCap Application Update](#redcap-application-update)
    - [Introduction](#introduction)
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

We recommend using the **Easy Upgrade** feature, available since REDCap version **8.6.0**.
This automates the update process and is fully compatible with this container.

From the 8.6.0 Changelog:

> Administrators may now upgrade to a more recent version of REDCap in an easier and more automated fashion with just a couple of clicks. The Easy Upgrade process (if fully enabled) allows REDCap administrators to upgrade REDCap using only the REDCap user interface in the Control Center (i.e., direct access to the web server or database server is not required).

However, there is one caveat:

> If a particular upgrade cannot complete via the Easy Upgrade process because it requires REDCap to be taken offline before executing the SQL upgrade script, the Easy Upgrade feature will still auto-download the REDCap upgrade file but redirect the administrator to the Upgrade Module to complete the upgrade manually. This occurs only in a small minority of upgrades.

### Manual Updates

This means, occasionally, you may need to log in to the MySQL database and run an SQL script.

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

> [!HINT]
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

> [!HINT]
> Don’t forget to switch your REDCap system back from **offline** to **online** in the Control Center.

```

---

Would you like me to also **add a short “Quick Summary” section at the top** with just the commands and steps, so readers can get the update done fast without reading the full details?

