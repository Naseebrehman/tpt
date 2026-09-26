<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Core configuration
 * ---------------------------------------------------------------------------
 *  Edit the database credentials below after importing database.sql.
 *  Everything else in the site reads its configuration from the `settings`
 *  table in MySQL (see /admin/settings.php).
 * ---------------------------------------------------------------------------
 */

/* 'production' hides all PHP errors from visitors. Switch to 'development'
   only while debugging on a private server. */
define('APP_ENV', 'production');

/* ------------------------- Database (MySQL) ------------------------------ */
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

/* ------------------------- Site ------------------------------------------ */
define('SITE_URL', 'https://thepietechnologies.com');   // canonical domain (SEO / OG tags)
define('SITE_NAME', 'The Pie Technologies');
define('ADMIN_EMAIL', 'admin@thepietechnologies.com');

/* ------------------------- Paths ----------------------------------------- */
define('BASE_PATH', dirname(__DIR__));                   // absolute filesystem path of the site root
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('VENDOR_PATH', BASE_PATH . '/vendor/');           // optional: composer (PHPMailer) lives here

/* When true, links are generated without the .php extension (requires the
   bundled .htaccess / mod_rewrite). Set to false on servers without rewrite
   support and every link falls back to plain .php URLs. */
define('PRETTY_URLS', true);

/* Base URL of the install, detected automatically so the site also works in
   a sub-folder (e.g. https://host/~user/tpt/). */
if (!defined('BASE_URL')) {
    $pieScript = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/index.php';
    $pieDir    = trim(dirname($pieScript), '/');
    $pieTop    = $pieDir === '' ? '' : explode('/', $pieDir)[0];
    /* known top-level folders of this project are not install roots */
    if (in_array($pieTop, array('services', 'admin', 'portfolio', 'includes', 'assets', 'uploads'), true)) {
        $pieDir = '';
    }
    define('BASE_URL', $pieDir === '' ? '' : '/' . $pieDir);
}
