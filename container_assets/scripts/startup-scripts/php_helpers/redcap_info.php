<?php

//IMPORTS
# import get_db_con() from php_helpers/db.php
require_once __DIR__ . '/db.php';


function get_redcap_source_path()
{
    return getenv('APACHE_DOCUMENT_ROOT');
}

function get_existent_redcap_version_dirs(?string $base_path = null)
{
    if ($base_path == null) {
        $base_path = get_redcap_source_path();
    }

    $version_dirs = [];

    // Ensure the base path is a directory
    if (is_dir($base_path)) {
        // Open the directory
        if ($handle = opendir($base_path)) {
            // Read each entry in the directory
            while (false !== ($entry = readdir($handle))) {
                // Check if the entry is a directory and matches the pattern
                if (is_dir($base_path . DIRECTORY_SEPARATOR . $entry) && preg_match('/^redcap_v(\d+\.\d+\.\d+)$/', $entry, $matches)) {
                    // Extract the version number
                    $version = $matches[1];
                    // Store the absolute path with the version number as the key
                    $version_dirs[$version] = realpath($base_path . DIRECTORY_SEPARATOR . $entry);
                }
            }
            // Close the directory handle
            closedir($handle);
        }
    }

    // Sort the array by version numbers in ascending order
    uksort($version_dirs, 'version_compare');

    return $version_dirs;
}
function get_highest_redcap_version_dir(?string $base_path = null)
{
    return end(get_existent_redcap_version_dirs($base_path));
}
function get_highest_redcap_version_no(?string $base_path = null)
{
    return array_key_last(get_existent_redcap_version_dirs($base_path));
}
function get_installed_redcap_version_no()
{
    $mysqli_con = get_db_con();
    $version_query = "SELECT `value` from redcap_config WHERE field_name = 'redcap_version';";
    $result = $mysqli_con->query($version_query)->fetch_column();
    $mysqli_con->close();
    return $result;
}
function get_installed_redcap_version_dir_path()
{
    $installed_version = get_installed_redcap_version_no();
    $dirname = "redcap_v$installed_version";
    $base_path = get_redcap_source_path();
    $full_path = "$base_path/$dirname";
    if (file_exists($full_path) && is_dir($full_path)) {
        return $full_path;
    }
    throw new Exception("Could not determine REDCap version path for version $installed_version. $full_path seems not to be an existing directory.");
}
