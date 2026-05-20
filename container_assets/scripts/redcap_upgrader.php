<?php

// ── Entry guard ───────────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

// ── Imports ───────────────────────────────────────────────────────────────────
$helpers_dir = __DIR__ . '/startup-scripts/php_helpers';
require_once $helpers_dir . '/db.php';
require_once $helpers_dir . '/redcap_info.php';
require_once $helpers_dir . '/run_sql_file.php';
require_once $helpers_dir . '/processed_file_state_manager.php';
require_once $helpers_dir . '/redcap_community_downloader.php';


// ── Usage ─────────────────────────────────────────────────────────────────────

function usage(): void
{
    echo <<<USAGE
Usage: redcap-upgrade [OPTIONS]

When called with no arguments (and attached to a terminal) an interactive
wizard guides you through checking for updates and performing the upgrade.

Upgrade REDCap in-place inside the running container without a full restart.

Source (one of the following is required in non-wizard mode):
  --version <X.X.X>         Target version to download from the community portal.
  --zip <path>              Use a locally provided redcap_vX.X.X.zip instead of
                            downloading. Version is inferred from the filename
                            (override with --version if needed).

Community portal credentials (required unless --zip is given; may also be set
via environment variables):
  --community-user <user>       REDCap community username
                                (env: REDCAP_COMMUNITY_USER)
  --community-password <pass>   REDCap community password
                                (env: REDCAP_COMMUNITY_PASSWORD)

Backup / rollback:
  --no-backup                  Skip the pre-upgrade database backup.
                               WARNING: disables automatic rollback on failure.
  --backup-dir <path>          Directory for backup files.
                               (env: REDCAP_UPGRADE_BACKUP_DIR,
                                default: /opt/redcap-docker/backups)
  --backup-db-user <user>      DB user for the backup (e.g. root).
                               Use when the app user lacks mysqldump privileges.
                               (env: REDCAP_UPGRADE_BACKUP_DB_USER)
  --backup-db-password <pass>  Password for --backup-db-user.
                               (env: REDCAP_UPGRADE_BACKUP_DB_PASSWORD)
  --rollback <file>            Restore a previously created backup.
                               Restores the database and removes the version
                               directory that was installed after the backup was
                               taken (parsed from the backup filename).

Behaviour flags:
  --dry-run       Show what would be done without making any changes.
  --keep-old      Keep the previous version directory after a successful
                  upgrade. When backup is enabled this is always the default
                  (the old directory is needed for a clean rollback).
  --no-sql        Skip SQL upgrade scripts (file extraction only).
  --no-offline    Do not set REDCap to offline mode during the upgrade.

  --help, -h      Show this help.

Examples:
  # Launch the interactive wizard:
  redcap-upgrade

  # Non-interactive upgrade (credentials from env vars):
  redcap-upgrade --version 14.9.5

  # Use a locally pre-downloaded zip:
  redcap-upgrade --zip /var/www/html/redcap_v14.9.5.zip

  # Roll back a previous upgrade:
  redcap-upgrade --rollback /opt/redcap-docker/backups/redcap_backup_20260518_143022_from_14.8.0_to_14.9.5.sql.gz

USAGE;
}


// ── Argument parsing ──────────────────────────────────────────────────────────

function parse_args(array $argv): array
{
    $opts = [
        'version'            => null,
        'zip'                => null,
        'community-user'     => getenv('REDCAP_COMMUNITY_USER') ?: null,
        'community-password' => getenv('REDCAP_COMMUNITY_PASSWORD') ?: null,
        'no-backup'          => false,
        'backup-dir'         => getenv('REDCAP_UPGRADE_BACKUP_DIR')          ?: '/opt/redcap-docker/backups',
        'backup-db-user'     => getenv('REDCAP_UPGRADE_BACKUP_DB_USER')      ?: null,
        'backup-db-password' => getenv('REDCAP_UPGRADE_BACKUP_DB_PASSWORD')  ?: null,
        'rollback'           => null,
        'dry-run'            => false,
        'keep-old'           => false,
        'no-sql'             => false,
        'no-offline'         => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        switch ($argv[$i]) {
            case '--version':            $opts['version']            = $argv[++$i] ?? null; break;
            case '--zip':                $opts['zip']                = $argv[++$i] ?? null; break;
            case '--community-user':     $opts['community-user']     = $argv[++$i] ?? null; break;
            case '--community-password': $opts['community-password'] = $argv[++$i] ?? null; break;
            case '--no-backup':          $opts['no-backup']          = true; break;
            case '--backup-dir':         $opts['backup-dir']         = $argv[++$i] ?? null; break;
            case '--backup-db-user':     $opts['backup-db-user']     = $argv[++$i] ?? null; break;
            case '--backup-db-password': $opts['backup-db-password'] = $argv[++$i] ?? null; break;
            case '--rollback':           $opts['rollback']           = $argv[++$i] ?? null; break;
            case '--dry-run':            $opts['dry-run']            = true; break;
            case '--keep-old':           $opts['keep-old']           = true; break;
            case '--no-sql':             $opts['no-sql']             = true; break;
            case '--no-offline':         $opts['no-offline']         = true; break;
            case '--help': case '-h':    usage(); exit(0);
            default:
                fwrite(STDERR, "Unknown option: {$argv[$i]}\n\n");
                usage();
                exit(1);
        }
    }

    return $opts;
}


