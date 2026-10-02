<?php
/** Offline provider tests: server-side flow is exercised with a fake HTTPS transport. */
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '');
define('SITE_URL', 'https://example.test');
define('SITE_NAME', 'The Pie Technologies');
define('ADMIN_EMAIL', 'admin@example.test');
define('PRETTY_URLS', true);
define('DB_OK', true);
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('PAYPAL_CLIENT_SECRET', 'unit-paypal-secret');
define('PAYPAL_ENVIRONMENT', 'sandbox');
define('STRIPE_SECRET_KEY', 'sk_test_fake_server_only');
define('STRIPE_WEBHOOK_SECRET', 'whsec_fake_server_only');
$GLOBALS['fake_settings'] = array('paypal_client_id' => 'Axxxxxxxxxxxxxxxxxxxxxxxx', 'site_name' => 'TPT Test');
$GLOBALS['fake_services'] = array('AI Optimization', 'Web Development');
$GLOBALS['fake_payment_records'] = array();
$GLOBALS['fake_payment_events'] = array();
$GLOBALS['fake_next_id'] = 1;
$GLOBALS['fake_stripe_sessions'] = array();
$GLOBALS['fake_paypal_reference'] = '';

require BASE_PATH . '/includes/functions.php';
settingsCache(true);
function dbAll($sql, $params = array())
{
    if (strpos($sql, 'FROM settings') !== false) {
        $rows = array();
        foreach ($GLOBALS['fake_settings'] as $key => $value) { $rows[] = array('setting_key' => $key, 'setting_value' => $value); }
        return $rows;
    }
    if (strpos($sql, 'FROM payment_services') !== false) {
        return array_map(function ($name, $index) { return array('name' => $name, 'sort_order' => $index + 1); }, $GLOBALS['fake_services'], array_keys($GLOBALS['fake_services']));
    }
    return array();
}
function dbOne($sql, $params = array())
{
    if (strpos($sql, 'information_schema.TABLES') !== false) { return array('c' => 1); }
    if (strpos($sql, 'FROM payment_records') !== false) {
        $reference = (string) ($params[0] ?? '');
        return $GLOBALS['fake_payment_records'][$reference] ?? null;
    }
    return null;
}
function dbInsert($sql, $params = array())
{
    if (strpos($sql, 'INSERT INTO payment_records') === false) { return -1; }
    $id = $GLOBALS['fake_next_id']++;
    $reference = (string) $params[0];
    $GLOBALS['fake_payment_records'][$reference] = array(
        'id' => $id, 'reference' => $reference, 'provider' => (string) $params[1],
        'provider_order_id' => null, 'provider_transaction_id' => null,
        'name' => (string) $params[2], 'service' => (string) $params[3],
        'amount' => (string) $params[4], 'currency' => 'USD', 'status' => 'pending',
        'state_hash' => (string) $params[5], 'created_at' => '2026-10-02 00:00:00', 'updated_at' => '2026-10-02 00:00:00',
    );
    return $id;
}
function dbExec($sql, $params = array())
{
    if (strpos($sql, 'UPDATE payment_records SET provider_order_id') !== false) {
        $id = (int) ($params[1] ?? 0);
        foreach ($GLOBALS['fake_payment_records'] as &$record) {
            if ((int) $record['id'] === $id) { $record['provider_order_id'] = (string) $params[0]; $record['updated_at'] = '2026-10-02 00:01:00'; break; }
        }
        unset($record);
        return 1;
    }
    if (strpos($sql, "SET status = 'completed'") !== false) {
        $reference = (string) ($params[1] ?? '');
        if (!isset($GLOBALS['fake_payment_records'][$reference])) { return -1; }
        $GLOBALS['fake_payment_records'][$reference]['status'] = 'completed';
        $GLOBALS['fake_payment_records'][$reference]['provider_transaction_id'] = (string) $params[0];
        return 1;
    }
    if (strpos($sql, 'INSERT IGNORE INTO payment_events') !== false) {
        $GLOBALS['fake_payment_events'][] = $params;
        return 1;
    }
    return 1;
}

require BASE_PATH . '/core/Payments.php';
$count = 0;
function paymentGatewayCheck($condition, $message)
{
    global $count;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    $count++;
    echo 'PASS: ' . $message . PHP_EOL;
}

