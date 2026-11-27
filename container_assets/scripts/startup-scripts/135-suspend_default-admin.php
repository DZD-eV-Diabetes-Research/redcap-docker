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

// A suspended site_admin must not keep its admin flags. REDCap warns in the Control Center
// as soon as the auth method is not "None (Public)" and site_admin is still an administrator (#19).
$admin_flags = array(
    "super_user",
    "account_manager",
    "access_system_config",
    "access_system_upgrade",
    "access_external_module_install",
    "admin_rights",
    "access_admin_dashboards",
);
$revoke_admin_flags = implode(", ", array_map(fn($flag) => "$flag = 0", $admin_flags));
$mysqli_con->query("UPDATE redcap_user_information SET user_suspended_time = IFNULL(user_suspended_time, NOW()), $revoke_admin_flags WHERE username = 'site_admin';");
printf("WARNING: REDCaps default user 'site_admin' is now suspended, has no admin rights and can not login. This is because Env var 'REDCAP_SUSPEND_SITE_ADMIN' is set to true. If that was on accident, set 'REDCAP_SUSPEND_SITE_ADMIN' to false and reenable the user with:\n");
printf("UPDATE redcap_user_information SET user_suspended_time = null, %s WHERE username = 'site_admin';\n", implode(", ", array_map(fn($flag) => "$flag = 1", $admin_flags)));

// This script runs after user provisioning (130), so provisioned admins are already in the table.
// Users of external auth methods (OpenID Connect, LDAP, ...) only show up here after their first login.
$other_admins = $mysqli_con->query("SELECT COUNT(*) FROM redcap_user_information WHERE username != 'site_admin' AND user_suspended_time IS NULL AND (super_user = 1 OR admin_rights = 1);")->fetch_column();
if ($other_admins == 0) {
    printf("WARNING: There is no other active REDCap user with admin rights ('super_user' or 'admin_rights'). Nobody will be able to administrate REDCap until one exists (e.g. via user provisioning, see USER_PROV.md).\n");
}

$mysqli_con->close();
printf("...supsending REDCap user 'site_admin' done.\n");
