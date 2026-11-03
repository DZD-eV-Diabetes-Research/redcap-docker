<?php
# Periodic data exports via the REDCap API.
# Used by /opt/redcap-docker/assets/scripts/redcap_export.php (the `redcap-export` command).
# See DATA_EXPORT.md for the user facing documentation.

require_once __DIR__ . '/db.php';

const EXPORT_LOG_PREFIX = "[DATA EXPORT]";
# Written into redcap_user_information.user_comments of the service user. An existing user without
# this marker is never touched, so the export can not take over a real account by accident.
const EXPORT_SERVICE_USER_MARKER = "Managed by redcap-docker data export (ENABLE_DATA_EXPORT). Do not edit, changes are reset on every export run.";
const EXPORT_JOB_LIST_ROOT_KEY = "REDCapExportJobList";
const EXPORT_CONTENT_FORMATS = [
    "record" => ["csv" => "csv", "json" => "json", "xml" => "xml", "odm" => "xml"],
    "report" => ["csv" => "csv", "json" => "json", "xml" => "xml"],
    "project_xml" => ["xml" => "xml"],
];
# API parameters a job may set in `options`, per content type. Everything else is rejected so typos fail loudly.
const EXPORT_CONTENT_OPTIONS = [
    "record" => [
        "type", "records", "fields", "forms", "events", "rawOrLabel", "rawOrLabelHeaders", "exportCheckboxLabel",
        "exportSurveyFields", "exportDataAccessGroups", "filterLogic", "dateRangeBegin", "dateRangeEnd",
        "csvDelimiter", "decimalCharacter", "exportBlankForGrayFormStatus",
    ],
    "report" => [
        "rawOrLabel", "rawOrLabelHeaders", "exportCheckboxLabel", "csvDelimiter", "decimalCharacter",
    ],
    "project_xml" => [
        "returnMetadataOnly", "records", "fields", "events", "exportSurveyFields", "exportDataAccessGroups",
        "filterLogic", "exportFiles",
    ],
];

# Problems go to stderr so `redcap-export crontab` keeps a clean stdout
function export_log(string $level, string $msg): void
{
    fprintf($level == "INFO" ? STDOUT : STDERR, "%s[%s]: %s\n", EXPORT_LOG_PREFIX, $level, $msg);
}

function export_env(string $name, string $default = ""): string
{
    $val = getenv($name);
    return ($val === false || $val === "") ? $default : $val;
}

class ExportJob
{
    public string $name;
    public int $project_id;
    public string $schedule;
    public string $content = "record";
    public string $format = "csv";
    public ?int $report_id = null;
    public array $options = [];
    public int $keep = 0;
    public string $source = "";

    public function file_extension(): string
    {
        return EXPORT_CONTENT_FORMATS[$this->content][$this->format];
    }

    # API POST fields for this job, without the token
    public function api_params(): array
    {
        $params = $this->options;
        $params["content"] = $this->content;
        $params["returnFormat"] = "json";
        if ($this->content == "project_xml") {
            # project_xml always answers with CDISC ODM XML; data is included unless the job asks otherwise
            $params += ["returnMetadataOnly" => "false"];
        } else {
            $params["format"] = $this->format;
        }
        if ($this->content == "report") {
            $params["report_id"] = $this->report_id;
        }
        foreach ($params as $key => $val) {
            if (is_bool($val)) {
                $params[$key] = $val ? "true" : "false";
            }
        }
        return $params;
    }
}

