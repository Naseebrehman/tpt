<?php
/** Stripe server-side hosted Checkout endpoint.
 * POST creates a card-only Stripe Checkout Session and redirects to Stripe.
 * The return route retrieves the Session and PaymentIntent directly from Stripe
 * before writing a successful payment record.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function pieStripeCheckoutFail($message, $status = 400)
{
    http_response_code((int) $status);
    piePaymentSetNotice('error', $message);
    piePaymentRedirectToForm();
}

function pieStripeCheckoutSuccess(array $attempt, array $confirmation)
{
    piePaymentSetNotice('success', '', array(
        'name' => (string) ($attempt['name'] ?? ''),
        'amount' => (string) ($confirmation['amount'] ?? ''),
        'reference' => (string) ($confirmation['reference'] ?? ''),
    ));
    piePaymentRedirectToForm();
}

function pieStripeCheckoutStart()
{
    if (!validateCSRF()) { pieStripeCheckoutFail('Your session expired. Refresh the page and try again.', 403); }
    if (!pieIsStripeEnabled() || !pieStripeServerReady()) {
        pieStripeCheckoutFail('Stripe is not currently available. Please choose another payment option or contact us.', 503);
    }
    if (class_exists('Ratelimit') && !Ratelimit::allow('stripe-checkout:' . pieClientIp(), 10, 600)) {
        pieStripeCheckoutFail('Too many payment attempts. Please wait a few minutes and try again.', 429);
    }
    $validated = piePaymentValidateSubmission($_POST);
    if (!$validated['ok']) { pieStripeCheckoutFail($validated['error'], 422); }
    if (!piePaymentStorageReady()) {
        pieStripeCheckoutFail('Payments are temporarily unavailable. Please contact our team before trying again.', 503);
    }

    $createdAttempt = piePaymentCreateAttempt('stripe', $validated['data']);
    if (!$createdAttempt['ok']) { pieStripeCheckoutFail($createdAttempt['error'], 503); }
    $attempt = $createdAttempt['attempt'];

    $endpointUrl = rtrim(SITE_URL, '/') . url('stripe-api');
    $successUrl = $endpointUrl . '?flow=return&session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl = $endpointUrl . '?flow=cancel&payment_token=' . rawurlencode($attempt['token']);
    $session = pieStripeCreateCheckoutSession($attempt, $successUrl, $cancelUrl);
    if (!$session['ok']) {
        piePaymentMarkAttempt($attempt, 'failed');
        pieStripeCheckoutFail('Stripe could not start your checkout. Please try again or contact our team.', 502);
    }
    if (!piePaymentSetAttemptReference($attempt, $session['id'])) {
        piePaymentMarkAttempt($attempt, 'failed');
        pieStripeCheckoutFail('The Stripe checkout could not be linked to your payment record. Please contact our team.', 503);
    }

    header('Location: ' . $session['url'], true, 303);
    exit;
}

function pieStripeCheckoutReturn()
{
    $sessionId = trim((string) ($_GET['session_id'] ?? ''));
    if (!pieIsValidStripeCheckoutSessionId($sessionId)) {
        pieStripeCheckoutFail('Stripe returned an invalid payment reference. No successful payment was recorded.', 400);
    }
    $attempt = piePaymentFindAttemptByReference('stripe', $sessionId);
    if (!$attempt) {
        pieStripeCheckoutFail('We could not match the Stripe return to a payment request. Please contact us before trying again.', 404);
    }
    if ((string) ($attempt['status'] ?? '') === 'paid' && !empty($attempt['provider_ref'])) {
        pieStripeCheckoutSuccess($attempt, array(
            'amount' => (string) ($attempt['amount_usd'] ?? ''),
            'reference' => (string) $attempt['provider_ref'],
        ));
    }
    if ((string) ($attempt['status'] ?? '') !== 'pending') {
        pieStripeCheckoutFail('This Stripe payment request is no longer active. No successful payment was recorded.', 409);
    }
    if (!pieStripeServerReady()) {
        pieStripeCheckoutFail('Stripe confirmation is temporarily unavailable. Please contact us with your Checkout reference.', 503);
    }

    $verified = pieStripeConfirmCheckoutSession($sessionId, $attempt);
    if (!$verified['ok'] || empty($verified['confirmation']['confirmed'])) {
        pieStripeCheckoutFail('Stripe did not confirm this payment. No successful payment was recorded. You may try again or contact our team.', 402);
    }
    $confirmation = $verified['confirmation'];
    $completed = piePaymentCompleteAttempt($attempt, 'stripe', $confirmation, $sessionId);
    if (!$completed['ok']) {
        pieStripeCheckoutFail($completed['error'], 503);
    }
    pieStripeCheckoutSuccess($attempt, $confirmation);
}

function pieStripeCheckoutCancel()
{
    $attempt = null;
    $paymentToken = strtolower(trim((string) ($_GET['payment_token'] ?? '')));
    if ($paymentToken !== '') { $attempt = piePaymentFindAttemptByToken($paymentToken); }
    if ($attempt && strtolower((string) ($attempt['method'] ?? '')) === 'stripe') {
        piePaymentMarkAttempt($attempt, 'cancelled');
    }
    piePaymentSetNotice('cancelled', 'Your Stripe payment was cancelled. No successful payment was recorded. You can try again whenever you are ready.');
    piePaymentRedirectToForm();
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST') {
    if (strtolower(trim((string) ($_POST['payment_action'] ?? ''))) !== 'start') {
        pieStripeCheckoutFail('This Stripe endpoint only accepts a payment form submission.', 405);
    }
    pieStripeCheckoutStart();
}
if ($method === 'GET') {
    $flow = strtolower(trim((string) ($_GET['flow'] ?? '')));
    if ($flow === 'return') { pieStripeCheckoutReturn(); }
    if ($flow === 'cancel') { pieStripeCheckoutCancel(); }
}

http_response_code(405);
header('Allow: POST, GET');
piePaymentSetNotice('error', 'This Stripe endpoint is only used during checkout.');
piePaymentRedirectToForm();
