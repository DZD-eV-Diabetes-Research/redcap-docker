<?php

/**
 * Project provisioning (startup script).
 *
 * Creates REDCap projects from REDCap project XML files (CDISC ODM, e.g. a
 * project_xml data export or "Download metadata & data (XML)" of another
 * instance) on container boot. Every spec is created exactly once; its key is
 * remembered in redcap_config.
 *
 * See PROJECT_PROV.md for the full operator documentation.
 *
 * Pipeline:
 *   1. Collect project specs (env vars + files).
 *   2. Per spec that was not provisioned yet: check the owner, copy or
 *      download the XML into a private work dir, verify the checksum and read
 *      title and purpose from the XML header.
 *   3. Hand the task to redcap_project_creator.php, a child process that
 *      switches to www-data, bootstraps REDCap and creates the project with
 *      REDCap's own XML import.
 */

require_once __DIR__ . '/php_helpers/redcap_info.php';
require_once __DIR__ . '/php_helpers/project_provisioning.php';

// Serializes provisioning when several web containers boot at the same time
const PROJECT_PROV_DB_LOCK = "redcap_docker_project_prov";

/** Returns null if the spec still has to be provisioned, otherwise a log message why not. */
function project_prov_done_message(mysqli $db, ProjectSpec $spec): ?string
{
    $stmt = $db->prepare("SELECT value FROM redcap_config WHERE field_name = ?");
    $stmt->execute([$spec->marker_field()]);
    $project_id = $stmt->get_result()->fetch_column();
    if ($project_id === false || $project_id === null) {
        return null;
    }
    $stmt = $db->prepare("SELECT date_deleted FROM redcap_projects WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->get_result()->fetch_assoc();
    if ($project !== null && $project["date_deleted"] === null) {
        return "Project '$spec->key' already provisioned as project $project_id. Skipping.";
    }
    return "Project '$spec->key' was provisioned as project $project_id, which has been deleted since. It is not created again. "
        . "To provision it anew, delete the row '{$spec->marker_field()}' from redcap_config.";
}

function project_prov_user_exists(mysqli $db, string $username): bool
{
    $stmt = $db->prepare("SELECT 1 FROM redcap_user_information WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->get_result()->fetch_column() !== false;
}

/** Puts the spec's XML into $work_dir and returns the task for the creator. Throws on any problem. */
function project_prov_build_task(ProjectSpec $spec, string $work_dir): array
{
    $xml_file = "$work_dir/project.xml";
    if ($spec->url !== null) {
        project_prov_log("INFO", "Downloading project XML for '$spec->key' from '$spec->url'...");
        project_prov_download($spec->url, $xml_file);
    } else {
        $src = str_starts_with($spec->xml, "/") ? $spec->xml : rtrim((string)getenv("PROJECT_PROV_FILE_DIR"), "/") . "/" . $spec->xml;
        if (!is_file($src) || !is_readable($src)) {
            throw new RuntimeException("XML file '$src' does not exist or is not readable.");
        }
        if (!copy($src, $xml_file)) {
            throw new RuntimeException("Can not copy '$src' to '$xml_file'.");
        }
    }
    if ($spec->sha256 !== null && !hash_equals($spec->sha256, hash_file("sha256", $xml_file))) {
        throw new RuntimeException("Checksum mismatch: the sha256 of '{$spec->location()}' is " . hash_file("sha256", $xml_file) . ", expected $spec->sha256.");
    }
    $header = project_xml_header($xml_file);

    $task = [
        "key" => $spec->key,
        "xml_file" => $xml_file,
        "metadata_only" => $spec->metadata_only,
        "owner" => $spec->owner,
        "title" => $spec->title ?? $header["title"],
        "purpose" => $spec->purpose ?? $header["purpose"],
        "purpose_other" => $spec->purpose_other ?? $header["purpose_other"],
        "project_notes" => $spec->project_notes ?? $header["project_notes"],
    ];
    if ($task["title"] === null) {
        throw new RuntimeException("The XML has no project title (StudyName). Set 'title' in the spec.");
    }
    if (filter_var($task["purpose"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 4]]) === false) {
        throw new RuntimeException("The XML has no valid project purpose (redcap:Purpose). Set 'purpose' (0 to 4) in the spec.");
    }
    $task["purpose"] = (int)$task["purpose"];
    if ($task["purpose"] == 1 && $task["purpose_other"] === null) {
        throw new RuntimeException("Purpose 1 (other) needs a description. Set 'purpose_other' in the spec.");
    }
    file_put_contents("$work_dir/task.json", json_encode($task));
    return $task;
}

/** Runs the creator for one task. It switches to www-data itself. Returns true on success. */
function project_prov_run_creator(string $work_dir): bool
{
    if (posix_geteuid() === 0) {
        chown($work_dir, "www-data");
        foreach (glob("$work_dir/*") as $file) {
            chown($file, "www-data");
        }
    }
    $creator = __DIR__ . '/php_helpers/redcap_project_creator.php';
    passthru('php -f ' . escapeshellarg($creator) . ' -- ' . escapeshellarg("$work_dir/task.json"), $exit_code);
    return $exit_code === 0;
}

function project_prov_remove_dir(string $dir): void
{
    foreach (glob("$dir/*") as $file) {
        unlink($file);
    }
    rmdir($dir);
}

function run_project_provisioning(): void
{
    $specs = collect_project_specs();
    if (!$specs) {
        project_prov_log("INFO", "No project specs found. Nothing to do.");
        return;
    }
    project_prov_log("INFO", "Collected " . count($specs) . " project spec(s).");

    $db = get_db_con();
    if ($db->query("SELECT GET_LOCK('" . PROJECT_PROV_DB_LOCK . "', 600)")->fetch_column() != 1) {
        project_prov_log("ERROR", "Another container is still provisioning projects (waited 600s for the database lock). Skipping project provisioning.");
        return;
    }
    $failed = 0;
    foreach ($specs as $spec) {
        $done_message = project_prov_done_message($db, $spec);
        if ($done_message !== null) {
            project_prov_log("INFO", $done_message);
            continue;
        }
        if ($spec->owner !== null && !project_prov_user_exists($db, $spec->owner)) {
            project_prov_log("ERROR", "Project '$spec->key' not created: owner '$spec->owner' does not exist. Create the user first, e.g. with user provisioning. Retried on the next start.");
            $failed++;
            continue;
        }
        $work_dir = sys_get_temp_dir() . "/redcap_project_prov_" . bin2hex(random_bytes(8));
        mkdir($work_dir, 0700);
        try {
            $task = project_prov_build_task($spec, $work_dir);
            project_prov_log("INFO", sprintf("Creating project '%s' (key '%s') from '%s'%s...",
                $task["title"], $spec->key, $spec->location(), $spec->metadata_only ? ", without records" : ""));
            if (!project_prov_run_creator($work_dir)) {
                $failed++;
            }
        } catch (Throwable $e) {
            project_prov_log("ERROR", "Project '$spec->key' not created: " . $e->getMessage() . " Retried on the next start.");
            $failed++;
        } finally {
            project_prov_remove_dir($work_dir);
        }
    }
    $db->query("SELECT RELEASE_LOCK('" . PROJECT_PROV_DB_LOCK . "')");
    $db->close();
    if ($failed) {
        project_prov_log("ERROR", "$failed project spec(s) failed, see above.");
    }
}

// ── Entry point ─────────────────────────────────────────────────────────────
$ENABLE_PROJECT_PROV = filter_var(getenv('ENABLE_PROJECT_PROV'), FILTER_VALIDATE_BOOLEAN);
printf("ENABLE_PROJECT_PROV: %s\n", $ENABLE_PROJECT_PROV ? 'true' : 'false');

if (!$ENABLE_PROJECT_PROV) {
    return;
}
if (!redcap_is_installed()) {
    project_prov_log("WARNING", "Skipping project provisioning, REDCap does not appear to be installed yet (no 'redcap_config' table).");
    return;
}
run_project_provisioning();
project_prov_log("INFO", "Project provisioning done.");
