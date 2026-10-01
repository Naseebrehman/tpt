<?php
/**
 * ---------------------------------------------------------------------------
 *  DEVELOPMENT ONLY — request entry point for the WASM preview server.
 * ---------------------------------------------------------------------------
 *  The preview server (tests/harness/server.mjs) routes every PHP request
 *  here, sets TPT_HARNESS_TARGET to the script Apache would have executed and
 *  pre-loads the mysqli data layer when TPT_HARNESS_DB=1. The target script
 *  then runs with exactly the same globals as it would on Apache/PHP-FPM.
 *
 *  Never used in production: /tests is blocked by .htaccess.
 */

$tptTarget = getenv('TPT_HARNESS_TARGET');
$tptRoot   = getenv('TPT_HARNESS_ROOT') ?: '/home/user/tpt';

/* The WASM runtime's default session directory does not exist; keep sessions
   and PHP error logs in a writable temp dir so pages render without notices. */
$tptTmp = getenv('TPT_HARNESS_TMP') ?: '/tmp/tpt-harness';
if (!is_dir($tptTmp)) { @mkdir($tptTmp . '/sessions', 0777, true); }
@ini_set('session.save_path', $tptTmp . '/sessions');
@ini_set('error_log', $tptTmp . '/php-error.log');

if ($tptTarget === false || $tptTarget === '' || !is_file($tptTarget)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Harness target not found.';
    return;
}

if (getenv('TPT_HARNESS_DB') === '1') {
    require __DIR__ . '/dev-db.php';
    /* Pre-load includes/db.php so the application's require_once is a no-op and
       $GLOBALS['pdo'] can stay pointed at the mysqli-backed shim, which lets
       Schema::ensure()/migrations run locally. */
    require $tptRoot . '/includes/db.php';
    $GLOBALS['pdo'] = new PieDevPdo();
} else {
    /* php-wasm has no PDO MySQL driver. Define the normal no-database contract
       before the application loads includes/db.php, so it degrades gracefully
       instead of trying to construct an unsupported PDO connection. */
    require_once $tptRoot . '/includes/config.php';
    if (!defined('DB_OK')) { define('DB_OK', false); }
    if (!function_exists('dbAll')) {
        function dbAll($sql, $params = array()) { return array(); }
    }
    if (!function_exists('dbOne')) {
        function dbOne($sql, $params = array()) { return null; }
    }
    if (!function_exists('dbExec')) {
        function dbExec($sql, $params = array()) { return -1; }
    }
    if (!function_exists('dbInsert')) {
        function dbInsert($sql, $params = array()) { return -1; }
    }
}

/* Apache runs the script with the document root as cwd; match that. */
chdir(dirname($tptTarget));
require $tptTarget;
