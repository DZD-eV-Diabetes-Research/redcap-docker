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
require_once $helpers_dir . '/redcap_community_downloader.php';


// ── Usage ─────────────────────────────────────────────────────────────────────

function usage(): void
{
    echo <<<USAGE
Usage: redcap-install [OPTIONS]

Install REDCap into the container for the first time.

When called with no arguments (and attached to a terminal) an interactive
wizard guides you through downloading and installing REDCap.

Source (one of the following is required in non-wizard mode):
  --version <X.X.X>         Version to download from the community portal.
  --zip <path>              Use a locally provided redcap_vX.X.X.zip instead.
                            Version is inferred from the filename.

Community portal credentials (required unless --zip is given):
  --community-user <user>       REDCap community username
                                (env: REDCAP_COMMUNITY_USER)
  --community-password <pass>   REDCap community password
                                (env: REDCAP_COMMUNITY_PASSWORD)

Behaviour flags:
  --dry-run       Show what would be done without making any changes.
  --no-sql        Extract files only; skip the database install step.
  --help, -h      Show this help.

Examples:
  # Launch the interactive wizard:
  redcap-install

  # Non-interactive install (credentials from env vars):
  redcap-install --version 14.9.5

  # Use a locally pre-downloaded zip:
  redcap-install --zip /var/www/html/redcap_v14.9.5.zip

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
        'dry-run'            => false,
        'no-sql'             => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        switch ($argv[$i]) {
            case '--version':            $opts['version']            = $argv[++$i] ?? null; break;
            case '--zip':                $opts['zip']                = $argv[++$i] ?? null; break;
            case '--community-user':     $opts['community-user']     = $argv[++$i] ?? null; break;
            case '--community-password': $opts['community-password'] = $argv[++$i] ?? null; break;
            case '--dry-run':            $opts['dry-run']            = true; break;
            case '--no-sql':             $opts['no-sql']             = true; break;
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

function prompt(string $label, ?string $default = null): string
{
    $hint = $default !== null ? " [$default]" : '';
    echo "$label$hint: ";
    $line = rtrim(fgets(STDIN) ?: '', "\n\r");
    return ($line === '' && $default !== null) ? $default : $line;
}

function prompt_password(string $label): string
{
    echo "$label: ";
    $stty_ok = @system('stty -echo 2>/dev/null', $rc) !== false && $rc === 0;
    $pass    = rtrim(fgets(STDIN) ?: '', "\n\r");
    if ($stty_ok) {
        system('stty echo');
    }
    echo "\n";
    return $pass;
}

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
    echo "\n" . ($title ? "── $title " . str_repeat('─', max(0, 58 - strlen($title))) : str_repeat('─', 60)) . "\n";
}


// ── Check whether REDCap is already installed ─────────────────────────────────

function redcap_is_installed(): bool
{
    try {
        $db = get_db_con();
        $result = $db->query("SHOW TABLES LIKE 'redcap_config'");
        $installed = $result && $result->num_rows > 0;
        $db->close();
        return $installed;
    } catch (Exception $e) {
        return false;
    }
}

function redcap_files_exist(): bool
{
    $doc_root = getenv('APACHE_DOCUMENT_ROOT') ?: '/var/www/html';
    return !empty(get_existent_redcap_version_dirs($doc_root));
}


// ── Interactive wizard ────────────────────────────────────────────────────────

function run_wizard(array $opts): void
{
    echo "=== REDCap Install Wizard ===\n\n";

    if (redcap_is_installed()) {
        echo "REDCap appears to already be installed (redcap_config table found).\n";
        echo "Use 'redcap-upgrade' to upgrade to a newer version.\n";
        exit(0);
    }

    if (redcap_files_exist()) {
        echo "REDCap files are already present on disk.\n";
        echo "If the database is empty, restart the container to trigger the database setup,\n";
        echo "or run 'redcap-install --no-sql --zip' if you only want to refresh the files.\n";
        exit(0);
    }

    // ── Fetch available versions ──────────────────────────────────────────────

    print_separator('Available Versions');

    $portal_data = null;
    if (!$opts['zip']) {
        echo "Fetching available versions from the REDCap community portal...\n";
        $portal_data = fetch_available_versions_from_portal('0.0.0');
        if ($portal_data !== null) {
            echo "Found " . count($portal_data['versions']) . " versions.\n\n";
        } else {
            echo "Could not reach the portal (network error).\n";
        }
    }

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
        $opts['version'] = prompt('Enter version to install (e.g. 14.9.5)');
        if (!$opts['version']) {
            echo "No version entered. Exiting.\n";
            exit(0);
        }
        $recommended = $opts['version'];

    } else {
        $by_branch = [];
        foreach ($portal_data['versions'] as $entry) {
            $by_branch[$entry['branch']][] = $entry;
        }

        // Recommend the latest LTS (most stable for production)
        $latest_lts = !empty($by_branch['lts']) ? end($by_branch['lts']) : null;
        $latest_std = !empty($by_branch['std']) ? end($by_branch['std']) : null;
        $recommended = $latest_lts ? $latest_lts['version'] : ($latest_std ? $latest_std['version'] : null);

        foreach (['lts', 'std'] as $branch) {
            if (empty($by_branch[$branch])) {
                continue;
            }
            $entries = $by_branch[$branch];
            $latest  = end($entries);
            $label   = strtoupper($branch);
            $rec_note = ($recommended === $latest['version']) ? '  <-- recommended' : '';

            // Show last 5 + the latest if not already included
            $display = array_slice($entries, -5);
            if ($display[count($display) - 1]['version'] !== $latest['version']) {
                $display[] = $latest;
            }

            echo "$label versions (recent):\n";
            foreach ($display as $entry) {
                $is_latest = $entry['version'] === $latest['version'];
                $marker    = $is_latest ? "  <-- latest$rec_note" : '';
                $date      = $entry['date'] ? "  ({$entry['date']})" : '';
                echo "  {$entry['version']}$date$marker\n";
            }
            echo "\n";
        }

        echo "LTS is recommended for production. STD has more frequent releases with new features.\n";
        $opts['version'] = prompt('Which version would you like to install', $recommended);
        if (!$opts['version']) {
            echo "No version entered. Exiting.\n";
            exit(0);
        }
    }

    // ── Credentials ───────────────────────────────────────────────────────────

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
            fwrite(STDERR, "Error: username and password are required for community portal download.\n");
            exit(1);
        }
    }

    // ── Show plan and confirm ─────────────────────────────────────────────────

    $doc_root = getenv('APACHE_DOCUMENT_ROOT') ?: '/var/www/html';

    print_separator('Install Plan');
    echo "  Version   : {$opts['version']}\n";
    echo "  Source    : " . ($opts['zip'] ? $opts['zip'] : 'community portal download') . "\n";
    echo "  Files to  : $doc_root/redcap_v{$opts['version']}/\n";
    echo "  Database  : " . ($opts['no-sql'] ? 'skip (--no-sql)' : 'run install.sql from the package') . "\n";
    echo "\n";

    if (!prompt_confirm('Start installation?')) {
        echo "Cancelled.\n";
        exit(0);
    }

    echo "\n";
    run_install($opts);
}


