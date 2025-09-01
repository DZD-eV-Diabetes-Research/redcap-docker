<?php

# from php_helpers/redcap_info.php
require_once __DIR__ . '/redcap_info.php';

$files_processed_state_file_path = get_redcap_source_path() . DIRECTORY_SEPARATOR . '.redcap_docker_files_processed_state';

/**
 * Check if a file was already processed by verifying its hash in a file named ".redcap_docker_files_processed_state" 
 *
 * @param string $filepath
 * @return bool true if already processed, false otherwise
 */
function file_was_processed(string $filepath,string $namespace=NULL): bool {
    $hash = md5_file($filepath); 
    if ($namespace){
        $hash = $namespace . $hash
    }
    if ($hash === false) {
        return false; // file not found or unreadable
    }

    if (!file_exists($files_processed_state_file_path)) {
        return false;
    }

    $lines = file($files_processed_state_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($hash, $lines, true);
}

/**
 * Mark a file as processed by adding its hash to a file named ".redcap_docker_files_processed_state" if not already present
 *
 * @param string $filepath
 * @return void
 */
function mark_file_as_processed(string $filepath,string $namespace=NULL): void {
    $hash = md5_file($filepath);
    if ($hash === false) {
        return; // file not found or unreadable
    }
    if ($namespace){
        $hash = $namespace . $hash
    }

    // If file exists, check if already there
    if (file_exists($files_processed_state_file_path)) {
        $lines = file($files_processed_state_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (in_array($hash, $lines, true)) {
            return; // already marked
        }
    }

    // Append hash to the file
    file_put_contents($files_processed_state_file_path, $hash . PHP_EOL, FILE_APPEND | LOCK_EX);
}

