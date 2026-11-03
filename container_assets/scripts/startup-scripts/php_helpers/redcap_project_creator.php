<?php

/**
 * REDCap-bootstrapping project creator (child process).
 *
 * Run as: php -f redcap_project_creator.php -- <task.json>
 *
 * Invoked once per project by 150_project_provisioning.php. Runs as www-data so
 * files from the XML (edocs, temp files) are owned by the web server user.
 * One process per project because REDCap keeps the new project id in the
 * PROJECT_ID constant.
 *
 * The project is created like the API method "Create Project" with an odm
 * parameter does it (Project::apiCreate()), but without an API token and
 * optionally without a creator. ODM::parseOdm() commits its own
 * transactions, so instead of a rollback a failed import is removed again
 * with deleteProjectNow(), also on fatal errors via a shutdown function.
 *
 * The task file is a JSON object:
 *   { "key": "study-a", "xml_file": "/tmp/.../project.xml", "metadata_only": false,
 *     "owner": "jdoe" | null, "title": "...", "purpose": 2,
 *     "purpose_other": "..." | null, "project_notes": "..." | null }
 */

require_once __DIR__ . '/redcap_info.php';
require_once __DIR__ . '/project_provisioning.php';

// Startup scripts run as root. Switch to www-data in-process: needs only the
// SETUID/SETGID capabilities, no PAM like su/runuser.
if (posix_geteuid() === 0) {
    $www = posix_getpwnam('www-data');
    if (!$www || !posix_initgroups('www-data', $www['gid']) || !posix_setgid($www['gid']) || !posix_setuid($www['uid'])) {
        project_prov_log("ERROR", "Can not switch to user www-data: " . posix_strerror(posix_get_last_error()));
        exit(1);
    }
}

$task_file = $argv[1] ?? null;
$task = ($task_file !== null && is_file($task_file)) ? json_decode((string)file_get_contents($task_file), true) : null;
if (!is_array($task)) {
    project_prov_log("ERROR", "Project creator called without a valid task file.");
    exit(1);
}

// ── Bootstrap REDCap ────────────────────────────────────────────────────────
$REDCAPDIR = get_installed_redcap_version_dir_path();
define('NOAUTH', true);
// The acting user in REDCap's logging. 'SYSTEM' is what REDCap's own cron uses.
define('USERID', $task['owner'] ?? 'SYSTEM');
// Same privileges as the API "Create Project" method, which needs a super token
define('SUPER_USER', 1);
define('CREATE_PROJECT_ODM', true);
require_once $REDCAPDIR . '/Config/init_global.php';

$key = $task['key'];
$created = false;
register_shutdown_function(function () use (&$created, $key) {
    if (!$created && defined('PROJECT_ID')) {
        project_prov_log("WARNING", "Removing the incomplete project " . PROJECT_ID . " of '$key' again.");
        deleteProjectNow(PROJECT_ID, false);
    }
});

System::increaseMemory(2048);
$odm = file_get_contents($task['xml_file']);
if ($task['metadata_only']) {
    $odm = project_xml_strip_clinical_data($odm);
}
$data = [
    'project_title' => $task['title'],
    'purpose' => $task['purpose'],
    'purpose_other' => $task['purpose_other'] ?? '',
    'project_notes' => $task['project_notes'],
];
function pc_fail(string $key, array $errors): never
{
    project_prov_log("ERROR", "Project '$key' was not created:\n  - " . implode("\n  - ", array_map('strip_tags', $errors)));
    exit(1);
}

$errors = Project::validateApiCreateProjectInput($data, $odm);
if ($errors) {
    pc_fail($key, $errors);
}

// The steps of Project::apiCreate(), which can not be called directly: it
// requires a creator (created_by references redcap_user_information) and hands
// out an API token with full API rights.
$app_title = Project::cleanTitle($task['title']);
$created_by = $task['owner'] === null ? null : User::getUIIDByUsername($task['owner']);
$q = db_query(
    "INSERT INTO redcap_projects (
        project_name, scheduling, repeatforms, purpose, purpose_other, app_title, creation_time, created_by,
        surveys_enabled, auto_inc_set, randomization, data_resolution_enabled, project_note, log_event_table, data_table, google_recaptcha_enabled
    ) VALUES (?, 0, 0, ?, ?, ?, ?, ?, 0, 1, 0, ?, ?, ?, ?, ?)",
    [
        Project::getValidProjectName($app_title), $task['purpose'],
        // "Other" stores its description here, research projects their research areas
        in_array($task['purpose'], [1, 2]) ? $task['purpose_other'] : null,
        $app_title, NOW, $created_by,
        ($GLOBALS['field_comment_log_enabled_default'] ?? '1') == '0' ? 0 : 1,
        $task['project_notes'] === null ? null : trim($task['project_notes']),
        Logging::getSmallestLogEventTable(), Records::getSmallestDataTable(), $GLOBALS['google_recaptcha_default'] ?? 0,
    ]
);
if (!$q) {
    pc_fail($key, ["Insert into redcap_projects failed: " . db_error()]);
}
define('PROJECT_ID', db_insert_id());
$project_id = PROJECT_ID;
Project::setDefaults($project_id);

// Arms, events, instruments, project settings and records from the XML
$odm_response = ODM::parseOdm($odm);
if (!empty($odm_response['errors'])) {
    pc_fail($key, $odm_response['errors']);
}
if ($task['owner'] !== null) {
    $form_names = [];
    $q = db_query("SELECT DISTINCT form_name FROM redcap_metadata WHERE project_id = ?", [$project_id]);
    while ($row = db_fetch_assoc($q)) {
        $form_names[] = $row['form_name'];
    }
    Project::insertUserRightsProjectCreator($project_id, $task['owner'], 0, 0, $form_names);
}
db_query(
    "INSERT INTO redcap_config (field_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)",
    [PROJECT_PROV_MARKER_PREFIX . $key, $project_id]
);
Logging::logEvent("", "redcap_projects", "MANAGE", $project_id, "project_id = $project_id",
    "Create project using REDCap XML file (redcap-docker project provisioning, key '$key')");
$created = true;

$records = db_result(db_query("SELECT COUNT(DISTINCT record) FROM " . Records::getDataTable($project_id) . " WHERE project_id = ?", [$project_id]), 0);
project_prov_log("INFO", sprintf("Created project %d '%s' for key '%s'%s with %d record(s).",
    $project_id, $task['title'], $key, $task['owner'] === null ? " without users" : " owned by '{$task['owner']}'", $records));
exit(0);
