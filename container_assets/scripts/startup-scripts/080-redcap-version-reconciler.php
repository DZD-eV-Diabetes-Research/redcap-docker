<?php

// Boot reconciler: when REDCAP_VERSION is set, ensure that version is present on disk.
//
// Decision table:
//   REDCAP_VERSION not set              → skip (existing behaviour preserved)
//   No redcap_v* dirs + version set     → fresh install: download + extract
//   Installed == desired                → skip
//   Installed <  desired + AUTO_UPGRADE → auto-upgrade via redcap-upgrade
//   Installed <  desired, no flag       → warn, skip (operator must act)
//   Installed >  desired                → warn, skip (never downgrade automatically)

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/php_helpers/redcap_info.php';
require_once __DIR__ . '/php_helpers/redcap_community_downloader.php';

$desired_version = getenv('REDCAP_VERSION') ?: null;

if (!$desired_version || trim($desired_version) === '') {
    exit(0);
}

$desired_version  = trim($desired_version);
$doc_root         = getenv('APACHE_DOCUMENT_ROOT') ?: '/var/www/html';
$community_user   = getenv('REDCAP_COMMUNITY_USER') ?: null;
$community_pass   = getenv('REDCAP_COMMUNITY_PASSWORD') ?: null;
$auto_upgrade     = filter_var(getenv('REDCAP_AUTO_UPGRADE') ?: 'false', FILTER_VALIDATE_BOOLEAN);

$version_dirs = get_existent_redcap_version_dirs($doc_root);
$installed    = !empty($version_dirs) ? array_key_last($version_dirs) : null;

// ── Resolve symbolic versions (latest-lts / latest-std) to a concrete X.Y.Z ───
if (is_symbolic_redcap_version($desired_version)) {
    try {
        $resolved = resolve_redcap_version($desired_version);
        printf("[RECONCILER] REDCAP_VERSION=%s resolved to v%s\n", $desired_version, $resolved);
        $desired_version = $resolved;
    } catch (Exception $e) {
        fwrite(STDERR, "[RECONCILER] ERROR: " . $e->getMessage() . "\n");
        // A fresh install cannot continue without a concrete version, but an
        // existing install can keep running on what it already has.
        if ($installed !== null) {
            fwrite(STDERR, "[RECONCILER] Keeping installed v$installed for this boot.\n");
            exit(0);
        }
        exit(1);
    }
}

printf("[RECONCILER] REDCAP_VERSION=%s  installed=%s\n",
    $desired_version,
    $installed ?? 'none'
);

// ── Already at desired version ────────────────────────────────────────────────
if ($installed !== null && version_compare($installed, $desired_version, '=')) {
    printf("[RECONCILER] Already at v%s — nothing to do.\n", $desired_version);
    exit(0);
}

// ── Newer version is installed — never auto-downgrade ────────────────────────
if ($installed !== null && version_compare($installed, $desired_version, '>')) {
    printf("[RECONCILER] WARNING: installed v%s is newer than REDCAP_VERSION=%s — skipping.\n",
        $installed, $desired_version);
    exit(0);
}

// ── Upgrade needed ────────────────────────────────────────────────────────────
if ($installed !== null && version_compare($installed, $desired_version, '<')) {
    if (!$auto_upgrade) {
        printf("[RECONCILER] WARNING: installed v%s, desired v%s.\n", $installed, $desired_version);
        printf("[RECONCILER] Set REDCAP_AUTO_UPGRADE=true to upgrade automatically on boot,\n");
        printf("[RECONCILER] or run 'redcap-upgrade' manually inside the container.\n");
        exit(0);
    }

    printf("[RECONCILER] REDCAP_AUTO_UPGRADE=true — upgrading v%s → v%s...\n",
        $installed, $desired_version);

    // Apache is not yet running, so offline mode is unnecessary.
    // If REDCAP_ZIP_PATH points to a pre-downloaded zip, use it to avoid a download.
    $zip_path = getenv('REDCAP_ZIP_PATH') ?: null;
    if ($zip_path && file_exists($zip_path)) {
        $cmd_parts = [
            'redcap-upgrade',
            '--zip', escapeshellarg($zip_path),
            '--version', escapeshellarg($desired_version),
            '--no-offline',
        ];
    } else {
        $cmd_parts = [
            'redcap-upgrade',
            '--version', escapeshellarg($desired_version),
            '--no-offline',
        ];
        if ($community_user) {
            $cmd_parts[] = '--community-user';
            $cmd_parts[] = escapeshellarg($community_user);
        }
        if ($community_pass) {
            $cmd_parts[] = '--community-password';
            $cmd_parts[] = escapeshellarg($community_pass);
        }
    }

    passthru(implode(' ', $cmd_parts), $exit_code);

    if ($exit_code !== 0) {
        fwrite(STDERR, "[RECONCILER] Auto-upgrade failed (exit $exit_code). See output above.\n");
        exit(1);
    }
    exit(0);
}

