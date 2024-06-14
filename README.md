
# redcap-docker
Yet another try to containerize [REDCap](https://www.project-redcap.org/) but with a focus on automated deployments.

**Status**: 🚧 This project is "work in progress" but we have a running alpha.  
**Maintainer**: Tim Bleimehl, DZD  
**Docker image**: https://hub.docker.com/r/dzdde/redcap-docker  
**Source**: https://github.com/DZD-eV-Diabetes-Research/redcap-docker
  

- [redcap-docker](#redcap-docker)
- [Disclaimer](#disclaimer)
- [About / Motivation](#about--motivation)
- [Features](#features)
- [Roadmap](#roadmap)
- [Ideas](#ideas)
- [Minimal example docker compose](#minimal-example-docker-compose)
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
- [Troubleshooting](#troubleshooting)
  - [I get a "permission denied" error, when trying to visit my new REDCap instance](#i-get-a-permission-denied-error-when-trying-to-visit-my-new-redcap-instance)

# Disclaimer

This is not an official REDCap project.  
We are only a institutional partner of the REDCap Consortium. But besides that we have no connection to REDCap. We are just REDCap users.  
This project does not distribute REDCap and will never do. Its just a wrapper to help deploy REDCap.  
Users still need to provide their own copy of REDCap.  

# About / Motivation

We drive our infrastructure with an emphasis on automation and reproducibility with containerization as our tool.
While there are currently great solutions out there like Andys https://github.com/123andy/redcap-docker-compose (Which was a great help to create this repo), we were not able to adapt REDCap to our environemnt without manual intervention.  
This is our try, to containerize REDCap in a way, we can deploy a new instance, with out the need for manual intervention during setup.

# Features

* Automated installation without the need to copy and run REDCap generated SQL scripts
* REDCap Database configuration via env vars
* REDCap Application configuration via env vars
* Automated basic routine task like deactivating the default admin (Optionaly)
* Provide simple mail setup (msmtprc config via env vars)
* "Cron mode" to run the same image as REDCap cronjob manager
* User provisioning via env vars and/or yaml files

# Roadmap

* Define user file repo directory in env var (investigate)
* Testing if REDCap upgrades work with this setup

# Ideas

* Project provisioning


# Minimal example docker compose

> We assume some basic knowledge about `docker` and `docker compose` and that it is installed


> 🔋🛑 Batteries not included! Due to the way how REDCap is licensed, you still need to provide the REDCap source-code/php-scripts.

Have a look in the [example section for a minimal docker compose](examples/local_instance_basic)  that you can start right now.

# Container image details
## Environment Variables

see [config_vars_list.md](config_vars_list.md) for all available variables.

### REDCap Application configuration

You can configure the REDCap instance with env vars.

see [REDCap allication env vars](config_vars_list.md#redcap-application-config-vars) for a list of all options

### Email configuration

see [config_vars_list.md#msmtp](config_vars_list.md#msmtp) for mail config vars

### Cron mode

you can use the same docker image to run the REDCap cron process.
Just set the env var `CRON_MODE`to true.  
  
> HINT: You still need to run a second container with the REDCap webserver)

see [config_vars_list.md#cron](config_vars_list.md#msmtp) for all env var options  
see [Cron example compose](examples/instance_with_cron) how to configure it next to a REDCap instance

## User provisioning

This container image can prefill your REDCap instance with table users.

See the manual at [USER_PROV.md](USER_PROV.md) for more details.  
See the list of env vars concerning user provisiong at [USER_PROV.md](config_vars_list.md#user-provisioning) for more details.  
Have a look at the [docker compose exmaple](examples/local_instance_with_user_prov) how it works in action.  

## Volume/Pathes

### REDCap php scripts aka apache document root dir

This directory will contain the REDCap php scripts.

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

# Troubleshooting

## I get a "permission denied" error, when trying to visit my new REDCap instance

Check and adapt the permissions of your REDCap source files.

First try to give ownership of the REDCap files to www-data:
```bash
docker compose exec redcap /bin/bash -c 'chown -R www-data ${APACHE_DOCUMENT_ROOT}'
```

If you still get permission problem try to set the permission for all directories and files with the following two commands:
```bash
docker compose exec redcap /bin/bash -c 'find ${APACHE_DOCUMENT_ROOT} -type d -exec chmod 755 {} \;'
```
```bash
docker compose exec redcap /bin/bash -c 'chmod -R 644 *.php'
```
