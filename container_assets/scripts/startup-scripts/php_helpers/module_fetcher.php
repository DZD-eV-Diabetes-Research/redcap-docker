<?php

/**
 * Fetch external module source code and place it into REDCap's /modules
 * directory as "<prefix>_<version>".
 *
 * Only the file-acquisition concerns live here (github / url / local). The
 * "repo" source and all database/enable operations are handled by the
 * REDCap-bootstrapping enabler (redcap_module_enabler.php), because they
 * require REDCap's own framework.
 *
 * IMPORTANT: this runs during container startup as root, *after*
 * 90-fix_permissions.sh has already locked the webroot read-only for www-data.
 * We therefore re-apply the same ownership/permissions on every file we add so
 * Apache (www-data) can read the freshly placed module.
 */

require_once __DIR__ . '/redcap_info.php';

/** Absolute path of REDCap's modules directory (sibling of redcap_vX.Y.Z). */
function module_provisioning_modules_dir(): string
{
    return rtrim(get_redcap_source_path(), '/') . '/modules';
}

/**
 * REDCap identifies a module version by a token that *includes* a leading "v"
 * (e.g. "v1.2.3"). That token is both the suffix of the module directory
 * ("<prefix>_v1.2.3") and the value REDCap stores/compares everywhere
 * (getModuleDirectoryPath, getEnabledVersion, the module constructor, ...).
 * A folder whose suffix lacks the "v" is parsed as version=null and the module
 * fails to construct. We therefore keep the user-supplied version bare
 * (e.g. "1.2.3") and convert to/from REDCap's token at the boundaries.
 */
function module_redcap_version_token(string $bare_version): string
{
    return 'v' . ltrim($bare_version, 'vV');
}

/** Directory name REDCap expects for a module: "<prefix>_v<version>". */
function module_dir_name(string $prefix, string $bare_version): string
{
    return $prefix . '_' . module_redcap_version_token($bare_version);
}

/** Ensure the modules directory exists with webroot-compatible permissions. */
function module_provisioning_ensure_modules_dir(): string
{
    $dir = module_provisioning_modules_dir();
    if (!is_dir($dir)) {
        printf("[MODULE PROVISIONING][INFO]: Creating modules directory '$dir'.\n");
        if (!mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create modules directory: $dir");
        }
    }
    module_apply_webroot_perms($dir);
    return $dir;
}

/**
 * Re-apply webroot ownership/permissions to a path we just wrote, mirroring
 * 90-fix_permissions.sh so the running web process can read the module.
 */
function module_apply_webroot_perms(string $path): void
{
    $easy_upgrade = filter_var(getenv('REDCAP_EASY_UPGRADE_ENABLE'), FILTER_VALIDATE_BOOLEAN);

    // www-data must always be able to read; in easy-upgrade mode it also owns
    // the tree so its UI can manage modules.
    $owner = $easy_upgrade ? 'www-data' : 'root';
    @chown($path, $owner);
    @chgrp($path, 'www-data');
    @chmod($path, is_dir($path) ? 0750 : 0640);

    if (is_dir($path)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            $p = $item->getPathname();
            @chown($p, $owner);
            @chgrp($p, 'www-data');
            @chmod($p, $item->isDir() ? 0750 : 0640);
        }
    }
}

/**
 * Recursively locate the directory that contains the module's config.json.
 * GitHub/zip archives commonly wrap everything in a single top-level folder
 * (e.g. "owner-repo-abc1234/"), so we cannot assume the root is the module.
 */
function module_find_config_root(string $dir): ?string
{
    if (is_file($dir . '/config.json')) {
        return $dir;
    }
    $entries = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
    foreach ($entries as $sub) {
        $found = module_find_config_root($sub);
        if ($found !== null) {
            return $found;
        }
    }
    return null;
}

