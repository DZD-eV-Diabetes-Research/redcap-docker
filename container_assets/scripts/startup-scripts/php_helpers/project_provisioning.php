<?php
# Project provisioning: create REDCap projects from REDCap project XML (CDISC ODM) files on container boot.
# Used by startup-scripts/150_project_provisioning.php and php_helpers/redcap_project_creator.php.
# See PROJECT_PROV.md for the user facing documentation.

require_once __DIR__ . '/db.php';

const PROJECT_PROV_LOG_PREFIX = "[PROJECT PROVISIONING]";
const PROJECT_PROV_LIST_ROOT_KEY = "REDCapProjectList";
# redcap_config row per provisioned spec: field_name = prefix + key, value = project_id
const PROJECT_PROV_MARKER_PREFIX = "redcap_docker_project_prov:";
const PROJECT_PROV_SPEC_PROPERTIES = [
    "key", "xml", "url", "sha256", "title", "purpose", "purpose_other", "project_notes", "owner", "metadata_only",
];
const PROJECT_PROV_REDCAP_NS = "https://projectredcap.org";

function project_prov_log(string $level, string $msg): void
{
    printf("%s[%s]: %s\n", PROJECT_PROV_LOG_PREFIX, $level, $msg);
}

class ProjectSpec
{
    public string $key;
    public ?string $xml = null;
    public ?string $url = null;
    public ?string $sha256 = null;
    public ?string $title = null;
    public ?int $purpose = null;
    public ?string $purpose_other = null;
    public ?string $project_notes = null;
    public ?string $owner = null;
    public bool $metadata_only = false;
    public string $source = "";

    public function marker_field(): string
    {
        return PROJECT_PROV_MARKER_PREFIX . $this->key;
    }

    public function location(): string
    {
        return $this->url ?? $this->xml;
    }
}

# Returns a ProjectSpec or null; every problem is logged with $ident so the user can find the bad entry.
function project_spec_from_array($raw, string $ident): ?ProjectSpec
{
    if (!is_array($raw) || ($raw && array_is_list($raw))) {
        project_prov_log("ERROR", "Project spec at $ident is not an object. Spec skipped.");
        return null;
    }
    $unknown = array_diff(array_keys($raw), PROJECT_PROV_SPEC_PROPERTIES);
    if ($unknown) {
        project_prov_log("ERROR", "Project spec at $ident contains unexpected properties: " . implode(", ", $unknown) . ". Allowed: " . implode(", ", PROJECT_PROV_SPEC_PROPERTIES) . ". Spec skipped.");
        return null;
    }
    $errors = [];
    $spec = new ProjectSpec();
    $spec->source = $ident;

    $key = (string)($raw["key"] ?? "");
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $key)) {
        $errors[] = "'key' is required and may only contain letters, digits, '_' and '-' (max 64 chars), got '$key'";
    }
    $spec->key = $key;

    if (isset($raw["xml"]) == isset($raw["url"])) {
        $errors[] = "exactly one of 'xml' (path to a local file) or 'url' (https download) is required";
    } elseif (isset($raw["xml"])) {
        $spec->xml = (string)$raw["xml"];
        if ($spec->xml === "") {
            $errors[] = "'xml' must not be empty";
        }
    } else {
        $spec->url = (string)$raw["url"];
        if (!str_starts_with(strtolower($spec->url), "https://")) {
            $errors[] = "'url' must start with https://, got '$spec->url'";
        }
    }

    if (isset($raw["sha256"])) {
        $spec->sha256 = strtolower((string)$raw["sha256"]);
        if (!preg_match('/^[0-9a-f]{64}$/', $spec->sha256)) {
            $errors[] = "'sha256' must be 64 hex characters";
        }
    }

    foreach (["title", "purpose_other", "project_notes", "owner"] as $prop) {
        if (!isset($raw[$prop])) {
            continue;
        }
        if (!is_scalar($raw[$prop]) || trim((string)$raw[$prop]) === "") {
            $errors[] = "'$prop' must be a non-empty string";
            continue;
        }
        $spec->$prop = (string)$raw[$prop];
    }

    if (isset($raw["purpose"])) {
        if (filter_var($raw["purpose"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 4]]) === false) {
            $errors[] = "'purpose' must be an integer from 0 to 4 (0 practice, 1 other, 2 research, 3 quality improvement, 4 operational support)";
        } else {
            $spec->purpose = (int)$raw["purpose"];
        }
    }

    if (isset($raw["metadata_only"])) {
        $metadata_only = filter_var($raw["metadata_only"], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($metadata_only === null) {
            $errors[] = "'metadata_only' must be true or false";
        } else {
            $spec->metadata_only = $metadata_only;
        }
    }

    if ($errors) {
        project_prov_log("ERROR", "Project spec at $ident is invalid. Spec skipped:\n  - " . implode("\n  - ", $errors));
        return null;
    }
    return $spec;
}

