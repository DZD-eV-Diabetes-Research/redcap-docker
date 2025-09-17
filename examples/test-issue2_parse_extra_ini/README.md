# Demo to load php ini files in all cases

This is just a debug example for https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues/2

It tries to prove that the normal apache-php and the busybox-crond php use the same php env (load the same ini files) and also load any custom ini files

Copy your redcap file into ./redcap

Mind the env var `REDCAP_DOCKER_SCRIPTS_DEBUG` set to `true` in the compose. This will make the significant log messages visible.
Also important is the `- ./customphp.ini:/config/php/custom_inis/customphp.ini` volume that mount the extra ini in our container.

start with `docker compose up -d`

Look at the logs with `docker compose logs`


```
...
redcap-1       | [DEBUG REDCAP DOCKER] PHP_INI_SCAN_DIR: :/config/php/custom_inis
redcap-1       | [DEBUG REDCAP DOCKER] 'php --ini': Configuration File (php.ini) Path: /usr/local/etc/php
redcap-1       |                       Loaded Configuration File:         /usr/local/etc/php/php.ini
redcap-1       |                       Scan for additional .ini files in: :/config/php/custom_inis
redcap-1       |                       Additional .ini files parsed:      /usr/local/etc/php/conf.d/80_timezone.ini,
redcap-1       |                       /usr/local/etc/php/conf.d/90_error_display.ini,
redcap-1       |                       /usr/local/etc/php/conf.d/docker-php-ext-gd.ini,
redcap-1       |                       /usr/local/etc/php/conf.d/docker-php-ext-imagick.ini,
redcap-1       |                       /usr/local/etc/php/conf.d/docker-php-ext-mysqli.ini,
redcap-1       |                       /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini,
redcap-1       |                       /usr/local/etc/php/conf.d/docker-php-ext-sodium.ini,
redcap-1       |                       /usr/local/etc/php/conf.d/docker-php-ext-zip.ini,
redcap-1       |                       /config/php/custom_inis/customphp.ini
...
```
Triggered by https://github.com/DZD-eV-Diabetes-Research/redcap-docker/blob/9918ada69ca9b1452281c321e57cf91b9609b5bc/container_assets/scripts/container_start.sh#L8
Which proves that the ini was loaded

```
...
redcap-cron-1  | #### Cron run start (10/09/2025 14:23:01 UTC)
redcap-cron-1  | [DEBUG REDCAP DOCKER] CRON: PHP_INI_SCAN_DIR: /usr/local/etc/php:/usr/local/etc/php/conf.d:/config/php/custom_inis
redcap-cron-1  | PHP Warning:  Module "yaml" is already loaded in Unknown on line 0
redcap-cron-1  | [DEBUG REDCAP DOCKER] CRON: 'php --ini': 
redcap-cron-1  |                       Warning: Module "yaml" is already loaded in Unknown on line 0
redcap-cron-1  |                       Configuration File (php.ini) Path: /usr/local/etc/php
redcap-cron-1  |                       Loaded Configuration File:         /usr/local/etc/php/php.ini
redcap-cron-1  |                       Scan for additional .ini files in: /usr/local/etc/php:/usr/local/etc/php/conf.d:/config/php/custom_inis
redcap-cron-1  |                       Additional .ini files parsed:      /usr/local/etc/php/php.ini,
redcap-cron-1  |                       /usr/local/etc/php/conf.d/80_timezone.ini,
redcap-cron-1  |                       /usr/local/etc/php/conf.d/90_error_display.ini,
redcap-cron-1  |                       /usr/local/etc/php/conf.d/docker-php-ext-gd.ini,
redcap-cron-1  |                       /usr/local/etc/php/conf.d/docker-php-ext-imagick.ini,
redcap-cron-1  |                       /usr/local/etc/php/conf.d/docker-php-ext-mysqli.ini,
redcap-cron-1  |                       /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini,
redcap-cron-1  |                       /usr/local/etc/php/conf.d/docker-php-ext-sodium.ini,
redcap-cron-1  |                       /usr/local/etc/php/conf.d/docker-php-ext-zip.ini,
redcap-cron-1  |                       /config/php/custom_inis/customphp.ini
...
```

> ToDo: Get rid of the yaml warning
