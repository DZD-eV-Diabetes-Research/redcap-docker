#!/bin/bash
# this script creates the cron job entry at /var/spool/cron/crontabs/www-data
mkdir -p /var/spool/cron/crontabs
# make sure the script is executable
chmod a+x /opt/redcap-docker/assets/scripts/cron-job.sh /opt/redcap-docker/assets/scripts/export-job.sh
echo "${CRON_INTERVAL} /opt/redcap-docker/assets/scripts/cron-job.sh" >/var/spool/cron/crontabs/www-data
# data export jobs (DATA_EXPORT.md). The jobs run as www-data, so it needs to own the export and state directories
if [[ "${ENABLE_DATA_EXPORT}" =~ ^(1|[yY]|[yY]es|[tT]rue)$ ]]; then
    mkdir -p "${EXPORT_DIR}" "${EXPORT_STATE_DIR}"
    chown www-data:www-data "${EXPORT_DIR}" "${EXPORT_STATE_DIR}"
    chmod 750 "${EXPORT_DIR}" "${EXPORT_STATE_DIR}"
    # results of the previous container run, possibly of jobs that are no longer configured; the healthcheck reads them
    find "${EXPORT_STATE_DIR}" -maxdepth 1 -name '*.status' -type f -delete
    export_crontab=$(php -f /opt/redcap-docker/assets/scripts/redcap_export.php -- crontab)
    echo "Define data export jobs:"
    echo "${export_crontab:-  (no valid export jobs configured)}"
    [ -n "${export_crontab}" ] && echo "${export_crontab}" >>/var/spool/cron/crontabs/www-data
fi
# never forget the new line at the end of a cron file. always bites me :D
echo "" >>/var/spool/cron/crontabs/www-data
