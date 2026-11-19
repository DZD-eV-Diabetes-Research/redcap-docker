<?php

// Boot-time warning for REDCap versions with known critical vulnerabilities.
// Never blocks startup: it only tells the operator what to upgrade to.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/php_helpers/redcap_info.php';

// Prefer the version REDCap itself reports (DB); fall back to the newest redcap_v* dir.
$version = null;
try {
    $version = get_installed_redcap_version_no();
} catch (Throwable $e) {
    // DB not reachable / not installed yet: fall through to the directory check.
}
if (!$version) {
    $version = get_highest_redcap_version_no();
}
if (!$version) {
    exit(0);
}

/**
 * CVE-2026-90817 — unauthenticated RCE via survey passthrough (CVSS 9.8, CISA KEV 2026-11-18).
 * Affected: >= 13.3.0. Fixed: 16.0.49 (16.0.x LTS), 17.3.10 (17.3.x LTS), 17.4.4 (17.4.x STD) and later.
 * Versions on other branches between 13.3.0 and 17.4.4 have no published fix -> treated as affected.
 */
function is_affected_by_cve_2026_90817(string $v): bool
{
    if (version_compare($v, '13.3.0', '<')) {
        return false;
    }
    if (version_compare($v, '16.0.49', '>=') && version_compare($v, '16.1.0', '<')) {
        return false;
    }
    if (version_compare($v, '17.3.10', '>=') && version_compare($v, '17.4.0', '<')) {
        return false;
    }
    if (version_compare($v, '17.4.4', '>=')) {
        return false;
    }
    return true;
}

if (is_affected_by_cve_2026_90817($version)) {
    $guard = getenv('REDCAP_REQUEST_GUARD');
    $guard_on = !($guard !== false && preg_match('/^\s*(0|n|no|false|off)\s*$/i', $guard));
    $lines = [
        "REDCap v$version is affected by CVE-2026-90817 (unauthenticated remote code execution",
        "via public survey links, CVSS 9.8, actively exploited — CISA KEV).",
        "UPGRADE NOW to 16.0.49 (LTS), 17.3.10 (LTS) or 17.4.4 (Standard) or later:",
        "    docker compose exec <service> redcap-upgrade --version <fixed version>",
        $guard_on
            ? "Interim: the container's request guard is ON and blocks the known exploit path."
            : "WARNING: REDCAP_REQUEST_GUARD is disabled — this instance is exposed. Re-enable it.",
        "Details: SECURITY.md",
    ];
    echo "\n";
    foreach ($lines as $l) {
        echo "  [SECURITY] $l\n";
    }
    echo "\n";
}
