<?php
global $log_all_errors;
$log_all_errors = FALSE;



$tmp_host = getenv('DB_HOSTNAME') ?? '';
$tmp_port = getenv('DB_PORT') ?? null;
if (isset($tmp_port)) {
    $hostname = $tmp_host . ':' . $tmp_port;
} else {
    $hostname = getenv('DB_HOSTNAME') ?? '';
}
$db = getenv('DB_NAME') ?? '';
$username = getenv('DB_USERNAME') ?? '';
$password = getenv('DB_PASSWORD') ?? '';


$db_ssl_key = getenv('DB_SSL_KEY_PATH') ?? '';
$db_ssl_cert = getenv('DB_SSL_CERT_PATH') ?? '';
$db_ssl_ca = getenv('DB_SSL_CA_FILE_PATH') ?? '';
$db_ssl_capath = getenv('DB_SSL_CA_DIR_PATH') ?? NULL;
$db_ssl_cipher = getenv('DB_SSL_ALGOS') ?? NULL;
$db_ssl_verify_server_cert = filter_var(getenv('DB_SSL_VERIFY_SERVER'), FILTER_VALIDATE_BOOLEAN);

$salt = getenv('DB_SALT') ?? '';

# REDCap Datatransferserver
$dtsHostname = getenv('DTS_HOSTNAME') ?? null;
$dtsDb = getenv('DTS_DB') ?? null;
$dtsUsername = getenv('DTS_USERNAME') ?? null;
$dtsPassword = getenv('DTS_PASSWORD') ?? null;