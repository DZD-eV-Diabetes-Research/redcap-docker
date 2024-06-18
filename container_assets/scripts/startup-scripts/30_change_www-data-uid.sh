#!/bin/bash
# Change the uid/gid of www-data if env vars WWW_DATA_UID/WWW_DATA_GID was changed by user
# if changed also re-chown all files, that belonged to www-data

if [ "$(id -u)" = 0 ]; then
    CURRENT_UID=$(id -u www-data)
    CURRENT_GID=$(id -g www-data)
    if [[ "$CURRENT_UID" != "$WWW_DATA_UID" ]]; then
        echo "Change UID of www-data from $CURRENT_UID to $WWW_DATA_UID"
        usermod -u $WWW_DATA_UID www-data
        # change all files that are still owned by the originial uid to be owned by our new gid.
        # expect the redcap dir, that will be handled by 90-fix-permission.sh
        find / -not -path "${APACHE_DOCUMENT_ROOT}" -xdev -user $CURRENT_UID -exec chown -h www-data {} \;
    fi
    if [[ "$CURRENT_GID" != "$WWW_DATA_GID" ]]; then
        echo "Change GID of www-data from $CURRENT_GID to $WWW_DATA_GID"
        groupmod -g $WWW_DATA_GID www-data
        # change all files that are still owned by the originial uid to be owned by our new gid.
        # expect the redcap dir, that will be handled by 90-fix-permission.sh
        find / -not -path "${APACHE_DOCUMENT_ROOT}" -xdev -group $CURRENT_GID -exec chgrp -h www-data {} \;
    fi
fi
