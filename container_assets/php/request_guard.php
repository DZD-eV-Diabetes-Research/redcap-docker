<?php
/**
 * redcap-docker request guard (loaded via auto_prepend_file, runs before REDCap on every web request)
 *
 * Virtual patch for CVE-2026-90817 — unauthenticated RCE via the public survey passthrough
 * (CVSS 9.8, CISA KEV). Fixed upstream in REDCap 16.0.49 LTS / 17.3.10 LTS / 17.4.4 STD.
 * UPGRADING REDCAP IS THE FIX. This guard only buys time and adds defence in depth.
 *
 * Root cause (surveys/index.php passthru block): the allow-list lookup runs on
 * urldecode($_GET['__passthru']), but the "index.php needs an allowed route" check compares the
 * undecoded value. A double-encoded "%2569ndex.php" therefore passes the lookup as "index.php",
 * skips the route check, and lets an anonymous survey visitor call any controller via ?route=.
 *
 * Rules (each one 403s and logs "[redcap-docker request-guard] BLOCKED ..." to the Apache error log):
 *   1. __passthru / route must be plain strings with no '%' left after PHP's own decoding.
 *      REDCap never generates such values; a '%' means double encoding.
 *   2. A passthru to index.php is only allowed with the two routes REDCap itself uses for it:
 *      PdfController:index and SendItController:download.
 *   3. Survey requests must not carry PHP stream wrappers (php://, phar://, ...) in any GET, POST or
 *      cookie value. This blocks the file-path/stream half of the published exploit chain.
 *
 * Disable with REDCAP_REQUEST_GUARD=false (not recommended).
 */

(static function (): void {
    if (PHP_SAPI === 'cli') {
        return;
    }
    $flag = getenv('REDCAP_REQUEST_GUARD');
    if ($flag !== false && preg_match('/^\s*(0|n|no|false|off)\s*$/i', $flag)) {
        return;
    }

    $block = static function (string $rule): void {
        error_log(sprintf(
            '[redcap-docker request-guard] BLOCKED (%s) %s %s from %s',
            $rule,
            $_SERVER['REQUEST_METHOD'] ?? '-',
            substr($_SERVER['REQUEST_URI'] ?? '-', 0, 2048),
            $_SERVER['REMOTE_ADDR'] ?? '-'
        ));
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden\n";
        exit;
    };

    // Decode until stable (max 5 rounds) so nested encodings can't hide a value.
    $fully_decode = static function (string $v): string {
        for ($i = 0; $i < 5; $i++) {
            $d = rawurldecode($v);
            if ($d === $v) {
                break;
            }
            $v = $d;
        }
        return $v;
    };

    // ── Rules 1 + 2: survey passthrough ──────────────────────────────────────────
    if (isset($_GET['__passthru'])) {
        $passthru = $_GET['__passthru'];
        $route    = $_GET['route'] ?? null;

        if (!is_string($passthru) || ($route !== null && !is_string($route))) {
            $block('CVE-2026-90817: non-string __passthru/route');
        }
        if (strpos($passthru, '%') !== false || ($route !== null && strpos($route, '%') !== false)) {
            $block('CVE-2026-90817: encoded __passthru/route');
        }

        $target = $fully_decode($passthru);
        if (strcasecmp(basename(str_replace('\\', '/', $target)), 'index.php') === 0) {
            $allowed_routes = ['PdfController:index', 'SendItController:download'];
            if ($route === null || !in_array($fully_decode($route), $allowed_routes, true)) {
                $block('CVE-2026-90817: passthru to index.php with disallowed route');
            }
        }
    }

    // ── Rule 3: no PHP stream wrappers in survey requests ───────────────────────
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $is_survey = isset($_GET['__passthru'])
        || (bool) preg_match('#/surveys/(index\.php)?$#i', $script);
    if ($is_survey) {
        $wrapper = '/\b(php|phar|expect|glob|zip|zlib|bzip2|rar|ogg|ssh2|data|compress\.zlib|compress\.bzip2)\s*:\/\//i';
        $scan = static function ($value, int $depth = 0) use (&$scan, $wrapper, $fully_decode, $block): void {
            if ($depth > 8) {
                return;
            }
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $scan((string) $k, $depth + 1);
                    $scan($v, $depth + 1);
                }
                return;
            }
            // Cheap pre-filter: a wrapper needs ':' (or '%' if encoded) to be present at all.
            if (is_string($value) && strpbrk($value, ':%') !== false
                && preg_match($wrapper, $fully_decode($value))) {
                $block('CVE-2026-90817: PHP stream wrapper in survey request');
            }
        };
        $scan($_GET);
        $scan($_POST);
        $scan($_COOKIE);
    }
})();