// ── Core install logic ────────────────────────────────────────────────────────

function run_install(array $opts): void
{
    $version        = $opts['version'];
    $zip_path       = $opts['zip'];
    $community_user = $opts['community-user'];
    $community_pass = $opts['community-password'];
    $dry_run        = $opts['dry-run'];
    $skip_sql       = $opts['no-sql'];
    $doc_root       = getenv('APACHE_DOCUMENT_ROOT') ?: '/var/www/html';

    printf("=== REDCap Installer ===\n\n");
    printf("Target version : %s\n", $version);
    printf("Document root  : %s\n\n", $doc_root);

    if (redcap_is_installed()) {
        fwrite(STDERR, "Error: REDCap is already installed (redcap_config table found).\n");
        fwrite(STDERR, "Use 'redcap-upgrade' to upgrade to a newer version.\n");
        exit(1);
    }

    $target_dir = "$doc_root/redcap_v$version";

    // ── Dry run ───────────────────────────────────────────────────────────────

    if ($dry_run) {
        $step = 1;
        printf("[DRY RUN] Steps that would be performed:\n\n");
        if ($zip_path) {
            printf("  %d. Use local zip: %s\n", $step++, $zip_path);
        } else {
            printf("  %d. Download redcap_v%s.zip from community portal.\n", $step++, $version);
        }
        printf("  %d. Extract to %s\n", $step++, $target_dir);
        if (!$skip_sql) {
            printf("  %d. Run %s/Resources/sql/install.sql\n", $step++, $target_dir);
            printf("  %d. Run %s/Resources/sql/install_data.sql\n", $step++, $target_dir);
            printf("  %d. Set redcap_version = %s in database.\n", $step, $version);
        } else {
            printf("  %d. Skip SQL (--no-sql).\n", $step);
        }
        printf("\n[DRY RUN] No changes were made.\n");
        return;
    }

    // ── Obtain zip ────────────────────────────────────────────────────────────

    $tmp_dir = sys_get_temp_dir() . '/redcap_install_' . $version . '_' . uniqid();
    mkdir($tmp_dir, 0755, true);

    try {

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

        // ── Extract ───────────────────────────────────────────────────────────

        printf("Extracting archive...\n");
        $zip = new ZipArchive();
        $res = $zip->open($zip_path);
        if ($res !== true) {
            throw new RuntimeException("Failed to open zip (ZipArchive error $res): $zip_path");
        }
        $zip->extractTo($tmp_dir);
        $zip->close();

        $extracted_dir = "$tmp_dir/redcap_v$version";
        if (!is_dir($extracted_dir)) {
            foreach (glob("$tmp_dir/*/redcap_v$version", GLOB_ONLYDIR) ?: [] as $path) {
                $extracted_dir = $path;
                break;
            }
        }
        if (!is_dir($extracted_dir)) {
            throw new RuntimeException(
                "Could not find 'redcap_v$version' in the extracted archive.\n" .
                "The zip may contain a different version or an unexpected structure."
            );
        }

        // ── Install files ─────────────────────────────────────────────────────

        printf("Installing files to %s...\n", $target_dir);
        if (is_dir($target_dir)) {
            printf("  Removing existing (partial) directory...\n");
            recursive_rmdir($target_dir);
        }
        recursive_copy_dir($extracted_dir, $target_dir);

        $uid = posix_getpwnam('www-data')['uid'] ?? 33;
        $gid = posix_getgrnam('www-data')['gid'] ?? 33;
        chown_www_data_recursive($target_dir, $uid, $gid);

        // The install zip ships REDCap's top-level bootstrap files (index.php,
        // redcap_connect.php, api/, surveys/, ...) alongside the versioned dir.
        // The webroot needs these or Apache serves 403/404. Skip the versioned
        // dir (already copied) and database.php (the container manages its own).
        $archive_root = dirname($extracted_dir);
        $handle = opendir($archive_root);
        if ($handle !== false) {
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') continue;
                if (strpos($entry, 'redcap_v') === 0) continue;
                if ($entry === 'database.php') continue;
                $src = "$archive_root/$entry";
                $dst = "$doc_root/$entry";
                if (is_dir($src)) {
                    recursive_copy_dir($src, $dst);
                } else {
                    copy($src, $dst);
                }
                chown_www_data_recursive($dst, $uid, $gid);
                printf("  Deployed webroot file: %s\n", $entry);
            }
            closedir($handle);
        }

        printf("Files installed.\n\n");

        // ── Database setup ────────────────────────────────────────────────────

        if (!$skip_sql) {
            printf("Running database install scripts...\n");

            $db = get_db_con();

            $install_scripts = [
                "$target_dir/Resources/sql/install.sql",
                "$target_dir/Resources/sql/install_data.sql",
            ];

            foreach ($install_scripts as $sql_file) {
                if (!file_exists($sql_file)) {
                    printf("  WARNING: %s not found — skipping.\n", basename($sql_file));
                    continue;
                }
                printf("  Running %s...\n", basename($sql_file));
                run_sql_files($sql_file, $db);
            }

            $db->query("UPDATE redcap_config SET value=? WHERE field_name='redcap_version'", [$version]);
            $stmt = $db->prepare("UPDATE redcap_config SET value=? WHERE field_name='redcap_version'");
            $stmt->bind_param('s', $version);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare(
                "REPLACE INTO redcap_history_version (`date`, redcap_version) VALUES (CURDATE(), ?)"
            );
            $stmt->bind_param('s', $version);
            $stmt->execute();
            $stmt->close();

            $db->close();
            printf("Database setup complete.\n\n");
        } else {
            printf("Database setup skipped (--no-sql).\n");
            printf("Restart the container to trigger automatic database setup.\n\n");
        }

        printf("REDCap v%s is installed.\n", $version);
        if (!$skip_sql) {
            printf("Start or restart the container to begin serving REDCap.\n");
        }

    } catch (Exception $e) {
        fwrite(STDERR, "\nInstall failed: " . $e->getMessage() . "\n");
        exit(1);
    } finally {
        recursive_rmdir($tmp_dir);
    }
}


// ── Helpers ───────────────────────────────────────────────────────────────────

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

function chown_www_data_recursive(string $path, int $uid, int $gid): void
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


// ── Main ──────────────────────────────────────────────────────────────────────

function main(array $argv): void
{
    $opts = parse_args($argv);

    $has_source = $opts['version'] !== null || $opts['zip'] !== null;

    // Wizard mode: no source specified and stdin is a terminal
    if (!$has_source && function_exists('posix_isatty') && posix_isatty(STDIN)) {
        run_wizard($opts);
        exit(0);
    }

    // Non-interactive mode
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

    run_install($opts);
}

main($argv);
