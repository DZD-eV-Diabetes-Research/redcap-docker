#!/bin/bash
# We do have 2 different run modes for this container image
# cron and webserver. therefor we need two different healthchecks
if [[ "${CRON_MODE}" =~ ^(1|[yY]|[yY]es|[tT]rue)$ ]]; then

    value=$(cat $CRON_HEALTH_STATE_FILE)
    if [ "$value" != "0" ]; then
        echo "Cron job exited with error: $value"
        exit 1
    fi
    exit 0
else
    echo "Start REDCap Docker Webserver..."
    # DB Check
    output=$(php -f /opt/redcap-docker/assets/scripts/startup-scripts/php_helpers/db_check.php 2>&1)
    if [[ $? -ne 0 ]]; then
        echo "No DB connection. PHP Error: "
        echo "$output"
        exit 1
    fi

    # Webserver basic check
    echo $(curl --fail --silent --show-error http://localhost:80/index.php >/dev/null || exit 1)

    # all good
    exit 0
fi
