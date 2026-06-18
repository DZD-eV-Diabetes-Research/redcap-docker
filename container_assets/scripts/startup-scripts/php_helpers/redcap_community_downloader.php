<?php

// Single endpoint for both version listing and download — from REDCap's Upgrade.php
define('REDCAP_UPGRADE_ENDPOINT', 'https://redcap.vumc.org/plugins/redcap_consortium/versions.php');

const REDCAP_CURL_USERAGENT = 'Mozilla/5.0 (compatible; redcap-docker-updater/1.0)';

// ── Version listing ───────────────────────────────────────────────────────────

/**
 * Fetch available REDCap versions from the community server.
 *
 * This is a public endpoint — no authentication required.
 * Mirrors REDCap's own Upgrade::fetchREDCapVersionUpdatesList().
 *
 * @param  string $current_version  e.g. "16.0.15"
 * @return array{
 *   current_branch: string,
 *   versions: list<array{version: string, branch: string, date: string, notes: string}>
 * }|null  null on network or parse error
 */
function fetch_available_versions_from_portal(string $current_version): ?array
{
    $url = REDCAP_UPGRADE_ENDPOINT . '?current_version=' . urlencode($current_version);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => REDCAP_CURL_USERAGENT,
        CURLOPT_SSL_VERIFYPEER => false, // matches REDCap's own http_get() behaviour
    ]);

    $body     = curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($body === false || empty($body)) {
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data)) {
        return null;
    }

    $current_branch = $data['current_branch'] ?? 'std';
    $versions       = [];

    foreach (['lts', 'std'] as $branch) {
        foreach ($data[$branch] ?? [] as $entry) {
            $v = $entry['version_number'] ?? null;
            if (!$v) {
                continue;
            }
            if (version_compare($v, $current_version, '>')) {
                $versions[] = [
                    'version' => $v,
                    'branch'  => $branch,
                    'date'    => $entry['release_date']  ?? '',
                    'notes'   => $entry['release_notes'] ?? '',
                ];
            }
        }
    }

    usort($versions, static fn($a, $b) => version_compare($a['version'], $b['version']));

    return [
        'current_branch' => $current_branch,
        'versions'       => $versions,
    ];
}

// ── Symbolic version resolution ───────────────────────────────────────────────

// User-facing symbolic version values mapped to their portal branch key.
const REDCAP_SYMBOLIC_VERSIONS = [
    'latest-lts' => 'lts',
    'latest-std' => 'std',
];

/**
 * True if $value is a symbolic version (e.g. "latest-lts") rather than a
 * concrete X.Y.Z version number. Case-insensitive.
 */
function is_symbolic_redcap_version(string $value): bool
{
    return isset(REDCAP_SYMBOLIC_VERSIONS[strtolower(trim($value))]);
}

/**
 * Resolve a symbolic version (latest-lts / latest-std) to the newest concrete
 * X.Y.Z version available on that branch from the community portal.
 *
 * The portal endpoint only returns versions newer than the current_version it
 * is given, so we query with a low baseline ("0.0.0") to get the full branch
 * list regardless of what is installed.
 *
 * @throws RuntimeException if the value is not symbolic, the portal is
 *                          unreachable, or the branch has no versions.
 */
function resolve_redcap_version(string $symbol): string
{
    $symbol = strtolower(trim($symbol));
    if (!isset(REDCAP_SYMBOLIC_VERSIONS[$symbol])) {
        throw new RuntimeException(
            "Unknown symbolic REDCap version '$symbol'. Valid values: latest-lts, latest-std."
        );
    }
    $branch = REDCAP_SYMBOLIC_VERSIONS[$symbol];

    $portal = fetch_available_versions_from_portal('0.0.0');
    if ($portal === null) {
        throw new RuntimeException(
            "Cannot resolve '$symbol': failed to fetch the version list from the "
            . "community portal (network or parse error)."
        );
    }

    $candidates = array_values(array_filter(
        $portal['versions'],
        static fn($e) => ($e['branch'] ?? null) === $branch
    ));
    if (empty($candidates)) {
        throw new RuntimeException(
            "Cannot resolve '$symbol': the community portal returned no '$branch' versions."
        );
    }

    usort($candidates, static fn($a, $b) => version_compare($a['version'], $b['version']));
    return end($candidates)['version'];
}

// ── Download ──────────────────────────────────────────────────────────────────

