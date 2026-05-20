<?php

/**
 * REDCap file integrity checker.
 *
 * Compares the running installation against a canonical REDCap zip to detect
 * modified, injected, or deleted PHP files. Useful for identifying compromises
 * on instances that were previously exposed to the internet.
 *
 * Usage (via wrapper):  redcap-integrity-check [OPTIONS]
 *
 * Options:
 *   --version <X.X.X>   Version to check against (default: auto-detect from disk)
 *   --zip <path>        Use a local zip instead of downloading from the portal
 *   --community-user    Community portal username (env: REDCAP_COMMUNITY_USER)
 *   --community-password Community portal password (env: REDCAP_COMMUNITY_PASSWORD)
 *   --extensions <list> Comma-separated file extensions to check (default: php)
 *   --report-missing    Also report files present in canonical but missing from install
 *   --help              Show this help text
 *
 * Exit codes:
 *   0  Clean — no unexpected differences found
 *   1  Tampering detected (modified or extra files)
 *   2  Check could not be completed (missing credentials, download failed, etc.)
 */

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once __DIR__ . '/startup-scripts/php_helpers/redcap_info.php';
require_once __DIR__ . '/startup-scripts/php_helpers/redcap_community_downloader.php';

// ── Argument parsing ──────────────────────────────────────────────────────────

$opts = [
    'version'            => null,
    'zip'                => null,
    'community_user'     => getenv('REDCAP_COMMUNITY_USER') ?: null,
    'community_password' => getenv('REDCAP_COMMUNITY_PASSWORD') ?: null,
    'extensions'         => ['php'],
    'report_missing'     => false,
];

$args = array_slice($argv, 1);
for ($i = 0; $i < count($args); $i++) {
    switch ($args[$i]) {
        case '--version':           $opts['version']            = $args[++$i] ?? null; break;
        case '--zip':               $opts['zip']                = $args[++$i] ?? null; break;
        case '--community-user':    $opts['community_user']     = $args[++$i] ?? null; break;
        case '--community-password':$opts['community_password'] = $args[++$i] ?? null; break;
        case '--extensions':
            $opts['extensions'] = array_map('trim', explode(',', $args[++$i] ?? 'php'));
            break;
        case '--report-missing':    $opts['report_missing']     = true; break;
        case '--help':
            print_help();
            exit(0);
        default:
            fwrite(STDERR, "Unknown option: {$args[$i]}\n");
            print_help();
            exit(2);
    }
}

// ── Resolve version ───────────────────────────────────────────────────────────

$doc_root = getenv('APACHE_DOCUMENT_ROOT') ?: '/var/www/html';
$version  = $opts['version'];

if (!$version) {
    $version_dirs = get_existent_redcap_version_dirs($doc_root);
    $version = !empty($version_dirs) ? array_key_last($version_dirs) : null;
}

if (!$version) {
    fwrite(STDERR, "[INTEGRITY] ERROR: No REDCap installation found and --version not specified.\n");
    exit(2);
}

$install_dir = "$doc_root/redcap_v$version";
if (!is_dir($install_dir)) {
    fwrite(STDERR, "[INTEGRITY] ERROR: Expected installation directory not found: $install_dir\n");
    exit(2);
}

printf("[INTEGRITY] Checking REDCap v%s at %s\n", $version, $install_dir);
printf("[INTEGRITY] File extensions checked: %s\n", implode(', ', $opts['extensions']));

// ── Obtain canonical zip ──────────────────────────────────────────────────────

$tmp_dir = sys_get_temp_dir() . '/redcap_integrity_' . $version . '_' . uniqid();
if (!mkdir($tmp_dir, 0755, true)) {
    fwrite(STDERR, "[INTEGRITY] ERROR: Cannot create temp directory $tmp_dir\n");
    exit(2);
}

$cleanup = function () use ($tmp_dir) {
    exec('rm -rf ' . escapeshellarg($tmp_dir));
};
register_shutdown_function($cleanup);

$zip_path = $opts['zip'];

if ($zip_path) {
    if (!file_exists($zip_path)) {
        fwrite(STDERR, "[INTEGRITY] ERROR: Provided zip not found: $zip_path\n");
        exit(2);
    }
    printf("[INTEGRITY] Using provided zip: %s\n", $zip_path);
} else {
    if (!$opts['community_user'] || !$opts['community_password']) {
        fwrite(STDERR, "[INTEGRITY] ERROR: Community portal credentials required to download canonical zip.\n");
        fwrite(STDERR, "             Set REDCAP_COMMUNITY_USER and REDCAP_COMMUNITY_PASSWORD,\n");
        fwrite(STDERR, "             or provide a local zip with --zip <path>.\n");
        exit(2);
    }
    printf("[INTEGRITY] Downloading canonical REDCap v%s from community portal...\n", $version);
    try {
        $zip_path = download_redcap_from_community(
            $opts['community_user'],
            $opts['community_password'],
            $version,
            $tmp_dir
        );
    } catch (Exception $e) {
        fwrite(STDERR, "[INTEGRITY] ERROR: Download failed: " . $e->getMessage() . "\n");
        exit(2);
    }
}

// ── Extract canonical zip ─────────────────────────────────────────────────────

printf("[INTEGRITY] Extracting canonical archive...\n");

$zip = new ZipArchive();
if ($zip->open($zip_path) !== true) {
    fwrite(STDERR, "[INTEGRITY] ERROR: Cannot open zip: $zip_path\n");
    exit(2);
}
$zip->extractTo($tmp_dir);
$zip->close();

