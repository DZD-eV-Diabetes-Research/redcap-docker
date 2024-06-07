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
// Filter the environment variables to only include those starting with "RCCONFIG_"
$redcap_config_env_vars = array_filter($all_env_vars, function ($key) use ($env_var_prefix) {
    return str_starts_with($key, $env_var_prefix);
}, ARRAY_FILTER_USE_KEY);
printf("Found following RCCONF env variables: ");
print_r($redcap_config_env_vars);
$redcap_config = array();
$replace_count = 1;
foreach ($redcap_config_env_vars as $key => $value) {
    $redcap_config_db_key = str_replace($env_var_prefix, "", $key, $replace_count);
    $redcap_config[$redcap_config_db_key] = $value;
}


// WRITE REDCAP SETTINGS INTO CONFIG TABLE ("redcap_config")

foreach ($redcap_config as $key => $value) {
    $sql_statement = "INSERT INTO `redcap_config` (`field_name`, `value`) VALUES( '$key', '$value') ON DUPLICATE KEY UPDATE `value` = '$value';";
    printf("Set '$key' to: '$value'\n");
    printf("SQL statement: $sql_statement \n");
    $mysqli_con->query($sql_statement);
}

$mysqli_con->close();
printf("...pre-config REDCap done.\n");