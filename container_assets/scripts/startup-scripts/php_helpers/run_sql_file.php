<?php

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