<?php
/** Dependency-free tests for server-side Stripe Checkout and webhook verification.
 * Run: php tests/stripe-server.php
 */
require __DIR__ . '/payment-test-bootstrap.php';

$stripeSecret = 'sk_test_' . str_repeat('A1b2C3d4', 4);
$webhookSecret = 'whsec_' . base64_encode(str_repeat('s', 32));
paymentSetSetting('stripe_enabled', '1');
paymentSetSetting('stripe_secret_key', piePaymentCredentialEncrypt($stripeSecret));
paymentSetSetting('stripe_webhook_secret', piePaymentCredentialEncrypt($webhookSecret));
paymentCheck(pieStripeServerReady() && pieStripeSecret() === $stripeSecret, 'Stripe Secret Key decrypts only in PHP and enables server-side Checkout');
paymentCheck(pieStripeKeyMode() === 'test', 'test-mode Secret Key is identified correctly');
paymentCheck(pieStripeWebhookSecretConfigured() && pieStripeWebhookSecret() === $webhookSecret, 'optional Webhook Secret decrypts only in PHP');
paymentCheck(pieIsValidStripeSecret($stripeSecret) && pieIsValidStripeSecret('rk_live_' . str_repeat('x', 20)), 'Stripe secret and restricted-key formats are accepted');
paymentCheck(!pieIsValidStripeSecret('pk_test_' . str_repeat('x', 20)) && !pieIsValidStripeSecret('not-a-key'), 'publishable and malformed keys are rejected as Secret Keys');
paymentCheck(pieIsValidStripeWebhookSecret($webhookSecret) && !pieIsValidStripeWebhookSecret('whsec_'), 'Stripe webhook signing secret is validated');

paymentCheck(pieStripeAmountCents('100') === 10000 && pieStripeAmountCents('0.01') === 1, 'valid USD amounts convert exactly to cents');
paymentCheck(pieStripeAmountCents('99.999') === 0 && pieStripeAmountCents('1e4') === 0 && pieStripeAmountCents('-1') === 0, 'invalid precision, exponent and negative amounts are rejected');
paymentCheck(pieStripeFormatAmount(25001) === '250.01', 'provider cents format back to an exact decimal amount');

