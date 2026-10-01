<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — server-side Stripe endpoint
 * ---------------------------------------------------------------------------
 *  POST only. Called by the Pay Online page (window.TPT_STRIPE) so that a
 *  Stripe payment is CREATED and CONFIRMED on the server with the Stripe
 *  Secret Key from Admin → Payments. The key never leaves this server.
 *
 *  Actions
 *    action=create   → creates a PaymentIntent for the amount the buyer
 *                      entered (the amount and USD are set HERE, never trusted
 *                      from the browser)
 *    action=confirm  → retrieves the payment from Stripe with the Secret Key
 *                      and verifies status = succeeded, the amount and USD;
 *                      the confirmed payment is then recorded once
 *    action=status   → reports whether server-side confirmation is configured
 *
 *  Answers are always JSON: {"success":bool, "message":string, ...}. A payment
 *  is only reported as confirmed when Stripe itself reports a succeeded USD
 *  payment of the expected amount — nothing here trusts the visitor's browser.
 *
 *  Output never contains the Stripe Secret Key, the Webhook Secret, an API key
 *  or a stack trace.
 *  Stripe webhooks (checkout.session.completed / payment_intent.succeeded) are
 *  handled by the separate, signature-verified stripe-webhook.php endpoint.
 */

if (!defined('DB_OK')) {
    require_once __DIR__ . '/includes/init.php';
}
require_once __DIR__ . '/includes/payments.php';
require_once __DIR__ . '/core/PaymentRecords.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
/* Buffer everything so a stray notice can never corrupt the JSON answer. */
ob_start();

/** JSON answer + exit. */
function pieStripeApiRespond($ok, $message, array $extra = array())
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array_merge(array('success' => (bool) $ok, 'message' => (string) $message), $extra));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    pieStripeApiRespond(false, 'This endpoint only accepts POST requests.');
}

/* The page posts JSON; classic form posts are accepted too. */
$rawBody = file_get_contents('php://input');
$payload = json_decode((string) $rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$action      = isset($payload['action']) ? strtolower(trim((string) $payload['action'])) : '';
$csrfToken   = isset($payload['csrf_token']) ? (string) $payload['csrf_token'] : '';
$reference   = isset($payload['payment_intent_id']) ? trim((string) $payload['payment_intent_id']) : '';
if ($reference === '' && isset($payload['reference'])) {
    $reference = trim((string) $payload['reference']);
}
$expectedAmt = isset($payload['expected_amount']) ? (string) $payload['expected_amount'] : '';
$service     = isset($payload['service']) ? mb_substr(sanitize($payload['service']), 0, 191) : '';
$buyerName   = isset($payload['name']) ? mb_substr(sanitize($payload['name']), 0, 150) : '';
$buyerEmail  = isset($payload['email']) ? mb_substr(sanitize($payload['email']), 0, 150) : '';

if (!in_array($action, array('create', 'confirm', 'status'), true)) {
    pieStripeApiRespond(false, 'Unknown payment action.');
}

if ($action === 'status') {
    pieStripeApiRespond(true, 'Stripe server-side confirmation is ' . (pieStripeServerReady() ? 'available.' : 'not configured.'), array(
        'serverVerification' => pieStripeServerReady(),
        'currency'           => 'USD',
    ));
}

/* Every money-moving action requires this session's CSRF token. */
if (!validateCSRF($csrfToken)) {
    http_response_code(403);
    pieStripeApiRespond(false, 'Your session expired. Please refresh the page and try again.');
}

if (!pieStripeServerReady()) {
    pieStripeApiRespond(false, 'Server-side Stripe confirmation is not configured yet. Add the Stripe Secret Key in Admin → Payments.');
}

/* Bound how often one visitor can hit Stripe through this endpoint. */
if (class_exists('Ratelimit') && !Ratelimit::allow('stripe:' . pieClientIp(), 40, 600)) {
    http_response_code(429);
    pieStripeApiRespond(false, 'Too many payment attempts. Please wait a few minutes and try again.');
}

if ($action === 'create') {
    $cents = pieStripeAmountCents($expectedAmt);
    if ($cents <= 0) {
        pieStripeApiRespond(false, 'Enter an amount greater than zero.');
    }
    $created = pieStripeCreatePaymentIntent($expectedAmt, $service, $buyerName, $buyerEmail);
    if (!$created['ok']) {
        pieStripeApiRespond(false, $created['error'] !== '' ? $created['error'] : 'Stripe could not start this payment.');
    }
    pieStripeApiRespond(true, 'Payment ready.', array(
        'payment_intent_id' => $created['id'],
        'client_secret'     => $created['client_secret'],
        'amount'            => pieStripeFormatAmount($created['amount_cents']),
        'currency'          => 'USD',
    ));
}

/* action=confirm — verify the payment with Stripe and report it. */
if ($reference === '') {
    pieStripeApiRespond(false, 'Stripe did not return a payment reference for this payment.');
}

$confirmed = pieStripeConfirmPayment($reference, $expectedAmt);
if (!$confirmed['ok']) {
    pieStripeApiRespond(false, $confirmed['error'] !== '' ? $confirmed['error'] : 'The payment could not be verified.', array(
        'confirmed' => false,
        'status'    => $confirmed['confirmation']['status'],
    ));
}

$confirmation = $confirmed['confirmation'];
$displayName  = $confirmation['name'] !== '' ? $confirmation['name'] : ($buyerName !== '' ? $buyerName : 'there');
$displayService = $confirmation['service'] !== '' ? $confirmation['service'] : $service;
$message = 'Thank You, ' . $displayName . '! Your payment of $' . $confirmation['amount'] . ' ' . $confirmation['currency']
    . ' was successfully completed. Payment Reference: ' . $confirmation['reference'];

/* Record the confirmed payment exactly once (a failure never affects the buyer). */
$recordId = piePaymentRecord(array(
    'provider'                => 'stripe',
    'provider_transaction_id' => $confirmation['provider_transaction_id'],
    'payer_name'              => $displayName,
    'payer_email'             => $confirmation['email'] !== '' ? $confirmation['email'] : $buyerEmail,
    'service'                 => $displayService,
    'amount'                  => $confirmation['amount'],
    'currency'                => 'USD',
    'status'                  => 'succeeded',
    'verification_mode'       => 'server',
    'raw_reference'           => $confirmation['reference'],
    'ip_address'              => pieClientIp(),
));

pieStripeApiRespond(true, $message, array(
    'confirmed' => true,
    'name'      => $displayName,
    'amount'    => $confirmation['amount'],
    'currency'  => 'USD',
    'reference' => $confirmation['reference'],
    'service'   => $displayService,
    'provider'  => 'stripe',
    'status'    => $confirmation['status'],
    'record_id' => $recordId,
));