// ── Interactive prompt helpers ────────────────────────────────────────────────

/**
 * Print a prompt and read one line from STDIN.
 * If the user just presses Enter, $default is returned.
 */
function prompt(string $label, ?string $default = null): string
{
    $hint = $default !== null ? " [$default]" : '';
    echo "$label$hint: ";
    $line = rtrim(fgets(STDIN) ?: '', "\n\r");
    return ($line === '' && $default !== null) ? $default : $line;
}

/**
 * Print a password prompt and read without echoing the typed characters.
 * Falls back to plain fgets if stty is unavailable.
 */
function prompt_password(string $label): string
{
    echo "$label: ";
    $stty_ok = @system('stty -echo 2>/dev/null', $rc) !== false && $rc === 0;
    $pass = rtrim(fgets(STDIN) ?: '', "\n\r");
    if ($stty_ok) {
        system('stty echo');
    }
    echo "\n";
    return $pass;
}

/**
 * Ask a yes/no question. Returns true for yes, false for no.
 * $default_yes controls what plain Enter means.
 */
function prompt_confirm(string $question, bool $default_yes = true): bool
{
    $hint = $default_yes ? '[Y/n]' : '[y/N]';
    echo "$question $hint: ";
    $answer = strtolower(trim(fgets(STDIN) ?: ''));
    if ($answer === '') {
        return $default_yes;
    }
    return in_array($answer, ['y', 'yes'], true);
}

function print_separator(string $title = ''): void
{
    $line = str_repeat('─', 60);
    echo "\n" . ($title ? "── $title " . str_repeat('─', max(0, 58 - strlen($title))) : $line) . "\n";
}


// ── Interactive wizard ────────────────────────────────────────────────────────

