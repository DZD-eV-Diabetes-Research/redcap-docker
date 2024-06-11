#!/usr/bin/env bash

# PASS VARIABLES TO APACHE?
. /etc/apache2/envvars

# Check if we should run in cron mode, if yes only start cron service

if [[ "${CRON_MODE}" =~ ^(1|[yY]|[yY]es|[tT]rue)$ ]]; then
    echo "Start REDCap Cron Service..."
    # config msmtp
    /etc/redcap_container_assets/scripts/startup-scripts/60_generate_msmtp_config.sh
    # config cronjob
    /etc/redcap_container_assets/scripts/startup-scripts/50_define-cronjob.sh
    echo "0" >$CRON_HEALTH_STATE_FILE
    chown www-data:www-data $CRON_HEALTH_STATE_FILE
    # if CRON_RUN_JOB_ON_START is set to true we run the cronjob now once before we go into interval mode
    if [[ "${CRON_RUN_JOB_ON_START}" =~ ^(1|[yY]|[yY]es|[tT]rue)$ ]]; then /etc/redcap_container_assets/scripts/cron-job.sh; fi
    # start service
    exec busybox crond -f -L /dev/stdout -l 0
else
    echo "Try starting REDCap Docker Webserver..."

    # CHECK IF REDCAP FILES ARE EXISTENT #

    # Initialize the variable to false
    redcap_source_existent=false

    # Check if there is any directory starting with "redcap_v" in /var/www/html/
    if find ${APACHE_DOCUMENT_ROOT} -maxdepth 1 -type d -name 'redcap_v*' | grep -q .; then
        redcap_source_existent=true
    fi

    # TODO: DOwnload redcap if redcap_source_existent=false annd REDCAP_SOURCE_DOWNLOAD_URL is set
    # Exit with an error if redcap_installed is false
    if [ "$redcap_source_existent" = false ]; then
        echo "Error: Can not find a copy of REDCap in ${APACHE_DOCUMENT_ROOT}."
        exit 1
    fi

    # DEPLOY CUSTOM DATABASE CONFIG #
    cp /etc/redcap_container_assets/config/redcap/database.php ${APACHE_DOCUMENT_ROOT}/database.php

    # DEFAULT TO THE DOCKER DEFAULT CONFIG DIRECTORY
    [ -d /etc/container-config/apache2 ] && cp -RT /etc/container-config/apache2 /etc/apache2
    [ -d /etc/container-config/php ] && cp -RT /etc/container-config/php /usr/local/etc/php/conf.d

    # RUN STARTUP BASH SCRIPTS
    for cmds in /etc/redcap_container_assets/scripts/startup-scripts/*.sh; do . ${cmds}; done

    # RUN STARTUP PHP SCRIPTS
    #for cmds in /etc/redcap_container_assets/scripts/startup-scripts/*.php; do php -f ${cmds}; done

    for cmds in /etc/redcap_container_assets/scripts/startup-scripts/*.php; do
        echo "Run 'php -f ${cmds}'..."
        php -f ${cmds}
    done

    echo "Start REDCap now..."
    # START APACHE
    mkdir -p /var/log/apache2
    exec /usr/sbin/apache2 -DFOREGROUND
fi