# Returns an ExportJob or null; every problem is logged with $ident so the user can find the bad entry.
function export_job_from_array($raw, string $ident): ?ExportJob
{
    if (!is_array($raw)) {
        export_log("ERROR", "Export job at $ident is not an object. Job skipped.");
        return null;
    }
    $allowed = ["name", "project_id", "schedule", "content", "format", "report_id", "options", "keep"];
    $unknown = array_diff(array_keys($raw), $allowed);
    if ($unknown) {
        export_log("ERROR", "Export job at $ident contains unexpected properties: " . implode(", ", $unknown) . ". Allowed: " . implode(", ", $allowed) . ". Job skipped.");
        return null;
    }
    $errors = [];
    $job = new ExportJob();
    $job->source = $ident;

    $name = (string)($raw["name"] ?? "");
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $name)) {
        $errors[] = "'name' is required and may only contain letters, digits, '_' and '-' (max 64 chars), got '$name'";
    }
    $job->name = $name;

    if (!isset($raw["project_id"]) || filter_var($raw["project_id"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) === false) {
        $errors[] = "'project_id' is required and must be a positive integer";
    } else {
        $job->project_id = (int)$raw["project_id"];
    }

    $schedule = trim((string)($raw["schedule"] ?? ""));
    if (!preg_match('/^[0-9A-Za-z*\/,-]+(\s+[0-9A-Za-z*\/,-]+){4}$/', $schedule)) {
        $errors[] = "'schedule' is required and must be a 5 field cron expression like '0 2 * * *', got '$schedule'";
    }
    $job->schedule = preg_replace('/\s+/', ' ', $schedule);

    $job->content = (string)($raw["content"] ?? "record");
    if (!array_key_exists($job->content, EXPORT_CONTENT_FORMATS)) {
        $errors[] = "'content' must be one of " . implode(", ", array_keys(EXPORT_CONTENT_FORMATS)) . ", got '$job->content'";
    } else {
        $default_format = $job->content == "project_xml" ? "xml" : "csv";
        $job->format = strtolower((string)($raw["format"] ?? $default_format));
        if (!array_key_exists($job->format, EXPORT_CONTENT_FORMATS[$job->content])) {
            $errors[] = "'format' for content '$job->content' must be one of " . implode(", ", array_keys(EXPORT_CONTENT_FORMATS[$job->content])) . ", got '$job->format'";
        }
        $options = $raw["options"] ?? [];
        if (!is_array($options) || ($options && array_is_list($options))) {
            $errors[] = "'options' must be an object of REDCap API parameters";
        } else {
            $unknown = array_diff(array_keys($options), EXPORT_CONTENT_OPTIONS[$job->content]);
            if ($unknown) {
                $errors[] = "'options' for content '$job->content' contains unsupported parameters: " . implode(", ", $unknown) . ". Supported: " . implode(", ", EXPORT_CONTENT_OPTIONS[$job->content]);
            }
            $job->options = $options;
        }
    }

    if ($job->content == "report") {
        if (!isset($raw["report_id"]) || filter_var($raw["report_id"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) === false) {
            $errors[] = "'report_id' is required for content 'report' and must be a positive integer";
        } else {
            $job->report_id = (int)$raw["report_id"];
        }
    } elseif (isset($raw["report_id"])) {
        $errors[] = "'report_id' is only allowed for content 'report'";
    }

    $keep = $raw["keep"] ?? export_env("EXPORT_DEFAULT_KEEP", "0");
    if (filter_var($keep, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]) === false) {
        $errors[] = "'keep' must be an integer >= 0 (0 keeps all exports), got '$keep'";
    } else {
        $job->keep = (int)$keep;
    }

    if ($errors) {
        export_log("ERROR", "Export job at $ident is invalid. Job skipped:\n  - " . implode("\n  - ", $errors));
        return null;
    }
    return $job;
}

function export_jobs_from_list($list, string $ident_base): array
{
    if (!is_array($list) || !array_is_list($list)) {
        export_log("ERROR", "Expected a list of export jobs at $ident_base. Skipped.");
        return [];
    }
    $jobs = [];
    foreach ($list as $index => $raw) {
        $job = export_job_from_array($raw, "$ident_base position $index");
        if ($job) {
            $jobs[] = $job;
        }
    }
    return $jobs;
}

