<?php
/**
 * Extracts a list of files from location uri like a local file/dir-path or remote http path.
 * If locatin is a zip it will be extracted.
 */

function get_files_from_location(string $location, string $only_with_extenstion = NULL): array {
    $tempDir = sys_get_temp_dir() . '/redcap_boot_' . uniqid();
    if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
        throw new RuntimeException("Failed to create temp dir: $tempDir");
    }

    // Determine if local or remote
    if (preg_match('/^https?:\/\//i', $location)) {
        // Remote: download
        $downloadPath = $tempDir . '/' . basename(parse_url($location, PHP_URL_PATH));
        $fileData = file_get_contents($location);

        if ($fileData === false) {
            throw new RuntimeException("Failed to download remote file: $location");
        }
        file_put_contents($downloadPath, $fileData);

        $path = $downloadPath;
    } else {
        // Local path
        $path = $location;
    }

    // If archive (zip only for now, extendable)
    $files = [];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if (in_array($ext, ['zip'])) {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $zip->extractTo($tempDir);
            $zip->close();
            $path = $tempDir;
        } else {
            throw new RuntimeException("Failed to extract archive: $path");
        }
    }

    // Collect files
    if (is_dir($path)) {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($rii as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
    } elseif (is_file($path)) {
        $files[] = $path;
    } else {
        throw new RuntimeException("Path is not a file or directory: $path");
    }

    // Apply extension filter if provided
    if ($only_with_extension !== null) {
        $only_with_extension = strtolower(ltrim($only_with_extension, '.')); // normalize
        $files = array_filter($files, function ($f) use ($only_with_extension) {
            return strtolower(pathinfo($f, PATHINFO_EXTENSION)) === $only_with_extension;
        });
        $files = array_values($files); // reindex
    }

    return $files;
}