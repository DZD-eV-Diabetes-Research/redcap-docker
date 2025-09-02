
- [How to update / upgrade](#how-to-update--upgrade)
  - [Environment Update](#environment-update)
    - [docker compose](#docker-compose)
    - [docker](#docker)
  - [RedCap Application Update](#redcap-application-update)
    - [Introduction](#introduction)
    - [The painfull updates](#the-painfull-updates)
    - [Mount /opt/redcap-docker/sql\_scripts\_run\_once](#mount-optredcap-dockersql_scripts_run_once)
    - [Update](#update)


# How to update / upgrade

We will distinguish between environment and application.

`Environment` means the Operation System, php runtime and all its third party modules like image ImageMagick.  

`Application` means the RedCap php files. 

Both of these have different update/upgrade procedures.


## Environment Update

This is simple as all the magic happend at docker image building.

You just need to pull the newest docker image from docker hub. 

Outside of the terminal you can check at https://hub.docker.com/r/dzdde/redcap-docker/tags or https://github.com/DZD-eV-Diabetes-Research/redcap-docker/releases if there are new releases of the RedCap Docker image.

### docker compose

```bash
docker compose pull
```

and restart RedCAP

```bash
docker compose down && docker compose up -d
```

### docker

if you use plain docker run `docker pull dzdde/redcap-docker`. And restart your container.

## RedCap Application Update

### Introduction

We recommend using the "Easy Upgrade" feature of redcap which available since REDCap version 8.6.0.
This will make the update process for you and is fully compatible with this container.

From the 8.6.0 Changelog:
> Administrators may now upgrade to a more recent version of REDCap in an easier and more automated fashion with just a couple clicks. The Easy Upgrade process (if fully enabled) allows REDCap administrators to upgrade REDCap using only the REDCap user interface in the Control Center (i.e., direct access to the web server or database server is not required).


But there is one catch:
> If a particular upgrade is not able to complete the Easy Upgrade process because it recommends that REDCap first be taken offline before executing the upgrade SQL script, the Easy Upgrade feature will still be able to auto-download the REDCap upgrade file, but it will redirect the administrator to the Upgrade Module to complete the upgrade manually. This will occur only a small minority of the time for an upgrade.

### The painfull updates

This means, sometimes we need to login to the MySQL database and run a SQL script.  

in this case you will get a message like

![painfull-update-ahead](/img/notice-pain-full-update.png)

But this container comes with a little feature to make this a little bit less painfull;

We will use the env var [AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION](/config_vars_list.md#run-custom-or-upgrade-sql-scripts-at-boot) to just drop in these  script.

The upgrader will look like this

![upgrader-with-script](/img/upgrader-with-script.png)

We can just use `Option B` downlaod the file and drop it in a directory next to our `docker-compose.yaml` file. 

### Mount /opt/redcap-docker/sql_scripts_run_once

The file has the name `redcap_upgrade_150012.sql` in our example and lets say you move it to `./sql_scripts`.

Now make sure sure have a volume mount to `/opt/redcap-docker/sql_scripts_run_once` inside of the container.

Your REDCap container volumes may look like this

```yaml
services:
  redcap:
    [...]
    volumes:
      # Here you need to mount your copy of the redcap source/php script.
      - ./data/redcap:/var/www/html
      # Here you can throw in some sql script that run once at boot. this can be handy for red updates
      - ./sql_scripts:/opt/redcap-docker/sql_scripts_run_once
    [...]
```

> [!HINT]
> You can keep this mount permanently. We will keep track of which SQL script did allready run and wont run it again.

### Update

If your updater told you so, you want set your REDCap instance in "offline" mode in the settings.

Now give the container restart with 

```bash
docker compose down && docker compose pull && docker compose up -d`
```

Check the logs with

```bash
docker compose logs
```

You should find and entry like

```
redcap-1       | [RUN CUSTOM BOOT SQLS] Try run file: '/opt/redcap-docker/sql_scripts_run_once/tmp/redcap_upgrade_150012.sql'
```

If there is no error message, all is fine. Now go back to your "Easy Upgrade" page and check further instructions.


> [!HINT]
> Do not forget to switch your REDCap form "offline" to "online" in the Control Center.