function export_jobs_from_file(string $path): array
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ["yaml", "yml"])) {
        $data = @yaml_parse_file($path);
    } else {
        $data = json_decode((string)file_get_contents($path), true);
    }
    if (!is_array($data) || !array_key_exists(EXPORT_JOB_LIST_ROOT_KEY, $data)) {
        export_log("ERROR", "Expected valid yaml/json with the root key '" . EXPORT_JOB_LIST_ROOT_KEY . "' in '$path'. File skipped.");
        return [];
    }
    return export_jobs_from_list($data[EXPORT_JOB_LIST_ROOT_KEY], "file '$path'");
}

# Collects jobs from EXPORT_JOBS (json list), EXPORT_JOBS_<n> (json object) and the yaml/json files in EXPORT_JOBS_FILE_DIR.
function collect_export_jobs(): array
{
    $jobs = [];
    $list_json = export_env("EXPORT_JOBS");
    if ($list_json !== "") {
        $list = json_decode($list_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            export_log("ERROR", "Env var 'EXPORT_JOBS' does not contain valid JSON. Skipped.");
        } else {
            $jobs = array_merge($jobs, export_jobs_from_list($list, "env var 'EXPORT_JOBS'"));
        }
    }
    $indexed = [];
    foreach (getenv() as $key => $value) {
        if (preg_match('/^EXPORT_JOBS_(\d+)$/', $key, $m)) {
            $indexed[(int)$m[1]] = [$key, $value];
        }
    }
    ksort($indexed);
    foreach ($indexed as [$key, $value]) {
        $raw = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            export_log("ERROR", "Env var '$key' does not contain valid JSON. Skipped.");
            continue;
        }
        $job = export_job_from_array($raw, "env var '$key'");
        if ($job) {
            $jobs[] = $job;
        }
    }
    $dir = export_env("EXPORT_JOBS_FILE_DIR");
    if ($dir !== "" && is_dir($dir)) {
        $files = glob(rtrim($dir, "/") . "/*.{yaml,yml,json}", GLOB_BRACE);
        sort($files);
        foreach ($files as $file) {
            $jobs = array_merge($jobs, export_jobs_from_file($file));
        }
    }
    # Names are used for directories, lock files and crontab lines, so they must be unique
    $by_name = [];
    foreach ($jobs as $job) {
        if (isset($by_name[$job->name])) {
            export_log("ERROR", "Export job name '$job->name' from $job->source is already used by {$by_name[$job->name]->source}. Job skipped.");
            continue;
        }
        $by_name[$job->name] = $job;
    }
    return $by_name;
}

class ExportServiceUser
{
    private mysqli $db;
    public string $username;

    public function __construct(mysqli $db, string $username)
    {
        $this->db = $db;
        $this->username = $username;
    }

