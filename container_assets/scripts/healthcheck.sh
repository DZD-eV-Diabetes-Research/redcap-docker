#!/bin/bash

# DB Check
output=$(php -f /etc/redcap_container_assets/scripts/startup-scripts/php_helpers/db_check.php 2>&1)
if [[ $? -ne 0 ]]; then
    echo "No DB connection. PHP Error: "
    echo "$output"
    exit 1
fi

# Webserver basic check
echo $(curl --fail --silent --show-error http://localhost:80 >/dev/null || exit 1)

# all good
exit 0
