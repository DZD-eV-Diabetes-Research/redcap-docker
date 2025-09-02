<?php

# from php_helpers/redcap_info.php
require_once __DIR__ . '/redcap_info.php';

#$FILES_PROCESSED_STATE_FILE_PATH = get_redcap_source_path() . DIRECTORY_SEPARATOR . '.redcap_docker_files_processed_state';
define(constant_name: 'FILES_PROCESSED_STATE_FILE_PATH', value: get_redcap_source_path() . DIRECTORY_SEPARATOR . '.redcap_docker_files_processed_state');
/**
 * Check if a file was already processed by verifying its hash in a file named ".redcap_docker_files_processed_state" 
 *
 * @param string $filepath
 * @return bool true if already processed, false otherwise
 */
function file_was_processed(string $filepath, ?string $namespace = null): bool
{
    $hash = md5_file($filepath);
    if ($namespace) {
        $hash = $namespace . $hash;
    }
    if ($hash === false) {
        return false; // file not found or unreadable
    }

    if (!file_exists(FILES_PROCESSED_STATE_FILE_PATH)) {
        return false;
    }

    $lines = file(FILES_PROCESSED_STATE_FILE_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($hash, $lines, true);
}

/**
 * Mark a file as processed by adding its hash to a file named ".redcap_docker_files_processed_state" if not already present
 *
 * @param string $filepath
 * @return void
 */
function mark_file_as_processed(string $filepath, ?string $namespace = null): void
{
    $hash = md5_file($filepath);
    if ($hash === false) {
        return; // file not found or unreadable
    }
    if ($namespace) {
        $hash = $namespace . $hash;
    }

    // If file exists, check if already there
    if (file_exists(FILES_PROCESSED_STATE_FILE_PATH)) {
        $lines = file(FILES_PROCESSED_STATE_FILE_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (in_array($hash, $lines, true)) {
            return; // already marked
        }
    }

    // Append hash to the file
    file_put_contents(FILES_PROCESSED_STATE_FILE_PATH, $hash . PHP_EOL, FILE_APPEND | LOCK_EX);
}

