<?php
/**
 * Dependency-free tests for the server-side PayPal flow (Task: PayPal secrets
 * and confirmation).
 *
 *   • the Secret lives in settings and is readable only on the server
 *   • server-side verification needs BOTH a Client ID and a Secret
 *   • amounts are normalised and bounded before they reach PayPal
 *   • a payment is only "confirmed" when PayPal reports COMPLETED
 *   • malformed order ids never reach the network
 *   • the browser-facing configuration carries no Secret
 *   • the public endpoint requires POST + CSRF, and its responses never leak
 *     the Secret
 *   • the endpoint is routed (and the removed legacy payment API stays gone)
 *
 * Run: php tests/paypal-server.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', ''); define('SITE_URL', 'https://example.test');
define('SITE_NAME', 'The Pie Technologies'); define('ADMIN_EMAIL', 'admin@example.test');
define('PRETTY_URLS', true); define('DB_OK', true); define('UPLOAD_PATH', BASE_PATH . '/uploads/');

$GLOBALS['fake_settings'] = array('paypal_env' => 'live');
$GLOBALS['fake_services'] = array(array('id' => 1, 'name' => 'AI Optimization', 'sort_order' => 1, 'is_active' => 1));
$GLOBALS['fake_tables'] = array();

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
    if (strpos($sql, 'information_schema.TABLES') !== false) {
        $table = isset($params[0]) ? (string) $params[0] : '';
        if ($table === '' && preg_match("/TABLE_NAME = '([a-z_]+)'/i", $sql, $m)) { $table = $m[1]; }
        return array('c' => !empty($GLOBALS['fake_tables'][$table]) ? 1 : 0);
    }
    if (strpos($sql, 'FROM settings') !== false) {
        $key = $params[0] ?? '';
        return isset($GLOBALS['fake_settings'][$key])
            ? array('setting_key' => $key, 'setting_value' => $GLOBALS['fake_settings'][$key]) : null;
    }
    return null;
}
function dbExec($sql, $params = array()) { return 1; }
function dbInsert($sql, $params = array()) { return 7; }

require BASE_PATH . '/includes/functions.php';
require BASE_PATH . '/core/Payments.php';

$count = 0;
function check($condition, $message) { global $count; $count++; if (!$condition) { throw new RuntimeException('FAIL: ' . $message); } echo 'PASS: ' . $message . PHP_EOL; }
function setFakeSetting($key, $value) { $GLOBALS['fake_settings'][$key] = $value; settingsCache(true); }
function source($relativePath) { return (string) file_get_contents(BASE_PATH . '/' . $relativePath); }
function resetPaypalSettings() { $GLOBALS['fake_settings'] = array('paypal_env' => 'live'); settingsCache(true); }

/* ------------------------- the secret stays server-side -------------------- */
resetPaypalSettings();
check(piePayPalSecret() === '', 'no Secret before one is saved');
check(piePayPalSecretConfigured() === false, 'an empty Secret is not "configured"');
setFakeSetting('paypal_client_id', 'AxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxX');
check(piePayPalServerReady() === false, 'a Client ID alone does not enable server-side capture');
setFakeSetting('paypal_secret', 'E' . str_repeat('x', 60));
check(piePayPalSecretConfigured() === true, 'the stored Secret is detected');
check(piePayPalServerReady() === true, 'Client ID + Secret enable server-side capture');

$config = piePayPalClientConfig();
check(!isset($config['secret']) && !isset($config['paypalSecret']), 'the browser-facing config has no Secret key');
check(strpos(json_encode($config), 'E' . str_repeat('x', 60)) === false, 'the browser-facing config never contains the Secret value');
check($config['serverVerification'] === true && $config['currency'] === 'USD', 'the browser is told that USD payments are server-verified');
check(strpos($config['endpoint'], 'paypal-api') !== false, 'the browser posts to the server endpoint');
check(piePayPalApiBase() === 'https://api-m.paypal.com', 'live mode talks to the live PayPal API');
setFakeSetting('paypal_env', 'sandbox');
check(piePayPalApiBase() === 'https://api-m.sandbox.paypal.com', 'sandbox mode talks to the sandbox PayPal API');
setFakeSetting('paypal_env', 'nonsense');
check(piePayPalEnv() === 'live', 'an unknown environment falls back to live');
check(pieIsValidPayPalSecret('short') === false, 'a Secret that is too short is rejected');
check(pieIsValidPayPalSecret('E' . str_repeat('A', 20)) === true, 'a realistic Secret is accepted');

/* ------------------------------ amount handling ---------------------------- */
check(piePayPalAmount('100') === '100.00', 'whole amounts get two decimals');
check(piePayPalAmount('99.999') === '100.00', 'amounts are rounded to cents');
check(piePayPalAmount('0') === '' && piePayPalAmount('-5') === '' && piePayPalAmount('') === '', 'zero and negative amounts are rejected');
check(piePayPalAmount('abc') === '' && piePayPalAmount('1e12') === '', 'junk and absurd amounts are rejected');

