#!/usr/bin/env bash

MODULES_WEB_INSTALL=false
if [[ "${REDCAP_MODULES_WEB_INSTALL_ENABLE:-false}" =~ ^(1|[yY]|[yY]es|[tT]rue)$ ]]; then
    MODULES_WEB_INSTALL=true
fi

if [[ ! "${FIX_REDCAP_DIR_PERMISSIONS}" =~ ^(1|[yY]|[yY]es|[tT]rue)$ ]]; then
    echo "[PERMISSIONS] FIX_REDCAP_DIR_PERMISSIONS is not true — skipping."
    if [[ "${MODULES_WEB_INSTALL}" == "true" ]]; then
        echo "[PERMISSIONS] REDCAP_MODULES_WEB_INSTALL_ENABLE=true has no effect while FIX_REDCAP_DIR_PERMISSIONS is not true."
    fi
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
        -exec chown -h www-data:www-data {} +
    if [[ "${MODULES_WEB_INSTALL}" == "true" ]]; then
        echo "[PERMISSIONS] REDCAP_MODULES_WEB_INSTALL_ENABLE=true has no extra effect: Easy Upgrade mode already makes modules/ writable."
    fi
else
    echo "[PERMISSIONS] Applying production-safe read-only webroot (REDCAP_EASY_UPGRADE_ENABLE=false)."

    # Resolve the edocs path up front: the webroot passes below must exclude it
    # (and temp/) so those writable directories are not chowned to root and then
    # flipped back to www-data on every boot. edocs can hold a large upload tree.
    EDOCS_PATH="${RCCONF_edoc_path:-${APACHE_DOCUMENT_ROOT}/edocs}"
    MODULES_DIR="${APACHE_DOCUMENT_ROOT}/modules"

    # Paths the root-owned passes below must skip. modules/ joins temp/ and
    # edocs only in web install mode (REDCAP_MODULES_WEB_INSTALL_ENABLE=true);
    # otherwise it is part of the read-only webroot like everything else.
    WRITABLE_PRUNE=(
        -path "${APACHE_DOCUMENT_ROOT}/temp" -prune -o
        -path "${EDOCS_PATH}" -prune -o
    )
    if [[ "${MODULES_WEB_INSTALL}" == "true" ]]; then
        WRITABLE_PRUNE+=( -path "${MODULES_DIR}" -prune -o )
    fi

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
    #
    # Every chown uses -h: www-data can create symlinks in its writable dirs
    # (temp/, edocs, and modules/ in web install mode). A plain chown would
    # follow such a link and hand its target (anything in the container) to the
    # new owner. The chmod passes only match -type d/-type f, which find does
    # not resolve through symlinks.
    perm_fix_failed=0
    find "${APACHE_DOCUMENT_ROOT}" "${WRITABLE_PRUNE[@]}" \
        ! \( -user root -group www-data \) -exec chown -h root:www-data {} + || perm_fix_failed=1
    find "${APACHE_DOCUMENT_ROOT}" "${WRITABLE_PRUNE[@]}" \
        -type d ! -perm 750 -exec chmod 750 {} + || perm_fix_failed=1
    find "${APACHE_DOCUMENT_ROOT}" "${WRITABLE_PRUNE[@]}" \
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
            -exec chown -h www-data:www-data {} +
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

    # REDCap's External Modules installer in the Control Center (Repo download,
    # module delete) writes into modules/ only: a "<name>_tmp" lock directory,
    # the extracted module and the final rename. The downloaded zip goes to
    # temp/, which is writable anyway.
    if [[ "${MODULES_WEB_INSTALL}" == "true" ]]; then
        echo ""
        echo "  ┌─────────────────────────────────────────────────────────────────┐"
        echo "  │  SECURITY NOTE: REDCAP_MODULES_WEB_INSTALL_ENABLE=true          │"
        echo "  │  www-data can write PHP into modules/, which Apache executes.   │"
        echo "  │  The rest of the webroot stays read-only. See SECURITY.md.      │"
        echo "  └─────────────────────────────────────────────────────────────────┘"
        echo ""
        mkdir -p "${MODULES_DIR}"
        fix_writable_dir "${MODULES_DIR}" || perm_fix_failed=1
        echo "[PERMISSIONS] modules/ ${MODULES_DIR} is writable by www-data: admins can install and delete external modules in the Control Center."
    else
        echo "[PERMISSIONS] Web UI module installer is disabled: modules/ is read-only for www-data."
        echo "[PERMISSIONS] In the Control Center, downloading a module then fails with \"A duplicate request was detected\"."
        echo "[PERMISSIONS] Use module provisioning (MODULE_PROV.md) or set REDCAP_MODULES_WEB_INSTALL_ENABLE=true."
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

    if [[ "${MODULES_WEB_INSTALL}" == "true" ]]; then
        echo "[PERMISSIONS] Webroot ${APACHE_DOCUMENT_ROOT} is now read-only for www-data, except modules/."
    else
        echo "[PERMISSIONS] Webroot ${APACHE_DOCUMENT_ROOT} is now read-only for www-data."
    fi
    echo "[PERMISSIONS] Set REDCAP_EASY_UPGRADE_ENABLE=true to allow REDCap's browser-based upgrade tool."
fi