PaymentGateway::setTransportForTests(function ($method, $url, $headers, $body) {
    if (strpos($url, '/v1/oauth2/token') !== false) {
        return array('status' => 200, 'json' => array('access_token' => 'fake-paypal-access-token', 'expires_in' => 3600));
    }
    if ($method === 'POST' && strpos($url, '/v2/checkout/orders') !== false && strpos($url, '/capture') === false) {
        $payload = json_decode((string) $body, true);
        $GLOBALS['fake_paypal_reference'] = (string) ($payload['purchase_units'][0]['custom_id'] ?? '');
        return array('status' => 201, 'json' => array(
            'id' => 'PAYPAL-ORDER-123',
            'links' => array(array('rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-123')),
        ));
    }
    if ($method === 'POST' && strpos($url, '/v2/checkout/orders/PAYPAL-ORDER-123/capture') !== false) {
        return array('status' => 201, 'json' => array(
            'status' => 'COMPLETED',
            'purchase_units' => array(array(
                'custom_id' => $GLOBALS['fake_paypal_reference'],
                'payments' => array('captures' => array(array(
                    'id' => 'PAYPAL-CAPTURE-456', 'status' => 'COMPLETED',
                    'amount' => array('value' => '12.50', 'currency_code' => 'USD'),
                ))),
            )),
        ));
    }
    if ($method === 'POST' && $url === 'https://api.stripe.com/v1/checkout/sessions') {
        parse_str((string) $body, $params);
        $reference = (string) ($params['metadata']['tpt_reference'] ?? '');
        $id = 'cs_test_' . count($GLOBALS['fake_stripe_sessions']) . '_123';
        $session = array(
            'id' => $id, 'client_reference_id' => $reference,
            'metadata' => array('tpt_reference' => $reference),
            'currency' => 'usd', 'amount_total' => (int) ($params['line_items'][0]['price_data']['unit_amount'] ?? 0),
            'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_' . count($GLOBALS['fake_stripe_sessions']),
        );
        $GLOBALS['fake_stripe_sessions'][$id] = $session;
        return array('status' => 200, 'json' => array('id' => $id, 'url' => 'https://checkout.stripe.com/c/pay/' . $id));
    }
    if ($method === 'GET' && strpos($url, 'https://api.stripe.com/v1/checkout/sessions/') === 0) {
        $id = rawurldecode(substr($url, strrpos($url, '/') + 1));
        return isset($GLOBALS['fake_stripe_sessions'][$id])
            ? array('status' => 200, 'json' => $GLOBALS['fake_stripe_sessions'][$id])
            : array('status' => 404, 'json' => array());
    }
    return array('status' => 500, 'json' => array());
});

$paypal = PaymentGateway::createCheckout('paypal', array('name' => 'Test Business', 'service' => 'AI Optimization', 'amount' => '12.50'));
$paypalRecord = $GLOBALS['fake_payment_records'][$paypal['payment_id']];
paymentGatewayCheck(strpos($paypal['redirect_url'], 'https://www.sandbox.paypal.com/') === 0, 'PayPal order creation returns a validated hosted checkout URL');
paymentGatewayCheck($paypalRecord['status'] === 'pending' && $paypalRecord['provider_order_id'] === 'PAYPAL-ORDER-123', 'PayPal order is recorded as pending before confirmation');
$paypalResult = PaymentGateway::processReturn(array(
    'payment_return' => 'paypal', 'payment_id' => $paypal['payment_id'], 'state' => $paypal['state'], 'token' => 'PAYPAL-ORDER-123',
));
paymentGatewayCheck($paypalResult['status'] === 'confirmed' && $paypalResult['reference'] === 'PAYPAL-CAPTURE-456', 'PayPal is captured and confirmed by the server before success');
paymentGatewayCheck($GLOBALS['fake_payment_records'][$paypal['payment_id']]['status'] === 'completed', 'verified PayPal success is saved to the payment ledger');

$stripe = PaymentGateway::createCheckout('stripe', array('name' => 'Test Business', 'service' => 'Web Development', 'amount' => '20.00'));
$stripeRecord = $GLOBALS['fake_payment_records'][$stripe['payment_id']];
paymentGatewayCheck(strpos($stripe['redirect_url'], 'https://checkout.stripe.com/') === 0, 'Stripe session creation returns a validated hosted checkout URL');
paymentGatewayCheck($stripeRecord['status'] === 'pending' && strpos($stripeRecord['provider_order_id'], 'cs_test_') === 0, 'Stripe session is recorded as pending before confirmation');
$stripeResult = PaymentGateway::processReturn(array(
    'payment_return' => 'stripe', 'payment_id' => $stripe['payment_id'], 'state' => $stripe['state'], 'session_id' => $stripeRecord['provider_order_id'],
));
paymentGatewayCheck($stripeResult['status'] === 'confirmed' && $stripeResult['provider'] === 'Stripe', 'Stripe return is retrieved and confirmed by the server');
paymentGatewayCheck($GLOBALS['fake_payment_records'][$stripe['payment_id']]['status'] === 'completed', 'verified Stripe success is saved to the payment ledger');

$event = array('id' => 'evt_test_123', 'type' => 'checkout.session.completed', 'data' => array('object' => $GLOBALS['fake_stripe_sessions'][$stripeRecord['provider_order_id']]));
$payload = json_encode($event);
$timestamp = time();
$signature = hash_hmac('sha256', $timestamp . '.' . $payload, STRIPE_WEBHOOK_SECRET);
paymentGatewayCheck(PaymentGateway::handleStripeWebhook($payload, 't=' . $timestamp . ',v1=' . $signature), 'valid Stripe webhook is accepted and idempotently recorded');
paymentGatewayCheck(!PaymentGateway::handleStripeWebhook($payload, 't=' . $timestamp . ',v1=' . str_repeat('0', 64)), 'invalid Stripe webhook signatures are rejected');

PaymentGateway::setTransportForTests(null);
echo "\n$count server-side payment gateway checks passed.\n";
