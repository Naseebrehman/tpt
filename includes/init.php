<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — bootstrap included at the top of every page
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/config.php';

/* Error reporting: off in production, on in development. */
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
ini_set('log_errors', '1');
date_default_timezone_set('UTC');

/* Session (secure cookie flags) */
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ));
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/data.php';

/* ---------------------------------------------------------------------------
   Maintenance mode — visitors see maintenance.php, admins pass through.
   --------------------------------------------------------------------------- */
if (getSetting('maintenance_mode', '0') === '1') {
    $script     = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $isAdminArea = strpos($script, '/admin/') !== false;
    $allowedIp  = getSetting('maintenance_ip', '');
    $clientIp   = pieClientIp();
    if (!$isAdminArea && !isAdminLoggedIn() && ($allowedIp === '' || $clientIp !== $allowedIp)) {
        http_response_code(503);
        require BASE_PATH . '/maintenance.php';
        exit;
    }
}

/* ---------------------------------------------------------------------------
   Lightweight page-view logging (GET HTML requests only).
   --------------------------------------------------------------------------- */
if (DB_OK && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    if (substr($script, -4) === '.php' && strpos($script, '/admin/') === false) {
        logPageView($script);
    }
}
