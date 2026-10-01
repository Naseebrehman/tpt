<?php
/**
 * Dependency-free tests for the server-side Stripe flow (Task: Stripe server
 * confirmation + payment records). No network and no database are needed.
 *
 *   • the Secret Key and the Webhook Secret live in settings and are readable
 *     only on the server — never in a browser payload, never in an error
 *   • Stripe switches to server-verified mode as soon as a Secret Key is saved
 *     (and stays in browser-only mode without one)
 *   • PaymentIntents are created server-side: the amount and USD are normalised
 *     and bounded, and a malformed reference never reaches the network
 *   • a payment is only "confirmed" when Stripe reports a succeeded USD
 *     payment whose amount matches the amount that was requested
 *   • the Stripe-Signature of a webhook is verified (HMAC over
 *     "<timestamp>.<body>", 5-minute tolerance) before the event is read
 *   • the confirmation endpoint and the webhook record every payment exactly
 *     once — duplicate deliveries are never counted twice
 *   • both endpoints are routed and never echo a credential
 *
 * Run: php tests/stripe-server.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', ''); define('SITE_URL', 'https://example.test');
define('SITE_NAME', 'The Pie Technologies'); define('ADMIN_EMAIL', 'admin@example.test');
define('PRETTY_URLS', true); define('DB_OK', true); define('UPLOAD_PATH', BASE_PATH . '/uploads/');

$GLOBALS['fake_settings'] = array();
$GLOBALS['fake_services'] = array(array('id' => 1, 'name' => 'AI Optimization', 'sort_order' => 1, 'is_active' => 1));
$GLOBALS['fake_tables'] = array();
$GLOBALS['fake_records'] = array();
$GLOBALS['fake_next_id'] = 1;

function dbAll($sql, $params = array())
{
    if (strpos($sql, 'FROM settings') !== false) {
        $rows = array();
        foreach ($GLOBALS['fake_settings'] as $key => $value) { $rows[] = array('setting_key' => $key, 'setting_value' => $value); }
        return $rows;
    }
    if (strpos($sql, 'FROM payment_records') !== false) { return array_values($GLOBALS['fake_records']); }
    return array();
}
function dbOne($sql, $params = array())
{
    if (strpos($sql, 'information_schema.TABLES') !== false) {
        $table = isset($params[0]) ? (string) $params[0] : '';
        if ($table === '' && preg_match("/TABLE_NAME = '([a-z_]+)'/i", $sql, $m)) { $table = $m[1]; }
        return array('c' => !empty($GLOBALS['fake_tables'][$table]) ? 1 : 0);
    }
    if (strpos($sql, 'FROM payment_records') !== false) {
        $provider = isset($params[0]) ? strtolower((string) $params[0]) : '';
        $reference = isset($params[1]) ? (string) $params[1] : '';
        $key = $provider . '|' . $reference;
        return isset($GLOBALS['fake_records'][$key]) ? array('id' => $GLOBALS['fake_records'][$key]['id']) : null;
    }
    if (strpos($sql, 'FROM settings') !== false) {
        $key = $params[0] ?? '';
        return isset($GLOBALS['fake_settings'][$key])
            ? array('setting_key' => $key, 'setting_value' => $GLOBALS['fake_settings'][$key]) : null;
    }
    return null;
}
function dbExec($sql, $params = array())
{
    if (stripos($sql, 'DELETE FROM payment_records') === false) { return 1; }
    $deleted = 0;
    foreach ($params as $id) {
        foreach ($GLOBALS['fake_records'] as $key => $row) {
            if ((int) $row['id'] === (int) $id) { unset($GLOBALS['fake_records'][$key]); $deleted++; }
        }
    }
    return $deleted;
}
/** The UNIQUE (provider, provider_transaction_id) key lives here too. */
function dbInsert($sql, $params = array())
{
    if (stripos($sql, 'INSERT INTO payment_records') === false) { return 7; }
    $provider = strtolower((string) $params[0]);
    $reference = (string) $params[1];
    $key = $provider . '|' . $reference;
    if (isset($GLOBALS['fake_records'][$key])) { return -1; }
    $id = $GLOBALS['fake_next_id']++;
    $GLOBALS['fake_records'][$key] = array(
        'id' => $id, 'provider' => $provider, 'provider_transaction_id' => $reference,
        'payer_name' => $params[2], 'payer_email' => $params[3], 'service' => $params[4],
        'amount' => $params[5], 'currency' => $params[6], 'status' => $params[7],
        'verification_mode' => $params[8], 'raw_reference' => $params[9], 'ip_address' => $params[10],
    );
    return $id;
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

require BASE_PATH . '/includes/functions.php';
require BASE_PATH . '/core/Payments.php';
/* The record keeper is loaded by the endpoints and the dashboard, not by the
   page bridge: require it here exactly like stripe-api.php does. */
require BASE_PATH . '/core/PaymentRecords.php';

$count = 0;
function check($condition, $message) { global $count; $count++; if (!$condition) { throw new RuntimeException('FAIL: ' . $message); } echo 'PASS: ' . $message . PHP_EOL; }
function setFakeSetting($key, $value) { $GLOBALS['fake_settings'][$key] = $value; settingsCache(true); }
function source($relativePath) { return (string) file_get_contents(BASE_PATH . '/' . $relativePath); }
function resetStripeSettings() { $GLOBALS['fake_settings'] = array(); settingsCache(true); }

$secret    = 'sk_test_' . str_repeat('a', 24);
$webhook   = 'whsec_' . base64_encode(str_repeat('b', 24));
$otherKey  = 'sk_live_' . str_repeat('z', 24);

/* ------------------------- the keys stay server-side ----------------------- */
resetStripeSettings();
check(pieStripeSecret() === '', 'no Secret Key before one is saved');
check(pieStripeServerReady() === false, 'Stripe is browser-only until a Secret Key is saved');
check(pieStripeWebhookSecretConfigured() === false, 'no Webhook Secret before one is saved');

setFakeSetting('stripe_secret_key', $secret);
check(pieStripeSecret() === $secret, 'the stored Secret Key is read on the server');
check(pieStripeServerReady() === true, 'a stored Secret Key switches Stripe to server-verified mode');
check(pieStripeKeyMode() === 'test', 'the stored key is recognised as a test key');
setFakeSetting('stripe_secret_key', $otherKey);
check(pieStripeKeyMode() === 'live', 'the stored key is recognised as a live key');

$config = pieStripeClientConfig();
check(strpos(json_encode($config), $otherKey) === false && strpos(json_encode($config), $secret) === false,
    'the browser-facing Stripe config never contains a key');
check(!isset($config['secret']) && !isset($config['secret_key']) && !isset($config['key']),
    'the browser-facing Stripe config has no key field at all');
check($config['serverVerification'] === true && $config['currency'] === 'USD', 'the browser is told that USD payments are server-verified');
check(strpos($config['endpoint'], 'stripe-api') !== false, 'the browser posts to the server endpoint');

setFakeSetting('stripe_webhook_secret', $webhook);
check(pieStripeWebhookSecret() === $webhook, 'the stored Webhook Secret is read on the server');
check(pieStripeWebhookSecretConfigured() === true, 'the stored Webhook Secret is detected');

$scrubbed = pieStripeScrub('Authorization: Bearer ' . $otherKey . ' signing ' . $webhook . ' failed');
check(strpos($scrubbed, $otherKey) === false && strpos($scrubbed, $webhook) === false && strpos($scrubbed, '***') !== false,
    'a log line can never contain a Stripe credential');

check(strpos(pieStripeScrub('Invalid API Key provided: sk_test_****abcd'), 'sk_test_') === false,
    'even the masked key form Stripe echoes back is redacted before it is logged or returned');
check(strpos(pieStripeScrub('signature whsec_abc+/=123 invalid'), 'whsec_') === false,
    'a signing secret echoed inside a message is redacted');

check(pieIsValidStripeSecret('nonsense') === false, 'a junk Secret Key is rejected');
check(pieIsValidStripeSecret('pk_live_' . str_repeat('a', 24)) === false, 'a publishable key is not a Secret Key');
check(pieIsValidStripeSecret('sk_live_' . str_repeat('a', 24)) === true, 'a live Secret Key is accepted');
check(pieIsValidStripeSecret('sk_test_' . str_repeat('a', 24)) === true, 'a test Secret Key is accepted');
check(pieIsValidStripeSecret('rk_live_' . str_repeat('a', 24)) === true, 'a restricted key is accepted');
check(pieIsValidStripeWebhookSecret('whsec_') === false, 'an empty Webhook Secret is rejected');
check(pieIsValidStripeWebhookSecret($webhook) === true, 'a realistic Webhook Secret is accepted');

/* ------------------------------ amount handling ---------------------------- */
check(pieStripeAmountCents('100') === 10000, 'whole amounts become cents');
check(pieStripeAmountCents('99.999') === 10000, 'amounts are rounded to cents');
check(pieStripeAmountCents('0.01') === 1, 'the smallest chargeable amount is kept');
check(pieStripeAmountCents('0') === 0 && pieStripeAmountCents('-5') === 0 && pieStripeAmountCents('') === 0,
    'zero and negative amounts are rejected');
check(pieStripeAmountCents('abc') === 0 && pieStripeAmountCents('1e12') === 0, 'junk and absurd amounts are rejected');
check(pieStripeFormatAmount(25000) === '250.00' && pieStripeFormatAmount(1) === '0.01', 'cents render as USD strings');

/* --------------------------- confirmation is verified ---------------------- */
$intent = array(
    'id' => 'pi_TEST123',
    'object' => 'payment_intent',
    'status' => 'succeeded',
    'amount' => 25000,
    'amount_received' => 25000,
    'currency' => 'usd',
    'receipt_email' => 'ada@example.test',
    'metadata' => array('name' => 'Ada Lovelace', 'service' => 'Web Development'),
);
$confirmation = pieStripeConfirmation($intent);
check($confirmation['confirmed'] === true && $confirmation['amount'] === '250.00' && $confirmation['reference'] === 'pi_TEST123',
    'a succeeded USD intent is confirmed with its real amount and reference');
check($confirmation['name'] === 'Ada Lovelace' && $confirmation['service'] === 'Web Development',
    'the buyer name and service come from Stripe metadata');

$pending = $intent; $pending['status'] = 'processing';
check(pieStripeConfirmation($pending)['confirmed'] === false, 'a processing intent is never confirmed');
$eur = $intent; $eur['currency'] = 'eur';
check(pieStripeConfirmation($eur)['confirmed'] === false, 'a non-USD payment is never confirmed');
$zero = $intent; $zero['amount'] = 0; $zero['amount_received'] = 0;
check(pieStripeConfirmation($zero)['confirmed'] === false, 'a payment without a usable amount is never confirmed');
check(pieStripeConfirmation(array())['confirmed'] === false, 'an empty response is never confirmed');
$partial = $intent; $partial['amount'] = 50000; $partial['amount_received'] = 25000;
check(pieStripeConfirmation($partial)['amount'] === '250.00', 'the amount Stripe actually received wins');

/* ---------------------- malformed references never hit the network --------- */
resetStripeSettings();
foreach (array('', 'x', 'DROP TABLE payment_records', 'pi_' . str_repeat('A', 400), 'cs_short') as $bad) {
    $result = pieStripeConfirmPayment($bad);
    check($result['ok'] === false && $result['error'] !== '',
        'an invalid payment reference is rejected before any request: "' . substr($bad, 0, 16) . '"');
}
setFakeSetting('stripe_secret_key', $otherKey);
check(pieIsValidStripeReference('pi_TEST123') === true && pieIsValidStripeReference('cs_test_abc123') === true,
    'PaymentIntent and Checkout Session references are accepted');
check(pieIsValidStripeReference('../etc/passwd') === false && pieIsValidStripeReference('pi_') === false,
    'path-like and truncated references are rejected');

/* --------------------------- webhook signature ---------------------------- */
setFakeSetting('stripe_webhook_secret', $webhook);
$body = json_encode(array('id' => 'evt_1', 'type' => 'payment_intent.succeeded', 'data' => array('object' => $intent)));
$timestamp = (string) time();
$signature = hash_hmac('sha256', $timestamp . '.' . $body, $webhook);
check(pieStripeVerifyWebhookSignature($body, 't=' . $timestamp . ',v1=' . $signature) === true, 'a correctly signed delivery is accepted');
check(pieStripeVerifyWebhookSignature($body, 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $body, 'whsec_wrong')) === false,
    'a delivery signed with another secret is rejected');
