<?php
// COLLECT REDCAP SETTINGS FROM ENV VARS
$env_var_prefix = 'RCCONFIG_';

// Get all environment variables
$all_env_vars = getenv();

// Filter the environment variables to only include those starting with "RCCONFIG_"
$redcap_config_env_vars = array_filter($all_env_vars, function ($key) use ($env_var_prefix) {
    return str_starts_with($key, $env_var_prefix);
}, ARRAY_FILTER_USE_KEY);

$redcap_config = array();
// Print the filtered environment variables and their values
$replace_count = 1;
foreach ($redcap_config_env_vars as $key => $value) {
    $redcap_config_db_key = str_replace($env_var_prefix, "", $key, $replace_count);
    $redcap_config[$redcap_config_db_key] = $value;
}

// WRITE REDCAP SETTINGS INTO CONFIG TABLE ("redcap_config")

$db_port = getenv('DB_PORT') ?? '';
$db_hostname = getenv('DB_HOSTNAME') ?? '';
$db_name = getenv('DB_NAME') ?? '';
$db_username = getenv('DB_USERNAME') ?? '';
$db_password = getenv('DB_PASSWORD') ?? '';

$db_ssl_key = getenv('DB_SSL_KEY_PATH') ?? '';
$db_ssl_cert = getenv('DB_SSL_CERT_PATH') ?? '';
$db_ssl_ca = getenv('DB_SSL_CA_FILE_PATH') ?? '';
$db_ssl_capath = getenv('DB_SSL_CA_DIR_PATH') ?? NULL;
$db_ssl_cipher = getenv('DB_SSL_ALGOS') ?? NULL;
$db_ssl_verify_server_cert = filter_var(getenv('DB_SSL_VERIFY_SERVER'), FILTER_VALIDATE_BOOLEAN);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($hostname, $username, $password, $db);
if (isset($db_ssl_key)) {
    $mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $db_ssl_verify_server_cert);
    $mysqli->ssl_set($db_ssl_key, $db_ssl_cert, $db_ssl_ca, $db_ssl_capath, $db_ssl_cipher);
}
## TODO: YOu are here:
# * Check if config table exists
# * upsert config variables


## MYSQL DEMO CODE: remove when finished
/* Create table doesn't return a resultset */
$mysqli->query("CREATE TEMPORARY TABLE myCity LIKE City");
printf("Table myCity successfully created.\n");

/* Select queries return a resultset */
$result = $mysqli->query("SELECT Name FROM City LIMIT 10");
printf("Select returned %d rows.\n", $result->num_rows);

/* If we have to retrieve large amount of data we use MYSQLI_USE_RESULT */
$result = $mysqli->query("SELECT * FROM City", MYSQLI_USE_RESULT);

/* Note, that we can't execute any functions which interact with the
    server until all records have been fully retrieved or the result
    set was closed. All calls will return an 'out of sync' error */
$mysqli->query("SET @a:='this will not work'");
