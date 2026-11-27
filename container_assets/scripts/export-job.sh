#!/bin/bash
# Runs one data export job. Called by crond with the job name, see 50_define-cronjob.sh
set -u
. /etc/container-environment.sh
datetime=$(date '+%d/%m/%Y %H:%M:%S %Z')
echo "#### Export job '$1' start ($datetime)"
php -f /opt/redcap-docker/assets/scripts/redcap_export.php -- run "$1" 2>&1
