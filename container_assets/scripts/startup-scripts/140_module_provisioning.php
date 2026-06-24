<?php

/**
 * External Module provisioning (startup script).
 *
 * Makes REDCap external modules available — and optionally enables/configures
 * them — on container boot, driven entirely by environment variables and/or
 * yaml/json files. Mirrors the table-user provisioning feature.
 *
 * See MODULE_PROV.md for the full operator documentation.
 *
 * Pipeline:
 *   1. Collect module specs (env vars + files).
 *   2. Acquire files for github/url/local sources into /modules/<prefix>_<ver>.
 *      (Runs as root, re-applying webroot permissions so www-data can read.)
 *   3. Hand a task list to redcap_module_enabler.php, a child process that
 *      bootstraps REDCap and performs central-repo downloads + the actual
 *      enable/configure via the External Module framework API.
 */

require_once __DIR__ . '/php_helpers/redcap_info.php';
require_once __DIR__ . '/php_helpers/module_data.php';
require_once __DIR__ . '/php_helpers/module_fetcher.php';

const MODULE_PROV_ENV_BASE = 'MODULE_PROV';

function mp_log(string $level, string $msg): void
{
    printf("[MODULE PROVISIONING][$level]: $msg\n");
}

/** Collect specs from indexed env vars: MODULE_PROV_1, MODULE_PROV_2, ... */
function collect_specs_from_indexed_env_vars(string $base): array
{
    $specs = [];
    foreach (getenv() as $key => $value) {
        // Match MODULE_PROV_<n> but not MODULE_PROV_FILE_DIR etc.
        if (!preg_match('/^' . preg_quote($base, '/') . '_(\d+)$/', $key)) {
            continue;
        }
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            mp_log('ERROR', "Env var '$key' does not contain valid JSON. Skipping.");
            continue;
        }
        $spec = module_spec_from_array($decoded, "env var '$key'");
        if ($spec !== null) {
            $specs[] = $spec;
        }
    }
    return $specs;
}

/** Collect specs from a single env var holding a JSON list: MODULE_PROV. */
function collect_specs_from_env_var(string $name): array
{
    $raw = getenv($name);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        mp_log('ERROR', "Env var '$name' does not contain valid JSON. Skipping.");
        return [];
    }
    // Accept either a single object or a list of objects.
    if (isset($decoded['source'])) {
        $decoded = [$decoded];
    }
    return module_specs_from_list($decoded, "env var '$name' at index");
}

/** Collect specs from a yaml or json file with root key 'REDCapModuleList'. */
function collect_specs_from_file(string $file): array
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'yaml' || $ext === 'yml') {
        $data = yaml_parse_file($file);
    } elseif ($ext === 'json') {
        $data = json_decode((string) file_get_contents($file), true);
    } else {
        return [];
    }

    if (!is_array($data) || !array_key_exists('REDCapModuleList', $data)) {
        mp_log('ERROR', "Expected a root level key 'REDCapModuleList' in '$file'. Skipping file.");
        return [];
    }
    if (!is_array($data['REDCapModuleList'])) {
        mp_log('ERROR', "'REDCapModuleList' in '$file' is not a list. Skipping file.");
        return [];
    }
    return module_specs_from_list($data['REDCapModuleList'], "file '$file' at pos");
}

/** Gather all module specs from every supported source. */
function collect_all_specs(): array
{
    $specs = [];
    $specs = array_merge($specs, collect_specs_from_indexed_env_vars(MODULE_PROV_ENV_BASE));
    $specs = array_merge($specs, collect_specs_from_env_var(MODULE_PROV_ENV_BASE));

    $file_dir = getenv('MODULE_PROV_FILE_DIR');
    if ($file_dir !== false && $file_dir !== '') {
        if (!is_dir($file_dir)) {
            mp_log('ERROR', "MODULE_PROV_FILE_DIR is '$file_dir' but that is not a directory. Skipping file scan.");
        } else {
            mp_log('INFO', "Scanning '$file_dir' for yaml/json module files...");
            foreach (new DirectoryIterator($file_dir) as $fileinfo) {
                if ($fileinfo->isDot() || !$fileinfo->isFile()) {
                    continue;
                }
                $path = $fileinfo->getPathname();
                $ext = strtolower($fileinfo->getExtension());
                if (in_array($ext, ['yaml', 'yml', 'json'], true)) {
                    mp_log('INFO', "Reading module file '$path'.");
                    $specs = array_merge($specs, collect_specs_from_file($path));
                }
            }
        }
    }
    return $specs;
}