/**
 * Download a REDCap version zip from the community portal.
 *
 * Mirrors REDCap's own Upgrade::performOneClickUpgrade() — credentials are sent
 * as POST fields to the same endpoint; no separate login/session step is needed.
 *
 * We always request the *install* zip (install=1) rather than the upgrade zip.
 * The two only differ by ~20 KB on a ~56 MB archive, but the upgrade zip contains
 * only the versioned redcap_vX.Y.Z/ directory, whereas the install zip also ships
 * REDCap's top-level bootstrap files (index.php, redcap_connect.php, api/,
 * surveys/, ...). A fresh webroot needs those bootstrap files or Apache has no
 * DirectoryIndex and serves 403/404. REDCap itself fetches the install zip the
 * same way for fresh AWS Elastic Beanstalk deploys (Upgrade::performOneClickUpgrade,
 * params['install']='1'). The upgrader ignores the extra files (it locates the
 * versioned dir specifically), so one download path is safe everywhere.
 *
 * On success returns the path to the downloaded zip file.
 * On failure throws RuntimeException (including community portal auth errors).
 */
function download_redcap_from_community(
    string $community_user,
    string $community_password,
    string $version,
    string $download_dir
): string {
    $zip_path = rtrim($download_dir, '/') . "/redcap_v{$version}.zip";

    $fp = fopen($zip_path, 'wb');
    if (!$fp) {
        throw new RuntimeException("Cannot open $zip_path for writing — check directory permissions.");
    }

    printf("Downloading REDCap v$version from community portal...\n");

    $last_draw       = 0.0;
    $last_speed_time = microtime(true);
    $last_bytes      = 0;
    $speed_bps       = 0.0;

    $progress_fn = function ($ch, $dl_total, $dl_now, $ul_total, $ul_now)
        use (&$last_draw, &$last_speed_time, &$last_bytes, &$speed_bps)
    {
        if ($dl_now === 0) {
            return 0;
        }
        $now = microtime(true);
        if ($now - $last_draw < 0.1) {
            return 0; // cap redraws at ~10 fps
        }
        $last_draw = $now;

        $elapsed = $now - $last_speed_time;
        if ($elapsed >= 0.5) {
            $speed_bps       = ($dl_now - $last_bytes) / $elapsed;
            $last_bytes      = $dl_now;
            $last_speed_time = $now;
        }

        $dl_mb    = $dl_now  / 1048576;
        $speed_mb = $speed_bps / 1048576;

        if ($dl_total > 0) {
            $pct    = min(100, (int)(($dl_now / $dl_total) * 100));
            $tot_mb = $dl_total / 1048576;
            $filled = (int)($pct * 28 / 100);
            $arrow  = $pct < 100 ? '>' : '';
            $bar    = str_repeat('=', $filled) . $arrow . str_repeat(' ', 28 - $filled - strlen($arrow));
            printf("\r  [%s] %3d%%  %5.1f / %5.1f MB  %5.1f MB/s ", $bar, $pct, $dl_mb, $tot_mb, $speed_mb);
        } else {
            printf("\r  %5.1f MB downloaded  %5.1f MB/s              ", $dl_mb, $speed_mb);
        }
        fflush(STDOUT);
        return 0;
    };

    $ch = curl_init(REDCAP_UPGRADE_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST             => true,
        CURLOPT_POSTFIELDS       => http_build_query([
            'username' => $community_user,
            'password' => $community_password,
            'version'  => $version,
            'install'  => '1', // fetch the full install zip (see function doc)
        ]),
        CURLOPT_FILE             => $fp,
        CURLOPT_FOLLOWLOCATION   => true,
        CURLOPT_TIMEOUT          => 600,
        CURLOPT_USERAGENT        => REDCAP_CURL_USERAGENT,
        CURLOPT_SSL_VERIFYPEER   => false,
        CURLOPT_NOPROGRESS       => false,
        CURLOPT_PROGRESSFUNCTION => $progress_fn,
    ]);

    $ok        = curl_exec($ch);
    $curl_err  = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $dl_bytes  = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
    curl_close($ch);
    fclose($fp);

    printf("\n"); // end the progress-bar line

    if (!$ok || $http_code >= 500) {
        @unlink($zip_path);
        throw new RuntimeException("Download request failed (HTTP $http_code): $curl_err");
    }

    // If the response starts with '{' it is a JSON error from the portal
    // (mirrors the check in REDCap's performOneClickUpgrade)
    $first_byte = @file_get_contents($zip_path, false, null, 0, 1);
    if ($first_byte === '{') {
        $error_body = @file_get_contents($zip_path) ?: '';
        @unlink($zip_path);
        $decoded = json_decode($error_body, true);
        $message = $decoded['ERROR'] ?? $error_body;
        throw new RuntimeException("Community portal error: $message");
    }

    if ($dl_bytes < 512 * 1024) {
        @unlink($zip_path);
        throw new RuntimeException(
            "Downloaded file is unexpectedly small ($dl_bytes bytes). " .
            "The version '$version' may not exist on the portal."
        );
    }

    printf("Downloaded %.1f MB.\n", $dl_bytes / 1024 / 1024);
    return $zip_path;
}
