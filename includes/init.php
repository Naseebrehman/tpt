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
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
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
    $isAdminArea = strpos(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/admin') === 0 || strpos($script, '/admin/') !== false;
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
        $viewPath = parse_url($_SERVER['REQUEST_URI'] ?? $script, PHP_URL_PATH);
        if (strpos($viewPath, '/api/') === false && strpos($viewPath, '/admin/') === false) { logPageView($viewPath); }
    }
}

/* Bound public mutations on both compatibility and new API entry points.
   Payment webhooks are exempt: they authenticate with their provider's
   signature (Stripe-Signature) instead of a session, and a delivery that is
   rate-limited away would mean a confirmed payment never reaches the
   dashboard. The signature check is the gate for those endpoints. */
require_once BASE_PATH . '/core/Ratelimit.php';
$pieRequestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$pieIsPaymentWebhook = strpos($pieRequestPath, 'stripe-webhook') !== false
    || strpos($pieRequestPath, '/api/stripe/webhook') !== false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !isAdminLoggedIn() && !$pieIsPaymentWebhook) {
    foreach ($_POST as $fieldValue) {
        if (!is_scalar($fieldValue)) { http_response_code(422); header('Content-Type: application/json'); echo json_encode(array('success' => false, 'message' => 'Invalid form field.')); exit; }
    }
    if (!Ratelimit::allow('post:' . pieClientIp(), 60, 600)) {
        http_response_code(429);
        header('Retry-After: 600');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success' => false, 'message' => 'Too many requests. Please try again later.'));
        exit;
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) {
        http_response_code(413); exit('Request too large.');
    }
}
