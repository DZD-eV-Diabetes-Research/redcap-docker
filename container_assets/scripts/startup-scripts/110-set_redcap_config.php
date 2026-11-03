<?php

# import get_db_con() from php_helpers/db.php
require __DIR__ . '/php_helpers/db.php';

$env_var_prefix = 'RCCONF_';

printf("Pre-Config REDCap...\n");



$set_conf_vars = filter_var(getenv('APPLY_RCCONF_VARIABLES'), FILTER_VALIDATE_BOOLEAN);
if (!$set_conf_vars) {
    printf("Skipping applying config env vars (RCCONF_*) as APPLY_RCCONF_VARIABLES is not set to true)\n");
    exit(0);
}

// Connect DB
$mysqli_con = get_db_con();


// ## CHECK IF REDCAP IS INSTALLED
# SHOW TABLES IN `redcap` LIKE `redcap_config`;
$config_table_exists = $mysqli_con->query("SHOW TABLES LIKE 'redcap_config';");
if ($config_table_exists->num_rows == 0) {
    printf("Can not apply config vars (RCCONF_*) as REDCap does not seem to be installed (Missing 'redcap_config' table.)\n");
    printf("You propably need to call the REDCap install page ('http(s)://<youinstance>/install.php') page first.)\n");
    exit(0);
}

// # COLLECT REDCAP SETTINGS FROM ENV VARS

// Get all environment variables
$all_env_vars = getenv();
// Filter the environment variables to only include those starting with "RCCONF_"
$redcap_config_env_vars = array_filter($all_env_vars, function ($key) use ($env_var_prefix) {
    return str_starts_with($key, $env_var_prefix);
}, ARRAY_FILTER_USE_KEY);
$redcap_config = array();
foreach ($redcap_config_env_vars as $key => $value) {
    $redcap_config_db_key = substr($key, strlen($env_var_prefix));
    $redcap_config[$redcap_config_db_key] = $value;
}
printf("Found %d RCCONF env variables.\n", count($redcap_config));

// Values of these fields must not end up in the container log.
// Matches every field documented as "secret" in config_vars_list.md.
function is_secret_config_field(string $field_name): bool
{
    return preg_match('/secret|password$|token$|key$/', $field_name) === 1;
}


// WRITE REDCAP SETTINGS INTO CONFIG TABLE ("redcap_config")

$stmt = $mysqli_con->prepare(
    "INSERT INTO `redcap_config` (`field_name`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?"
);
$failed = array();
foreach ($redcap_config as $key => $value) {
    $log_value = is_secret_config_field($key) ? '***' : $value;
    try {
        $stmt->bind_param('sss', $key, $value, $value);
        $stmt->execute();
        printf("Set '%s' to: '%s'\n", $key, $log_value);
    } catch (mysqli_sql_exception $e) {
        // Do not print $e->getMessage(): MySQL quotes parts of the statement, which can contain the value.
        printf("ERROR: Could not set '%s' (MySQL error %d). Continuing with the remaining RCCONF variables.\n", $key, $e->getCode());
        $failed[] = $key;
    }
}
$stmt->close();

$mysqli_con->close();
if (count($failed) > 0) {
    printf("...pre-config REDCap done, but %d RCCONF variable(s) could not be applied: %s\n", count($failed), implode(', ', $failed));
    exit(1);
}
printf("...pre-config REDCap done.\n");