function run_wizard(array $opts): void
{
    echo "=== REDCap Upgrade Wizard ===\n";
    echo "\n";
    echo "  *** BETA FEATURE ***\n";
    echo "  This upgrader is beta software. Verify your backup before\n";
    echo "  discarding the old version directory. Report issues at:\n";
    echo "  https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues\n";
    echo "\n";

    // ── Current version ───────────────────────────────────────────────────────

    try {
        $db              = get_db_con();
        $current_version = get_installed_redcap_version_no();
        $db->close();
    } catch (Exception $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(1);
    }

    echo "\nInstalled version: $current_version\n";

    // ── Fetch available versions (public endpoint — no auth needed) ───────────

    print_separator('Available Updates');

    $portal_data = null;
    if (!$opts['zip']) {
        echo "Fetching available versions...";
        $portal_data = fetch_available_versions_from_portal($current_version);
        if ($portal_data !== null) {
            echo " found " . count($portal_data['versions']) . "\n";
        } else {
            echo " failed (network or parse error)\n";
        }
    }

    // ── Version selection ─────────────────────────────────────────────────────

    $recommended = null;

    if ($opts['zip']) {
        echo "Using local zip: {$opts['zip']}\n";
        if (!$opts['version']) {
            if (preg_match('/redcap_v(\d+\.\d+\.\d+)\.zip$/i', basename($opts['zip']), $m)) {
                $opts['version'] = $m[1];
            }
        }
        $recommended = $opts['version'];

    } elseif ($portal_data === null || empty($portal_data['versions'])) {
        if ($opts['version']) {
            echo "Could not fetch version list; using --version {$opts['version']}.\n";
            $recommended = $opts['version'];
        } else {
            echo "Could not fetch version list from the community portal.\n";
            $opts['version'] = prompt('Enter target version (e.g. 16.0.16)');
            if (!$opts['version']) {
                echo "No version entered. Exiting.\n";
                exit(0);
            }
            $recommended = $opts['version'];
        }

    } else {
        $current_branch = $portal_data['current_branch'];
        echo "Current branch  : $current_branch\n\n";

        // Group by branch for display
        $by_branch = [];
        foreach ($portal_data['versions'] as $entry) {
            $by_branch[$entry['branch']][] = $entry;
        }

        $branch_versions = $by_branch[$current_branch] ?? [];

        if (empty($branch_versions)) {
            // Already on the latest version in the current branch
            $branch_label = strtoupper($current_branch);
            echo "You are already on the latest $branch_label version ($current_version).\n";

            // Collect newer versions from other branches
            $other_versions = [];
            foreach ($by_branch as $branch => $entries) {
                if ($branch !== $current_branch) {
                    foreach ($entries as $entry) {
                        $other_versions[] = $entry;
                    }
                }
            }

            if (empty($other_versions)) {
                echo "No newer versions available in any branch. You are up to date.\n";
                exit(0);
            }

            // Show what is available across other branches
            echo "\n";
            foreach (['lts', 'std'] as $branch) {
                if ($branch === $current_branch || empty($by_branch[$branch])) {
                    continue;
                }
                $latest_other = end($by_branch[$branch]);
                echo strtoupper($branch) . " versions:\n";
                foreach ($by_branch[$branch] as $entry) {
                    $marker = ($entry['version'] === $latest_other['version']) ? '  <-- latest' : '';
                    $date   = $entry['date'] ? "  ({$entry['date']})" : '';
                    echo "  {$entry['version']}$date$marker\n";
                }
                echo "\n";
            }

            echo "Note: switching branches is a one-way upgrade — you cannot go back to $branch_label without a rollback.\n\n";

            if (!prompt_confirm('Switch to a newer branch?', false)) {
                echo "Staying on $branch_label. Nothing to do.\n";
                exit(0);
            }

            usort($other_versions, static fn($a, $b) => version_compare($a['version'], $b['version']));
            $recommended = end($other_versions)['version'];

        } else {
            // Normal case: there are newer versions in the current branch
            $recommended = end($branch_versions)['version'];

            foreach (['lts', 'std'] as $branch) {
                if (empty($by_branch[$branch])) {
                    continue;
                }
                $label = strtoupper($branch);
                echo "$label versions:\n";
                foreach ($by_branch[$branch] as $entry) {
                    $marker = ($entry['version'] === $recommended)
                        ? '  <-- recommended (your branch, latest)'
                        : '';
                    $date = $entry['date'] ? "  ({$entry['date']})" : '';
                    echo "  {$entry['version']}$date$marker\n";
                }
                echo "\n";
            }
        }

        $opts['version'] = prompt('Which version would you like to install', $recommended);
        if (!$opts['version']) {
            echo "No version entered. Exiting.\n";
            exit(0);
        }
    }

    if (version_compare($opts['version'], $current_version, '<=')) {
        echo "Version {$opts['version']} is not newer than installed $current_version. Nothing to do.\n";
        exit(0);
    }

    // ── Credentials (only needed for portal download, not for --zip) ──────────

    if (!$opts['zip']) {
        print_separator('Community Portal Credentials');

        if ($opts['community-user']) {
            echo "Username: (set via environment)\n";
        } else {
            $opts['community-user'] = prompt('Username');
        }

        if ($opts['community-password']) {
            echo "Password: (set via environment)\n";
        } else {
            $opts['community-password'] = prompt_password('Password');
        }

        if (!$opts['community-user'] || !$opts['community-password']) {
            fwrite(STDERR, "Error: Username and password are required for the community portal download.\n");
            exit(1);
        }
    }

    // ── Show upgrade plan and confirm ─────────────────────────────────────────

    print_separator('Upgrade Plan');

    $backup_desc     = $opts['no-backup'] ? 'disabled (--no-backup)' : $opts['backup-dir'];
    $keep_old_desc   = ($opts['no-backup'] && !$opts['keep-old']) ? 'no' : 'yes (for rollback)';

    echo "  From           : $current_version\n";
    echo "  To             : {$opts['version']}\n";
    echo "  Source         : " . ($opts['zip'] ? $opts['zip'] : 'community portal download') . "\n";
    echo "  Database backup: $backup_desc\n";
    echo "  Offline mode   : " . ($opts['no-offline'] ? 'no' : 'yes (automatic)') . "\n";
    echo "  Keep old dir   : $keep_old_desc\n";
    echo "\n";

    if (!prompt_confirm('Start upgrade?')) {
        echo "Cancelled.\n";
        exit(0);
    }

    echo "\n";
    run_upgrade($opts);
}


// ── Database backup / restore ─────────────────────────────────────────────────

/**
 * Run a quick no-data test dump to verify the given credentials have
 * sufficient privileges for a full mysqldump.
 *
 * Returns null on success, or the mysqldump error string on failure.
 */
