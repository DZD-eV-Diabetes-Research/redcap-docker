<?php
# import get_db_con() from php_helpers/db.php
require __DIR__ . '/db.php';
// Connect DB
$mysqli_con = get_db_con();

// ## CHECK IF REDCAP IS INSTALLED
# SHOW TABLES IN `redcap` LIKE `redcap_config`;
$config_table_exists = $mysqli_con->query("SHOW TABLES LIKE 'redcap_config';");
if (!$config_table_exists->num_rows == 0) {
    printf("Nice, REDCap seems to be installed (Table 'redcap_config' exists).\n");
    exit(0);
}
else{
    throw new Exception("REDCap error: Can not find 'redcap_config' table in database.");
}