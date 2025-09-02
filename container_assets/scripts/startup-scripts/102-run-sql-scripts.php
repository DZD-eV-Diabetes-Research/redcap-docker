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

# import get_files_from_location()
require __DIR__ . '/php_helpers/get_files_from_location.php';

# import file_was_processed(), mark_file_as_processed()
require __DIR__ . '/php_helpers/processed_file_state_manager.php';

# import run_sql_files()
require __DIR__ . '/php_helpers/run_sql_file.php';


//MAIN SCRIPT

$sql_scripts_run_at_boot_location = getenv('AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION') ?? null;
printf("[RUN CUSTOM BOOT SQLS] AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION: `$sql_scripts_run_at_boot_location`\n");
if (!$sql_scripts_run_at_boot_location) {
    printf("[RUN CUSTOM BOOT SQLS] No boot SQL location configured in env var `AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION`. Skipping.\n");
    return; // clean exit
}

$file_list = get_files_from_location($sql_scripts_run_at_boot_location, "sql");
printf("[RUN CUSTOM BOOT SQLS] Found files: '" . json_encode($file_list) . "'\n");
// Connect DB
$mysqli_con = get_db_con();

foreach ($file_list as $sql_file_path) {
    printf("[RUN CUSTOM BOOT SQLS] Try run file: '$sql_file_path'\n");
    if (file_was_processed($sql_file_path, 'CUSTOM_BOOT_SCRIPTS')) {
        printf("[RUN CUSTOM BOOT SQLS] Skipping file '$sql_file_path': File allready run before.\n");
        continue;
    }
    if (!file_exists($sql_file_path)) {
        printf("[RUN CUSTOM BOOT SQLS] Skipping file '$sql_file_path': File not existing.\n");
        continue;
    }
    run_sql_files($sql_file_path, $mysqli_con);
    printf("[RUN CUSTOM BOOT SQLS] Done with file '$sql_file_path'. Mark as processed\n");
    mark_file_as_processed($sql_file_path, 'CUSTOM_BOOT_SCRIPTS');
}

$mysqli_con->close();
printf("[RUN CUSTOM BOOT SQLS] Done all.\n");