function test_backup_prerequisites(
    string $user,
    string $pass,
    string $host,
    string $port,
    string $dbname
): ?string {
    $opts_file = tempnam(sys_get_temp_dir(), 'redcap_mysql_opts_');
    file_put_contents($opts_file, "[mysqldump]\npassword=$pass\n");
    chmod($opts_file, 0600);

    $cmd = sprintf(
        'mysqldump --defaults-file=%s -h %s -P %s -u %s --no-tablespaces --no-data %s 2>&1 > /dev/null',
        escapeshellarg($opts_file),
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($user),
        escapeshellarg($dbname)
    );

    exec($cmd, $output, $exit_code);
    @unlink($opts_file);

    return $exit_code === 0 ? null : implode("\n", $output);
}

/**
 * Dump the configured database to a gzip-compressed SQL file.
 *
 * @param string      $backup_dir     Directory to write the backup file
 * @param string      $from_version   Current REDCap version (used in filename)
 * @param string      $to_version     Target REDCap version (used in filename)
 * @param string|null $override_user  Optional elevated DB user (e.g. root)
 * @param string|null $override_pass  Password for $override_user
 */
function backup_database(
    string  $backup_dir,
    string  $from_version,
    string  $to_version,
    ?string $override_user = null,
    ?string $override_pass = null
): string {
    if (!is_dir($backup_dir) && !mkdir($backup_dir, 0750, true)) {
        throw new RuntimeException("Cannot create backup directory: $backup_dir");
    }

    $timestamp   = date('Ymd_His');
    $backup_file = "$backup_dir/redcap_backup_{$timestamp}_from_{$from_version}_to_{$to_version}.sql.gz";

    $host   = getenv('DB_HOSTNAME') ?: 'localhost';
    $port   = getenv('DB_PORT')     ?: '3306';
    $dbname = getenv('DB_NAME')     ?: '';
    $user   = $override_user  ?? (getenv('DB_USERNAME') ?: '');
    $pass   = $override_pass  ?? (getenv('DB_PASSWORD') ?: '');

    // Write a temporary MySQL options file so the password never appears
    // in the process list or shell history.
    $opts_file = tempnam(sys_get_temp_dir(), 'redcap_mysql_opts_');
    file_put_contents($opts_file, "[mysqldump]\npassword=$pass\n");
    chmod($opts_file, 0600);

    // --no-tablespaces avoids requiring the PROCESS privilege that MySQL 8+
    // demands for tablespace metadata — unnecessary for an application backup.
    $cmd = sprintf(
        'mysqldump --defaults-file=%s -h %s -P %s -u %s --no-tablespaces --single-transaction --routines --triggers %s | gzip > %s',
        escapeshellarg($opts_file),
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($user),
        escapeshellarg($dbname),
        escapeshellarg($backup_file)
    );

    exec($cmd . ' 2>&1', $output, $exit_code);
    @unlink($opts_file);

    if ($exit_code !== 0) {
        @unlink($backup_file);
        throw new RuntimeException(
            "mysqldump failed (exit $exit_code):\n" . implode("\n", $output)
        );
    }

    $size_mb = round(filesize($backup_file) / 1024 / 1024, 1);
    printf("Backup created: %s (%.1f MB)\n", $backup_file, $size_mb);
    return $backup_file;
}

function restore_database_backup(string $backup_file): void
{
    if (!file_exists($backup_file)) {
        throw new RuntimeException("Backup file not found: $backup_file");
    }

    $host   = getenv('DB_HOSTNAME') ?: 'localhost';
    $port   = getenv('DB_PORT')     ?: '3306';
    $dbname = getenv('DB_NAME')     ?: '';
    $user   = getenv('DB_USERNAME') ?: '';
    $pass   = getenv('DB_PASSWORD') ?: '';

    $opts_file = tempnam(sys_get_temp_dir(), 'redcap_mysql_opts_');
    file_put_contents($opts_file, "[client]\npassword=$pass\n");
    chmod($opts_file, 0600);

    $cmd = sprintf(
        'gzip -dc %s | mysql --defaults-file=%s -h %s -P %s -u %s %s',
        escapeshellarg($backup_file),
        escapeshellarg($opts_file),
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($user),
        escapeshellarg($dbname)
    );

    exec($cmd . ' 2>&1', $output, $exit_code);
    @unlink($opts_file);

    if ($exit_code !== 0) {
        throw new RuntimeException(
            "Database restore failed (exit $exit_code):\n" . implode("\n", $output)
        );
    }

    printf("Database successfully restored from: $backup_file\n");
}

function parse_to_version_from_backup_filename(string $backup_file): ?string
{
    if (preg_match('/_to_(\d+\.\d+\.\d+)\.sql\.gz$/i', basename($backup_file), $m)) {
        return $m[1];
    }
    return null;
}


// ── Rollback ──────────────────────────────────────────────────────────────────