    private function query(string $sql, array $params = []): mysqli_result|bool
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->get_result();
    }

    # Creates the service user if missing. Throws if the username belongs to an account this container did not create.
    public function ensure_user(): void
    {
        $row = $this->query("SELECT user_comments, user_suspended_time, super_user FROM redcap_user_information WHERE username = ?", [$this->username])->fetch_assoc();
        if ($row === null) {
            export_log("INFO", "Create export service user '$this->username'.");
            $this->query(
                "INSERT INTO redcap_user_information (username, user_email, user_firstname, user_lastname, user_comments, super_user, allow_create_db, display_on_email_users, user_creation)
                 VALUES (?, NULL, 'REDCap Docker', 'Data Export', ?, 0, 0, 0, NOW())
                 ON DUPLICATE KEY UPDATE username = username",
                [$this->username, EXPORT_SERVICE_USER_MARKER]
            );
            return;
        }
        if (!str_contains((string)$row["user_comments"], EXPORT_SERVICE_USER_MARKER)) {
            throw new RuntimeException("User '$this->username' already exists but was not created by the data export. Refusing to use it. Set EXPORT_SERVICE_USERNAME to an unused username.");
        }
        if ($row["user_suspended_time"] !== null) {
            throw new RuntimeException("Export service user '$this->username' is suspended in REDCap. Unsuspend it in the Control Center to resume exports.");
        }
        if ($row["super_user"] != 0) {
            $this->query("UPDATE redcap_user_information SET super_user = 0 WHERE username = ?", [$this->username]);
            export_log("WARNING", "Export service user '$this->username' had super user rights. Removed them.");
        }
    }

    # Gives the service user API export and full export rights on every instrument of the project, and nothing else.
    # Returns the API token for the project.
    public function ensure_project_token(int $project_id): string
    {
        $project = $this->query("SELECT date_deleted FROM redcap_projects WHERE project_id = ?", [$project_id])->fetch_assoc();
        if ($project === null) {
            throw new RuntimeException("Project $project_id does not exist.");
        }
        if ($project["date_deleted"] !== null) {
            throw new RuntimeException("Project $project_id is deleted.");
        }
        # Per instrument: full data set export (1), no data viewing (128). Listing every instrument in data_entry stops
        # REDCap from auto granting view-edit on instruments missing there.
        $export_rights = $view_rights = "";
        $res = $this->query("SELECT form_name, MIN(field_order) AS o FROM redcap_metadata WHERE project_id = ? GROUP BY form_name ORDER BY o", [$project_id]);
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
            $export_rights .= "[" . $row["form_name"] . ",1]";
            $view_rights .= "[" . $row["form_name"] . ",128]";
        }
        # Every other flag column (user_rights, design, record_delete, api_import, ...) is reset to 0. Read the column list
        # from the schema so flags added by future REDCap versions are covered too.
        $keep_cols = ["project_id", "username", "api_export", "data_export_tool", "role_id", "group_id"];
        $flag_cols = [];
        $res = $this->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'redcap_user_rights' AND DATA_TYPE IN ('int', 'tinyint')"
        );
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
            if (!in_array($row["COLUMN_NAME"], $keep_cols)) {
                $flag_cols[] = "`" . $row["COLUMN_NAME"] . "` = 0";
            }
        }
        $this->query("INSERT IGNORE INTO redcap_user_rights (project_id, username) VALUES (?, ?)", [$project_id, $this->username]);
        $this->query(
            "UPDATE redcap_user_rights SET " . implode(", ", $flag_cols) . ",
                api_export = 1, data_export_tool = 1, data_export_instruments = ?, data_entry = ?,
                role_id = NULL, group_id = NULL, expiration = NULL
             WHERE project_id = ? AND username = ?",
            [$export_rights, $view_rights, $project_id, $this->username]
        );
        $token = $this->query("SELECT api_token FROM redcap_user_rights WHERE project_id = ? AND username = ?", [$project_id, $this->username])->fetch_column();
        if ($token === null || $token === "") {
            export_log("INFO", "Create API token for export service user '$this->username' on project $project_id.");
            $this->query(
                "UPDATE redcap_user_rights SET api_token = ? WHERE project_id = ? AND username = ? AND api_token IS NULL",
                [strtoupper(bin2hex(random_bytes(16))), $project_id, $this->username]
            );
            $token = $this->query("SELECT api_token FROM redcap_user_rights WHERE project_id = ? AND username = ?", [$project_id, $this->username])->fetch_column();
        }
        return $token;
    }
}

function export_api_enabled(mysqli $db): bool
{
    $res = $db->query("SELECT value FROM redcap_config WHERE field_name = 'api_enabled'");
    return $res && $res->fetch_column() == "1";
}

function export_state_file(string $job_name, string $suffix): string
{
    $dir = export_env("EXPORT_STATE_DIR", "/tmp/redcap-export-state");
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return "$dir/$job_name.$suffix";
}

