<?php
/**
 * Extracts a list of files from location uri like a local file/dir-path or remote http path.
 * If location is a zip it will be extracted.
 */
function get_files_from_location(string $location, ?string $only_with_extension = null): array
{
    $tempDir = sys_get_temp_dir() . '/redcap_boot_' . uniqid();
    if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
        printf("Failed to create temp dir: $tempDir\n");
        throw new RuntimeException("Failed to create temp dir: $tempDir");
    }

    // Determine if local or remote


    if (preg_match('/^https?:\/\//i', $location)) {
        printf("Location `$location` is remote. Start downloading...\n");

        $ch = curl_init($location);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $headerData = curl_exec($ch);
        if ($headerData === false) {
            $error = curl_error($ch);
            curl_close($ch);
            printf("Failed to fetch headers: $location ($error)\n");
            throw new RuntimeException("Failed to fetch headers: $location ($error)");
        }
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        // Try to extract filename from headers
        $filename = null;
        if (preg_match('/Content-Disposition:.*filename="?([^"]+)"?/i', $headerData, $matches)) {
            $filename = $matches[1];
        }

        // Fallback to basename of final effective URL
        if ($filename === null) {
            $basename = basename(parse_url($effectiveUrl, PHP_URL_PATH));
            $filename = $basename !== '' ? $basename : 'downloaded_file';
        }

        $downloadPath = $tempDir . '/' . $filename;

        // Now actually download
        $ch = curl_init($location);
        $fp = fopen($downloadPath, 'w');
        if (!$fp) {
            throw new RuntimeException("Failed to create file: $downloadPath");
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        if (!curl_exec($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            throw new RuntimeException("Failed to download remote file: $location ($error)");
        }

        curl_close($ch);
        fclose($fp);

        $path = $downloadPath;
    } else {
        // Local path
        $path = $location;
    }

    // If archive (zip only for now, extendable)
    $files = [];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if (in_array($ext, ['zip'])) {
        printf("Location `$path` is zipped. Start extracting...\n");
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
    // Sort file names
    usort($files, function ($a, $b) {
        return strnatcmp(basename($a), basename($b));
    });
    return $files;
}


