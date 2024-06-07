
# redcap-docker
Yet another try to containerize REDCap but with a focus on automated deployments.

Status: This is a work progress with a working alpha version.  
Maintainer: Tim Bleimehl, DZD  
Docker image: https://hub.docker.com/r/dzdde/redcap-docker  
  

- [redcap-docker](#redcap-docker)
- [Disclaimer](#disclaimer)
- [About / Motivation](#about--motivation)
- [Minimal example docker compose](#minimal-example-docker-compose)
  - [Environment Variables](#environment-variables)
  - [container pathes](#container-pathes)
    - [REDCap php scripts aka apache document root dir](#redcap-php-scripts-aka-apache-document-root-dir)
    - [Custom php ini config](#custom-php-ini-config)
    - [Custom apache virtual host directives](#custom-apache-virtual-host-directives)
    - [Custom install SQL Script](#custom-install-sql-script)
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

# Minimal example docker compose

> We assume some basic knowledge about `docker` and `docker compose` and that it is installed


> 🔋🛑 Batteries not included! Due to the way how REDCap is licenced you still need to provide the REDCap source-code/php-scripts.


Create a docker-compose file with following content:

```yaml
services:
  redcap:
    build: ../..
    # image: dzdde/redcap-docker
    environment:
      DB_PORT: 3306
      DB_HOSTNAME: db
      DB_NAME: redcap
      DB_USERNAME: redcap
      DB_PASSWORD: redcap123
      # Do not reuse this example DB_SALT if doing anything serious
      DB_SALT: d369a86842347f7e3e40a3ec64b9f9d950bdfde05beba3a61da69bb1fb28dcea9152fbf723889181a9bd9a97f34b90faf17a
      REDCAP_INSTALL_ENABLE: true
      # REDCAP_SUSPEND_SITE_ADMIN: true
      APPLY_RCCONF_VARIABLES: true
      RCCONF_institution: "Weyland-Yutani Corporation"
      RCCONF_homepage_contact: "Karl Bishop "
      RCCONF_homepage_contact_email: "k.bishop@wyyu.earth"
      # TODO Provide base config here as example. see insatll.sql 4272 and followin lines
    restart: always
    depends_on:
          db:
            condition: service_healthy
    ports:
      - "80:80"
    volumes:
      # target your redcap directory here. We need the directory that contains the index.php file (among all other php files).
      - ./redcap:/var/www/html
    logging:
      options:
        max-size: "10m"
        max-file: "3"
  db:
    image: mysql:lts
    restart: always
    cap_add:
      - SYS_NICE # CAP_SYS_NICE
    volumes:
      - ./data/db:/var/lib/mysql
    ports:
      - 3306:3306
    environment:
      - MYSQL_ROOT_PASSWORD=redcaproot123
      - MYSQL_DATABASE=redcap
      - MYSQL_USER=redcap
      - MYSQL_PASSWORD=redcap123
      - TZ=UTC
    healthcheck:
            test: "/usr/bin/mysql -u $$MYSQL_USER -p$$MYSQL_PASSWORD $$MYSQL_DATABASE --execute \"SHOW TABLES;\""
            timeout: 5s
            interval: 5s
            retries: 4
    command:
      # Per REDCap Recommendations
      - --max_allowed_packet=128M
      # If you have a larger development database, you may want to increase this value:
      # Default is 128MB (134217728) - I'm upping it to 512MB
      - --innodb_buffer_pool_size=536870912
      # 2x default
      # sort_buffer_size=524288
      - --sort_buffer_size=1024K
      # Default
      #read_rnd_buffer_size=262144
      - --read_rnd_buffer_size=1024K
      # MAKE SEPARATE FILES PER TABLE
      - --innodb_file_per_table=1
      # Disabling symbolic-links is recommended to prevent assorted security risks
      - --symbolic-links=0
      # SLOW QUERY LOGGING
      - --log_output=FILE
      - --slow_query_log=0
      - --slow_query_log_file=/var/log/mysql_slow.log
      - --long_query_time=2.000
      - --log-queries-not-using-indexes=0
```

🛑 You need to provide the REDCap source. See the `services`->`redcap`->`volumes` chapter.

Now we just need to run `docker compose up -d` and keep an eye on the logs for any errors (Remember this is an alpha version) with `docker compose logs -f`
There will be some log messages about the inital installation and configuration. After you see a message "Start REDCap now..." you can visit http://localhost and admire your local REDCap instance.


## Environment Variables

see [config_vars_list.md](config_vars_list.md) for all available variables.

## container pathes

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

You can provide the REDCap installation script (generated by `http(s)://<myredcapdomain>/install.php`)

`/config/redcap/install/install.sql`

If the script is existent and REDCap is considered to be **not** installed (there is no `redcap_config` table in the database)
We will run this script at startup.

This dir can be changed via env var `REDCAP_INSTALL_SQL_SCRIPT_PATH`

If the file is not provided, we will just pull a generic version it from the REDCap sources you provided.

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
