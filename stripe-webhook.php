<?php
/** Signature-verified Stripe Checkout webhook.
 * Configure /stripe-webhook in Stripe and subscribe to checkout.session.completed
 * plus payment_intent.succeeded. Only TPT-created pending attempts are recorded.
 */
if (!defined('DB_OK')) {
    require_once __DIR__ . '/includes/init.php';
}
require_once __DIR__ . '/includes/payments.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ob_start();

function pieStripeWebhookRespond($status, $ok, $message, array $extra = array())
{
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        http_response_code((int) $status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array_merge(array('success' => (bool) $ok, 'message' => (string) $message), $extra));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    pieStripeWebhookRespond(405, false, 'This endpoint only accepts signed POST requests from Stripe.');
}
if (!pieStripeWebhookSecretConfigured()) {
    pieStripeWebhookRespond(503, false, 'The Stripe webhook signing secret is not configured.');
}

$payload = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
if (!pieStripeVerifyWebhookSignature($payload, $signature)) {
    error_log('[TPT] Stripe webhook rejected because its signature was invalid.');
    pieStripeWebhookRespond(400, false, 'Invalid Stripe signature.');
}

$event = json_decode($payload, true);
if (!is_array($event) || !is_array($event['data']['object'] ?? null)) {
    pieStripeWebhookRespond(400, false, 'Invalid Stripe event payload.');
}
$eventType = (string) ($event['type'] ?? '');
$object = $event['data']['object'];

if ($eventType === 'checkout.session.completed' || $eventType === 'checkout.session.async_payment_succeeded') {
    $sessionId = trim((string) ($object['id'] ?? ''));
    $attempt = piePaymentFindAttemptByReference('stripe', $sessionId);
    if (!$attempt || strtolower((string) ($attempt['token'] ?? '')) !== strtolower((string) ($object['client_reference_id'] ?? ''))) {
        pieStripeWebhookRespond(200, true, 'Event does not match a TPT payment attempt; ignored.', array('recorded' => false));
    }
    $verified = pieStripeConfirmCheckoutSession($sessionId, $attempt);
    if (!$verified['ok'] || empty($verified['confirmation']['confirmed'])) {
        /* Stripe retries transient lookup/storage errors; the browser return
           path independently performs the same server-to-server verification. */
        pieStripeWebhookRespond(500, false, 'Stripe payment verification is temporarily unavailable.', array('recorded' => false));
    }
    $confirmation = $verified['confirmation'];
    $rawReference = $sessionId;
} elseif ($eventType === 'payment_intent.succeeded') {
    $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : array();
    if ((string) ($metadata['integration'] ?? '') !== 'tpt-pay-online-v1') {
        pieStripeWebhookRespond(200, true, 'Event is not a TPT payment; ignored.', array('recorded' => false));
    }
    $attempt = piePaymentFindAttemptByToken((string) ($metadata['payment_token'] ?? ''));
    if (!$attempt || strtolower((string) ($attempt['method'] ?? '')) !== 'stripe') {
        pieStripeWebhookRespond(200, true, 'Event does not match a TPT payment attempt; ignored.', array('recorded' => false));
    }
    $verified = pieStripeConfirmPaymentIntent((string) ($object['id'] ?? ''), $attempt);
    if (!$verified['ok'] || empty($verified['confirmation']['confirmed'])) {
        pieStripeWebhookRespond(500, false, 'Stripe payment verification is temporarily unavailable.', array('recorded' => false));
    }
    $confirmation = $verified['confirmation'];
    $rawReference = (string) ($attempt['reference'] ?? '');
} else {
    pieStripeWebhookRespond(200, true, 'Event received and ignored.', array('recorded' => false, 'event' => $eventType));
}

$completed = piePaymentCompleteAttempt($attempt, 'stripe', $confirmation, $rawReference);
if (!$completed['ok']) {
    error_log('[TPT] Confirmed Stripe payment could not be saved to the payment records.');
    pieStripeWebhookRespond(500, false, 'Confirmed payment could not be saved yet; Stripe may retry this delivery.', array('recorded' => false));
}

pieStripeWebhookRespond(200, true, 'Payment recorded.', array(
    'recorded' => true,
    'record_id' => (int) $completed['record_id'],
    'reference' => (string) $confirmation['reference'],
    'amount' => (string) $confirmation['amount'],
    'currency' => 'USD',
));