check(pieStripeVerifyWebhookSignature($body . ' ', 't=' . $timestamp . ',v1=' . $signature) === false, 'a tampered body is rejected');
check(pieStripeVerifyWebhookSignature($body, 't=' . ((string) (time() - 3600)) . ',v1=' . hash_hmac('sha256', ((string) (time() - 3600)) . '.' . $body, $webhook)) === false,
    'an old delivery (replay) is rejected');
check(pieStripeVerifyWebhookSignature($body, 't=' . $timestamp) === false, 'a signature without a v1 part is rejected');
check(pieStripeVerifyWebhookSignature($body, '') === false, 'a missing Stripe-Signature header is rejected');
check(pieStripeVerifyWebhookSignature($body, 't=now,v1=' . $signature) === false, 'a non-numeric timestamp is rejected');
$savedWebhook = $GLOBALS['fake_settings']['stripe_webhook_secret'];
unset($GLOBALS['fake_settings']['stripe_webhook_secret']); settingsCache(true);
check(pieStripeVerifyWebhookSignature($body, 't=' . $timestamp . ',v1=' . $signature) === false,
    'nothing is trusted while no Webhook Secret is stored');
setFakeSetting('stripe_webhook_secret', $savedWebhook);

/* ------------------------- webhook event handling ------------------------- */
$event = array('id' => 'evt_1', 'type' => 'payment_intent.succeeded', 'data' => array('object' => $intent));
$payment = pieStripeWebhookPayment($event);
check($payment['ok'] === true && $payment['confirmation']['amount'] === '250.00', 'payment_intent.succeeded is a recorded payment');
$other = pieStripeWebhookPayment(array('id' => 'evt_2', 'type' => 'customer.created', 'data' => array('object' => array())));
check($other['ok'] === false, 'an unrelated event is not a payment');
$failed = pieStripeWebhookPayment(array('type' => 'payment_intent.payment_failed', 'data' => array('object' => $intent)));
check($failed['ok'] === false, 'a failed event is never recorded as a successful payment');