/**
 * Turn validated specs into enabler tasks, acquiring files for non-repo
 * sources along the way.
 */
function build_tasks(array $specs, bool $default_enable): array
{
    $tasks = [];
    foreach ($specs as $spec) {
        $enable = $spec->should_enable($default_enable);
        mp_log('INFO', sprintf("Processing module '%s' (source: %s, enable: %s).", $spec->label(), $spec->source, $enable ? 'yes' : 'no'));

        if ($spec->source === 'repo') {
            // Files + enable both handled in the REDCap-bootstrapped child.
            $tasks[] = [
                'source' => 'repo',
                'module_id' => $spec->module_id,
                'enable' => $enable,
                'projects' => $spec->projects,
                'settings' => $spec->settings,
            ];
            continue;
        }

        $resolved = null;
        switch ($spec->source) {
            case 'local':
                $resolved = module_resolve_local($spec);
                break;
            case 'github':
                $resolved = module_fetch_github($spec);
                break;
            case 'url':
                $resolved = module_fetch_url($spec);
                break;
        }

        if ($resolved === null) {
            mp_log('ERROR', "Skipping module '{$spec->label()}' — could not acquire its files.");
            continue;
        }
        [$prefix, $version] = $resolved;
        $tasks[] = [
            'source' => 'resolved',
            'prefix' => $prefix,
            'version' => $version,
            'enable' => $enable,
            'projects' => $spec->projects,
            'settings' => $spec->settings,
        ];
    }
    return $tasks;
}

/** Invoke the REDCap-bootstrapping enabler with the task list. */
function run_enabler(array $tasks): void
{
    if (empty($tasks)) {
        mp_log('INFO', "No module tasks to apply.");
        return;
    }
    $tasks_file = tempnam(sys_get_temp_dir(), 'redcap_module_tasks_');
    file_put_contents($tasks_file, json_encode($tasks));

    $enabler = __DIR__ . '/php_helpers/redcap_module_enabler.php';
    $cmd = 'php -f ' . escapeshellarg($enabler) . ' -- ' . escapeshellarg($tasks_file);
    mp_log('INFO', "Applying " . count($tasks) . " module task(s) via REDCap...");
    passthru($cmd, $exit_code);
    @unlink($tasks_file);
    if ($exit_code !== 0) {
        mp_log('ERROR', "Module enabler exited with code $exit_code.");
    }
}

// ── Entry point ─────────────────────────────────────────────────────────────
$ENABLE_MODULE_PROV = filter_var(getenv('ENABLE_MODULE_PROV'), FILTER_VALIDATE_BOOLEAN);
printf("ENABLE_MODULE_PROV: %s\n", $ENABLE_MODULE_PROV ? 'true' : 'false');

if (!$ENABLE_MODULE_PROV) {
    return;
}

if (!redcap_is_installed()) {
    mp_log('WARNING', "Skipping module provisioning — REDCap does not appear to be installed yet (no 'redcap_config' table).");
    return;
}

$default_enable = filter_var(getenv('MODULE_PROV_DEFAULT_ENABLE'), FILTER_VALIDATE_BOOLEAN);
mp_log('INFO', "Default enable policy (MODULE_PROV_DEFAULT_ENABLE): " . ($default_enable ? 'enable' : 'install-only'));

$specs = collect_all_specs();
if (empty($specs)) {
    mp_log('INFO', "No module specs found. Nothing to do.");
    return;
}
mp_log('INFO', "Collected " . count($specs) . " module spec(s).");

$tasks = build_tasks($specs, $default_enable);
run_enabler($tasks);
mp_log('INFO', "Module provisioning done.");
