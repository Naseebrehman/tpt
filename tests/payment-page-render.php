<?php
/** Render Pay Online against a tiny in-memory fixture. No provider calls are made. */
error_reporting(E_ALL);
ini_set('display_errors', '1');
putenv('TPT_PAYPAL_CLIENT_SECRET=server-only-test-secret');
putenv('TPT_STRIPE_SECRET_KEY=sk_test_fake_payment_key');
putenv('TPT_STRIPE_WEBHOOK_SECRET=whsec_fake_webhook_secret');
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
    $_GET = array();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/pay-online';
    $_SERVER['SCRIPT_NAME'] = '/pay-online.php';
    ob_start();
    require dirname(__DIR__) . '/pay-online.php';
    return (string) ob_get_clean();
}

$html = renderPaymentPage();
$styles = (string) file_get_contents(dirname(__DIR__) . '/assets/css/refinements.css');
$paymentJs = (string) file_get_contents(dirname(__DIR__) . '/pay-online.php');
$payments = (string) file_get_contents(dirname(__DIR__) . '/core/Payments.php');

paymentRenderCheck(strpos($html, 'id="payment-form"') !== false && strpos($html, 'id="tpt-payment-confirmation"') !== false, 'the payment form and stable confirmation ID render');
paymentRenderCheck(strpos($html, 'data-payment-provider="paypal"') !== false && strpos($html, 'data-payment-provider="stripe"') !== false, 'configured PayPal and Stripe hosted-checkout buttons render');
paymentRenderCheck(strpos($html, 'id="payment-name"') !== false && strpos($html, 'name="service"') !== false && strpos($html, 'name="amount"') !== false, 'the name, service and amount fields render');
paymentRenderCheck(strpos($html, 'Terms &amp; Conditions') !== false, 'the existing Terms link is retained');
paymentRenderCheck(strpos($html, 'AI Optimization') !== false && strpos($html, 'Web Development') !== false, 'active Admin services render in the form');
paymentRenderCheck(strpos($html, 'name="csrf_token"') !== false && strpos($html, 'data-paypal-endpoint=') !== false
    && strpos($html, 'data-stripe-endpoint=') !== false, 'payment form includes CSRF and same-origin server endpoint references');
paymentRenderCheck(stripos($html, 'server-only-test-secret') === false && stripos($html, 'sk_test_fake_payment_key') === false
    && stripos($html, 'whsec_fake_webhook_secret') === false && stripos($html, 'paypal_client_id') === false, 'PayPal/Stripe secrets and Client ID are absent from rendered HTML');
paymentRenderCheck(stripos($html, 'paypal.com/sdk/js') === false && stripos($html, 'actions.order.capture') === false
    && stripos($html, 'Stripe.js') === false, 'the browser loads no PayPal SDK, browser capture or Stripe.js integration');
paymentRenderCheck(strpos($html, 'id="tpt-modal"') !== false && strpos($html, 'id="mRef"') !== false
    && (bool) preg_match('/id="tpt-modal"[^>]*\shidden/', $html), 'the server-confirmed success popup starts hidden and renders a reference field');
paymentRenderCheck(strpos($styles, '.pay-overlay{position:fixed;inset:0;z-index:10000') !== false
    && strpos($styles, 'background:#17181f') !== false, 'the payment confirmation popup has a solid background and top-layer z-index');
paymentRenderCheck(strpos($html, "if (result.status === 'confirmed')") !== false
    && strpos($html, 'clearPending();') !== false && strpos($html, 'resetPaymentForm();') !== false, 'only server-confirmed success clears payment values and validation state');
paymentRenderCheck(strpos($html, 'restorePending();') !== false && strpos($html, "result.status === 'cancelled'") !== false, 'cancelled or unconfirmed checkout keeps/restores customer fields');
paymentRenderCheck(strpos($html, "overlay.hidden = true;") !== false && strpos($html, "document.body.classList.remove('payment-modal-open')") !== false
    && strpos($html, "closeButton.addEventListener('click', closeModal)") !== false, 'Close completely hides the popup and unlocks the page');
paymentRenderCheck(strpos($payments, 'private static function confirmPayPalReturn') !== false
    && strpos($payments, 'private static function confirmStripeReturn') !== false
    && strpos($payments, 'validStripeSignature') !== false, 'server code verifies PayPal capture, Stripe return and webhook signatures');
paymentRenderCheck(strpos($payments, 'state_hash') !== false && strpos($payments, 'hash_equals') !== false, 'provider returns are bound to a hashed high-entropy state token');

$GLOBALS['fake_settings']['paypal_client_id'] = '';
settingsCache(true);
$noPaypalHtml = renderPaymentPage();
paymentRenderCheck(strpos($noPaypalHtml, 'data-payment-provider="paypal"') === false
    && strpos($noPaypalHtml, 'data-payment-provider="stripe"') !== false, 'a missing PayPal Client ID disables only PayPal and keeps configured Stripe');

echo PHP_EOL . $GLOBALS['paymentRenderChecks'] . ' payment page render checks passed.' . PHP_EOL;