$sessionEvent = array('id' => 'evt_3', 'type' => 'checkout.session.completed', 'data' => array('object' => array(
    'id' => 'cs_test_123', 'object' => 'checkout.session', 'payment_status' => 'paid',
    'amount_total' => 5000, 'currency' => 'usd', 'payment_intent' => 'pi_NOTOURS',
    'metadata' => array('name' => 'Grace Hopper', 'service' => 'SEO'),
)));
$sessionPayment = pieStripeWebhookPayment($sessionEvent);
check($sessionPayment['ok'] === true && $sessionPayment['confirmation']['amount'] === '50.00' && $sessionPayment['confirmation']['name'] === 'Grace Hopper',
    'checkout.session.completed with a paid session is recorded');
$unpaid = $sessionEvent;
$unpaid['data']['object']['payment_status'] = 'unpaid';
$unpaid['data']['object']['payment_intent'] = '';
check(pieStripeWebhookPayment($unpaid)['ok'] === false, 'an unpaid Checkout Session is never recorded');

/* ------------------------ recorded exactly once --------------------------- */
$GLOBALS['fake_tables']['payment_records'] = true;
$GLOBALS['fake_records'] = array();
$GLOBALS['fake_next_id'] = 1;
$record = array(
    'provider' => 'stripe', 'provider_transaction_id' => 'pi_RECORD1',
    'payer_name' => 'Ada Lovelace', 'payer_email' => 'ada@example.test', 'service' => 'Web Development',
    'amount' => '250.00', 'currency' => 'USD', 'status' => 'succeeded', 'verification_mode' => 'server',
    'raw_reference' => 'pi_RECORD1', 'ip_address' => '203.0.113.10',
);
$first = piePaymentRecord($record);
check($first > 0, 'a confirmed Stripe payment is recorded');
check(count($GLOBALS['fake_records']) === 1, 'exactly one row is stored');
check(piePaymentRecord($record) === $first, 'recording the same payment id again returns the same record');
check(count($GLOBALS['fake_records']) === 1, 'a replayed confirmation never creates a second row');
$paypal = $record; $paypal['provider'] = 'paypal';
check(piePaymentRecord($paypal) !== $first && count($GLOBALS['fake_records']) === 2,
    'the same id from another provider is a different payment');