function project_specs_from_list($list, string $ident_base): array
{
    if (!is_array($list) || !array_is_list($list)) {
        project_prov_log("ERROR", "Expected a list of project specs at $ident_base. Skipped.");
        return [];
    }
    $specs = [];
    foreach ($list as $index => $raw) {
        $spec = project_spec_from_array($raw, "$ident_base position $index");
        if ($spec) {
            $specs[] = $spec;
        }
    }
    return $specs;
}

function project_specs_from_file(string $path): array
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ["yaml", "yml"])) {
        $data = @yaml_parse_file($path);
    } else {
        $data = json_decode((string)file_get_contents($path), true);
    }
    if (!is_array($data) || !array_key_exists(PROJECT_PROV_LIST_ROOT_KEY, $data)) {
        project_prov_log("ERROR", "Expected valid yaml/json with the root key '" . PROJECT_PROV_LIST_ROOT_KEY . "' in '$path'. File skipped.");
        return [];
    }
    return project_specs_from_list($data[PROJECT_PROV_LIST_ROOT_KEY], "file '$path'");
}

# Collects specs from PROJECT_PROV (json list), PROJECT_PROV_<n> (json object) and the yaml/json files in PROJECT_PROV_FILE_DIR.
# Keys must be unique, later duplicates are skipped.
function collect_project_specs(): array
{
    $specs = [];
    $list_json = (string)getenv("PROJECT_PROV");
    if (trim($list_json) !== "") {
        $list = json_decode($list_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            project_prov_log("ERROR", "Env var 'PROJECT_PROV' does not contain valid JSON. Skipped.");
        } else {
            $specs = array_merge($specs, project_specs_from_list($list, "env var 'PROJECT_PROV'"));
        }
    }
    $indexed = [];
    foreach (getenv() as $name => $value) {
        if (preg_match('/^PROJECT_PROV_(\d+)$/', $name, $m)) {
            $indexed[(int)$m[1]] = [$name, $value];
        }
    }
    ksort($indexed);
    foreach ($indexed as [$name, $value]) {
        $raw = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            project_prov_log("ERROR", "Env var '$name' does not contain valid JSON. Skipped.");
            continue;
        }
        $spec = project_spec_from_array($raw, "env var '$name'");
        if ($spec) {
            $specs[] = $spec;
        }
    }
    $dir = (string)getenv("PROJECT_PROV_FILE_DIR");
    if ($dir !== "" && is_dir($dir)) {
        $files = glob(rtrim($dir, "/") . "/*.{yaml,yml,json}", GLOB_BRACE);
        sort($files);
        foreach ($files as $file) {
            project_prov_log("INFO", "Reading project spec file '$file'.");
            $specs = array_merge($specs, project_specs_from_file($file));
        }
    }
    $by_key = [];
    foreach ($specs as $spec) {
        if (isset($by_key[$spec->key])) {
            project_prov_log("ERROR", "Project key '$spec->key' from $spec->source is already used by {$by_key[$spec->key]->source}. Spec skipped.");
            continue;
        }
        $by_key[$spec->key] = $spec;
    }
    return $by_key;
}

