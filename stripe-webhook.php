<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Stripe webhook endpoint
 * ---------------------------------------------------------------------------
 *  Register this URL in the Stripe dashboard (Developers → Webhooks):
 *
 *      https://your-domain.com/stripe-webhook
 *      (also reachable as /api/stripe/webhook)
 *
 *  Events to send:  payment_intent.succeeded
 *                   checkout.session.completed
 *
 *  Every delivery is authenticated with the Webhook Secret (`whsec_…`) stored
 *  in Admin → Payments: the Stripe-Signature header is verified (HMAC-SHA256
 *  over "<timestamp>.<body>", with a 5-minute tolerance) before the event is
 *  looked at. A payment is only recorded when Stripe reports a succeeded USD
 *  payment, and the same Stripe payment id is never recorded twice — so
 *  Stripe's retries (or a customer closing the tab before the browser
 *  confirmed) can never double-count a payment.
 *
 *  Responses are always JSON. The Webhook Secret and the Secret Key are never
 *  echoed, logged or returned.
 */

if (!defined('DB_OK')) {
    require_once __DIR__ . '/includes/init.php';
}
require_once __DIR__ . '/includes/payments.php';
require_once __DIR__ . '/core/PaymentRecords.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ob_start();

/** JSON answer + exit (a webhook must always answer with a status). */
function pieStripeWebhookRespond($statusCode, $ok, $message, array $extra = array())
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code((int) $statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array_merge(array('success' => (bool) $ok, 'message' => (string) $message), $extra));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    pieStripeWebhookRespond(405, false, 'This endpoint only accepts POST requests from Stripe.');
}

if (!pieStripeWebhookSecretConfigured()) {
    /* Nothing can be trusted without the signing secret. */
    pieStripeWebhookRespond(503, false, 'Stripe webhooks are not configured yet. Add the Stripe Webhook Secret in Admin → Payments.');
}

$payload   = (string) file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? (string) $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

if (!pieStripeVerifyWebhookSignature($payload, $signature)) {
    error_log('[TPT] Stripe webhook rejected: invalid signature.');
    pieStripeWebhookRespond(400, false, 'Invalid Stripe signature.');
}

$event = json_decode($payload, true);
if (!is_array($event) || empty($event['type'])) {
    pieStripeWebhookRespond(400, false, 'Invalid webhook payload.');
}
$eventType = (string) $event['type'];
$eventId   = isset($event['id']) ? (string) $event['id'] : '';

$payment = pieStripeWebhookPayment($event);
if (!$payment['ok']) {
    /* Signature-verified, but not a completed payment (or an event we do not
       record). Answer 200 so Stripe stops retrying, and say why. */
    pieStripeWebhookRespond(200, true, 'Event received and ignored.', array(
        'recorded'   => false,
        'event'      => $eventType,
        'reason'     => $payment['error'],
    ));
}

$confirmation = $payment['confirmation'];
/* Duplicate protection: the same Stripe payment id is never recorded twice,
   however often Stripe re-delivers the event. */
$alreadyRecorded = piePaymentRecordFind('stripe', $confirmation['provider_transaction_id']) > 0;
$recordId = piePaymentRecord(array(
    'provider'                => 'stripe',
    'provider_transaction_id' => $confirmation['provider_transaction_id'],
    'payer_name'              => $confirmation['name'],
    'payer_email'             => $confirmation['email'],
    'service'                 => $confirmation['service'],
    'amount'                  => $confirmation['amount'],
    'currency'                => 'USD',
    'status'                  => 'succeeded',
    'verification_mode'       => 'server',
    'raw_reference'           => $eventId !== '' ? $eventId : $confirmation['reference'],
    'ip_address'              => pieClientIp(),
));

pieStripeWebhookRespond(200, true, $alreadyRecorded ? 'Payment was already recorded.' : 'Payment recorded.', array(
    'recorded'    => !$alreadyRecorded,
    'duplicate'   => $alreadyRecorded,
    'record_id'   => $recordId,
    'event'       => $eventType,
    'reference'   => $confirmation['reference'],
    'amount'      => $confirmation['amount'],
    'currency'    => 'USD',
));