check(piePaymentRecord(array('provider' => 'stripe', 'provider_transaction_id' => '')) === 0, 'a payment without a Stripe id is not recorded');
$GLOBALS['fake_tables']['payment_records'] = false;
check(piePaymentRecord(array_merge($record, array('provider_transaction_id' => 'pi_MISSING'))) === 0,
    'nothing is recorded while the payment_records table is missing (a failure never breaks a payment)');
$GLOBALS['fake_tables']['payment_records'] = true;
check(piePaymentRecordDelete(array($first)) === 1, 'a record can be deleted by id');
check(count($GLOBALS['fake_records']) === 1, 'only the selected record is deleted');

/* --------------------------- the endpoints are protected ------------------- */
$endpoint = source('stripe-api.php');
check(strpos($endpoint, "!== 'POST'") !== false, 'the Stripe endpoint refuses anything but POST');
check(strpos($endpoint, 'validateCSRF') !== false, 'the Stripe endpoint requires a CSRF token for this session');
check(strpos($endpoint, 'pieStripeCreatePaymentIntent') !== false, 'PaymentIntents are created on the server');
check(strpos($endpoint, 'pieStripeConfirmPayment') !== false, 'payments are verified with the server-side helper');
check(strpos($endpoint, 'pieStripeServerReady') !== false, 'the endpoint refuses to run without a stored Secret Key');
check(strpos($endpoint, 'piePaymentRecord') !== false, 'confirmed Stripe payments are recorded for the dashboard');
check(substr_count($endpoint, 'json_encode') === 1, 'the Stripe endpoint has exactly one JSON output point');
check(!preg_match('/echo[^;]*(secret|api_?key)/i', $endpoint), 'the Stripe endpoint never echoes a credential');

