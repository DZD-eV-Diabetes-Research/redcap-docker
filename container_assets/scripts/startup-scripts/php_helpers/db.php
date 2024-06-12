<?php

function get_db_con(): mysqli
{

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
    #printf("Connect to database '$db_hostname:$db_port/$db_name' with user '$db_username'\n");
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli_con = new mysqli($db_hostname, $db_username, $db_password, $db_name);
    if (is_null($db_ssl_key)) {
        #printf("SET DB SSL CONFIG\n");
        $mysqli_con->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $db_ssl_verify_server_cert);
        $mysqli_con->ssl_set($db_ssl_key, $db_ssl_cert, $db_ssl_ca, $db_ssl_capath, $db_ssl_cipher);
    }
    return $mysqli_con;
}