// Locate the redcap_vX.X.X directory inside the extracted archive
$canonical_dir = "$tmp_dir/redcap_v$version";
if (!is_dir($canonical_dir)) {
    foreach (glob("$tmp_dir/*/redcap_v$version", GLOB_ONLYDIR) ?: [] as $path) {
        $canonical_dir = $path;
        break;
    }
}
if (!is_dir($canonical_dir)) {
    fwrite(STDERR, "[INTEGRITY] ERROR: Could not find redcap_v$version/ in the extracted archive.\n");
    exit(2);
}

printf("[INTEGRITY] Canonical source: %s\n", $canonical_dir);

// ── Build checksum maps ───────────────────────────────────────────────────────

printf("[INTEGRITY] Building checksums...\n");

$canonical_map = build_checksum_map($canonical_dir, $opts['extensions']);
$install_map   = build_checksum_map($install_dir, $opts['extensions']);

printf("[INTEGRITY] Canonical files: %d  |  Installed files: %d\n",
    count($canonical_map), count($install_map));

// ── Compare ───────────────────────────────────────────────────────────────────

$modified = [];
$extra    = [];
$missing  = [];

foreach ($canonical_map as $rel_path => $canonical_hash) {
    if (!isset($install_map[$rel_path])) {
        $missing[] = $rel_path;
    } elseif ($install_map[$rel_path] !== $canonical_hash) {
        $modified[] = $rel_path;
    }
}

foreach ($install_map as $rel_path => $_) {
    if (!isset($canonical_map[$rel_path])) {
        $extra[] = $rel_path;
    }
}

// Also scan for extra PHP files in the document root OUTSIDE the version directory
// These could be backdoors placed directly in the webroot
$root_extra = find_extra_root_files($doc_root, $version, $opts['extensions']);

// ── Report ────────────────────────────────────────────────────────────────────

$tampering_detected = false;

if (!empty($modified)) {
    $tampering_detected = true;
    printf("\n[INTEGRITY] *** MODIFIED FILES (%d) — files differ from canonical source ***\n", count($modified));
    foreach ($modified as $path) {
        printf("  MODIFIED  %s\n", $path);
    }
}

if (!empty($extra)) {
    $tampering_detected = true;
    printf("\n[INTEGRITY] *** EXTRA FILES IN INSTALL (%d) — not present in canonical source ***\n", count($extra));
    foreach ($extra as $path) {
        printf("  EXTRA     redcap_v%s/%s\n", $version, $path);
    }
}

if (!empty($root_extra)) {
    $tampering_detected = true;
    printf("\n[INTEGRITY] *** EXTRA FILES IN WEBROOT (%d) — PHP files outside any redcap_v* directory ***\n", count($root_extra));
    foreach ($root_extra as $path) {
        printf("  WEBROOT   %s\n", $path);
    }
}

if ($opts['report_missing'] && !empty($missing)) {
    printf("\n[INTEGRITY] Missing files (%d) — present in canonical but absent from install:\n", count($missing));
    foreach ($missing as $path) {
        printf("  MISSING   %s\n", $path);
    }
}

if ($tampering_detected) {
    printf("\n[INTEGRITY] *** RESULT: TAMPERING DETECTED — investigate the files listed above. ***\n\n");
    exit(1);
}

$skipped_missing = !$opts['report_missing'] && !empty($missing)
    ? sprintf(" (%d missing files not shown — use --report-missing)", count($missing))
    : '';

printf("\n[INTEGRITY] RESULT: Clean — no unexpected differences found.%s\n\n", $skipped_missing);
exit(0);


// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Recursively build a map of relative_path => md5_hash for files matching extensions.
 */
function build_checksum_map(string $base_dir, array $extensions): array
{
    $map    = [];
    $base   = rtrim(realpath($base_dir), '/') . '/';
    $iter   = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iter as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $extensions, true)) {
            continue;
        }
        $abs      = $file->getRealPath();
        $rel_path = substr($abs, strlen($base));
        $map[$rel_path] = md5_file($abs);
    }

    return $map;
}

/**
 * Find PHP files in the document root that live outside all redcap_v* directories.
 * These are candidates for injected backdoors.
 */
function find_extra_root_files(string $doc_root, string $installed_version, array $extensions): array
{
    // Known legitimate files at the document root placed by the container itself
    static $known_root_files = ['database.php', 'hook_functions.php'];

    $extra = [];
    $handle = opendir($doc_root);
    if (!$handle) {
        return [];
    }

    while (($entry = readdir($handle)) !== false) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = "$doc_root/$entry";
        // Skip all redcap_v* directories — those are checked separately
        if (is_dir($full) && preg_match('/^redcap_v/', $entry)) {
            continue;
        }
        // Skip known container-managed files
        if (in_array($entry, $known_root_files, true)) {
            continue;
        }
        // Only flag files (not directories like edocs/, temp/)
        if (is_file($full)) {
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions, true)) {
                $extra[] = $entry;
            }
        }
    }

    closedir($handle);
    return $extra;
}

function print_help(): void
{
    echo <<<'HELP'
Usage: redcap-integrity-check [OPTIONS]

Compares the running REDCap installation against a canonical downloaded copy to
detect modified, injected, or extra PHP files (potential backdoors).

Options:
  --version <X.X.X>       Version to check (default: auto-detect from disk)
  --zip <path>            Use a local zip instead of downloading
  --community-user        Community portal username (or REDCAP_COMMUNITY_USER)
  --community-password    Community portal password (or REDCAP_COMMUNITY_PASSWORD)
  --extensions <list>     Comma-separated extensions to compare (default: php)
  --report-missing        Also report files in canonical but absent from install
  --help                  Show this text

Exit codes:
  0  Clean
  1  Tampering detected
  2  Check could not be completed

HELP;
}
