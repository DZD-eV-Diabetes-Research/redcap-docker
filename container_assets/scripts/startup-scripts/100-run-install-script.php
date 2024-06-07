<?php

$env_var_prefix = 'RCCONFIG_';

printf("Install Database Scheme if it is not existent and a REDCap install SQL-script is available...\n");


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

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($db_hostname, $db_username, $db_password, $db_name);
if (is_null($db_ssl_key)) {
    printf("SET DB SSL CONFIG\n");
    $mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $db_ssl_verify_server_cert);
    $mysqli->ssl_set($db_ssl_key, $db_ssl_cert, $db_ssl_ca, $db_ssl_capath, $db_ssl_cipher);
}

printf("Connect to database '$db_hostname:$db_port/$db_name' with user '$db_username' to check if need/can to install the REDCap database scheme\n");


// ## CHECK IF REDCAP IS INSTALLED
# SHOW TABLES IN `redcap` LIKE `redcap_config`;
$config_table_exists = $mysqli->query("SHOW TABLES LIKE 'redcap_config';");
if (!$config_table_exists->num_rows == 0) {
    printf("Nice, REDCap seems to be installed (Table 'redcap_config' exists).\n");
    exit(0);
}
$redcap_install_sql_script_path = getenv('REDCAP_INSTALL_SQL_SCRIPT_PATH') ?? null;
if (!file_exists($redcap_install_sql_script_path)) {
    printf("REDCap seems not to be installed and there is not install SQL script provided at '$redcap_install_sql_script_path'\n");
    printf("You propably need to call the REDCap install page ('http(s)://<youinstance>/install.php') page first.)\n");
    exit(0);
}
$redcap_install_sql_commands = file_get_contents($redcap_install_sql_script_path);
if ('' == $redcap_install_sql_commands) {
    printf("Can not install REDCap Database Scehme. '$redcap_install_sql_script_path' seems to be empty. Check the path and content.\n");
    exit(0);
}
printf("REDCap seems not to be installed and there is install SQL script provided at '$redcap_install_sql_script_path'\n");
printf("Will install database scheme now...\n");
$mysqli->multi_query($redcap_install_sql_commands);
do {
    /* store the result set in PHP */
    if ($result = $mysqli->store_result()) {
        while ($row = $result->fetch_row()) {
            printf("%s\n", $row[0]);
        }
    }
} while ($mysqli->next_result());
$mysqli->close();
printf("...installation of REDCap database scheme is done.\n");