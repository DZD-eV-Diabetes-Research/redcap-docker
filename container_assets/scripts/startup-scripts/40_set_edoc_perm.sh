#!/bin/bash
# ON NEW INSTALL CHECK PERMISSIONS
permissionFile="/var/www/html/.delete_to_recheck_permissions.txt"
if [[ -z "${RCCONF_edoc_path}" ]]; then
    # RCCONF_edoc_path is not set
    REDCAP_FILE_REPOSITORY_PATH="${APACHE_DOCUMENT_ROOT}/edocs"
else
    REDCAP_FILE_REPOSITORY_PATH=${RCCONF_edoc_path}
fi
permissionFile="${REDCAP_FILE_REPOSITORY_PATH}/.delete_to_recheck_permissions.txt"
if [[ ! -f "${permissionFile}" ]]; then
    echo "Verifying permissions on web user homedir ${REDCAP_FILE_REPOSITORY_PATH}"
    nohup chown -R $WWW_DATA_UID:$WWW_DATA_GID $APACHE_RUN_HOME
    echo "Delete this file and recreate the web container to verify the file permissions of this directory" >$permissionFile
else
    echo "To force permission verification, delete ${permissionFile} and re-run."
fi
