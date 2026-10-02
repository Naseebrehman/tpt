<?php
/** Offline integration check for the project popup through the shared ContactController. */
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '');
define('SITE_URL', 'https://example.test');
define('SITE_NAME', 'The Pie Technologies');
define('ADMIN_EMAIL', 'admin@example.test');
define('PRETTY_URLS', true);
define('DB_OK', true);
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
$GLOBALS['fake_contact_settings'] = array();
$GLOBALS['fake_contact_record'] = array();
$GLOBALS['fake_contact_emails'] = array();
$GLOBALS['fake_contact_deliveries'] = array();

function dbAll($sql, $params = array())
{
    if (strpos($sql, 'FROM settings') !== false) {
        $rows = array();
        foreach ($GLOBALS['fake_contact_settings'] as $key => $value) { $rows[] = array('setting_key' => $key, 'setting_value' => $value); }
        return $rows;
    }
    if (strpos($sql, 'FROM notification_emails') !== false) { return array(); }
    return array();
}
function dbOne($sql, $params = array())
{
    if (strpos($sql, 'information_schema.TABLES') !== false) { return array('c' => 1); }
    if (strpos($sql, 'FROM email_templates') !== false || strpos($sql, 'FROM contact_submissions') !== false) { return null; }
    return null;
}
function dbExec($sql, $params = array()) { return 1; }
function dbInsert($sql, $params = array())
{
    if (strpos($sql, 'INSERT INTO contact_submissions') !== false) { $GLOBALS['fake_contact_record'] = $params; return 41; }
    return 1;
}
require BASE_PATH . '/includes/functions.php';
require BASE_PATH . '/includes/data.php';
$GLOBALS['fake_contact_settings'] = array(
    'captcha_enabled' => '1',
    'captcha_provider' => 'turnstile',
    'captcha_site_key' => '0x4AAAAAA_popup_site_key',
    'captcha_secret_key' => '0x4AAAAAA_popup_secret_key',
);
settingsCache(true);
ob_start();
require BASE_PATH . '/includes/quick-contact.php';
$popupMarkup = (string) ob_get_clean();
$GLOBALS['popup_captcha_rendered'] = class_exists('Captcha')
    && strpos($popupMarkup, 'cf-turnstile') !== false
    && strpos($popupMarkup, '0x4AAAAAA_popup_site_key') !== false
    && strpos($popupMarkup, 'tptRenderTurnstiles') !== false;
$GLOBALS['fake_contact_settings'] = array();
settingsCache(true);
require_once BASE_PATH . '/core/Captcha.php';
require BASE_PATH . '/core/Notifications.php';
require BASE_PATH . '/includes/email-templates.php';
require BASE_PATH . '/app/Models/Repository.php';
require BASE_PATH . '/app/Controllers/ContactController.php';

$_SESSION['csrf_token'] = str_repeat('a', 64);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['HTTP_USER_AGENT'] = 'TPT offline contact test';
$_POST = array(
    'csrf_token' => $_SESSION['csrf_token'],
    'contact_submit' => '1',
    'contact_variant' => 'project_popup',
    'name' => 'Test Business',
    'email' => 'customer@example.test',
    'phone' => '+1 555 123 4567',
    'message' => 'Please contact me about a new business website.',
    /* Service and source are intentionally omitted: these are not popup fields. */
);
Notifications::$onDeliver = function ($to, $subject) { $GLOBALS['fake_contact_deliveries'][] = array($to, $subject); };
register_shutdown_function(function () {
    $row = $GLOBALS['fake_contact_record'];
    $saved = count($row) === 10 && ($row[0] ?? '') === 'Test Business' && ($row[1] ?? '') === 'customer@example.test'
        && ($row[4] ?? '') === '' && ($row[7] ?? '') === 'project_popup';
    $emails = count($GLOBALS['fake_contact_deliveries']) === 2;
    $passed = !empty($GLOBALS['popup_captcha_rendered']) && $saved && $emails;
    echo "\nContact popup shared-controller integration: " . ($passed ? 'PASS' : 'FAIL') . PHP_EOL;
    echo '  Popup renders Captcha widget & script without caller preloading Captcha.php: ' . (!empty($GLOBALS['popup_captcha_rendered']) ? 'YES' : 'NO') . PHP_EOL;
    echo '  Admin/customer notification delivery attempts (SMTP intentionally unconfigured): ' . count($GLOBALS['fake_contact_deliveries']) . PHP_EOL;
    if (!$passed) { exit(1); }
});

/* The configured Contact Us source options intentionally exclude project_popup. */
ContactController::handle(array('SEO', 'Web Development'), array('Under $1,000'), array('Website', 'Referral'));
