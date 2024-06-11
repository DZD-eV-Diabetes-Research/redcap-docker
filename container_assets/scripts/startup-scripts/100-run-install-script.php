<?php

//IMPORTS
# import get_db_con() from php_helpers/db.php
require __DIR__ . '/php_helpers/db.php';

# import 
#get_redcap_source_path()
#get_existent_redcap_version_dirs()
#get_highest_redcap_version_dir()
#get_highest_redcap_version_no()
#get_installed_redcap_version_no()
#get_installed_redcap_version_dir_path() 
# from php_helpers/redcap_info.php
require __DIR__ . '/php_helpers/redcap_info.php';

//STATICS
$env_var_prefix = 'RCCONFIG_';
//FUNCTIONS

function run_sql_files(string $sql_file_path, mysqli $sql_connection)
{
    printf("Run SQL script from '$sql_file_path'\n");
    if (!file_exists($sql_file_path)) {
        printf("WARNING:  '$sql_file_path' is not a file we can access. Check the path and permissions.\n");
    }
    $sql_file_content = file_get_contents($sql_file_path);
    if ('' == $sql_file_content) {
        printf("WARNING: '$sql_file_path' seems to be empty. Check the path and content.\n");
        return;
    }

    $sql_connection->multi_query($sql_file_content);
    do {
        if ($result = $sql_connection->store_result()) {
            while ($row = $result->fetch_row()) {
                printf("%s\n", $row[0]);
            }
        }
    } while ($sql_connection->next_result());
}


//MAIN SCRIPT

## DEBUG
$local_redcap_versions = get_existent_redcap_version_dirs();
printf("Localy available REDCap Versions:\n");
print_r($local_redcap_versions);
## /DEBUG


$latest_redcap_version = get_highest_redcap_version_no();
$latest_redcap_version_dir_path = get_highest_redcap_version_dir();
printf("Latest available REDCap Versions is $latest_redcap_version located at '$latest_redcap_version_dir_path'\n");


$suspend_default_admin = filter_var(getenv('REDCAP_INSTALL_ENABLE'), FILTER_VALIDATE_BOOLEAN);
if (!$suspend_default_admin) {
    exit(0);
}

printf("Install Database Scheme if it is not existent and a REDCap install SQL-script is available...\n");


// Connect DB

// Connect DB
$mysqli_con = get_db_con();


// ## CHECK IF REDCAP IS INSTALLED
# SHOW TABLES IN `redcap` LIKE `redcap_config`;
$config_table_exists = $mysqli_con->query("SHOW TABLES LIKE 'redcap_config';");
if (!$config_table_exists->num_rows == 0) {
    printf("Nice, REDCap seems to be installed (Table 'redcap_config' exists).\n");
    exit(0);
}
$redcap_install_sql_script_custom = getenv('REDCAP_INSTALL_SQL_SCRIPT_PATH') ?? null;

$redcap_install_sql_script_pathes = array($redcap_install_sql_script_custom);

$concluding_sql_statements = array();

if (!file_exists($redcap_install_sql_script_pathes[0])) {
    # use install script of local REDCap source.
    $redcap_install_sql_script_pathes = array("$latest_redcap_version_dir_path/Resources/sql/install.sql", "$latest_redcap_version_dir_path/Resources/sql/install_data.sql");
    $concluding_sql_statements = array(
        "UPDATE redcap_config SET value = '$latest_redcap_version' WHERE field_name = 'redcap_version';",
        "REPLACE INTO redcap_history_version (`date`, redcap_version) values (CURDATE(), '$latest_redcap_version');"
    );
}

if (!file_exists($redcap_install_sql_script_pathes[0])) {
    # We could not find any install sql scripts. Warn user the need to fix this! 
    printf("WARNING: REDCap seems not to be installed and there is no install SQL script provided at '$redcap_install_sql_script_custom' or availabel via source in $$latest_redcap_version_dir_path\n");
    printf("You propably need to call the REDCap install page ('http(s)://<youinstance>/install.php') page first.)\n");
    exit(0);
}



printf("REDCap seems not to be installed and there is a install SQL script provided at '$redcap_install_sql_script_pathes[0]'\n");
printf("Will install database scheme now...\n");
### RUN SCIPRS
foreach ($redcap_install_sql_script_pathes as $sql_file) {
    run_sql_files($sql_file, $mysqli_con);
}

printf("Run SQL queries to conclude installation:\n");
foreach ($concluding_sql_statements as $sql_statement) {
    printf("    $sql_statement\n");
    $mysqli_con->query($sql_statement);
}

$mysqli_con->close();
printf("...installation of REDCap database scheme is done.\n");