# Downloads an https URL to $dest. Redirects are followed, but only to https.
function project_prov_download(string $url, string $dest): void
{
    $fh = fopen($dest, "w");
    if ($fh === false) {
        throw new RuntimeException("Can not write '$dest'.");
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_PROTOCOLS_STR => "https",
        CURLOPT_REDIR_PROTOCOLS_STR => "https",
        CURLOPT_FAILONERROR => true,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => (int)(getenv("PROJECT_PROV_DOWNLOAD_TIMEOUT") ?: 600),
        CURLOPT_HTTPHEADER => ["User-Agent: redcap-docker-project-provisioner"],
    ]);
    $ok = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fh);
    if (!$ok) {
        @unlink($dest);
        throw new RuntimeException("Download of '$url' failed: $err");
    }
}

# Reads the project title, purpose and notes from the GlobalVariables of a REDCap project XML without loading the
# whole file. Throws if the file is not an ODM document.
function project_xml_header(string $path): array
{
    $reader = new XMLReader();
    if (!@$reader->open($path, null, LIBXML_NONET)) {
        throw new RuntimeException("Can not open XML file.");
    }
    $header = ["title" => null, "purpose" => null, "purpose_other" => null, "project_notes" => null];
    $redcap_tags = ["Purpose" => "purpose", "PurposeOther" => "purpose_other", "ProjectNotes" => "project_notes"];
    $is_odm = false;
    try {
        while (@$reader->read()) {
            if ($reader->nodeType == XMLReader::END_ELEMENT && $reader->localName == "GlobalVariables") {
                break;
            }
            if ($reader->nodeType != XMLReader::ELEMENT) {
                continue;
            }
            if (!$is_odm) {
                if ($reader->localName != "ODM") {
                    throw new RuntimeException("Not a REDCap project XML: root element is '$reader->localName', expected 'ODM'.");
                }
                $is_odm = true;
            } elseif ($reader->localName == "StudyName") {
                $header["title"] = trim($reader->readString());
            } elseif ($reader->namespaceURI == PROJECT_PROV_REDCAP_NS && isset($redcap_tags[$reader->localName])) {
                $header[$redcap_tags[$reader->localName]] = trim($reader->readString());
            } elseif ($reader->localName == "MetaDataVersion" || $reader->localName == "ClinicalData") {
                break;
            }
        }
    } finally {
        $reader->close();
    }
    if (!$is_odm) {
        throw new RuntimeException("Not a valid XML file.");
    }
    return array_map(fn($v) => $v === "" ? null : $v, $header);
}

# Removes all <ClinicalData> blocks (the records) from an ODM document, keeping the project definition.
function project_xml_strip_clinical_data(string $xml): string
{
    $out = "";
    $pos = 0;
    while (($start = strpos($xml, "<ClinicalData", $pos)) !== false) {
        $tag_end = strpos($xml, ">", $start);
        if ($tag_end === false) {
            break;
        }
        # Skip elements that only share the prefix
        if (!in_array($xml[$start + 13], [" ", "\t", "\r", "\n", ">", "/"], true)) {
            $out .= substr($xml, $pos, $tag_end + 1 - $pos);
            $pos = $tag_end + 1;
            continue;
        }
        if ($xml[$tag_end - 1] === "/") {
            $end = $tag_end + 1;
        } else {
            $close = strpos($xml, "</ClinicalData>", $tag_end);
            if ($close === false) {
                break;
            }
            $end = $close + strlen("</ClinicalData>");
        }
        $out .= substr($xml, $pos, $start - $pos);
        $pos = $end;
    }
    return $out . substr($xml, $pos);
}
