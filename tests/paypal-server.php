<?php
/** Dependency-free tests for server-side PayPal Orders/capture verification.
 * Run: php tests/paypal-server.php
 */
require __DIR__ . '/payment-test-bootstrap.php';

$paypalId = 'AxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxX';
$paypalSecret = 'PayPalSecret-' . str_repeat('aZ9_', 12);
paymentSetSetting('paypal_client_id', $paypalId);
paymentSetSetting('paypal_secret', piePaymentCredentialEncrypt($paypalSecret));
paymentSetSetting('paypal_env', 'live');
paymentCheck(piePayPalServerReady() && piePayPalSecret() === $paypalSecret, 'PayPal credentials decrypt and are used server-side');
paymentCheck(piePayPalApiBase() === 'https://api-m.paypal.com', 'Live mode uses the PayPal live API hostname');
paymentSetSetting('paypal_env', 'sandbox');
paymentCheck(piePayPalEnv() === 'sandbox' && piePayPalApiBase() === 'https://api-m.sandbox.paypal.com', 'Sandbox mode uses the PayPal Sandbox API hostname');
paymentSetSetting('paypal_env', 'unknown');
paymentCheck(piePayPalEnv() === 'live', 'invalid environment values safely default to Live');
paymentCheck(pieIsValidPayPalClientId($paypalId) && !pieIsValidPayPalClientId('bad id') && !pieIsValidPayPalClientId('x'), 'PayPal Client IDs are validated');
paymentCheck(pieIsValidPayPalSecret('short') === false && pieIsValidPayPalSecret($paypalSecret), 'PayPal Secrets have a minimum format/length check');

paymentCheck(piePayPalAmount('100') === '100.00' && piePayPalAmount('0.01') === '0.01', 'PayPal amount formatting keeps exact USD cents');
paymentCheck(piePayPalAmount('99.999') === '' && piePayPalAmount('1e3') === '' && piePayPalAmount('-1') === '', 'PayPal rejects excess precision, exponent notation and negative amounts');

$attemptToken = str_repeat('a', 64);
$completedOrder = array(
    'id' => 'ORDER123456789',
    'status' => 'COMPLETED',
    'payer' => array('name' => array('given_name' => 'Ada', 'surname' => 'Lovelace'), 'email_address' => 'ada@example.test'),
    'purchase_units' => array(array(
        'custom_id' => $attemptToken,
        'invoice_id' => $attemptToken,
        'payments' => array('captures' => array(array(
            'id' => 'CAPTURE123456', 'status' => 'COMPLETED',
            'amount' => array('value' => '250.00', 'currency_code' => 'USD'),
        ))),
    )),
);
$confirmation = piePayPalConfirmation($completedOrder);
paymentCheck($confirmation['confirmed'] === true && $confirmation['amount'] === '250.00' && $confirmation['currency'] === 'USD', 'a completed PayPal capture confirms the provider amount and currency');
paymentCheck($confirmation['reference'] === 'CAPTURE123456' && $confirmation['order_id'] === 'ORDER123456789', 'the capture transaction ID is distinct from the PayPal order reference');
paymentCheck($confirmation['name'] === 'Ada Lovelace' && $confirmation['email'] === 'ada@example.test', 'PayPal payer details are extracted from the verified response');
paymentCheck(hash_equals($attemptToken, $confirmation['custom_id']) && hash_equals($attemptToken, $confirmation['invoice_id']), 'PayPal order metadata carries the server attempt token');

$pending = $completedOrder;
$pending['purchase_units'][0]['payments']['captures'][0]['status'] = 'PENDING';
paymentCheck(piePayPalConfirmation($pending)['confirmed'] === false, 'a pending capture is never confirmed');
$wrongCurrency = $completedOrder;
$wrongCurrency['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] = 'EUR';
paymentCheck(piePayPalConfirmation($wrongCurrency)['confirmed'] === false, 'a non-USD capture is never confirmed');
$emptyAmount = $completedOrder;
$emptyAmount['purchase_units'][0]['payments']['captures'][0]['amount']['value'] = '';
paymentCheck(piePayPalConfirmation($emptyAmount)['confirmed'] === false, 'a capture with no valid amount is never confirmed');

/* Invalid references are rejected locally and never reach the network. */
foreach (array('', 'x', 'DROP TABLE payments', str_repeat('A', 60)) as $badOrderId) {
    $result = piePayPalCaptureOrder($badOrderId, '250.00', $attemptToken);
    paymentCheck($result['ok'] === false && $result['error'] !== '', 'malformed PayPal order ID is rejected before API access');
}
$badCreate = piePayPalCreateOrder('250.00', 'AI Optimization', 'Ada', 'ada@example.test', 'javascript:alert(1)', 'https://example.test/cancel', $attemptToken);
paymentCheck($badCreate['ok'] === false && strpos($badCreate['error'], 'return URLs') !== false, 'unsafe PayPal return URL is rejected before OAuth/API access');

$cleaned = piePayPalScrub('secret=' . $paypalSecret . ' client=' . $paypalId);
paymentCheck(strpos($cleaned, $paypalSecret) === false && strpos($cleaned, $paypalId) === false, 'PayPal API errors/logs redact both Client ID and Secret');

$endpoint = paymentSource('paypal-api.php');
paymentCheck(strpos($endpoint, 'validateCSRF()') !== false && strpos($endpoint, 'payment_action') !== false, 'PayPal checkout POST retains CSRF and explicit action checks');
paymentCheck(strpos($endpoint, 'piePayPalCaptureOrder') !== false && strpos($endpoint, 'piePaymentCompleteAttempt') !== false, 'PayPal return is captured and recorded only by shared server-confirmation logic');
paymentCheck(strpos($endpoint, 'piePaymentMarkAttempt($attempt, \'cancelled\')') !== false, 'PayPal cancellation marks only the pending attempt cancelled');
paymentCheck(strpos($endpoint, '$_POST[\'success\']') === false && strpos($endpoint, '$_GET[\'success\']') === false, 'the endpoint does not trust browser-posted success flags');
paymentCheck(stripos($endpoint, $paypalSecret) === false && strpos($endpoint, 'echo json_encode') === false, 'PayPal endpoint never emits credentials or browser JSON callbacks');
$routes = paymentSource('app/routes.php');
paymentCheck(strpos($routes, '~^/paypal-api') !== false, 'the distinct PayPal server endpoint is routed');
paymentTestSummary();
