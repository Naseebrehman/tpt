<?php
/**
 * Offline smoke test for one admin screen: stubs the DB layer (the db.php
 * helpers are function_exists-guarded so this works) and renders the page
 * with a fake logged-in admin, checking expected UI markers.
 * Run: php tests/admin-smoke.php <settings|index|payments|...>
 */
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
@session_save_path(dirname(__DIR__) . '/storage/runtime');
session_start();
define('DB_OK', true); /* keep the real connect attempt out of the way */

$GLOBALS['fake_settings'] = array();
$GLOBALS['fake_admin'] = array('id' => 1, 'name' => 'Smoke Admin', 'email' => 'admin@example.test', 'role' => 'admin');
$GLOBALS['fake_templates'] = array();
$GLOBALS['fake_recipients'] = array();

function dbAll($sql, $params = array())
{
    if (strpos($sql, 'FROM settings') !== false) {
        $rows = array();
        foreach ($GLOBALS['fake_settings'] as $k => $v) { $rows[] = array('setting_key' => $k, 'setting_value' => $v); }
        return $rows;
    }
    if (strpos($sql, 'FROM notification_emails') !== false) { return array_values($GLOBALS['fake_recipients']); }
    return array();
}
function dbOne($sql, $params = array())
{
    if (strpos($sql, 'FROM admin_users') !== false) { return $GLOBALS['fake_admin']; }
    if (strpos($sql, 'FROM settings') !== false) {
        $key = $params[0] ?? '';
        return isset($GLOBALS['fake_settings'][$key]) ? array('setting_key' => $key, 'setting_value' => $GLOBALS['fake_settings'][$key]) : null;
    }
    return null;
}
function dbExec($sql, $params = array()) { return 1; }
function dbInsert($sql, $params = array()) { return 1; }

require dirname(__DIR__) . '/includes/init.php';
$_SESSION['admin_id'] = 1;
$_SESSION['admin_email'] = 'admin@example.test';

$expectations = array(
    'settings'     => array('Payments', 'Send Test Email', 'Email Templates', 'Notification recipients', 'AI / Alia', 'CAPTCHA', 'Active provider'),
    'index'        => array('Dashboard'),
    'payments'     => array('PayPal Client ID', 'Save', 'Payment form services'),
    'submissions'  => array('Contact Submissions'),
    'leads'        => array('Lead'),
    'blog'         => array('Blog Manager'),
    'resources'    => array('Resources Manager'),
    'testimonials' => array('Testimonials Manager'),
    'portfolio'    => array('Portfolio Manager'),
    'team'         => array('Team Manager'),
    'subscribers'  => array('Newsletter Subscribers'),
    'admins'       => array('Admin Management'),
    'media'        => array('Media Library'),
    'chats'        => array('Alia'),
    'content'      => array('Content'),
);

$smokeArgs = isset($argv[1]) ? (string) $argv[1] : (string) getenv('TPT_HARNESS_ARGS');
$smokePage = trim($smokeArgs) !== '' ? preg_replace('/[^a-z_-]/', '', trim($smokeArgs)) : 'settings';
if (!isset($expectations[$smokePage])) {
    fwrite(STDOUT, "FAIL unknown admin page '$smokePage'\n");
    exit(1);
}
$_GET = array(); $_POST = array(); $_REQUEST = array();
ob_start();
require BASE_PATH . '/admin/' . $smokePage . '.php';
$html = ob_get_clean();

if (strlen($html) <= 2000) {
    fwrite(STDOUT, "FAIL admin/$smokePage.php rendered only " . strlen($html) . " bytes\n");
    exit(1);
}
foreach ($expectations[$smokePage] as $needle) {
    if (strpos($html, $needle) === false) {
        fwrite(STDOUT, "FAIL admin/$smokePage missing “{$needle}”\n");
        exit(1);
    }
}
/* No secrets ever leak into admin HTML. */
foreach (array('sk_live', 'sk_test_', 'paypal_secret', 'smtp password here') as $leak) {
    if (stripos($html, $leak) !== false) {
        fwrite(STDOUT, "FAIL admin/$smokePage leaks “{$leak}”\n");
        exit(1);
    }
}
fwrite(STDOUT, "PASS admin/$smokePage.php (" . strlen($html) . " bytes)\n");