/** Read and decode a module's config.json. Returns null on failure. */
function module_read_config(string $config_root): ?array
{
    $file = $config_root . '/config.json';
    if (!is_file($file)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Download a (binary) URL to $dest_file using curl. Sends a User-Agent (GitHub
 * rejects requests without one) and an optional bearer token.
 */
function module_curl_download(string $url, string $dest_file, ?string $token = null): void
{
    $fp = fopen($dest_file, 'w');
    if (!$fp) {
        throw new RuntimeException("Failed to open '$dest_file' for writing");
    }
    $headers = ['User-Agent: redcap-docker-module-provisioner'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FAILONERROR => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $ok = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if (!$ok) {
        @unlink($dest_file);
        throw new RuntimeException("Download failed for '$url' (HTTP $code): $err");
    }
}

/** GET a URL and return the body as a string (for JSON API calls). */
function module_curl_get(string $url, ?string $token = null): string
{
    $headers = ['User-Agent: redcap-docker-module-provisioner', 'Accept: application/vnd.github+json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) {
        throw new RuntimeException("GitHub API request failed for '$url' (HTTP $code): $err");
    }
    return (string) $body;
}

/** Create and return a fresh temp working directory. */
function module_make_temp_dir(): string
{
    $dir = sys_get_temp_dir() . '/redcap_module_' . uniqid();
    if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException("Failed to create temp dir: $dir");
    }
    return $dir;
}

function module_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

/**
 * Move an extracted module's config-root directory to
 * modules/<prefix>_v<version>, applying webroot permissions.
 * $version is the bare version (no leading "v").
 * Returns the final installed directory path.
 */
function module_place_dir(string $config_root, string $prefix, string $version): string
{
    $modules_dir = module_provisioning_ensure_modules_dir();
    $dir_name = module_dir_name($prefix, $version);
    $target = "$modules_dir/$dir_name";

    if (is_dir($target)) {
        printf("[MODULE PROVISIONING][INFO]: '$dir_name' already present in /modules — leaving existing files untouched.\n");
        return $target;
    }

    // rename() fails across filesystems (temp dir vs. webroot can differ), so
    // fall back to a recursive copy.
    if (!@rename($config_root, $target)) {
        module_copy_dir($config_root, $target);
    }
    module_apply_webroot_perms($target);
    printf("[MODULE PROVISIONING][INFO]: Installed module files to '$target'.\n");
    return $target;
}

function module_copy_dir(string $src, string $dst): void
{
    if (!mkdir($dst, 0750, true) && !is_dir($dst)) {
        throw new RuntimeException("Failed to create '$dst'");
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        $rel = substr($item->getPathname(), strlen($src) + 1);
        $destPath = "$dst/$rel";
        if ($item->isDir()) {
            @mkdir($destPath, 0750, true);
        } else {
            copy($item->getPathname(), $destPath);
        }
    }
}

/**
 * Resolve the installed directory for a "local" (already mounted) module.
 * If no version is given, picks the highest version present for the prefix.
 * Returns [prefix, "v<version>"] (REDCap's version token) or null on no match.
 */
function module_resolve_local(ModuleSpec $spec): ?array
{
    $modules_dir = module_provisioning_modules_dir();
    if (!is_dir($modules_dir)) {
        printf("[MODULE PROVISIONING][ERROR]: modules directory '$modules_dir' does not exist; cannot resolve local module '$spec->prefix'.\n");
        return null;
    }

    if (!empty($spec->version)) {
        $dir_name = module_dir_name($spec->prefix, $spec->version);
        if (is_dir("$modules_dir/$dir_name")) {
            return [$spec->prefix, module_redcap_version_token($spec->version)];
        }
        printf("[MODULE PROVISIONING][ERROR]: Local module '$dir_name' not found in '$modules_dir'.\n");
        return null;
    }

    // No version pinned: find the highest "<prefix>_v<version>" directory.
    $tokens = [];
    foreach (glob("$modules_dir/{$spec->prefix}_v*", GLOB_ONLYDIR) ?: [] as $dir) {
        $name = basename($dir);
        if (preg_match('/^' . preg_quote($spec->prefix, '/') . '_(v.+)$/', $name, $m)) {
            $tokens[] = $m[1];
        }
    }
    if (empty($tokens)) {
        printf("[MODULE PROVISIONING][ERROR]: No local module directory found for prefix '$spec->prefix' in '$modules_dir'.\n");
        return null;
    }
    // Sort by the numeric part so "v10.0.0" > "v9.0.0".
    usort($tokens, fn($a, $b) => version_compare(ltrim($a, 'v'), ltrim($b, 'v')));
    return [$spec->prefix, end($tokens)];
}

/**
 * Fetch a module from a GitHub repository release (or tag/branch ref).
 * Returns [prefix, version] of the installed module, or null on failure.
 */
function module_fetch_github(ModuleSpec $spec): ?array
{
    $token = $spec->github_token ?: (getenv('MODULE_PROV_GITHUB_TOKEN') ?: null);
    $version = $spec->version;
    $ref = null;

    try {
        if (empty($version)) {
            // Resolve the latest release to learn its tag.
            $json = json_decode(module_curl_get("https://api.github.com/repos/$spec->repo/releases/latest", $token), true);
            $tag = $json['tag_name'] ?? null;
            if (!$tag) {
                printf("[MODULE PROVISIONING][ERROR]: Could not determine latest release for github repo '$spec->repo'. Pin an explicit 'version'.\n");
                return null;
            }
            $ref = $tag;
            $version = ltrim($tag, 'vV');
        } else {
            // Prefer a "v"-prefixed tag (common convention), fall back to raw.
            $ref = $version;
        }

        $prefix = $spec->prefix ?: module_prefix_from_repo($spec->repo);
        $tmp = module_make_temp_dir();
        $zip = "$tmp/module.zip";

        // Try the given ref, then a "v"-prefixed variant for tags.
        $refs = array_unique([$ref, "v$version", $version]);
        $downloaded = false;
        foreach ($refs as $candidate) {
            try {
                module_curl_download("https://api.github.com/repos/$spec->repo/zipball/$candidate", $zip, $token);
                $downloaded = true;
                break;
            } catch (Throwable $e) {
                continue;
            }
        }
        if (!$downloaded) {
            printf("[MODULE PROVISIONING][ERROR]: Could not download github repo '$spec->repo' at ref '$ref'.\n");
            module_rrmdir($tmp);
            return null;
        }

        $result = module_extract_and_place($zip, $tmp, $prefix, $version);
        module_rrmdir($tmp);
        return $result;
    } catch (Throwable $e) {
        printf("[MODULE PROVISIONING][ERROR]: GitHub fetch failed for '$spec->repo': %s\n", $e->getMessage());
        return null;
    }
}

/** Fetch a module from an arbitrary zip URL. Returns [prefix, version]. */
function module_fetch_url(ModuleSpec $spec): ?array
{
    $token = $spec->github_token ?: null;
    try {
        $tmp = module_make_temp_dir();
        $zip = "$tmp/module.zip";
        module_curl_download($spec->url, $zip, $token);
        $result = module_extract_and_place($zip, $tmp, $spec->prefix, $spec->version);
        module_rrmdir($tmp);
        return $result;
    } catch (Throwable $e) {
        printf("[MODULE PROVISIONING][ERROR]: URL fetch failed for '$spec->url': %s\n", $e->getMessage());
        return null;
    }
}

/** Derive a sensible prefix from a "owner/repo" slug. */
function module_prefix_from_repo(string $repo): string
{
    $name = substr($repo, strpos($repo, '/') + 1);
    // Strip a common "redcap-" / "redcap_em_" style prefix on the repo name.
    $name = preg_replace('/^redcap[-_](em[-_])?/i', '', $name);
    return strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $name));
}

/**
 * Extract a downloaded zip, find the module's config-root, and place it at
 * modules/<prefix>_<version>. Returns [prefix, version] or null.
 */
function module_extract_and_place(string $zip_file, string $tmp_dir, string $prefix, string $version): ?array
{
    $extract = "$tmp_dir/extracted";
    mkdir($extract, 0700, true);
    $za = new ZipArchive();
    if ($za->open($zip_file) !== true) {
        printf("[MODULE PROVISIONING][ERROR]: Could not open downloaded archive '$zip_file'.\n");
        return null;
    }
    $za->extractTo($extract);
    $za->close();

    $config_root = module_find_config_root($extract);
    if ($config_root === null) {
        $dir_name = module_dir_name($prefix, $version);
        printf("[MODULE PROVISIONING][ERROR]: No config.json found in downloaded archive for '$dir_name'. Is this a REDCap external module?\n");
        return null;
    }

    $config = module_read_config($config_root);
    if ($config !== null && !empty($config['name'])) {
        printf("[MODULE PROVISIONING][INFO]: Resolved module '%s' (namespace '%s').\n", $config['name'], $config['namespace'] ?? '?');
    }

    module_place_dir($config_root, $prefix, $version);
    return [$prefix, module_redcap_version_token($version)];
}
