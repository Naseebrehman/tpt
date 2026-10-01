<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — server-side PayPal endpoint
 * ---------------------------------------------------------------------------
 *  POST only. Called by the Pay Online page (window.TPT_PAYPAL) so that an
 *  approved PayPal order is CREATED and CAPTURED on the server with the PayPal
 *  Secret from Admin → Payments. The Secret never leaves this server.
 *
 *  Actions
 *    action=create   → creates an order for the amount the buyer entered
 *                      (shipping_preference is always NO_SHIPPING)
 *    action=capture  → captures AND verifies an approved order; the amount is
 *                      read from PayPal's response (never from the browser)
 *    action=status   → reports whether server-side verification is configured
 *
 *  Answers are always JSON: {"success":bool, "message":string, ...}. A payment
 *  is only reported as confirmed when PayPal itself reports a COMPLETED
 *  capture — nothing here trusts the visitor's browser.
 *
 *  Output never contains the PayPal Secret, an API key or a stack trace.
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
function piePayPalApiRespond($ok, $message, array $extra = array())
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
    piePayPalApiRespond(false, 'This endpoint only accepts POST requests.');
}

/* The page posts JSON; classic form posts are accepted too. */
$rawBody = file_get_contents('php://input');
$payload = json_decode((string) $rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$action       = isset($payload['action']) ? strtolower(trim((string) $payload['action'])) : '';
$csrfToken    = isset($payload['csrf_token']) ? (string) $payload['csrf_token'] : '';
$orderId      = isset($payload['order_id']) ? trim((string) $payload['order_id']) : '';
$expectedAmt  = isset($payload['expected_amount']) ? (string) $payload['expected_amount'] : '';
$service      = isset($payload['service']) ? mb_substr(sanitize($payload['service']), 0, 127) : '';
$buyerName    = isset($payload['name']) ? mb_substr(sanitize($payload['name']), 0, 150) : '';
$buyerEmail   = isset($payload['email']) ? mb_substr(sanitize($payload['email']), 0, 150) : '';
$buyerPhone   = isset($payload['phone']) ? mb_substr(sanitize($payload['phone']), 0, 30) : '';

if (!in_array($action, array('create', 'capture', 'status'), true)) {
    piePayPalApiRespond(false, 'Unknown payment action.');
}

if ($action === 'status') {
    piePayPalApiRespond(true, 'PayPal server-side verification is ' . (piePayPalServerReady() ? 'available.' : 'not configured.'), array(
        'serverVerification' => piePayPalServerReady(),
        'currency'           => 'USD',
    ));
}

/* Every money-moving action requires this session's CSRF token. */
if (!validateCSRF($csrfToken)) {
    http_response_code(403);
    piePayPalApiRespond(false, 'Your session expired. Please refresh the page and try again.');
}

if (!piePayPalServerReady()) {
    piePayPalApiRespond(false, 'Server-side PayPal verification is not configured yet. Add the PayPal Client ID and Secret in Admin → Payments.');
}

/* Bound how often one visitor can hit PayPal through this endpoint. */
if (class_exists('Ratelimit') && !Ratelimit::allow('paypal:' . pieClientIp(), 40, 600)) {
    http_response_code(429);
    piePayPalApiRespond(false, 'Too many payment attempts. Please wait a few minutes and try again.');
}

if ($action === 'create') {
    $amount = piePayPalAmount($expectedAmt);
    if ($amount === '') {
        piePayPalApiRespond(false, 'Enter an amount greater than zero.');
    }
    $created = piePayPalCreateOrder($amount, $service, $buyerName, $buyerEmail);
    if (!$created['ok']) {
        piePayPalApiRespond(false, $created['error'] !== '' ? $created['error'] : 'PayPal could not start this payment.');
    }
    piePayPalApiRespond(true, 'Order created.', array('order_id' => $created['id'], 'amount' => $amount, 'currency' => 'USD'));
}

/* action=capture — verify an approved order and report the confirmed payment. */
if ($orderId === '') {
    piePayPalApiRespond(false, 'PayPal did not return an order id for this payment.');
}

$capture = piePayPalCaptureOrder($orderId, $expectedAmt);
if (!$capture['ok']) {
    piePayPalApiRespond(false, $capture['error'] !== '' ? $capture['error'] : 'The payment could not be verified.');
}

$confirmation = piePayPalConfirmation($capture['data']);
if (!$confirmation['confirmed']) {
    /* PENDING and other non-final states are NOT reported as successful. */
    piePayPalApiRespond(false, 'PayPal has not completed this payment yet (status: ' . ($confirmation['status'] !== '' ? strtolower($confirmation['status']) : 'unknown') . ').', array(
        'confirmed' => false,
        'status'    => $confirmation['status'],
    ));
}

$displayName = $confirmation['name'] !== '' ? $confirmation['name'] : ($buyerName !== '' ? $buyerName : 'there');
$message = 'Thank You, ' . $displayName . '! Your payment of $' . $confirmation['amount'] . ' ' . $confirmation['currency']
    . ' was successfully completed. Payment Reference: ' . $confirmation['reference'];

/* Existing tables, best effort — a logging failure never affects the buyer. */
piePayPalRecord($capture['data'], $service, $displayName, $buyerEmail, $buyerPhone);

/* The dashboard's payment record, written EXACTLY ONCE per PayPal capture
   (the same capture id is never recorded twice, whatever the browser sends).
   The payer email PayPal itself reports is preferred over the typed one. */
$recordEmail = $buyerEmail;
if ($recordEmail === '' && !empty($capture['data']['payer']['email_address'])) {
    $recordEmail = mb_substr(sanitize($capture['data']['payer']['email_address']), 0, 150);
}
$recordId = piePaymentRecord(array(
    'provider'                => 'paypal',
    'provider_transaction_id' => $confirmation['reference'],
    'payer_name'              => $displayName,
    'payer_email'             => $recordEmail,
    'service'                 => $service,
    'amount'                  => $confirmation['amount'],
    'currency'                => $confirmation['currency'],
    'status'                  => 'succeeded',
    'verification_mode'       => 'server',
    'raw_reference'           => $orderId,
    'ip_address'              => pieClientIp(),
));

piePayPalApiRespond(true, $message, array(
    'confirmed' => true,
    'name'      => $displayName,
    'amount'    => $confirmation['amount'],
    'currency'  => $confirmation['currency'],
    'reference' => $confirmation['reference'],
    'status'    => $confirmation['status'],
    'service'   => $service,
    'provider'  => 'paypal',
    'record_id' => $recordId,
    'details'   => $capture['data'],
));