function run_rollback(string $backup_file, bool $dry_run): void
{
    printf("=== REDCap Rollback ===\n\n");

    if (!file_exists($backup_file)) {
        fwrite(STDERR, "Error: Backup file not found: $backup_file\n");
        exit(1);
    }

    $to_version = parse_to_version_from_backup_filename($backup_file);
    $doc_root   = get_redcap_source_path();

    printf("Backup file : %s\n", $backup_file);
    if ($to_version) {
        printf("Will remove : redcap_v%s (if present)\n", $to_version);
    }
    printf("\n");

    if ($dry_run) {
        printf("[DRY RUN] Steps that would be performed:\n");
        printf("  1. Restore database from backup.\n");
        if ($to_version) {
            printf("  2. Remove %s/redcap_v%s/ (if present).\n", $doc_root, $to_version);
        }
        printf("\n[DRY RUN] No changes were made.\n");
        exit(0);
    }

    try {
        $db = get_db_con();
        set_redcap_offline(true, $db);
        $db->close();
    } catch (Exception $e) {
        printf("Warning: Could not set REDCap offline before rollback: %s\n", $e->getMessage());
    }

    try {
        printf("Restoring database...\n");
        restore_database_backup($backup_file);
    } catch (Exception $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(1);
    }

    if ($to_version) {
        $dir_to_remove = "$doc_root/redcap_v$to_version";
        if (is_dir($dir_to_remove)) {
            printf("Removing installed version directory: %s\n", $dir_to_remove);
            recursive_rmdir($dir_to_remove);
        } else {
            printf("Version directory not present (already removed): %s\n", $dir_to_remove);
        }
    }

    try {
        $db = get_db_con();
        set_redcap_offline(false, $db);
        $db->close();
    } catch (Exception $e) {
        printf("Warning: Could not restore REDCap to online mode: %s\n", $e->getMessage());
        printf("Set system_offline=0 in redcap_config manually if needed.\n");
    }

    printf("\nRollback complete.\n");

    $version_dirs = get_existent_redcap_version_dirs();
    if (!empty($version_dirs)) {
        printf("Active version : redcap_v%s\n", array_key_last($version_dirs));
    }
}


// ── Helpers ───────────────────────────────────────────────────────────────────

function find_redcap_version_dir_in_extraction(string $extract_root, string $version): string
{
    $expected = "redcap_v$version";

    if (is_dir("$extract_root/$expected")) {
        return "$extract_root/$expected";
    }

    foreach (glob("$extract_root/*/", GLOB_ONLYDIR) ?: [] as $sub) {
        $nested = rtrim($sub, '/') . "/$expected";
        if (is_dir($nested)) {
            return $nested;
        }
    }

    throw new RuntimeException(
        "Could not find '$expected' in the extracted archive at $extract_root.\n" .
        "The zip may contain a different version or have an unexpected structure."
    );
}

function extract_version_from_sql_filename(string $filename): ?string
{
    if (!preg_match('/upgrade_redcap_v(\d+\.\d+\.?\d*)/i', $filename, $m)) {
        return null;
    }
    $parts = explode('.', $m[1]);
    return implode('.', array_map('intval', $parts));
}

function find_upgrade_sql_files(string $version_dir, string $current_version, string $target_version): array
{
    $sql_dir = "$version_dir/Resources/sql";

    if (!is_dir($sql_dir)) {
        printf("WARNING: Expected SQL directory not found at '$sql_dir'. Skipping SQL step.\n");
        return [];
    }

    $candidates = [];
    $handle     = opendir($sql_dir);
    while ($handle && ($entry = readdir($handle)) !== false) {
        if (!preg_match('/^upgrade_.*\.sql$/i', $entry)) {
            continue;
        }
        $file_version = extract_version_from_sql_filename($entry);
        if (
            $file_version !== null &&
            version_compare($file_version, $current_version, '>') &&
            version_compare($file_version, $target_version, '<=')
        ) {
            $candidates[$file_version] = "$sql_dir/$entry";
        }
    }
    if ($handle) {
        closedir($handle);
    }

    uksort($candidates, 'version_compare');
    return array_values($candidates);
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

function recursive_rmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $handle = opendir($dir);
    while (($entry = readdir($handle)) !== false) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = "$dir/$entry";
        if (is_link($path) || is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            recursive_rmdir($path);
        }
    }
    closedir($handle);
    rmdir($dir);
}

