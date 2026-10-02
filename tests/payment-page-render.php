<?php
/** Render the real Pay Online page against a tiny in-memory fixture.
 * The only payment setting is the PayPal Client ID (a public value).
 * Run: node tests/harness/cli.mjs tests/payment-page-render.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
require dirname(__DIR__) . '/includes/config.php';
define('DB_OK', true);
$GLOBALS['fake_settings'] = array(
    'paypal_client_id' => 'Axxxxxxxxxxxxxxxxxxxxxxxx',
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

$GLOBALS['paymentRenderChecks'] = 0;
function paymentRenderCheck($condition, $message)
{
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    $GLOBALS['paymentRenderChecks']++;
    echo 'PASS: ' . $message . PHP_EOL;
}

function renderPaymentPage()
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/pay-online';
    $_SERVER['SCRIPT_NAME'] = '/pay-online.php';
    ob_start();
    require dirname(__DIR__) . '/pay-online.php';
    return (string) ob_get_clean();
}

$html = renderPaymentPage();
$styles = (string) file_get_contents(dirname(__DIR__) . '/assets/css/refinements.css');
$clientId = $GLOBALS['fake_settings']['paypal_client_id'];

paymentRenderCheck(strpos($html, 'id="payment-form"') !== false && strpos($html, 'id="tpt-payment-confirmation"') !== false, 'real public template renders the stable form and confirmation IDs');
paymentRenderCheck(strpos($html, 'id="paypal-button-container"') !== false, 'the single PayPal SDK button container renders');
paymentRenderCheck(strpos($html, 'https://www.paypal.com/sdk/js?client-id=' . $clientId . '&amp;currency=USD') !== false, 'the PayPal JavaScript SDK is loaded with the saved Client ID');
paymentRenderCheck(stripos($html, 'stripe') === false, 'the payment page contains no Stripe markup, script or option');
paymentRenderCheck(strpos($html, 'id="payment-name"') !== false && strpos($html, 'name="service"') !== false && strpos($html, 'name="amount"') !== false, 'the name, Services and amount fields render');
paymentRenderCheck(strpos($html, 'name="email"') === false && strpos($html, 'name="phone"') === false && strpos($html, 'name="notes"') === false, 'the email, phone and notes fields are removed');
preg_match('/<form id="payment-form".*?<\/form>/s', $html, $formBlock);
$formHtml = $formBlock ? $formBlock[0] : '';
paymentRenderCheck(strpos($formHtml, 'Terms &amp; Conditions') !== false
    && strpos($formHtml, 'name="amount"') < strpos($formHtml, 'Terms &amp; Conditions'), 'the Terms & Conditions link sits in the payment form below the amount');
paymentRenderCheck(strpos($html, 'Powered by PayPal') === false, 'the payment method panel has no duplicate PayPal/terms/security notes');
paymentRenderCheck(strpos($html, 'AI Optimization') !== false && strpos($html, 'Web Development') !== false, 'active Services from the existing system render in the form');
paymentRenderCheck(stripos($html, 'paypal_secret') === false && stripos($html, 'client_secret') === false && stripos($html, 'sk_live') === false && stripos($html, 'sk_test') === false, 'no PayPal secret, Stripe key or server credential appears in the HTML');
paymentRenderCheck($formHtml !== '' && stripos($formHtml, 'csrf') === false && stripos($formHtml, 'action=') === false && stripos($formHtml, 'paypal-api') === false, 'the payment form posts nowhere and needs no CSRF field');
paymentRenderCheck((bool) preg_match('/id="tpt-payment-confirmation"[^>]*\shidden/', $html) && strpos($styles, '.pay-confirmation[hidden]{display:none}') !== false, 'the confirmation notice stays hidden until PayPal approves the payment');
paymentRenderCheck(strpos($html, 'id="tpt-modal"') !== false && strpos($html, 'id="mRef"') !== false, 'the PayPal success popup markup renders');
paymentRenderCheck(strpos($styles, '.pay-overlay{position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(4,5,10,.9);backdrop-filter:blur(5px)}') !== false
    && strpos($styles, 'background:#17181f') !== false, 'the payment-success popup keeps the solid dark background');
paymentRenderCheck(strpos($html, 'refinements.css') !== false && strpos($html, 'pay-layout') !== false, 'rendered payment form uses the responsive checkout stylesheet/classes');

/* Empty and invalid fields must be pointed at exactly, never as a generic
   PayPal failure. The client script is asserted here; the behaviour itself is
   exercised against a stubbed PayPal SDK in development. */
paymentRenderCheck(strpos($html, 'Please fill the required field: Name / Business name.') !== false
    && strpos($html, 'Please fill the required field: Service.') !== false
    && strpos($html, 'Please fill the required field: Amount (USD).') !== false, 'each required field has its own "fill the required field" message');
paymentRenderCheck(strpos($html, 'onClick: function(data, actions){') !== false
    && strpos($html, "if (!details()) { return actions.reject(); }") !== false, 'the PayPal click is validated before any order is created');
paymentRenderCheck(strpos($html, "field.setAttribute('aria-invalid', 'true')") !== false
    && strpos($html, "node.className = 'pay-field-error'") !== false
    && strpos($html, "problems.forEach(function (problem) { setFieldError(problem.key, problem.message); });") !== false, 'every empty field is marked and its message printed under the input');
paymentRenderCheck(strpos($html, "['input', 'change'].forEach") !== false, 'editing a field clears its own error');
paymentRenderCheck(strpos($html, 'if (validationMessage) { fail(validationMessage); return; }') !== false, 'PayPal\'s error callback never overwrites a validation message');
paymentRenderCheck(strpos($html, "'PayPal reported: ' + detail") !== false, 'a real PayPal error quotes PayPal\'s own message');
paymentRenderCheck(strpos($html, "'Please enter a valid amount in USD (0.01 to 1,000,000.00).'") !== false, 'a zero amount is reported as an amount problem');
paymentRenderCheck(strpos($styles, '.pay-field-error{') !== false
    && strpos($styles, '.pay-field input[aria-invalid="true"]') !== false, 'the marked field and its message are styled');

/* Without a saved Client ID the page asks visitors to contact the team. */
$GLOBALS['fake_settings']['paypal_client_id'] = '';
settingsCache(true);
$noIdHtml = renderPaymentPage();
paymentRenderCheck(strpos($noIdHtml, 'Online payments are currently unavailable.') !== false, 'an empty Client ID shows the unavailable notice');
paymentRenderCheck(strpos($noIdHtml, 'paypal.com/sdk/js') === false && strpos($noIdHtml, 'id="paypal-button-container"') === false, 'no PayPal SDK or button is loaded without a Client ID');

echo PHP_EOL . $GLOBALS['paymentRenderChecks'] . ' payment page render checks passed.' . PHP_EOL;
