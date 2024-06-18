#!/usr/bin/env bash
if [[ "${FIX_REDCAP_DIR_PERMISSIONS}" =~ ^(1|[yY]|[yY]es|[tT]rue)$ ]]; then
    echo "Verifying permissions on web user homedir ${APACHE_RUN_HOME}"
    nohup chown -R www-data:www-data $APACHE_RUN_HOME
fi
