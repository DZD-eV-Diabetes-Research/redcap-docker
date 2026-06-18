<?php

# import get_db_con() from php_helpers/db.php
require __DIR__ . '/php_helpers/db.php';

$suspend_default_admin = filter_var(getenv('REDCAP_SUSPEND_SITE_ADMIN'), FILTER_VALIDATE_BOOLEAN);
if (!$suspend_default_admin) {
    exit(0);
}
printf("Supsending REDCap user 'site_admin'...\n");
// Connect DB
$mysqli_con = get_db_con();

// ## CHECK IF REDCAP IS INSTALLED
$user_table_exists = $mysqli_con->query("SHOW TABLES LIKE 'redcap_user_information';");
if (!$user_table_exists || $user_table_exists->num_rows == 0) {
    printf("Can not suspend user 'site_admin' as REDCap does not seem to be installed (Missing 'redcap_user_information' table.)\n");
    printf("You probably need to call the REDCap install page ('http(s)://<yourinstance>/install.php') first.\n");
    $mysqli_con->close();
    exit(0);
}

$mysqli_con->query("UPDATE redcap_user_information SET user_suspended_time = NOW() WHERE username = 'site_admin';");
printf("WARNING: REDCaps default user 'site_admin' is now suspended and can not login. This is because Env var 'REDCAP_SUSPEND_SITE_ADMIN' is set to true. If that was on accident you can reanable the user with:\n");
printf("UPDATE redcap_user_information SET user_suspended_time = null WHERE username = 'site_admin';\n");

$mysqli_con->close();
printf("...supsending REDCap user 'site_admin' done.\n");