$token = str_repeat('c', 64);
$sessionId = 'cs_test_1234567890';
$intentId = 'pi_1234567890';
$attempt = array('id' => 7, 'token' => $token, 'reference' => $sessionId, 'amount_usd' => '250.00', 'name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'service' => 'Web Development', 'method' => 'stripe', 'status' => 'pending');
$session = array(
    'id' => $sessionId,
    'mode' => 'payment',
    'status' => 'complete',
    'payment_status' => 'paid',
    'amount_total' => 25000,
    'currency' => 'usd',
    'client_reference_id' => $token,
    'customer_email' => 'ada@example.test',
    'customer_details' => array('name' => 'Ada Lovelace', 'email' => 'ada@example.test'),
    'metadata' => array('integration' => 'tpt-pay-online-v1', 'payment_token' => $token, 'service' => 'Web Development'),
);
$intent = array(
    'id' => $intentId,
    'status' => 'succeeded',
    'amount' => 25000,
    'amount_received' => 25000,
    'currency' => 'usd',
    'metadata' => array('integration' => 'tpt-pay-online-v1', 'payment_token' => $token),
);
$confirmation = pieStripeCheckoutConfirmation($session, $intent);
paymentCheck($confirmation['confirmed'] === true && $confirmation['amount'] === '250.00' && $confirmation['currency'] === 'USD', 'paid Checkout Session plus succeeded PaymentIntent confirms exact USD amount');
paymentCheck($confirmation['reference'] === $intentId && $confirmation['session_id'] === $sessionId, 'Stripe transaction ID and Checkout Session reference remain distinct');
paymentCheck($confirmation['name'] === 'Ada Lovelace' && $confirmation['email'] === 'ada@example.test' && $confirmation['service'] === 'Web Development', 'verified Stripe customer/service metadata is returned');

$mutated = $session; $mutated['payment_status'] = 'unpaid';
paymentCheck(pieStripeCheckoutConfirmation($mutated, $intent)['confirmed'] === false, 'unpaid Checkout Session is never confirmed');
$mutated = $intent; $mutated['status'] = 'processing';
paymentCheck(pieStripeCheckoutConfirmation($session, $mutated)['confirmed'] === false, 'processing PaymentIntent is never confirmed');
$mutated = $intent; $mutated['amount_received'] = 24999;
paymentCheck(pieStripeCheckoutConfirmation($session, $mutated)['confirmed'] === false, 'mismatched Session and Intent amounts are rejected');
$mutated = $session; $mutated['currency'] = 'eur';
paymentCheck(pieStripeCheckoutConfirmation($mutated, $intent)['confirmed'] === false, 'non-USD Checkout Session is rejected');
$mutated = $session; $mutated['metadata']['payment_token'] = str_repeat('d', 64);
paymentCheck(pieStripeCheckoutConfirmation($mutated, $intent)['confirmed'] === false, 'session and intent metadata tokens must match');
$mutated = $intent; $mutated['metadata']['integration'] = 'other-app';
paymentCheck(pieStripeCheckoutConfirmation($session, $mutated)['confirmed'] === false, 'only sessions created by this TPT integration can confirm');

paymentCheck(pieIsValidStripeCheckoutSessionId($sessionId) && pieIsValidStripePaymentIntentId($intentId), 'provider Checkout Session and PaymentIntent references pass strict ID validation');
paymentCheck(!pieIsValidStripeCheckoutSessionId('../etc/passwd') && !pieIsValidStripePaymentIntentId('pi_'), 'malformed provider IDs are rejected');
$successUrl = 'https://example.test/stripe-api?flow=return&session_id={CHECKOUT_SESSION_ID}';
$cancelUrl = 'https://example.test/stripe-api?flow=cancel&payment_token=' . $token;
paymentCheck(pieStripeReturnUrlsValid($successUrl, $cancelUrl), 'Stripe literal {CHECKOUT_SESSION_ID} placeholder survives return-URL validation');
paymentCheck(!pieStripeReturnUrlsValid('javascript:alert(1)', $cancelUrl), 'non-HTTP Stripe return URLs are rejected');
$invalidSession = pieStripeCreateCheckoutSession(array('amount_usd' => '250.00', 'token' => $token), 'javascript:alert(1)', $cancelUrl);
paymentCheck($invalidSession['ok'] === false && strpos($invalidSession['error'], 'return URLs') !== false, 'unsafe Stripe return URLs are rejected before any API request');

$encoded = pieStripeFormEncode(array('mode' => 'payment', 'line_items' => array(array('quantity' => 1, 'price_data' => array('currency' => 'usd')))));
paymentCheck(strpos($encoded, 'line_items%5B0%5D%5Bprice_data%5D%5Bcurrency%5D=usd') !== false, 'Checkout Session parameters use Stripe bracketed form encoding');

$timestamp = (string) time();
$payload = json_encode(array('id' => 'evt_test_123', 'type' => 'checkout.session.completed', 'data' => array('object' => $session)));
$signature = hash_hmac('sha256', $timestamp . '.' . $payload, $webhookSecret);
paymentCheck(pieStripeVerifyWebhookSignature($payload, 't=' . $timestamp . ',v1=' . $signature), 'a current Stripe timestamped HMAC signature is accepted');
paymentCheck(!pieStripeVerifyWebhookSignature($payload . ' ', 't=' . $timestamp . ',v1=' . $signature), 'a changed webhook payload is rejected');
$wrongSignature = hash_hmac('sha256', $timestamp . '.' . $payload, 'whsec_wrong_secret');
paymentCheck(!pieStripeVerifyWebhookSignature($payload, 't=' . $timestamp . ',v1=' . $wrongSignature), 'a webhook signed by another secret is rejected');
$oldTimestamp = (string) (time() - 3600);
$oldSignature = hash_hmac('sha256', $oldTimestamp . '.' . $payload, $webhookSecret);
paymentCheck(!pieStripeVerifyWebhookSignature($payload, 't=' . $oldTimestamp . ',v1=' . $oldSignature), 'a replayed/out-of-tolerance webhook is rejected');
paymentCheck(!pieStripeVerifyWebhookSignature($payload, 't=' . $timestamp), 'a signature without a v1 digest is rejected');
paymentCheck(!pieStripeVerifyWebhookSignature($payload, 't=nope,v1=' . $signature), 'a non-numeric webhook timestamp is rejected');

$scrubbed = pieStripeScrub('Authorization: ' . $stripeSecret . ' / ' . $webhookSecret . ' pk_test_ABCDEF012345');
paymentCheck(strpos($scrubbed, $stripeSecret) === false && strpos($scrubbed, $webhookSecret) === false && strpos($scrubbed, 'pk_test_') === false, 'Stripe API/log messages scrub credential values and credential-shaped strings');

$endpoint = paymentSource('stripe-api.php');
paymentCheck(strpos($endpoint, 'validateCSRF()') !== false && strpos($endpoint, 'payment_action') !== false, 'Stripe checkout POST retains session CSRF and explicit action checks');
paymentCheck(strpos($endpoint, 'pieStripeConfirmCheckoutSession') !== false && strpos($endpoint, 'piePaymentCompleteAttempt') !== false, 'Stripe return verifies the provider before recording success');
paymentCheck(strpos($endpoint, 'piePaymentMarkAttempt($attempt, \'cancelled\')') !== false, 'Stripe cancellation only marks the pending attempt cancelled');
$confirmationAmountNeedle = "'amount' => (string) (" . '$confirmation' . "['amount'] ?? '')";
paymentCheck(strpos($endpoint, $confirmationAmountNeedle) !== false, 'thank-you amount comes from Stripe confirmation, not the browser');
$webhook = paymentSource('stripe-webhook.php');
paymentCheck(strpos($webhook, 'pieStripeVerifyWebhookSignature') !== false && strpos($webhook, 'pieStripeConfirmCheckoutSession') !== false, 'webhook verifies the signature and re-fetches provider state');
paymentCheck(strpos($webhook, 'piePaymentCompleteAttempt') !== false && strpos($webhook, 'payment_intent.succeeded') !== false, 'only verified TPT Stripe events can create a successful record');
$routes = paymentSource('app/routes.php');
paymentCheck(strpos($routes, '~^/stripe-api') !== false && strpos($routes, "'/stripe-webhook'") !== false, 'separate Stripe checkout and signed webhook endpoints are routed');
paymentTestSummary();
