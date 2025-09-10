#!/usr/bin/env bash

# PASS VARIABLES TO APACHE?
. /etc/apache2/envvars
. /opt/redcap-docker/assets/scripts/debug_echo.sh
# Check if we should run in cron mode, if yes only start cron service

debug_echo "PHP_INI_SCAN_DIR: $PHP_INI_SCAN_DIR"
debug_echo "'php --ini': $(php --ini)"
# Chmod PHP INI scan directories to 755 (readable + traversable)
IFS=':' read -ra dirs <<< "$PHP_INI_SCAN_DIR"; for dir in "${dirs[@]}"; do [[ -d "$dir" ]] && chmod 755 "$dir" 2>/dev/null; done
if [[ "${CRON_MODE}" =~ ^(1|[yY]|[yY]es|[tT]rue)$ ]]; then
    . /opt/redcap-docker/assets/scripts/get_php_ini_dirs.sh
    echo "Setup REDCap Cron Service..."
    #  Export the PHP env to be able to feed it to busybox
    ENV_EXPORT_FILE="/etc/container-environment.sh"
    printenv | sed 's/^\([^=]*\)=\(.*\)$/export \1="\2"/' > $ENV_EXPORT_FILE
    # Add the current php ini files to the env
    current_ini_env=$(get_php_ini_dirs)
    if grep -q "^export PHP_INI_SCAN_DIR=" "$ENV_EXPORT_FILE"; then
       sed -i.bak "s|^export PHP_INI_SCAN_DIR=.*|export PHP_INI_SCAN_DIR=\"$current_ini_env\"|" "$ENV_EXPORT_FILE"
    else
        echo "export PHP_INI_SCAN_DIR=\"$current_ini_env\"" >> "$ENV_EXPORT_FILE"
    fi
    # make the file accesable
    chmod +x $ENV_EXPORT_FILE
    chown www-data:www-data $ENV_EXPORT_FILE
    # config msmtp
    /opt/redcap-docker/assets/scripts/startup-scripts/60_generate_msmtp_config.sh
    # config cronjob
    echo "Define Cronjob"
    /opt/redcap-docker/assets/scripts/startup-scripts/50_define-cronjob.sh
    echo "0" >$CRON_HEALTH_STATE_FILE
    
    
    # if CRON_RUN_JOB_ON_START is set to true we run the cronjob now once before we go into interval mode
    if [[ "${CRON_RUN_JOB_ON_START}" =~ ^(1|[yY]|[yY]es|[tT]rue)$ ]]; then 
        echo "Run inital cron job because env var 'CRON_RUN_JOB_ON_START' set to '$CRON_RUN_JOB_ON_START'..."
        /opt/redcap-docker/assets/scripts/cron-job.sh; 
    else
        echo "Skip cron job run on container start because env var 'CRON_RUN_JOB_ON_START' set to '$CRON_RUN_JOB_ON_START'."
    fi
    chown www-data:www-data $CRON_HEALTH_STATE_FILE
    echo "Start cron service..."
    # start cron service
    exec busybox crond -l 2 -f
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
    cp /opt/redcap-docker/assets/config/redcap/database.php ${APACHE_DOCUMENT_ROOT}/database.php

    # DEFAULT TO THE DOCKER DEFAULT CONFIG DIRECTORY
    [ -d /etc/container-config/apache2 ] && cp -RT /etc/container-config/apache2 /etc/apache2
    [ -d /etc/container-config/php ] && cp -RT /etc/container-config/php /usr/local/etc/php/conf.d

    # RUN STARTUP BASH SCRIPTS
    for cmds in /opt/redcap-docker/assets/scripts/startup-scripts/*.sh; do . ${cmds}; done

    # RUN STARTUP PHP SCRIPTS
    #for cmds in /opt/redcap-docker/assets/scripts/startup-scripts/*.php; do php -f ${cmds}; done

    for cmds in /opt/redcap-docker/assets/scripts/startup-scripts/*.php; do
        echo "Run 'php -f ${cmds}'..."
        php -f ${cmds}
    done

    echo "Start REDCap now..."
    # START APACHE
    mkdir -p /var/log/apache2
    exec /usr/sbin/apache2 -DFOREGROUND
fi