/* --------------------------- confirmation is verified ---------------------- */
$completed = array(
    'id' => 'ORDER123',
    'payer' => array('name' => array('given_name' => 'Ada', 'surname' => 'Lovelace')),
    'purchase_units' => array(array('payments' => array('captures' => array(array(
        'id' => 'CAPTURE123', 'status' => 'COMPLETED',
        'amount' => array('value' => '250.00', 'currency_code' => 'USD'),
    ))))),
);
$confirmation = piePayPalConfirmation($completed);
check($confirmation['confirmed'] === true && $confirmation['amount'] === '250.00' && $confirmation['reference'] === 'CAPTURE123', 'a COMPLETED capture is confirmed with its real amount and reference');
check($confirmation['name'] === 'Ada Lovelace', 'the payer name comes from PayPal');
check(piePayPalConfirmation(array('status' => 'PENDING'))['confirmed'] === false, 'a PENDING order is never confirmed');
check(piePayPalConfirmation(array())['confirmed'] === false, 'an empty response is never confirmed');
$noAmount = $completed; $noAmount['purchase_units'][0]['payments']['captures'][0]['amount']['value'] = '';
check(piePayPalConfirmation($noAmount)['confirmed'] === false, 'a capture without a usable amount is never confirmed');

/* ---------------------- malformed orders never hit the network ------------- */
foreach (array('', 'x', 'DROP TABLE payments', str_repeat('A', 60)) as $bad) {
    $result = piePayPalCaptureOrder($bad);
    check($result['ok'] === false && $result['error'] !== '', 'an invalid order id is rejected before any request: "' . substr($bad, 0, 12) . '"');
}
$create = piePayPalCreateOrder('not-a-number');
check($create['ok'] === false && strpos($create['error'], 'amount') !== false, 'an invalid amount is rejected before any request');

/* Nothing above may have started a network call: an OAuth attempt needs a
   reachable PayPal and would have taken seconds per call. */

/* --------------------------- audit trail is best effort ------------------- */
resetPaypalSettings();
check(piePayPalRecord($completed) === 0, 'a confirmed payment is not stored when the payments table is missing');
$GLOBALS['fake_tables']['payments'] = true;
$GLOBALS['fake_tables']['payment_events'] = true;
check(piePayPalRecord($completed) === 7, 'a confirmed payment is recorded in the existing payments table');
check(piePayPalRecord(array('status' => 'PENDING')) === 0, 'an unconfirmed payment is never recorded');

/* --------------------------- the endpoint is protected --------------------- */
$endpoint = source('paypal-api.php');
check(strpos($endpoint, "!== 'POST'") !== false, 'the endpoint refuses anything but POST');
check(strpos($endpoint, 'validateCSRF') !== false, 'the endpoint requires this session\'s CSRF token');
check(strpos($endpoint, 'piePayPalCaptureOrder') !== false, 'the endpoint captures through the server-side verification helper');
check(strpos($endpoint, 'piePayPalServerReady') !== false, 'the endpoint refuses to run without stored credentials');
check(strpos($endpoint, 'piePayPalRecord') !== false, 'confirmed captures are written to the payments table');
check(strpos($endpoint, 'echo json_encode') === 1 || substr_count($endpoint, 'echo json_encode') === 1, 'the endpoint has exactly one JSON output point');
check(substr_count($endpoint, '$_POST') <= 1, 'the endpoint does not read request fields into settings');
check(!preg_match('/echo[^;]*(secret|api_?key)/i', $endpoint), 'the endpoint never echoes a credential');

$routes = source('app/routes.php');
check(strpos($routes, "'/paypal-api'") !== false && strpos($routes, "'/api/paypal/capture-order'") !== false, 'the server-side PayPal endpoint is routed');
check(strpos($routes, "/api/payment") === false, 'the removed legacy payment API is still absent');
check(strpos(source('core/Payments.php'), 'piePayPalSecret') !== false && strpos(source('core/Payments.php'), 'SERVER-SIDE ONLY') !== false, 'Payments.php includes the server-side module and documents the Secret rule');

/* ------------------------- Alia answers with real facts ------------------- */
require BASE_PATH . '/includes/chatbot-api.php';
setFakeSetting('site_address', 'Collingswood, New Jersey, USA');
setFakeSetting('site_email', 'info@thepietechnologies.com');
setFakeSetting('site_phone', '');
setFakeSetting('paypal_enabled', '1');
setFakeSetting('stripe_enabled', '1');
setFakeSetting('terms_url', '');
$facts = aliaFactSheet();
check(strpos($facts, 'Collingswood, New Jersey, USA') !== false, 'Alia is told the real company location');
check(stripos($facts, 'Punjab') === false && stripos($facts, 'Pakistan') === false, 'Alia is never told an old location');
check(strpos($facts, 'info@thepietechnologies.com') !== false, 'Alia is told the real email address');
check(stripos($facts, 'phone: no public phone number') !== false, 'Alia says the phone is unpublished when none is configured');
check(strpos($facts, 'PayPal and Stripe') !== false, 'Alia knows both payment methods');
check(strpos($facts, 'Never invent') !== false || strpos($facts, 'never invent') !== false, 'Alia is told never to invent details');
check(strpos($facts, str_repeat('x', 60)) === false, 'Alia is never given the PayPal Secret');

echo "\n$count checks passed.\n";
