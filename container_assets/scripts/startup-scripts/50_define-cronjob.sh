#!/bin/bash
mkdir -p /var/spool/cron/crontabs
echo "${CRON_INTERVAL} /opt/redcap-docker/assets/scripts/cron-job.sh" >/var/spool/cron/crontabs/www-data
# never forget the new line at the end of a cron file. always bites me :D
echo "" >>/var/spool/cron/crontabs/www-data
