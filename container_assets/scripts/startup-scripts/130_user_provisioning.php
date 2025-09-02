<?php

require_once __DIR__ . '/php_helpers/create_user.php';
require_once __DIR__ . '/php_helpers/db.php';
$ENABLE_USER_PROV = filter_var(getenv('ENABLE_USER_PROV'), FILTER_VALIDATE_BOOLEAN);


function user_data_from_array(?array $user_data_array = null, string $ident = ""): ?UserData
{
    if (is_null($user_data_array)) {
        return [];
    }
    $user_data = new UserData();
    foreach ($user_data_array as $user_prop_name => $user_prop_val) {
        if (!property_exists(new UserData, $user_prop_name)) {
            printf("[USER PROVISIONING][ERROR]: User data at $ident contains unexcpected property '$user_prop_name'. User will not be created. Data: $user_prop_name: '$user_prop_val'\n");
            return null;
        }
        $user_data->$user_prop_name = $user_prop_val;
    }
    if (!$user_data->required_fields_not_empty()) {
        printf("[USER PROVISIONING][ERROR]: User data from env var '$ident' is not complete. User will not be created. Missing fields: %s\n", implode(", ", $user_data->get_required_fields_that_are_empty()));
    } else {
        return $user_data;
    }
    return null;
}

function users_data_from_list(?array $users_data_list = null, string $ident_base = ""): array
{
    if (is_null($users_data_list)) {
        return [];
    }
    $user_data_objs = [];
    foreach ($users_data_list as $user_data_index => $user_props) {
        $user = user_data_from_array($user_props, "$ident_base '$user_data_index'");
        if ($user != null) {
            $user_data_objs[] = $user;
        }
    }
    return $user_data_objs;
}

function collect_user_data_from_indexed_env_vars(string $env_var_prefix): array
{
    // Get all environment variables that start with the value in $env_var_prefix and end with a number
    $all_env_vars = getenv();
    $user_data_env_vars = array();

    foreach ($all_env_vars as $key => $value) {
        if (preg_match("/^$env_var_prefix.*_(\d+)$/", $key)) {
            $user_data_env_vars[$key] = $value;
        }
    }
    $users_raw = array();
    // parse content in env vars as json into arrays
    foreach ($user_data_env_vars as $key => $value) {
        $decoded_data = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $users_raw[$key] = $decoded_data;
        } else {
            printf("[USER PROVISIONING][ERROR]: User data from env var '$key' does not contain valid JSON. User will not be created. Data:\n$value\n");
        }
    }

    $user_data_objs = users_data_from_list($users_raw, "env var ");
    return $user_data_objs;
}

function collect_user_data_from_env_var(string $env_var_name): array
{
    $raw_user_list_json = getenv($env_var_name);

    $users_data_raw = json_decode($raw_user_list_json, true);
    if (!json_last_error() === JSON_ERROR_NONE) {
        printf("[USER PROVISIONING][ERROR]: User data list from env var '$env_var_name' does not contain valid JSON. Users will not be created. Data:\n$raw_user_list_json\n");
        return array();
    }
    $user_data_objs = users_data_from_list($users_data_raw, "env var '$env_var_name' at index ");
    return $user_data_objs;
}

function collect_user_data_from_yaml_file(string $yaml_file): array
{
    $users_data_raw = yaml_parse_file($yaml_file);
    if (!array_key_exists("REDCapUserList", $users_data_raw)) {
        printf("[USER PROVISIONING][ERROR]: Expected a root level key 'REDCapUserList' in yaml file $yaml_file. Can not parse user data file.\n");
    }
    if (!is_array($users_data_raw["REDCapUserList"])) {
        printf("[USER PROVISIONING][ERROR]: Expected a list of users in 'REDCapUserList' in file $yaml_file. Can not parse user data file.\n");
    }
    $user_data_objs = users_data_from_list($users_data_raw["REDCapUserList"], "file '$yaml_file' at pos ");
    return $user_data_objs;
}

function collect_user_data_from_json_file(string $json_file): array
{
    $json_file_raw = file_get_contents($json_file);
    $users_data_raw = json_decode($json_file_raw, true);
    if (!array_key_exists("REDCapUserList", $users_data_raw)) {
        printf("[USER PROVISIONING][ERROR]: Expected a root level key 'REDCapUserList' in json file $json_file. Can not parse user data file.\n");
    }
    if (!is_array($users_data_raw["REDCapUserList"])) {
        printf("[USER PROVISIONING][ERROR]: Expected a list of users in 'REDCapUserList' in file $json_file. Can not parse user data file.\n");
    }
    $user_data_objs = users_data_from_list($users_data_raw["REDCapUserList"], "file '$json_file' at pos ");
    return $user_data_objs;
}

function write_users_to_db(array $users, bool $overwrite_existing = false)
{
    $mysqli_con = get_db_con();
    $uc = new UserCRUD($mysqli_con);
    foreach ($users as $user) {
        $uc->UpsertUser($user, $overwrite_existing);
    }
}

function run_user_provisioning()
{
    $USER_PROV_ENV_VAR_BASE = "USER_PROV";
    $USER_PROV_FILE_DIR = getenv("USER_PROV_FILE_DIR");
    $USER_PROV_OVERWRITE_EXISTING = filter_var(getenv('USER_PROV_OVERWRITE_EXISTING'), FILTER_VALIDATE_BOOLEAN);
    $users = array();
    $users = array_merge($users, collect_user_data_from_indexed_env_vars($USER_PROV_ENV_VAR_BASE));
    $users = array_merge($users, collect_user_data_from_env_var($USER_PROV_ENV_VAR_BASE));
    if (!is_dir($USER_PROV_FILE_DIR)) {
        printf("[USER PROVISIONING][ERROR]: Environment variable USER_PROV_FILE_DIR is defined as '$USER_PROV_FILE_DIR', but this is not a directory. Skip scanning for user data files.\n");
    } else {
        printf("[USER PROVISIONING][INFO]: Scan '$USER_PROV_FILE_DIR' for yaml or json files...\n");
        $user_data_files_dir = new DirectoryIterator($USER_PROV_FILE_DIR);

        foreach ($user_data_files_dir as $fileinfo) {
            $file_path = $fileinfo->getPathname();
            if (!$fileinfo->isDot()) {
                $file_ext = strtolower(pathinfo($fileinfo->getFilename(), PATHINFO_EXTENSION));

                if ($file_ext == "yaml") {
                    printf("[USER PROVISIONING][INFO]: Found yaml file at '$file_path'. Start parsing for user data...\n");
                    $users = array_merge($users, collect_user_data_from_yaml_file($file_path));
                } elseif ($file_ext == "json") {
                    printf("[USER PROVISIONING][INFO]: Found json file at '$file_path'. Start parsing for user data...\n");
                    $users = array_merge($users, collect_user_data_from_json_file($file_path));
                }
            }
        }
    }
    $users_count = count($users);
    printf("[USER PROVISIONING][INFO]: Will write $users_count users into db ");
    if ($USER_PROV_OVERWRITE_EXISTING) {
        printf("(if not allready existing).\n");
    } else {
        printf(".\n");
    }
    write_users_to_db($users, $USER_PROV_OVERWRITE_EXISTING);
}
printf("ENABLE_USER_PROV: $ENABLE_USER_PROV\n");
if ($ENABLE_USER_PROV) {
    printf("Start user provisining...\n");
    run_user_provisioning();
    printf("... user provisining done.\n");
}
