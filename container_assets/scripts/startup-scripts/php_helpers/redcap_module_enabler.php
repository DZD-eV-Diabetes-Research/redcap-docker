<?php

/**
 * REDCap-bootstrapping module enabler (child process).
 *
 * Run as: php -f redcap_module_enabler.php -- <tasks.json>
 *
 * This is invoked as a separate process by 140_module_provisioning.php so that
 * REDCap's heavy global bootstrap (Config/init_global.php) is isolated from the
 * orchestrator. It is the only place that talks to the External Module
 * framework, which gives us correct behaviour for free: config-default
 * settings, cron registration, PHP/REDCap compatibility checks and the proper
 * typed storage of settings — none of which a hand-written SQL INSERT provides.
 *
 * The tasks file is a JSON list of objects:
 *   { "source": "repo", "module_id": 123, "enable": true,
 *     "projects": [1,2], "settings": {"key": "value"} }
 *   { "source": "resolved", "prefix": "x", "version": "1.0.0",
 *     "enable": false, "projects": [], "settings": {} }
 *
 * For "repo" tasks the module is downloaded from the REDCap central repository
 * first; "resolved" tasks have already had their files placed on disk by the
 * orchestrator.
 */

require_once __DIR__ . '/redcap_info.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/module_fetcher.php';

$EM_ERROR_COUNT = 0;

function em_log(string $level, string $msg): void
{
    global $EM_ERROR_COUNT;
    if ($level === 'ERROR') {
        $EM_ERROR_COUNT++;
    }
    printf("[MODULE PROVISIONING][%s]: %s\n", $level, $msg);
}

$tasks_file = $argv[1] ?? null;
if ($tasks_file === null || !is_file($tasks_file)) {
    em_log('ERROR', "Enabler called without a valid tasks file. Nothing to do.");
    exit(1);
}

$tasks = json_decode((string) file_get_contents($tasks_file), true);
if (!is_array($tasks)) {
    em_log('ERROR', "Tasks file '$tasks_file' did not contain valid JSON.");
    exit(1);
}
if (empty($tasks)) {
    exit(0);
}

// ── Bootstrap REDCap ────────────────────────────────────────────────────────
// Mirrors how REDCap's own cron.php initialises the framework for CLI use.
$REDCAPDIR = get_installed_redcap_version_dir_path();
if (!defined('NOAUTH')) {
    define('NOAUTH', true);
}
// Designate this as a system (cron-like) context, exactly as REDCap's own
// cron.php does. Besides disabling auth, the External Module framework uses the
// CRON flag to skip the *user*-based "save settings" permission checks — there
// is no interactive super-user in this startup context, so without it
// enableAndCatchExceptions()/setSystemSetting() would be rejected.
if (!defined('CRON')) {
    define('CRON', true);
}
if (!defined('USERID')) {
    define('USERID', 'SYSTEM');
}
em_log('INFO', "Bootstrapping REDCap from '$REDCAPDIR' to manage external modules...");
require_once $REDCAPDIR . '/Config/init_global.php';

use ExternalModules\ExternalModules;

if (!class_exists('ExternalModules\\ExternalModules')) {
    em_log('ERROR', "External Module framework not available after REDCap bootstrap. Aborting module enable step.");
    exit(1);
}

/**
 * Re-read which module versions are enabled system-wide.
 *
 * The framework caches this per PHP process (ExternalModules::$systemwideEnabledVersions)
 * and disable() does not refresh it. Without a refresh, enabling a new version
 * right after disabling the old one still sees the old version and fails with
 * "a different version of the module is already enabled". REDCap's own UI does
 * the disable and the enable in two separate requests, so it never hits this.
 * The refresh method is private, hence the reflection.
 */
function em_refresh_enabled_versions_cache(): bool
{
    try {
        $method = new ReflectionMethod(ExternalModules::class, 'cacheAllEnableData');
        $method->setAccessible(true); // no-op since PHP 8.1, needed before
        $method->invoke(null);
        return true;
    } catch (Throwable $e) {
        em_log('ERROR', "Could not refresh the External Module framework's enabled-version cache: " . $e->getMessage());
        return false;
    }
}

/**
 * Re-enable $old_version after a failed version switch, so the module does not
 * stay disabled system-wide until the next boot.
 */
function em_restore_old_version(string $prefix, string $old_version, string $new_version): void
{
    em_refresh_enabled_versions_cache();
    $err = ExternalModules::enableAndCatchExceptions($prefix, $old_version);
    if ($err === null) {
        em_log('ERROR', "Module '$prefix' stays at '$old_version', the switch to '$new_version' did not happen. Fix the error above and restart the container.");
        return;
    }
    em_log('ERROR', "Re-enabling '{$prefix}_{$old_version}' through the framework failed: " . $err->getMessage());

    // Last resort: put the version row back directly. That is all the DB-only
    // disable removed besides the cron jobs, which the next successful enable
    // registers again.
    try {
        ExternalModules::setSystemSetting($prefix, ExternalModules::KEY_VERSION, $old_version);
        em_log('ERROR', "Restored the version row of '$prefix' to '$old_version' directly (module cron jobs stay unregistered until the next successful enable).");
    } catch (Throwable $e) {
        em_log('ERROR', "Restoring the version row also failed: " . $e->getMessage()
            . ". Module '$prefix' is now DISABLED system-wide. Enable it in the Control Center or fix the error and restart.");
    }
}

/**
 * Recover the "<prefix>_<version>" folder name REDCap assigned to a central
 * repository module after downloading it, then split it.
 */
