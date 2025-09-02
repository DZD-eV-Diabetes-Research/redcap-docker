
# redcap-docker
Yet another try to containerize [REDCap](https://www.project-redcap.org/) but with a focus on automated deployments.

**Status**: 🚧 This project is "work in progress" but we have a running ~~alpha~~ beta. I would appriciate your [feedback](https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues)  
**Maintainer**: Tim Bleimehl, DZD  
**Docker image**: https://hub.docker.com/r/dzdde/redcap-docker  
**Source**: https://github.com/DZD-eV-Diabetes-Research/redcap-docker
  

- [redcap-docker](#redcap-docker)
- [Disclaimer](#disclaimer)
- [About / Motivation](#about--motivation)
  - [Target Audience](#target-audience)
- [Features](#features)
- [Roadmap](#roadmap)
- [Ideas](#ideas)
- [Minimal example docker compose](#minimal-example-docker-compose)
- [REDCap Updates](#redcap-updates)
- [Container image details](#container-image-details)
  - [Environment Variables](#environment-variables)
    - [REDCap Application configuration](#redcap-application-configuration)
    - [Email configuration](#email-configuration)
    - [Cron mode](#cron-mode)
  - [User provisioning](#user-provisioning)
  - [Volume/Pathes](#volumepathes)
    - [REDCap php scripts aka apache document root dir](#redcap-php-scripts-aka-apache-document-root-dir)
    - [Custom php ini config](#custom-php-ini-config)
    - [Custom apache virtual host directives](#custom-apache-virtual-host-directives)
    - [Custom install SQL Script](#custom-install-sql-script)
    - [User provisioning files](#user-provisioning-files)
    - [REDCap file repository](#redcap-file-repository)
  - [File Ownership](#file-ownership)

# Disclaimer

This is not an official REDCap project.  
We are only a institutional partner of the REDCap Consortium. But besides that we have no connection to REDCap. We are just REDCap users.  
This project does not distribute REDCap and will never do. Its just a wrapper to help deploy REDCap.  
Users still need to provide their own copy of REDCap.  

> [!CAUTION]
> We are not responsible for any data loss or damage that may occur from the use of our container image. Use it at your own risk. Make backups!


# About / Motivation

We drive our infrastructure with an emphasis on automation and reproducibility with containerization as our tool.
While there are currently great solutions out there like Andys https://github.com/123andy/redcap-docker-compose (Which was a great help to create this repo), we were not able to adapt REDCap to our environemnt without manual intervention.  
This is our try, to containerize REDCap in a way, we can deploy a new instance, without the need for manual intervention during setup.

## Target Audience

* IT Operators: Professionals with knowledge of container operations who need to deploy and manage a REDCap instances.
* REDCap Admins: Users familiar with Docker or Podman who require local instances of REDCap for testing or experimentation.

# Features

* Automated installation without the need to copy and run REDCap generated SQL scripts
* REDCap Database configuration via env vars
* REDCap Application configuration via env vars
* Automated basic routine task like deactivating the default admin (Optionaly)
* Provide simple mail setup (msmtprc config via env vars)
* "Cron mode" to run the same image as REDCap cronjob manager
* User provisioning via env vars and/or yaml files

# Roadmap

* Update user admin priviledges for existing (also external like ldap or oauth2 ) users

# Ideas

* Project provisioning

# Minimal example docker compose

> We assume some basic knowledge about `docker` and `docker compose` and that it is installed


> 🔋🛑 Batteries not included! Due to the way how REDCap is licensed, you still need to provide the REDCap source-code/php-scripts.

Have a look in the [example section for a minimal docker compose](examples/local_instance_basic)  that you can start right now.


# REDCap Updates

On how to update your REDCap with this container image see the dedicated page at [REDCAP_UPGRADE.md](/REDCAP_UPGRADE.md)

# Container image details

## Environment Variables

see [config_vars_list.md](config_vars_list.md) for all available variables.

### REDCap Application configuration

You can configure the REDCap instance with env vars. Just prefix the config variables available in the REDCap database table `redcap_config` with `RCCONF_`

see [REDCap application env vars](config_vars_list.md#redcap-application-config-vars) for a list of (almost) all available options

### Email configuration

see [config_vars_list.md#msmtp](config_vars_list.md#msmtp) for mail config vars

### Cron mode

you can use the same docker image to run the REDCap cron process.
Just set the env var `CRON_MODE`to true.  
  
> HINT: You still need to run a second container with the REDCap webserver)

see [config_vars_list.md#cron](config_vars_list.md#msmtp) for all env var options  
see [Cron example compose](examples/instance_with_cron) how to configure it next to a REDCap instance

## User provisioning

This container image can prefill your REDCap instance with table users based on json in the environemnt variable `USER_PROV`.

But there are also different options:

See the manual at [USER_PROV.md](USER_PROV.md) for more details.  
See the list of env vars concerning user provisiong at [USER_PROV.md](config_vars_list.md#user-provisioning) for more details.  
Have a look at the [docker compose example](examples/local_instance_with_user_prov) how it works in action.  

> [!IMPORTANT]  
> If you are using user provisioning you also want to enable the table based [authentication method](config_vars_list.md#rcconf\_auth\_meth\_global) to `table`. env var: `RCCONF_auth_meth_global=table`.

## Volume/Pathes

### REDCap php scripts aka apache document root dir

This directory must contain the REDCap php scripts.

`/var/www/html`

This dir can be changed via env var `APACHE_DOCUMENT_ROOT`

### Custom php ini config

Put any custom php ini config in this directory to be included in the php configuration. See https://www.php.net/manual/en/ini.list.php for all config variables

`/config/php/custom_inis`

This dir can be changed via env var `PHP_INI_SCAN_DIR`

### Custom apache virtual host directives

`/config/apache/custom.virtualhost`


### Custom install SQL Script

> This is optional

You can provide the REDCap installation script (generated by `http(s)://<myredcapdomain>/install.php`)

`/config/redcap/install/install.sql`

If the script is existent and REDCap is considered to be **not** installed (there is no `redcap_config` table in the database)
We will run this script at startup.

This dir can be changed via env var `REDCAP_INSTALL_SQL_SCRIPT_PATH`

If the file is not provided, we will just pull a generic version it from the REDCap sources you provided.

### User provisioning files

`/opt/redcap-docker/users` directory that will be scanned for user data to be provisioned. See [User provisioning](#user-provisioning) for more details.

### REDCap file repository

By the default the REDCap user uploaded filw will be saved into your document root dir `/var/www/html/edocs`. But as stated by the REDCap manual you may want to change that.  

This is easy with this container image, Just set `RCCONF_edoc_path` (e.g. `RCCONF_edoc_path=/data`) to the path of your choice and mount this path via docker to your host system.  

Have a look at the [docker compose example](examples/local_instance_custom_edocs) how it works in action.



## File Ownership

If you are not happy with the file ownership UID/GID of the containers internal apache run user, have a look at [`WWW_DATA_UID`/`WWW_DATA_GID`](#www-data-user-and-group-id).

