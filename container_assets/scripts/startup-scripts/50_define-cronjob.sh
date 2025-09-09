#!/bin/bash
# this script creates the cron job entry at /var/spool/cron/crontabs/www-data
mkdir -p /var/spool/cron/crontabs
# make sure the script is executable
chmod a+x /opt/redcap-docker/assets/scripts/cron-job.sh
echo "${CRON_INTERVAL} /opt/redcap-docker/assets/scripts/cron-job.sh" >/var/spool/cron/crontabs/www-data
# never forget the new line at the end of a cron file. always bites me :D
echo "" >>/var/spool/cron/crontabs/www-data