// ── Fresh install (no files on disk) ─────────────────────────────────────────
printf("[RECONCILER] No REDCap files found — starting fresh install of v%s.\n", $desired_version);

if (!$community_user || !$community_pass) {
    fwrite(STDERR, "[RECONCILER] ERROR: REDCAP_COMMUNITY_USER and REDCAP_COMMUNITY_PASSWORD are required\n");
    fwrite(STDERR, "             to download REDCap from the community portal.\n");
    fwrite(STDERR, "             Set these environment variables, or mount REDCap files manually.\n");
    exit(1);
}

$tmp_dir = sys_get_temp_dir() . '/redcap_install_' . $desired_version . '_' . uniqid();
if (!mkdir($tmp_dir, 0755, true)) {
    fwrite(STDERR, "[RECONCILER] ERROR: Cannot create temp directory $tmp_dir\n");
    exit(1);
}

try {
    $zip_path = download_redcap_from_community($community_user, $community_pass, $desired_version, $tmp_dir);

    printf("[RECONCILER] Extracting archive...\n");
    $zip = new ZipArchive();
    $res = $zip->open($zip_path);
    if ($res !== true) {
        throw new RuntimeException("Failed to open zip (ZipArchive error $res): $zip_path");
    }
    $zip->extractTo($tmp_dir);
    $zip->close();

    $extracted_dir = "$tmp_dir/redcap_v$desired_version";
    if (!is_dir($extracted_dir)) {
        foreach (glob("$tmp_dir/*/redcap_v$desired_version", GLOB_ONLYDIR) ?: [] as $path) {
            $extracted_dir = $path;
            break;
        }
    }
    if (!is_dir($extracted_dir)) {
        throw new RuntimeException("Could not find 'redcap_v$desired_version' in the extracted archive.");
    }

    $target_dir = "$doc_root/redcap_v$desired_version";
    if (is_dir($target_dir)) {
        printf("[RECONCILER] Removing existing partial target: $target_dir\n");
        exec('rm -rf ' . escapeshellarg($target_dir));
    }

    recursive_copy_dir($extracted_dir, $target_dir);

    $uid = posix_getpwnam('www-data')['uid'] ?? 33;
    $gid = posix_getgrnam('www-data')['gid'] ?? 33;
    chown_path_recursive($target_dir, $uid, $gid);

    printf("[RECONCILER] REDCap v%s files installed to %s\n", $desired_version, $target_dir);

    // The install zip ships REDCap's top-level bootstrap files (index.php,
    // redcap_connect.php, api/, surveys/, cron.php, ...) alongside the versioned
    // dir. A fresh webroot needs these or Apache serves 403/404 (no index.php).
    // This is the fresh-install branch (no redcap_v* dirs existed), so the webroot
    // is empty apart from the container-deployed database.php — deploy the rest.
    $archive_root = dirname($extracted_dir);
    deploy_redcap_root_files($archive_root, $doc_root, $uid, $gid);

    printf("[RECONCILER] Database setup will run in the next startup step.\n");

} catch (Exception $e) {
    fwrite(STDERR, "[RECONCILER] ERROR: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    exec('rm -rf ' . escapeshellarg($tmp_dir));
}

exit(0);


// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Deploy REDCap's top-level bootstrap files from the install archive's `redcap/`
 * root into the webroot. The versioned redcap_v* dir is skipped (already copied
 * separately) and database.php is skipped (the container manages its own).
 */
function deploy_redcap_root_files(string $archive_root, string $doc_root, int $uid, int $gid): void
{
    $handle = opendir($archive_root);
    if ($handle === false) {
        return;
    }
    while (($entry = readdir($handle)) !== false) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        // Versioned application code is deployed by the caller.
        if (strpos($entry, 'redcap_v') === 0) {
            continue;
        }
        // Preserve the container-deployed database config.
        if ($entry === 'database.php') {
            continue;
        }
        $src = "$archive_root/$entry";
        $dst = "$doc_root/$entry";
        if (is_dir($src)) {
            recursive_copy_dir($src, $dst);
        } else {
            copy($src, $dst);
        }
        chown_path_recursive($dst, $uid, $gid);
        printf("[RECONCILER] Deployed webroot file: %s\n", $entry);
    }
    closedir($handle);
}

function recursive_copy_dir(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    $handle = opendir($src);
    while (($entry = readdir($handle)) !== false) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $s = "$src/$entry";
        $d = "$dst/$entry";
        if (is_link($s)) {
            if (!file_exists($d)) {
                symlink(readlink($s), $d);
            }
        } elseif (is_dir($s)) {
            recursive_copy_dir($s, $d);
        } else {
            copy($s, $d);
        }
    }
    closedir($handle);
}

function chown_path_recursive(string $path, int $uid, int $gid): void
{
    chown($path, $uid);
    chgrp($path, $gid);
    if (!is_dir($path)) {
        return;
    }
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iter as $item) {
        chown($item->getPathname(), $uid);
        chgrp($item->getPathname(), $gid);
    }
}
