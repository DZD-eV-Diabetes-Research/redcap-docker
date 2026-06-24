<?php

/**
 * Data model + parsing helpers for REDCap external module provisioning.
 *
 * A "module spec" describes one external module that the container should make
 * available (and optionally enable) on boot. Specs can be supplied via
 * environment variables or yaml/json files — see 140_module_provisioning.php
 * and MODULE_PROV.md.
 *
 * This file intentionally has NO REDCap dependency so it can be unit reasoned
 * about and reused both by the orchestrator and the REDCap-bootstrapping
 * enabler child process.
 */

const MODULE_PROV_SOURCES = ['github', 'url', 'local', 'repo'];

class ModuleSpec
{
    /** One of MODULE_PROV_SOURCES. */
    public ?string $source = null;

    /** Directory prefix of the module (the part before "_<version>"). */
    public ?string $prefix = null;

    /** Module version (without a leading "v"); the part after "<prefix>_". */
    public ?string $version = null;

    /** GitHub "owner/repo" slug (source: github). */
    public ?string $repo = null;

    /** Direct https URL to a module zip (source: url). */
    public ?string $url = null;

    /** Numeric module id in the REDCap central repository (source: repo). */
    public ?int $module_id = null;

    /** Optional GitHub token to lift API rate limits / read private repos. */
    public ?string $github_token = null;

    /**
     * Whether to system-enable the module. null means "fall back to the global
     * MODULE_PROV_DEFAULT_ENABLE policy".
     */
    public ?bool $enabled = null;

    /** Project ids to enable the module for (implies a system enable). */
    public array $projects = [];

    /** System-level settings to apply after enabling (key => value). */
    public array $settings = [];

    /** Cosmetic label used only in log output. */
    public ?string $title = null;

    /**
     * Fields a user may set. Anything else in the input is rejected so typos
     * surface loudly instead of being silently ignored.
     */
    public static function known_fields(): array
    {
        return [
            'source', 'prefix', 'version', 'repo', 'url', 'module_id',
            'github_token', 'enabled', 'projects', 'settings', 'title',
        ];
    }

    /**
     * Validate the spec for its declared source. Returns a list of human
     * readable error strings; an empty list means the spec is usable.
     */
    public function validation_errors(): array
    {
        $errors = [];

        if ($this->source === null) {
            return ["missing required field 'source' (one of: " . implode(', ', MODULE_PROV_SOURCES) . ")"];
        }
        if (!in_array($this->source, MODULE_PROV_SOURCES, true)) {
            return ["invalid 'source' value '$this->source' (expected one of: " . implode(', ', MODULE_PROV_SOURCES) . ")"];
        }

        switch ($this->source) {
            case 'github':
                if (empty($this->repo)) {
                    $errors[] = "source 'github' requires 'repo' (e.g. 'owner/module-repo')";
                } elseif (!preg_match('#^[^/\s]+/[^/\s]+$#', $this->repo)) {
                    $errors[] = "'repo' must be in 'owner/name' form, got '$this->repo'";
                }
                break;
            case 'url':
                if (empty($this->url)) {
                    $errors[] = "source 'url' requires 'url' pointing to a module zip";
                }
                // For an arbitrary zip we cannot reliably derive the folder name.
                if (empty($this->prefix)) {
                    $errors[] = "source 'url' requires an explicit 'prefix'";
                }
                if (empty($this->version)) {
                    $errors[] = "source 'url' requires an explicit 'version'";
                }
                break;
            case 'local':
                if (empty($this->prefix)) {
                    $errors[] = "source 'local' requires 'prefix' (the module directory prefix already present in /modules)";
                }
                break;
            case 'repo':
                if (empty($this->module_id)) {
                    $errors[] = "source 'repo' requires the numeric 'module_id' from the REDCap central repository";
                }
                break;
        }

        if (!is_array($this->projects)) {
            $errors[] = "'projects' must be a list of project ids";
        }
        if (!is_array($this->settings)) {
            $errors[] = "'settings' must be a key/value object";
        }

        return $errors;
    }

    /** Short identifier used in log lines. */
    public function label(): string
    {
        if ($this->title) {
            return $this->title;
        }
        if ($this->prefix) {
            return $this->prefix . ($this->version ? "_$this->version" : '');
        }
        if ($this->repo) {
            return "github:$this->repo";
        }
        if ($this->url) {
            return "url:$this->url";
        }
        if ($this->module_id) {
            return "repo-module#$this->module_id";
        }
        return '(unidentified module)';
    }

    /** Resolve the effective enable decision given the global default policy. */
    public function should_enable(bool $default_enable): bool
    {
        if ($this->enabled !== null) {
            return $this->enabled;
        }
        // Asking to enable for specific projects only makes sense if the module
        // is system-enabled too, so treat that as an implicit enable.
        if (!empty($this->projects)) {
            return true;
        }
        return $default_enable;
    }
}

/**
 * Build a ModuleSpec from a decoded array, validating field names and types.
 * Returns null (after logging) if the data is unusable.
 */
function module_spec_from_array(?array $data, string $ident): ?ModuleSpec
{
    if ($data === null) {
        return null;
    }

    $spec = new ModuleSpec();
    $known = ModuleSpec::known_fields();
    foreach ($data as $key => $value) {
        if (!in_array($key, $known, true)) {
            printf("[MODULE PROVISIONING][ERROR]: Module data at $ident contains unexpected property '$key'. Module will be skipped.\n");
            return null;
        }
        if ($key === 'enabled') {
            $spec->enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        } elseif ($key === 'module_id') {
            $spec->module_id = (int) $value;
        } elseif ($key === 'version') {
            // Normalize a leading "v" so "v1.2.3" and "1.2.3" behave the same.
            $spec->version = ltrim((string) $value, 'vV');
        } else {
            $spec->$key = $value;
        }
    }

    $errors = $spec->validation_errors();
    if (!empty($errors)) {
        printf("[MODULE PROVISIONING][ERROR]: Module data at $ident is invalid and will be skipped:\n  - %s\n", implode("\n  - ", $errors));
        return null;
    }
    return $spec;
}

/**
 * Build a list of ModuleSpec objects from a list of decoded arrays.
 */
function module_specs_from_list(?array $list, string $ident_base): array
{
    if ($list === null) {
        return [];
    }
    $specs = [];
    foreach ($list as $index => $entry) {
        if (!is_array($entry)) {
            printf("[MODULE PROVISIONING][ERROR]: Module data at $ident_base '$index' is not an object. Skipping.\n");
            continue;
        }
        $spec = module_spec_from_array($entry, "$ident_base '$index'");
        if ($spec !== null) {
            $specs[] = $spec;
        }
    }
    return $specs;
}
