<?php

require_once __DIR__ . '/php_helpers/create_user.php';
$ENABLE_USER_PROV = filter_var(getenv('USER_PROV_YAML_DIR'), FILTER_VALIDATE_BOOLEAN);


function user_data_from_array($user_data_array): UserData
{
    $user_data = new UserData();
    foreach ($user_data_array as $user_prop_name => $user_prop_val) {
        $user_data->$user_prop_name = $user_prop_val;
    }
    return $user_data;
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
    $user_data_objs = array();
    // cast arrays data into UserData Objects
    foreach ($users_raw as $user_env_var_name => $user_vals) {
        $user_data = user_data_from_array($user_vals);
        if ($user_data->check_if_required_fields_not_empty()) {
            $user_data_objs[$user_env_var_name] = $user_data;
        } else {
            printf("[USER PROVISIONING][ERROR]: User data from env var '$user_env_var_name' is not complete. User will not be created. Missing fields: %s", implode(", ", $user_data->get_required_fields_that_are_empty()));
        }
    }
    return $user_data_objs;
}

function collect_user_data_from_env_var(string $env_var_name): array
{
    $raw_user_list_json = getenv($env_var_name);
    $decoded_user_data_list = json_decode($raw_user_list_json, true);
    if (!json_last_error() === JSON_ERROR_NONE) {
        printf("[USER PROVISIONING][ERROR]: User data list from env var '$env_var_name' does not contain valid JSON. Users will not be created. Data:\n$raw_user_list_json\n");
        return array();
    }
    $user_data_objs = array();
    foreach ($decoded_user_data_list as $index => $user_data_raw) {
        $user_data = user_data_from_array($user_data_raw);
        if ($user_data->check_if_required_fields_not_empty()) {
            $user_data_objs[] = $user_data;
        } else {
            printf("[USER PROVISIONING][ERROR]: User data from env var '$env_var_name' at positon $index is not complete. User will not be created. Missing fields: %s", implode(", ", $user_data->get_required_fields_that_are_empty()));
        }
    }
    return $user_data_objs;
}


function collect_user_data_from_yaml_file(string $yaml_file): array
{
    ## TODO-WIP: YOU ARE HERE!
}

function run_user_provisioning()
{
    $USER_PROV_ENV_VAR_BASE = "USER_PROV";
    $USER_PROV_YAML_DIR = getenv("USER_PROV_YAML_DIR");
    $USER_PROV_OVERWRITE_EXISTING = getenv("USER_PROV_OVERWRITE_EXISTING");
}

if ($ENABLE_USER_PROV) {
    run_user_provisioning();
}
