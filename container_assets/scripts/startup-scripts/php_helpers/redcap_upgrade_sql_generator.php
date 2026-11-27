<?php

/**
 * Print the SQL that upgrades the REDCap database to the version in <version_dir>.
 *
 * Run as a separate process (REDCap's bootstrap defines globals and constants):
 *   php -d display_errors=stderr -f redcap_upgrade_sql_generator.php -- <version_dir>
 *
 * <version_dir> must already sit in the REDCap webroot (next to database.php),
 * e.g. /var/www/html/redcap_v16.0.49. The "from" version is whatever
 * redcap_config.redcap_version currently says.
 *
 * This runs REDCap's own upgrade page in its "download SQL" mode
 * (redcap_vX.Y.Z/upgrade.php?sql=1), which prints Upgrade::getUpgradeSql():
 * every Resources/sql/upgrade_*.sql and upgrade_*.php file in REDCap's order,
 * the redcap_last_install_date / redcap_history_version bookkeeping and the
 * final redcap_version update. We do not reimplement any of that.
 *
 * Why upgrade.php and not Config/init_global.php: init_global.php stops with the
 * offline message when REDCap is in offline mode (as it is during an upgrade),
 * and it runs authentication, hooks and module code. upgrade.php is built to run
 * against a database that is still at the old version and does none of that.
 *
 * The caller must check the output: REDCap prints errors (e.g. a failed DB
 * connection) as HTML on stdout and still exits 0.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$version_dir = rtrim($argv[1] ?? '', '/');
if ($version_dir === '' || !is_file("$version_dir/upgrade.php")) {
    fwrite(STDERR, "Usage: php -f " . basename(__FILE__) . " -- <redcap_vX.Y.Z dir in the webroot>\n");
    exit(2);
}
if (!is_file(dirname($version_dir) . '/database.php')) {
    fwrite(STDERR, "No database.php next to '$version_dir'. The version directory must be installed in the REDCap webroot.\n");
    exit(2);
}

// Make upgrade.php believe it was requested as /redcap_vX.Y.Z/upgrade.php?sql=1.
$self = '/' . basename($version_dir) . '/upgrade.php';
$_GET['sql']               = '1';
$_SERVER['PHP_SELF']       = $self;
$_SERVER['SCRIPT_NAME']    = $self;
$_SERVER['REQUEST_URI']    = "$self?sql=1";
$_SERVER['QUERY_STRING']   = 'sql=1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_NAME']    = 'localhost';
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['SERVER_PORT']    = '80';
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';

chdir($version_dir);
require $version_dir . '/upgrade.php';

// upgrade.php exits right after printing the SQL. Getting here means it did not.
fwrite(STDERR, "REDCap's upgrade.php returned without printing the upgrade SQL.\n");
exit(3);
