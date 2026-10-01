<?php
/** PayPal server-side checkout endpoint.
 * POST creates an Order and redirects to PayPal. PayPal's return is captured
 * and verified here with the Secret before an Admin payment record is created.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function piePayPalCheckoutFail($message, $status = 400)
{
    http_response_code((int) $status);
    piePaymentSetNotice('error', $message);
    piePaymentRedirectToForm();
}

function piePayPalCheckoutSuccess(array $attempt, $transactionId, $amount)
{
    piePaymentSetNotice('success', '', array(
        'name' => (string) ($attempt['name'] ?? ''),
        'amount' => (string) $amount,
        'reference' => (string) $transactionId,
    ));
    piePaymentRedirectToForm();
}

function piePayPalCheckoutStart()
{
    if (!validateCSRF()) { piePayPalCheckoutFail('Your session expired. Refresh the page and try again.', 403); }
    if (!pieIsPayPalEnabled() || !piePayPalServerReady()) {
        piePayPalCheckoutFail('PayPal is not currently available. Please choose another payment option or contact us.', 503);
    }
    if (class_exists('Ratelimit') && !Ratelimit::allow('paypal-checkout:' . pieClientIp(), 10, 600)) {
        piePayPalCheckoutFail('Too many payment attempts. Please wait a few minutes and try again.', 429);
    }
    $validated = piePaymentValidateSubmission($_POST);
    if (!$validated['ok']) { piePayPalCheckoutFail($validated['error'], 422); }
    if (!piePaymentStorageReady()) {
        piePayPalCheckoutFail('Payments are temporarily unavailable. Please contact our team before trying again.', 503);
    }

    $createdAttempt = piePaymentCreateAttempt('paypal', $validated['data']);
    if (!$createdAttempt['ok']) { piePayPalCheckoutFail($createdAttempt['error'], 503); }
    $attempt = $createdAttempt['attempt'];

    $endpointUrl = rtrim(SITE_URL, '/') . url('paypal-api');
    $returnUrl = $endpointUrl . '?flow=return';
    $cancelUrl = $endpointUrl . '?flow=cancel&payment_token=' . rawurlencode($attempt['token']);
    $order = piePayPalCreateOrder(
        $attempt['amount_usd'],
        $attempt['service'],
        $attempt['name'],
        $attempt['email'],
        $returnUrl,
        $cancelUrl,
        $attempt['token']
    );
    if (!$order['ok']) {
        piePaymentMarkAttempt($attempt, 'failed');
        piePayPalCheckoutFail('PayPal could not start your checkout. Please try again or contact our team.', 502);
    }
    if (!piePaymentSetAttemptReference($attempt, $order['id'])) {
        piePaymentMarkAttempt($attempt, 'failed');
        piePayPalCheckoutFail('The PayPal checkout could not be linked to your payment record. Please contact our team.', 503);
    }

    header('Location: ' . $order['approval_url'], true, 303);
    exit;
}

function piePayPalCheckoutReturn()
{
    $orderId = trim((string) ($_GET['token'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9-]{6,50}$/D', $orderId)) {
        piePayPalCheckoutFail('PayPal returned an invalid payment reference. No successful payment was recorded.', 400);
    }
    $attempt = piePaymentFindAttemptByReference('paypal', $orderId);
    if (!$attempt) {
        piePayPalCheckoutFail('We could not match the PayPal return to a payment request. Please contact us before trying again.', 404);
    }
    if ((string) ($attempt['status'] ?? '') === 'paid' && !empty($attempt['provider_ref'])) {
        piePayPalCheckoutSuccess($attempt, $attempt['provider_ref'], $attempt['amount_usd']);
    }
    if ((string) ($attempt['status'] ?? '') !== 'pending') {
        piePayPalCheckoutFail('This PayPal payment request is no longer active. No successful payment was recorded.', 409);
    }
    if (!piePayPalServerReady()) {
        piePayPalCheckoutFail('PayPal confirmation is temporarily unavailable. Please contact us with your PayPal order reference.', 503);
    }

    $capture = piePayPalCaptureOrder($orderId, $attempt['amount_usd'] ?? '', $attempt['token'] ?? '');
    if (!$capture['ok'] || empty($capture['confirmation']['confirmed'])) {
        piePayPalCheckoutFail('PayPal did not confirm this payment. No successful payment was recorded. You may try again or contact our team.', 402);
    }
    $confirmation = $capture['confirmation'];
    if ((string) ($confirmation['order_id'] ?? '') !== $orderId
        || strtoupper((string) ($confirmation['currency'] ?? '')) !== 'USD') {
        piePayPalCheckoutFail('PayPal returned a payment that did not match this order. No successful payment was recorded.', 402);
    }

    $completed = piePaymentCompleteAttempt($attempt, 'paypal', $confirmation, $orderId);
    if (!$completed['ok']) {
        piePayPalCheckoutFail($completed['error'], 503);
    }
    piePayPalCheckoutSuccess($attempt, $confirmation['reference'], $confirmation['amount']);
}

function piePayPalCheckoutCancel()
{
    $attempt = null;
    $paymentToken = strtolower(trim((string) ($_GET['payment_token'] ?? '')));
    if ($paymentToken !== '') {
        $attempt = piePaymentFindAttemptByToken($paymentToken);
    }
    if (!$attempt && !empty($_GET['token'])) {
        $attempt = piePaymentFindAttemptByReference('paypal', (string) $_GET['token']);
    }
    if ($attempt && strtolower((string) ($attempt['method'] ?? '')) === 'paypal') {
        piePaymentMarkAttempt($attempt, 'cancelled');
    }
    piePaymentSetNotice('cancelled', 'Your PayPal payment was cancelled. No successful payment was recorded. You can try again whenever you are ready.');
    piePaymentRedirectToForm();
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST') {
    if (strtolower(trim((string) ($_POST['payment_action'] ?? ''))) !== 'start') {
        piePayPalCheckoutFail('This PayPal endpoint only accepts a payment form submission.', 405);
    }
    piePayPalCheckoutStart();
}
if ($method === 'GET') {
    $flow = strtolower(trim((string) ($_GET['flow'] ?? '')));
    if ($flow === 'return') { piePayPalCheckoutReturn(); }
    if ($flow === 'cancel') { piePayPalCheckoutCancel(); }
}

http_response_code(405);
header('Allow: POST, GET');
piePaymentSetNotice('error', 'This PayPal endpoint is only used during checkout.');
piePaymentRedirectToForm();