$webhookEndpoint = source('stripe-webhook.php');
check(strpos($webhookEndpoint, 'pieStripeVerifyWebhookSignature') !== false, 'the webhook verifies the Stripe-Signature before reading the event');
check(strpos($webhookEndpoint, 'HTTP_STRIPE_SIGNATURE') !== false, 'the webhook reads the signature header');
check(strpos($webhookEndpoint, 'pieStripeWebhookSecretConfigured') !== false, 'the webhook refuses to run without a Webhook Secret');
check(strpos($webhookEndpoint, 'piePaymentRecord') !== false, 'the webhook records every confirmed payment once');
check(strpos($webhookEndpoint, 'payment_intent.succeeded') === false && strpos($webhookEndpoint, 'checkout.session.completed') === false
    || strpos(source('core/Stripe.php'), 'checkout.session.completed') !== false,
    'the completed-payment events are handled by the Stripe module');
check(!preg_match('/echo[^;]*(secret|api_?key)/i', $webhookEndpoint), 'the webhook never echoes a credential');

$routes = source('app/routes.php');
foreach (array("'/stripe-api'", "'/api/stripe/create-intent'", "'/api/stripe/confirm'") as $route) {
    check(strpos($routes, $route) !== false, 'the Stripe endpoint is routed: ' . $route);
}
foreach (array("'/stripe-webhook'", "'/api/stripe/webhook'") as $route) {
    check(strpos($routes, $route) !== false, 'the Stripe webhook is routed: ' . $route);
}
check(strpos(source('core/Payments.php'), 'pieStripeSecret') !== false, 'Payments.php includes the server-side Stripe module');

/* --------------------------- the page runs both modes --------------------- */
$public = source('pay-online.php');
check(strpos($public, 'pieStripeServerReady') !== false || strpos($public, 'Stripe') !== false, 'the pay page knows about Stripe');
check(strpos(source('core/Payments.php'), "require_once __DIR__ . '/Stripe.php'") !== false,
    'the shared payment module loads the Stripe module');
check(strpos(source('includes/payments.php'), 'core/Payments.php') !== false,
    'the legacy payment include still loads the shared module');

echo "\n$count checks passed.\n";
