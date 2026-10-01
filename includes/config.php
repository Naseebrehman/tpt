<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Core configuration
 * ---------------------------------------------------------------------------
 *  Set deployment credentials in config.local.php (see the example file).
 *  Everything else in the site reads its configuration from the `settings`
 *  table in MySQL (see /admin/settings.php).
 * ---------------------------------------------------------------------------
 */

/*
 * Deployment overrides stay out of Git. On Hostinger, copy
 * includes/config.local.php.example to includes/config.local.php and enter
 * the database/domain values there. Environment variables are also supported.
 */
$pieLocalConfig = array();
$pieLocalConfigFile = is_file(dirname(__DIR__) . '/config/config.local.php')
    ? dirname(__DIR__) . '/config/config.local.php' : __DIR__ . '/config.local.php';
if (is_file($pieLocalConfigFile)) {
    $pieLoadedConfig = require $pieLocalConfigFile;
    if (is_array($pieLoadedConfig)) {
        $pieLocalConfig = $pieLoadedConfig;
    }
}
$pieConfigValue = function ($key, $environmentVariable, $default) use ($pieLocalConfig) {
    if (array_key_exists($key, $pieLocalConfig)) {
        return $pieLocalConfig[$key];
    }
    $environmentValue = getenv($environmentVariable);
    return $environmentValue !== false && $environmentValue !== '' ? $environmentValue : $default;
};

/* 'production' hides all PHP errors from visitors. Switch to 'development'
   only while debugging on a private server. */
define('APP_ENV', (string) $pieConfigValue('APP_ENV', 'TPT_APP_ENV', 'production'));

/* ------------------------- Database (MySQL) ------------------------------ */
define('DB_HOST', (string) $pieConfigValue('DB_HOST', 'TPT_DB_HOST', 'localhost'));
define('DB_NAME', (string) $pieConfigValue('DB_NAME', 'TPT_DB_NAME', 'your_database'));
define('DB_USER', (string) $pieConfigValue('DB_USER', 'TPT_DB_USER', 'your_username'));
define('DB_PASS', (string) $pieConfigValue('DB_PASS', 'TPT_DB_PASS', 'your_password'));

/* ------------------------- Site ------------------------------------------ */
define('SITE_URL', rtrim((string) $pieConfigValue('SITE_URL', 'TPT_SITE_URL', 'https://thepietechnologies.com'), '/'));
define('SITE_NAME', (string) $pieConfigValue('SITE_NAME', 'TPT_SITE_NAME', 'The Pie Technologies'));
define('ADMIN_EMAIL', (string) $pieConfigValue('ADMIN_EMAIL', 'TPT_ADMIN_EMAIL', 'admin@thepietechnologies.com'));

/* ------------------------- Paths ----------------------------------------- */
define('BASE_PATH', dirname(__DIR__));                   // absolute filesystem path of the site root
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('VENDOR_PATH', BASE_PATH . '/vendor/');           // optional: composer (PHPMailer) lives here

/* When true, links are generated without the .php extension (requires the
   bundled .htaccess / mod_rewrite). Set to false on servers without rewrite
   support and every link falls back to plain .php URLs. */
$piePrettyUrls = $pieConfigValue('PRETTY_URLS', 'TPT_PRETTY_URLS', true);
if (is_string($piePrettyUrls)) {
    $piePrettyUrls = filter_var($piePrettyUrls, FILTER_VALIDATE_BOOLEAN);
}
define('PRETTY_URLS', (bool) $piePrettyUrls);

/* Base URL of the install, detected automatically so the site also works in
   a sub-folder (e.g. https://host/~user/tpt/). */
if (!defined('BASE_URL')) {
    $pieScript = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/index.php';
    $pieDir    = trim(dirname($pieScript), '/');
    $pieTop    = $pieDir === '' ? '' : explode('/', $pieDir)[0];
    /* known top-level folders of this project are not install roots */
    if (in_array($pieTop, array('services', 'admin', 'portfolio', 'includes', 'assets', 'uploads', 'api'), true)) {
        $pieDir = '';
    }
    define('BASE_URL', $pieDir === '' ? '' : '/' . $pieDir);
}
