<?php
/** Render the real Pay Online page against a tiny in-memory fixture.
 * No credentials leave this process and no provider requests are made.
 * Run: node tests/harness/cli.mjs tests/payment-page-render.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
require dirname(__DIR__) . '/includes/config.php';
define('DB_OK', true);
$GLOBALS['fake_settings'] = array(
    'paypal_enabled' => '1',
    'paypal_client_id' => 'Axxxxxxxxxxxxxxxxxxxxxxxx',
    'paypal_secret' => 'PayPalSandboxSecret-0123456789abcdef',
    'paypal_env' => 'sandbox',
    'stripe_enabled' => '1',
    'stripe_secret_key' => 'sk_test_' . str_repeat('A1b2C3d4', 4),
    'terms_url' => 'https://example.test/terms',
    'site_email' => 'payments@example.test',
);
$GLOBALS['fake_services'] = array(
    array('id' => 1, 'name' => 'AI Optimization', 'is_active' => 1, 'sort_order' => 1),
    array('id' => 2, 'name' => 'Web Development', 'is_active' => 1, 'sort_order' => 2),
);
function dbAll($sql, $params = array())
{
    if (strpos($sql, 'FROM settings') !== false) {
        $rows = array();
        foreach ($GLOBALS['fake_settings'] as $key => $value) { $rows[] = array('setting_key' => $key, 'setting_value' => $value); }
        return $rows;
    }
    if (strpos($sql, 'FROM payment_services') !== false) { return $GLOBALS['fake_services']; }
    return array();
}
function dbOne($sql, $params = array())
{
    if (strpos($sql, 'information_schema.TABLES') !== false) { return array('c' => 1); }
    return null;
}
function dbExec($sql, $params = array()) { return 1; }
function dbInsert($sql, $params = array()) { return 1; }

function paymentRenderCheck($condition, $message)
{
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . PHP_EOL;
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/pay-online';
$_SERVER['SCRIPT_NAME'] = '/pay-online.php';
ob_start();
require dirname(__DIR__) . '/pay-online.php';
$html = (string) ob_get_clean();
$styles = (string) file_get_contents(dirname(__DIR__) . '/assets/css/refinements.css');

paymentRenderCheck(strpos($html, 'id="payment-form"') !== false && strpos($html, 'id="tpt-payment-confirmation"') !== false, 'real public template renders the stable form and confirmation IDs');
paymentRenderCheck(strpos($html, 'Continue with PayPal') !== false && strpos($html, 'Pay by card') !== false, 'both separate gateway buttons render when both providers are configured');
paymentRenderCheck(strpos($html, 'name="phone"') !== false && strpos($html, 'name="service"') !== false && strpos($html, 'name="amount"') !== false, 'the existing customer, Services and amount fields render');
paymentRenderCheck(strpos($html, 'AI Optimization') !== false && strpos($html, 'Web Development') !== false, 'active Services from the existing system render in the form');
paymentRenderCheck(strpos($html, 'https://example.test/terms') !== false, 'the shared Terms URL reaches the public page');
paymentRenderCheck(strpos($html, 'Axxxxxxxxxxxxxxxxxxxxxxxx') === false && strpos($html, 'PayPalSandboxSecret-0123456789abcdef') === false && strpos($html, 'sk_test_' . str_repeat('A1b2C3d4', 4)) === false, 'PayPal Client ID and PayPal/Stripe test secrets never appear in rendered HTML');
paymentRenderCheck(stripos($html, 'paypal.com/sdk') === false && stripos($html, 'stripe-js') === false && stripos($html, 'client_secret') === false, 'rendered page loads no provider SDK or client secret');
paymentRenderCheck(strpos($html, 'refinements.css') !== false && strpos($html, 'pay-layout') !== false, 'rendered payment form uses the responsive checkout stylesheet/classes');
paymentRenderCheck((bool) preg_match('/id="tpt-payment-confirmation"[^>]*\shidden/', $html) && strpos($styles, '.pay-confirmation[hidden]{display:none}') !== false, 'the confirmation notice remains hidden until a server-set result is available');

echo "\n9 payment page render checks passed.\n";
