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
    # Only chown entries that are not already www-data:www-data, so an unchanged
    # restart does not walk the whole tree again (issue #11).
    find "${APACHE_RUN_HOME}" ! \( -user www-data -group www-data \) \
        -exec chown www-data:www-data {} +
else
    echo "[PERMISSIONS] Applying production-safe read-only webroot (REDCAP_EASY_UPGRADE_ENABLE=false)."

    # Resolve the edocs path up front: the webroot passes below must exclude it
    # (and temp/) so those writable directories are not chowned to root and then
    # flipped back to www-data on every boot. edocs can hold a large upload tree.
    EDOCS_PATH="${RCCONF_edoc_path:-${APACHE_DOCUMENT_ROOT}/edocs}"

    # Webroot owned by root, group-readable by www-data — Apache can serve files
    # but cannot write them.
    #
    # Each pass filters on the target state so an unchanged restart touches
    # nothing — this is the fix for the startup bottleneck in issue #11, where an
    # unconditional recursive chown + chmod re-applied identical permissions to
    # every file on every boot (minutes of downtime with several REDCap versions).
    #
    # Ownership is fixed BEFORE mode so each chmod runs on a root-owned file and
    # needs no FOWNER capability, which the documented minimal cap set drops
    # (see issue #7). temp/ and edocs/ are pruned here and handled separately.
    perm_fix_failed=0
    find "${APACHE_DOCUMENT_ROOT}" \
        -path "${APACHE_DOCUMENT_ROOT}/temp" -prune -o \
        -path "${EDOCS_PATH}" -prune -o \
        ! \( -user root -group www-data \) -exec chown root:www-data {} + || perm_fix_failed=1
    find "${APACHE_DOCUMENT_ROOT}" \
        -path "${APACHE_DOCUMENT_ROOT}/temp" -prune -o \
        -path "${EDOCS_PATH}" -prune -o \
        -type d ! -perm 750 -exec chmod 750 {} + || perm_fix_failed=1
    find "${APACHE_DOCUMENT_ROOT}" \
        -path "${APACHE_DOCUMENT_ROOT}/temp" -prune -o \
        -path "${EDOCS_PATH}" -prune -o \
        -type f ! -perm 640 -exec chmod 640 {} + || perm_fix_failed=1

    # Bring a writable directory to mode 750 owned by www-data, working under a
    # dropped FOWNER capability. These dirs are normally already www-data-owned
    # (40_set_edoc_perm.sh chowns the tree to www-data before we run), and root
    # cannot chmod a file it does not own without FOWNER. So when — and only
    # when — the mode is wrong, we briefly flip just this one directory to root
    # to change it; the filtered recursive chown then (re)asserts www-data
    # ownership without rewalking a large, already-correct upload tree. In steady
    # state the mode is already 750, so nothing here runs on a restart.
    fix_writable_dir() {
        local dir="$1"
        if [[ "$(stat -c '%a' "${dir}" 2>/dev/null)" != "750" ]]; then
            chown root:www-data "${dir}" && chmod 750 "${dir}" || return 1
        fi
        find "${dir}" ! \( -user www-data -group www-data \) \
            -exec chown www-data:www-data {} +
    }

    # REDCap requires write access to its temp/ directory.
    # Create it if absent (REDCap creates it lazily; we need it owned correctly
    # from the start).
    mkdir -p "${APACHE_DOCUMENT_ROOT}/temp"
    fix_writable_dir "${APACHE_DOCUMENT_ROOT}/temp" || perm_fix_failed=1
    echo "[PERMISSIONS] temp/ ${APACHE_DOCUMENT_ROOT}/temp is writable by www-data."

    # edocs directory (user uploads) must stay writable.
    if [[ -d "${EDOCS_PATH}" ]]; then
        fix_writable_dir "${EDOCS_PATH}" || perm_fix_failed=1
        echo "[PERMISSIONS] edocs directory ${EDOCS_PATH} remains writable by www-data."
    fi

    unset -f fix_writable_dir

    # Non-fatal by design: a failed permission op (an immutable file, an odd
    # mount, or a reduced capability set missing CHOWN/DAC_OVERRIDE) must not
    # wedge the container the way `set -e` would — but it must not pass silently
    # either. Surface it loudly so an operator sees the webroot may not be locked
    # down. With the chown-before-chmod ordering above this should never fire
    # under the documented capabilities.
    if [[ "${perm_fix_failed}" -ne 0 ]]; then
        echo "[PERMISSIONS] WARNING: one or more permission operations failed under ${APACHE_DOCUMENT_ROOT}."
        echo "[PERMISSIONS] WARNING: the webroot may not be fully locked down — review the errors above."
        echo "[PERMISSIONS] WARNING: if you restrict Linux capabilities, ensure CHOWN and DAC_OVERRIDE are granted (see SECURITY.md)."
    fi

    echo "[PERMISSIONS] Webroot ${APACHE_DOCUMENT_ROOT} is now read-only for www-data."
    echo "[PERMISSIONS] Set REDCAP_EASY_UPGRADE_ENABLE=true to allow REDCap's browser-based upgrade tool."
fi
