<?php

// Sync REDCAP_COMMUNITY_USER / REDCAP_COMMUNITY_PASSWORD into the REDCap
// database config table so REDCap's own update-checker web UI also has the
// credentials — without requiring the operator to also set the legacy
// RCCONF_redcap_updates_community_user / _password env vars.
//
// Runs unconditionally (independent of APPLY_RCCONF_VARIABLES).
// Skipped silently if REDCap is not yet installed.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/php_helpers/db.php';

$user = getenv('REDCAP_COMMUNITY_USER') ?: null;
$pass = getenv('REDCAP_COMMUNITY_PASSWORD') ?: null;

if (!$user && !$pass) {
    exit(0);
}

$mysqli = get_db_con();

$result = $mysqli->query("SHOW TABLES LIKE 'redcap_config'");
if (!$result || $result->num_rows === 0) {
    $mysqli->close();
    exit(0);
}

$upsert = static function (mysqli $db, string $field, string $value): void {
    $field = $db->real_escape_string($field);
    $value = $db->real_escape_string($value);
    $db->query(
        "INSERT INTO `redcap_config` (`field_name`, `value`) VALUES ('$field', '$value')"
        . " ON DUPLICATE KEY UPDATE `value` = '$value'"
    );
    printf("[COMMUNITY-CREDS] Set redcap_config.%s\n", $field);
};

if ($user) {
    $upsert($mysqli, 'redcap_updates_community_user', $user);
}
if ($pass) {
    $upsert($mysqli, 'redcap_updates_community_password', $pass);
}

$mysqli->close();