function chown_www_data_recursive(string $path): void
{
    $uid = posix_getpwnam('www-data')['uid'] ?? 33;
    $gid = posix_getgrnam('www-data')['gid'] ?? 33;

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

function set_redcap_offline(bool $offline, mysqli $db): void
{
    $value = $offline ? '1' : '0';
    $label = $offline ? 'OFFLINE' : 'ONLINE';
    $db->query("UPDATE redcap_config SET value='$value' WHERE field_name='system_offline'");
    printf("REDCap set to $label mode.\n");
}


// ── Core upgrade logic ────────────────────────────────────────────────────────

/**
 * Execute the full upgrade using the given options array.
 * Called by both main() (non-interactive) and run_wizard() (interactive).
 *
 * When run_wizard() has already authenticated, it passes the cookie jar via
 * $opts['_cookie_jar'] to avoid a second login.
 */
function run_upgrade(array $opts): void
{
    $version          = $opts['version'];
    $zip_path         = $opts['zip'];
    $community_user   = $opts['community-user'];
    $community_pass   = $opts['community-password'];
    $no_backup        = $opts['no-backup'];
    $backup_dir       = $opts['backup-dir'];
    $backup_db_user   = $opts['backup-db-user']     ?? null;
    $backup_db_pass   = $opts['backup-db-password'] ?? null;
    $dry_run          = $opts['dry-run'];
    $keep_old         = $opts['keep-old'];
    $skip_sql         = $opts['no-sql'];
    $skip_offline     = $opts['no-offline'];
    // ── Pre-flight ────────────────────────────────────────────────────────────

    printf("=== REDCap In-Place Upgrade [BETA] ===\n\n");
    printf("  *** BETA FEATURE — please report issues at ***\n");
    printf("  *** https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues ***\n\n");

    try {
        $db = get_db_con();
    } catch (Exception $e) {
        fwrite(STDERR, "Error: Cannot connect to database: " . $e->getMessage() . "\n");
        exit(1);
    }

    try {
        $current_version = get_installed_redcap_version_no();
    } catch (Exception $e) {
        fwrite(STDERR, "Error: Cannot determine installed REDCap version: " . $e->getMessage() . "\n");
        $db->close();
        exit(1);
    }

    printf("Installed version : %s\n", $current_version);
    printf("Target version    : %s\n", $version);
    printf("Backup            : %s\n", $no_backup ? 'disabled (--no-backup)' : "enabled -> $backup_dir");
    printf("\n");

    if (version_compare($version, $current_version, '<=')) {
        fwrite(STDERR, "Error: Target $version is not newer than installed $current_version. Nothing to do.\n");
        $db->close();
        exit(1);
    }

    try {
        $old_version_dir = get_installed_redcap_version_dir_path();
    } catch (Exception $e) {
        fwrite(STDERR, "Error: Cannot locate current version directory: " . $e->getMessage() . "\n");
        $db->close();
        exit(1);
    }

    $doc_root        = get_redcap_source_path();
    $target_dir      = "$doc_root/redcap_v$version";
    $will_delete_old = !$keep_old && $no_backup;

    // ── Dry run ───────────────────────────────────────────────────────────────

    if ($dry_run) {
        $step = 1;
        printf("[DRY RUN] Steps that would be performed:\n\n");
        if (!$no_backup) {
            printf("  %d. Dump database to %s/redcap_backup_*_from_%s_to_%s.sql.gz\n",
                $step++, $backup_dir, $current_version, $version);
        }
        if (!$skip_offline) {
            printf("  %d. Set REDCap to OFFLINE mode.\n", $step++);
        }
        if ($zip_path) {
            printf("  %d. Use local zip: %s\n", $step++, $zip_path);
        } else {
            printf("  %d. Download redcap_v%s.zip from community portal.\n", $step++, $version);
        }
        printf("  %d. Extract zip to a temporary directory.\n", $step++);
        printf("  %d. %s\n", $step++, $skip_sql
            ? 'Skip SQL scripts (--no-sql).'
            : "Run SQL upgrade scripts for versions > $current_version and <= $version.");
        printf("  %d. Copy redcap_v%s/ into: %s\n", $step++, $version, $doc_root);
        printf("  %d. %s\n", $step++, $will_delete_old
            ? "Remove old version directory: $old_version_dir"
            : "Keep old version directory for rollback: $old_version_dir");
        if (!$skip_offline) {
            printf("  %d. Set REDCap back to ONLINE mode.\n", $step);
        }
        printf("\n[DRY RUN] No changes were made.\n");
        $db->close();
        return;
    }

    // ── Backup preflight + backup ─────────────────────────────────────────────

    $backup_file = null;
    if (!$no_backup) {
        $bk_host   = getenv('DB_HOSTNAME') ?: 'localhost';
        $bk_port   = getenv('DB_PORT')     ?: '3306';
        $bk_dbname = getenv('DB_NAME')     ?: '';
        $bk_user   = $backup_db_user  ?? (getenv('DB_USERNAME') ?: '');
        $bk_pass   = $backup_db_pass  ?? (getenv('DB_PASSWORD') ?: '');

        printf("Checking backup prerequisites...\n");
        $preflight_error = test_backup_prerequisites($bk_user, $bk_pass, $bk_host, $bk_port, $bk_dbname);

        if ($preflight_error !== null) {
            printf("Backup preflight failed:\n  %s\n\n", str_replace("\n", "\n  ", $preflight_error));

            if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
                // Interactive: offer choices
                echo "How would you like to proceed?\n";
                echo "  1. Enter elevated DB credentials (e.g. root)\n";
                echo "  2. Skip the backup and continue without rollback protection\n";
                echo "  3. Abort\n";
                $choice = trim(prompt('Choice', '3'));

                if ($choice === '1') {
                    $bk_user = prompt('DB username (e.g. root)');
                    $bk_pass = prompt_password('DB password');
                    $retry   = test_backup_prerequisites($bk_user, $bk_pass, $bk_host, $bk_port, $bk_dbname);
                    if ($retry !== null) {
                        fwrite(STDERR, "Still failing with provided credentials:\n  $retry\nAborting.\n");
                        $db->close();
                        exit(1);
                    }
                    $backup_db_user = $bk_user;
                    $backup_db_pass = $bk_pass;
                } elseif ($choice === '2') {
                    printf("Backup skipped by user choice.\n\n");
                    $no_backup = true;
                } else {
                    printf("Aborted.\n");
                    $db->close();
                    exit(0);
                }
            } else {
                // Non-interactive: abort with guidance
                fwrite(STDERR, "Aborting upgrade. To fix, choose one of:\n");
                fwrite(STDERR, "  --backup-db-user root --backup-db-password <pass>   use elevated credentials\n");
                fwrite(STDERR, "  --no-backup                                          skip backup (no rollback)\n");
                $db->close();
                exit(1);
            }
        }

        if (!$no_backup) {
            printf("Creating pre-upgrade database backup...\n");
            try {
                $backup_file = backup_database($backup_dir, $current_version, $version, $backup_db_user, $backup_db_pass);
            } catch (Exception $e) {
                fwrite(STDERR, "Error: Backup failed: " . $e->getMessage() . "\n");
                fwrite(STDERR, "Aborting upgrade. Use --no-backup to skip (not recommended).\n");
                $db->close();
                exit(1);
            }
            printf("\n");
        }
    }

    // ── Set offline ───────────────────────────────────────────────────────────

    if (!$skip_offline) {
        set_redcap_offline(true, $db);
    }

    // ── Upgrade (wrapped so we can auto-restore on failure) ───────────────────

    $tmp_dir     = sys_get_temp_dir() . '/redcap_upgrade_' . $version . '_' . uniqid();
    $sql_was_run = false;
    mkdir($tmp_dir, 0755, true);

    try {

        // ── Obtain zip ───────────────────────────────────────────────────────

        if ($zip_path) {
            printf("Using local zip: $zip_path\n");
            if (!file_exists($zip_path)) {
                throw new RuntimeException("Zip file not found: $zip_path");
            }
        } else {
            if (!$community_pass) {
                throw new RuntimeException(
                    "Community portal password required.\n" .
                    "Set --community-password or the REDCAP_COMMUNITY_PASSWORD environment variable."
                );
            }
            $zip_path = download_redcap_from_community($community_user, $community_pass, $version, $tmp_dir);
        }

        // ── Extract ──────────────────────────────────────────────────────────

        printf("Extracting archive...\n");
        $zip = new ZipArchive();
        $res = $zip->open($zip_path);
        if ($res !== true) {
            throw new RuntimeException("Failed to open zip (ZipArchive error code $res): $zip_path");
        }
        $zip->extractTo($tmp_dir);
        $zip->close();

        $version_dir_in_tmp = find_redcap_version_dir_in_extraction($tmp_dir, $version);
        printf("Found version directory in archive: $version_dir_in_tmp\n\n");

        // ── SQL upgrade scripts ───────────────────────────────────────────────

        if (!$skip_sql) {
            $sql_files = find_upgrade_sql_files($version_dir_in_tmp, $current_version, $version);

            if (count($sql_files) === 0) {
                printf("No SQL upgrade scripts found for versions > $current_version and <= $version.\n\n");
            } else {
                printf("Running %d SQL upgrade script(s)...\n", count($sql_files));
                foreach ($sql_files as $sql_file) {
                    $basename = basename($sql_file);
                    if (file_was_processed($sql_file, 'REDCAP_UPGRADE')) {
                        printf("  SKIP (already ran): $basename\n");
                        continue;
                    }
                    printf("  Running : $basename\n");
                    run_sql_files($sql_file, $db);
                    $sql_was_run = true;
                    mark_file_as_processed($sql_file, 'REDCAP_UPGRADE');
                    printf("  Done    : $basename\n");
                }
                printf("\n");
            }
        } else {
            printf("SQL upgrade scripts skipped (--no-sql).\n\n");
        }

        // ── Install new version directory ─────────────────────────────────────

        printf("Installing redcap_v$version to $target_dir...\n");

        if (is_dir($target_dir)) {
            printf("Removing existing (possibly partial) target directory...\n");
            recursive_rmdir($target_dir);
        }

        recursive_copy_dir($version_dir_in_tmp, $target_dir);
        chown_www_data_recursive($target_dir);
        printf("Files installed.\n\n");

        // ── Update version in database ────────────────────────────────────────

        $stmt = $db->prepare("UPDATE redcap_config SET value=? WHERE field_name='redcap_version'");
        $stmt->bind_param('s', $version);
        $stmt->execute();
        $stmt->close();
        printf("Database version updated to %s.\n\n", $version);

        // ── Remove old version (only when backup is disabled) ─────────────────

        if ($will_delete_old) {
            $old_real = realpath($old_version_dir);
            $new_real = realpath($target_dir);
            if ($old_real && $new_real && $old_real !== $new_real) {
                printf("Removing old version directory: $old_version_dir\n");
                recursive_rmdir($old_version_dir);
            }
        }

        printf("Upgrade complete: v%s --> v%s\n", $current_version, $version);

    } catch (Exception $e) {
        fwrite(STDERR, "\nUpgrade failed: " . $e->getMessage() . "\n");

        if (is_dir($tmp_dir)) {
            recursive_rmdir($tmp_dir);
        }

        if ($sql_was_run && $backup_file) {
            printf("\nSQL changes were applied. Automatically restoring database from backup...\n");
            try {
                restore_database_backup($backup_file);
                printf("Database restored to pre-upgrade state.\n");
            } catch (Exception $restore_ex) {
                fwrite(STDERR, "CRITICAL: Auto-restore also failed: " . $restore_ex->getMessage() . "\n");
                fwrite(STDERR, "Manual restore required: redcap-upgrade --rollback $backup_file\n");
            }
        } elseif ($sql_was_run) {
            fwrite(STDERR, "WARNING: SQL changes were applied but no backup exists to restore from.\n");
        }

        if (!$skip_offline) {
            printf("Restoring REDCap to ONLINE mode...\n");
            set_redcap_offline(false, $db);
        }

        $db->close();
        exit(1);
    }

    // ── Restore online ────────────────────────────────────────────────────────

    if (!$skip_offline) {
        set_redcap_offline(false, $db);
    } else {
        printf("Note: offline mode was not changed (--no-offline). Toggle it in the Control Center if needed.\n");
    }

    if (is_dir($tmp_dir)) {
        recursive_rmdir($tmp_dir);
    }

    $db->close();

    // ── Post-upgrade summary ──────────────────────────────────────────────────

    printf("\n--- Post-upgrade summary ---\n");
    if ($backup_file) {
        printf("Backup file  : %s\n", $backup_file);
        printf("Old version  : %s (kept for rollback)\n", $old_version_dir);
        printf("Rollback cmd : redcap-upgrade --rollback %s\n", $backup_file);
        printf("\nOnce satisfied with the upgrade you can clean up:\n");
        printf("  rm -rf %s\n", $old_version_dir);
        printf("  rm %s\n", $backup_file);
    }
}


