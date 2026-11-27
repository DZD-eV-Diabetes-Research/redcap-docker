<?php

/**
 * Compare the REDCap database structure with what the installed version expects.
 *
 * Run as a separate process (REDCap's bootstrap defines globals and constants):
 *   php -d display_errors=stderr -f redcap_table_check.php [-- --fix]
 *
 * Prints the SQL fixes REDCap's Control Center would suggest
 * (SQLTableCheck::build_table_fixes()) between two marker lines:
 *
 *   ===TABLE_CHECK_BEGIN===
 *   <fix SQL, empty when the structure is correct>
 *   ===TABLE_CHECK_END===
 *
 * With --fix and differences found, it then runs REDCap's Auto-Fix
 * (Upgrade::autoFixTables(), the Control Center's "Auto-Fix" button) and
 * prints the result and what is left:
 *
 *   ===AUTOFIX_RESULT=fixed|failed|nothing===
 *   ===TABLE_CHECK_AFTER_BEGIN===
 *   <remaining fix SQL>
 *   ===TABLE_CHECK_AFTER_END===
 *
 * Missing markers mean the check could not run (e.g. REDCap is offline: its
 * bootstrap then prints the offline message and exits). The caller decides
 * what to do with that.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/redcap_info.php';

$REDCAPDIR = get_installed_redcap_version_dir_path();
// Same CLI bootstrap as redcap_module_enabler.php and REDCap's cron.php.
defined('NOAUTH') or define('NOAUTH', true);
defined('CRON') or define('CRON', true);
defined('USERID') or define('USERID', 'SYSTEM');
require_once $REDCAPDIR . '/Config/init_global.php';

$fix   = in_array('--fix', array_slice($argv, 1), true);
$fixes = trim((string) (new SQLTableCheck())->build_table_fixes());

$autofix = null;
$after   = null;
if ($fix && $fixes !== '') {
    // true: fixed, null: nothing to do, false: no ALTER privileges or it failed.
    $result  = Upgrade::autoFixTables();
    $autofix = $result === true ? 'fixed' : ($result === null ? 'nothing' : 'failed');
    $after   = trim((string) (new SQLTableCheck())->build_table_fixes());
}

// System::init() buffers output. Flush whatever the bootstrap printed first so
// the markers stay together.
while (ob_get_level() > 0) {
    ob_end_flush();
}
echo "\n===TABLE_CHECK_BEGIN===\n";
if ($fixes !== '') {
    echo $fixes, "\n";
}
echo "===TABLE_CHECK_END===\n";
if ($autofix !== null) {
    echo "===AUTOFIX_RESULT=$autofix===\n";
    echo "===TABLE_CHECK_AFTER_BEGIN===\n";
    if ($after !== '') {
        echo $after, "\n";
    }
    echo "===TABLE_CHECK_AFTER_END===\n";
}
exit(0);