function em_resolve_repo_folder(int $module_id): ?array
{
    $con = get_db_con();
    $stmt = $con->prepare(
        "SELECT module_name FROM redcap_external_modules_downloads WHERE module_id = ? ORDER BY time_downloaded DESC LIMIT 1"
    );
    $stmt->bind_param('i', $module_id);
    $stmt->execute();
    $folder = $stmt->get_result()->fetch_column();
    $con->close();
    if (!$folder) {
        return null;
    }
    // Folder name is "<prefix>_<version>"; version is the last "_"-segment.
    $pos = strrpos($folder, '_');
    if ($pos === false) {
        return null;
    }
    return [substr($folder, 0, $pos), substr($folder, $pos + 1)];
}

/** System-enable + configure + project-enable a resolved module. */
function em_enable_module(string $prefix, string $version, bool $enable, array $projects, array $settings): void
{
    if (!$enable) {
        em_log('INFO', "Module '{$prefix}_{$version}' installed (files present), left disabled per policy.");
        return;
    }

    $current = ExternalModules::getEnabledVersion($prefix);
    if ($current === $version) {
        em_log('INFO', "Module '$prefix' already system-enabled at version '$version'. Skipping enable.");
    } else {
        if ($current !== null) {
            // The module is already enabled at a different version (e.g. a
            // pinned version was bumped, or 'latest' resolved to a newer
            // release). enableAndCatchExceptions() refuses to enable a second
            // version of the same namespace, so first clear the old enabled
            // version. disable(..., true) is DB-only: it removes the version
            // setting and cron jobs but keeps system settings and project
            // enablement, and fires no disable hook.
            em_log('INFO', "Updating module '$prefix' from '$current' to '$version'...");
            ExternalModules::disable($prefix, true);
            if (!em_refresh_enabled_versions_cache() || ExternalModules::getEnabledVersion($prefix) !== null) {
                em_log('ERROR', "Module '$prefix' still counts as enabled at '" . ExternalModules::getEnabledVersion($prefix)
                    . "' after disabling it, so '$version' cannot be enabled.");
                em_restore_old_version($prefix, $current, $version);
                return;
            }
        }
        $err = ExternalModules::enableAndCatchExceptions($prefix, $version);
        if ($err !== null) {
            em_log('ERROR', "Failed to enable '{$prefix}_{$version}': " . $err->getMessage());
            if ($current !== null) {
                em_restore_old_version($prefix, $current, $version);
            }
            return;
        }
        em_log('INFO', "System-enabled module '{$prefix}_{$version}'.");
    }

    foreach ($settings as $key => $value) {
        try {
            ExternalModules::setSystemSetting($prefix, (string) $key, $value);
            em_log('INFO', "Set system setting '$key' for '$prefix'.");
        } catch (Throwable $e) {
            em_log('ERROR', "Failed to set system setting '$key' for '$prefix': " . $e->getMessage());
        }
    }

    foreach ($projects as $pid) {
        try {
            ExternalModules::enableForProject($prefix, $version, (string) (int) $pid);
            em_log('INFO', "Enabled module '$prefix' for project $pid.");
        } catch (Throwable $e) {
            em_log('ERROR', "Failed to enable '$prefix' for project $pid: " . $e->getMessage());
        }
    }
}

// ── Process tasks ───────────────────────────────────────────────────────────
foreach ($tasks as $task) {
    $enable = (bool) ($task['enable'] ?? false);
    $projects = $task['projects'] ?? [];
    $settings = $task['settings'] ?? [];

    if (($task['source'] ?? null) === 'repo') {
        $module_id = (int) ($task['module_id'] ?? 0);
        em_log('INFO', "Downloading module #$module_id from the REDCap central repository...");
        try {
            $result = ExternalModules::downloadModule($module_id, true);
        } catch (Throwable $e) {
            em_log('ERROR', "Download of central repo module #$module_id failed: " . $e->getMessage());
            continue;
        }
        if ($result !== 'success') {
            em_log('ERROR', "Download of central repo module #$module_id failed: $result");
            continue;
        }
        $resolved = em_resolve_repo_folder($module_id);
        if ($resolved === null) {
            em_log('ERROR', "Downloaded central repo module #$module_id but could not determine its directory name.");
            continue;
        }
        [$prefix, $version] = $resolved;
        em_log('INFO', "Central repo module #$module_id resolved to '{$prefix}_{$version}'.");
        // REDCap extracted the module as root (root:root, world-readable). Give
        // it the same ownership as modules placed from github/url sources, so it
        // matches the webroot mode right away and not only after the next boot.
        // Group www-data means it was already handled; skip to keep restarts cheap.
        $module_dir = module_provisioning_modules_dir() . "/{$prefix}_{$version}";
        $www_gid = posix_getgrnam('www-data')['gid'] ?? 33;
        if (is_dir($module_dir) && !is_link($module_dir) && @filegroup($module_dir) !== $www_gid) {
            module_apply_webroot_perms($module_dir);
        }
        em_enable_module($prefix, $version, $enable, $projects, $settings);
        continue;
    }

    // "resolved": files already placed by the orchestrator.
    $prefix = $task['prefix'] ?? null;
    $version = $task['version'] ?? null;
    if (!$prefix || !$version) {
        em_log('ERROR', "Resolved task missing prefix/version. Skipping.");
        continue;
    }
    em_enable_module($prefix, $version, $enable, $projects, $settings);
}

// A non-zero exit tells the orchestrator that at least one ERROR was logged.
exit($EM_ERROR_COUNT > 0 ? 1 : 0);