# Deletes the oldest exports of a job so that $job->keep remain. Only touches files this exporter named.
function export_apply_retention(ExportJob $job, string $job_dir): void
{
    if ($job->keep == 0) {
        return;
    }
    $pattern = '/^' . preg_quote($job->name, '/') . '_\d{8}T\d{6}Z\.' . preg_quote($job->file_extension(), '/') . '$/';
    $files = array_values(array_filter(scandir($job_dir), fn($f) => preg_match($pattern, $f)));
    sort($files);
    foreach (array_slice($files, 0, max(0, count($files) - $job->keep)) as $old) {
        unlink("$job_dir/$old");
        export_log("INFO", "Job '$job->name': retention (keep $job->keep) deleted '$old'.");
    }
}

# Runs one export job. Returns the path of the written file, throws on any failure.
function run_export_job(ExportJob $job, mysqli $db): string
{
    $api_url = export_env("EXPORT_API_URL");
    if ($api_url === "") {
        throw new RuntimeException("EXPORT_API_URL is not set. Set it to the REDCap API URL as reachable from this container, e.g. 'http://redcap/api/'.");
    }
    if (!export_api_enabled($db)) {
        throw new RuntimeException("The REDCap API is disabled. Enable it in the Control Center or set RCCONF_api_enabled=1 on the web container.");
    }
    $service_user = new ExportServiceUser($db, export_env("EXPORT_SERVICE_USERNAME", "redcap_docker_export"));
    $service_user->ensure_user();
    $token = $service_user->ensure_project_token($job->project_id);

    $job_dir = rtrim(export_env("EXPORT_DIR", "/opt/redcap-docker/exports"), "/") . "/" . $job->name;
    if (!is_dir($job_dir) && !mkdir($job_dir, 0750, true)) {
        throw new RuntimeException("Can not create export directory '$job_dir'.");
    }
    $final = sprintf("%s/%s_%s.%s", $job_dir, $job->name, gmdate("Ymd\THis\Z"), $job->file_extension());
    $tmp = "$job_dir/.$job->name.partial";

    # Exports are copies of research data: only the owner (and its group) may read them
    $old_umask = umask(0027);
    $fh = fopen($tmp, "w");
    umask($old_umask);
    if ($fh === false) {
        throw new RuntimeException("Can not write '$tmp'.");
    }
    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(["token" => $token] + $job->api_params()),
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => (int)export_env("EXPORT_API_TIMEOUT", "3600"),
        CURLOPT_SSL_VERIFYPEER => filter_var(export_env("EXPORT_API_VERIFY_TLS", "true"), FILTER_VALIDATE_BOOLEAN),
        CURLOPT_SSL_VERIFYHOST => filter_var(export_env("EXPORT_API_VERIFY_TLS", "true"), FILTER_VALIDATE_BOOLEAN) ? 2 : 0,
    ]);
    $ok = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    fclose($fh);

    if (!$ok || $http_code != 200) {
        # On errors REDCap answers with a short message (returnFormat=json), which is safe to log
        $body = substr((string)file_get_contents($tmp), 0, 1000);
        unlink($tmp);
        $reason = $ok ? "HTTP $http_code: $body" : "request failed: $curl_error";
        throw new RuntimeException("API export of project $job->project_id ($job->content) failed, $reason");
    }
    rename($tmp, $final);
    export_apply_retention($job, $job_dir);
    return $final;
}

# Runs a job with a per job lock and records the result for the healthcheck. Returns true on success.
function run_export_job_guarded(ExportJob $job): bool
{
    $lock = fopen(export_state_file($job->name, "lock"), "c");
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        export_log("WARNING", "Job '$job->name' is still running from a previous schedule. Skipped this run.");
        return true;
    }
    $started = microtime(true);
    try {
        $db = get_db_con();
        $path = run_export_job($job, $db);
        $db->close();
        export_log("INFO", sprintf("Job '%s': wrote '%s' (%d bytes, %.1fs).", $job->name, $path, filesize($path), microtime(true) - $started));
        file_put_contents(export_state_file($job->name, "status"), "0");
        return true;
    } catch (Throwable $e) {
        export_log("ERROR", "Job '$job->name': " . $e->getMessage());
        file_put_contents(export_state_file($job->name, "status"), gmdate("c") . " " . $e->getMessage());
        return false;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
