#!/usr/bin/env bash

if [[ ! "${FIX_REDCAP_DIR_PERMISSIONS}" =~ ^(1|[yY]|[yY]es|[tT]rue)$ ]]; then
    echo "[PERMISSIONS] FIX_REDCAP_DIR_PERMISSIONS is not true — skipping."
    return 0
fi

EASY_UPGRADE="${REDCAP_EASY_UPGRADE_ENABLE:-false}"

if [[ "${EASY_UPGRADE}" =~ ^(1|[yY]|[yY]es|[tT]rue)$ ]]; then
    echo ""
    echo "  ┌─────────────────────────────────────────────────────────────────┐"
    echo "  │  SECURITY WARNING: REDCAP_EASY_UPGRADE_ENABLE=true              │"
    echo "  │                                                                 │"
    echo "  │  www-data has write access to the REDCap webroot.               │"
    echo "  │  Using Easy Upgrade on a production server is NOT recommended   │"
    echo "  │  because it makes the server vulnerable to certain attacks.     │"
    echo "  │  Disable this for production: REDCAP_EASY_UPGRADE_ENABLE=false  │"
    echo "  │  See SECURITY.md for details.                                   │"
    echo "  └─────────────────────────────────────────────────────────────────┘"
    echo ""
    echo "[PERMISSIONS] Granting www-data full write access to ${APACHE_RUN_HOME} (Easy Upgrade mode)."
    chown -R www-data:www-data "${APACHE_RUN_HOME}"
else
    echo "[PERMISSIONS] Applying production-safe read-only webroot (REDCAP_EASY_UPGRADE_ENABLE=false)."

    # Webroot owned by root, group-readable by www-data — Apache can serve files but cannot write them
    chown -R root:www-data "${APACHE_DOCUMENT_ROOT}"
    find "${APACHE_DOCUMENT_ROOT}" -type d -exec chmod 750 {} \;
    find "${APACHE_DOCUMENT_ROOT}" -type f -exec chmod 640 {} \;

    # REDCap requires write access to its temp/ directory.
    # Create it if absent (REDCap creates it lazily; we need it owned correctly from the start).
    mkdir -p "${APACHE_DOCUMENT_ROOT}/temp"
    # chmod before chown so the mode change runs while root still owns the dir
    # (avoids needing the FOWNER capability). See issue #7.
    chmod 750 "${APACHE_DOCUMENT_ROOT}/temp"
    chown -R www-data:www-data "${APACHE_DOCUMENT_ROOT}/temp"
    echo "[PERMISSIONS] temp/ ${APACHE_DOCUMENT_ROOT}/temp is writable by www-data."

    # edocs directory (user uploads) must stay writable
    EDOCS_PATH="${RCCONF_edoc_path:-${APACHE_DOCUMENT_ROOT}/edocs}"
    if [[ -d "${EDOCS_PATH}" ]]; then
        # chmod before chown so the mode change runs while root still owns the dir
        # (avoids needing the FOWNER capability). See issue #7.
        chmod 750 "${EDOCS_PATH}"
        chown -R www-data:www-data "${EDOCS_PATH}"
        echo "[PERMISSIONS] edocs directory ${EDOCS_PATH} remains writable by www-data."
    fi

    echo "[PERMISSIONS] Webroot ${APACHE_DOCUMENT_ROOT} is now read-only for www-data."
    echo "[PERMISSIONS] Set REDCAP_EASY_UPGRADE_ENABLE=true to allow REDCap's browser-based upgrade tool."
fi
