<?php

$env_var_prefix = 'RCCONFIG_';

printf("Pre-Config REDCap...\n");

// # CHECK IF WE NEED TO SET CONFIG VARS
printf(getenv('APPLY_RCCONF_VARIABLES'));
printf("\n");

$set_conf_vars = filter_var(getenv('APPLY_RCCONF_VARIABLES'), FILTER_VALIDATE_BOOLEAN);
if (!$set_conf_vars) {
    printf("Skipping applying config env vars (RCCONF_*) as APPLY_RCCONF_VARIABLES is not set to true)\n");
    exit(0);
}

// Connect DB

$db_port = getenv('DB_PORT') ?? '';
$db_hostname = getenv('DB_HOSTNAME') ?? '';
$db_name = getenv('DB_NAME') ?? '';
$db_username = getenv('DB_USERNAME') ?? '';
$db_password = getenv('DB_PASSWORD') ?? '';

$db_ssl_key = getenv('DB_SSL_KEY_PATH');
if ($db_ssl_key === '') {
    $db_ssl_key = null;
} else {
    $db_ssl_key = $db_ssl_key ?? null;
}

$db_ssl_cert = getenv('DB_SSL_CERT_PATH') ?? null;
$db_ssl_ca = getenv('DB_SSL_CA_FILE_PATH') ?? null;
$db_ssl_capath = getenv('DB_SSL_CA_DIR_PATH') ?? null;
$db_ssl_cipher = getenv('DB_SSL_ALGOS') ?? null;
$db_ssl_verify_server_cert = filter_var(getenv('DB_SSL_VERIFY_SERVER'), FILTER_VALIDATE_BOOLEAN);
printf("Connect to database '$db_hostname:$db_port/$db_name' with user '$db_username' to apply env var configs\n");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($db_hostname, $db_username, $db_password, $db_name);
if (is_null($db_ssl_key)) {
    printf("SET DB SSL CONFIG\n");
    $mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $db_ssl_verify_server_cert);
    $mysqli->ssl_set($db_ssl_key, $db_ssl_cert, $db_ssl_ca, $db_ssl_capath, $db_ssl_cipher);
}



// ## CHECK IF REDCAP IS INSTALLED
# SHOW TABLES IN `redcap` LIKE `redcap_config`;
$config_table_exists = $mysqli->query("SHOW TABLES LIKE 'redcap_config';");
if ($config_table_exists->num_rows == 0) {
    printf("Can not apply config vars (RCCONF_*) as REDCap does not seem to be installed (Missing 'redcap_config' table.)\n");
    exit(0);
}

// # COLLECT REDCAP SETTINGS FROM ENV VARS

// Get all environment variables
$all_env_vars = getenv();

// Filter the environment variables to only include those starting with "RCCONFIG_"
$redcap_config_env_vars = array_filter($all_env_vars, function ($key) use ($env_var_prefix) {
    return str_starts_with($key, $env_var_prefix);
}, ARRAY_FILTER_USE_KEY);
printf("Found following RCCONF env variables: $redcap_config_env_vars \n");
$redcap_config = array();
$replace_count = 1;
foreach ($redcap_config_env_vars as $key => $value) {
    $redcap_config_db_key = str_replace($env_var_prefix, "", $key, $replace_count);
    $redcap_config[$redcap_config_db_key] = $value;
}


// WRITE REDCAP SETTINGS INTO CONFIG TABLE ("redcap_config")

foreach ($redcap_config as $key => $value) {
    $sql_statement = "INSERT INTO `$db_name`.`redcap_config` (`field_name`, `value`) VALUES( '$key', '$value') ON DUPLICATE KEY UPDATE `value` = '$value';";
    printf("Set '$key' to: '$value'\n");
    printf("SQL statement: $sql_statement \n");
    $mysqli->query($sql_statement);
}

$mysqli->close();
printf("...pre-config REDCap done.\n")