// ── Main ──────────────────────────────────────────────────────────────────────

function main(array $argv): void
{
    $opts = parse_args($argv);

    // ── Rollback mode ─────────────────────────────────────────────────────────
    if ($opts['rollback'] !== null) {
        run_rollback($opts['rollback'], $opts['dry-run']);
        exit(0);
    }

    // ── Wizard mode: no version/zip specified and stdin is a terminal ─────────
    $has_source = $opts['version'] !== null || $opts['zip'] !== null;
    if (!$has_source && function_exists('posix_isatty') && posix_isatty(STDIN)) {
        run_wizard($opts);
        exit(0);
    }

    // ── Non-interactive mode ──────────────────────────────────────────────────
    if (!$has_source) {
        fwrite(STDERR, "Error: Provide --version <X.X.X> or --zip <path>.\n\n");
        usage();
        exit(1);
    }

    // Infer version from zip filename when --version is omitted
    if ($opts['zip'] && !$opts['version']) {
        if (preg_match('/redcap_v(\d+\.\d+\.\d+)\.zip$/i', basename($opts['zip']), $m)) {
            $opts['version'] = $m[1];
            printf("Detected version %s from zip filename.\n", $opts['version']);
        } else {
            fwrite(STDERR, "Error: Cannot infer version from zip filename '" . basename($opts['zip']) . "'. Use --version.\n");
            exit(1);
        }
    }

    run_upgrade($opts);
}

main($argv);
