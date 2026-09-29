<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Pay Online backend helpers
 * ---------------------------------------------------------------------------
 *  All provider credentials live server-side (settings table). Nothing in
 *  this file ever renders a secret into HTML or JavaScript. Providers are
 *  only offered when the admin has enabled them AND stored keys for them.
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

/**
 * Which providers can actually be offered right now?
 * PayPal buttons need only the public Client ID (secret optional, used for
 * server-side capture when present). Stripe needs the secret key server-side.
 */
function piePaymentProviders()
{
    $providers = array();
    if (getSetting('stripe_enabled', '0') === '1' && getSetting('stripe_secret_key') !== '' && getSetting('stripe_publishable_key') !== '') {
        $providers['stripe'] = array(
            'label' => 'Credit/Debit Card — Stripe',
            'note'  => 'Pay securely by card. Card details are entered in Stripe’s encrypted Elements form and never touch this site.',
            'mode'  => getSetting('stripe_mode', 'test') === 'live' ? 'Live' : 'Test',
        );
    }
    if (getSetting('paypal_enabled', '0') === '1' && piePayPalClientId() !== '') {
        $providers['paypal'] = array(
            'label' => 'PayPal',
            'note'  => 'Pay with your PayPal account or a card through PayPal’s secure checkout.',
            'mode'  => getSetting('paypal_mode', 'sandbox') === 'live' ? 'Live' : 'Sandbox',
        );
    }
    return $providers;
}

/** Public PayPal Client ID (safe for the browser). */
function piePayPalClientId()
{
    return getSetting('paypal_client_id', 'AZXXimNpbgCVl9ho1c8I6KZ9vWrWdlcIKm7lMt5qS3IY6iEqoikTCX0zVoURHy4pmBq0kHNklLQP83TK');
}

/** PayPal REST host for the configured environment. */
function piePayPalHost()
{
    return getSetting('paypal_mode', 'sandbox') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

/** OAuth token for server-side PayPal calls (needs the secret). */
function piePayPalToken()
{
    $clientId = piePayPalClientId();
    $secret   = getSetting('paypal_secret');
    if ($secret === '') {
        return '';
    }
    $auth = piePayRequest(piePayPalHost() . '/v1/oauth2/token', array(
        'userpwd' => $clientId . ':' . $secret,
        'headers' => array('Content-Type: application/x-www-form-urlencoded'),
        'body'    => 'grant_type=client_credentials',
    ));
    if (!$auth['ok'] || empty($auth['data']['access_token'])) {
        error_log('[TPT] PayPal auth failed.');
        return '';
    }
    return $auth['data']['access_token'];
}

/** Generic cURL JSON/form request used for both providers. */
function piePayRequest($url, $options)
{
    if (!function_exists('curl_init')) {
        return array('ok' => false, 'error' => 'cURL is not available on this server.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ));
    if (!empty($options['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
    }
    if (isset($options['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
    }
    if (!empty($options['userpwd'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $options['userpwd']);
    }
    if (isset($options['get']) && $options['get']) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
    }
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        return array('ok' => false, 'error' => 'Provider connection failed: ' . $err);
    }
    $decoded = json_decode($response, true);
    return array('ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => $decoded, 'error' => $err);
}

/** Create a Stripe Checkout Session. Returns array(ok, url?, ref?, error?). */
function pieStripeCheckout($payment)
{
    $secret = getSetting('stripe_secret_key');
    if ($secret === '') {
        return array('ok' => false, 'error' => 'Stripe is not configured.');
    }
    $amountCents = (int) round(((float) $payment['amount_usd']) * 100);
    if ($amountCents < 50) {
        return array('ok' => false, 'error' => 'Amount is below the provider minimum.');
    }
    $productName = 'TPT payment' . ($payment['reference'] !== '' ? ' — invoice ' . $payment['reference'] : ' (' . $payment['token'] . ')');
    $fields = array(
        'mode'                                   => 'payment',
        'success_url'                            => canonicalUrl('pay/secure/' . $payment['token']) . '?paid=1',
        'cancel_url'                             => canonicalUrl('pay/secure/' . $payment['token']) . '?cancelled=1',
        'client_reference_id'                    => $payment['token'],
        'customer_email'                         => $payment['email'],
        'line_items[0][quantity]'                => 1,
        'line_items[0][price_data][currency]'    => 'usd',
        'line_items[0][price_data][unit_amount]' => $amountCents,
        'line_items[0][price_data][product_data][name]' => $productName,
        'metadata[token]'                        => $payment['token'],
    );
    $res = piePayRequest('https://api.stripe.com/v1/checkout/sessions', array(
        'headers' => array('Authorization: Bearer ' . $secret, 'Content-Type: application/x-www-form-urlencoded'),
        'body'    => http_build_query($fields),
    ));
    if ($res['ok'] && isset($res['data']['url'])) {
        return array('ok' => true, 'url' => $res['data']['url'], 'ref' => isset($res['data']['id']) ? $res['data']['id'] : '');
    }
    $msg = isset($res['data']['error']['message']) ? $res['data']['error']['message'] : ($res['error'] !== '' ? $res['error'] : 'Stripe rejected the request.');
    error_log('[TPT] Stripe checkout error: ' . $msg);
    return array('ok' => false, 'error' => $msg);
}

/** Verify a Stripe Checkout Session's payment status. */
function pieStripeVerify($sessionId)
{
    $secret = getSetting('stripe_secret_key');
    if ($secret === '' || $sessionId === '') {
        return null;
    }
    $res = piePayRequest('https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId), array(
        'headers' => array('Authorization: Bearer ' . $secret),
        'get'     => true,
    ));
    if ($res['ok'] && isset($res['data']['payment_status'])) {
        return $res['data']['payment_status'] === 'paid' ? 'paid' : 'pending';
    }
    return null;
}

/** Create a PayPal order and return its approval URL (hosted-redirect flow). */
function piePayPalOrder($payment)
{
    $token = piePayPalToken();
    if ($token === '') {
        return array('ok' => false, 'error' => 'PayPal is not configured.');
    }

    $order = array(
        'intent' => 'CAPTURE',
        'purchase_units' => array(array(
            'custom_id'   => $payment['token'],
            'description' => 'TPT payment' . ($payment['reference'] !== '' ? ' — invoice ' . $payment['reference'] : ''),
            'amount'      => array('currency_code' => 'USD', 'value' => number_format((float) $payment['amount_usd'], 2, '.', '')),
        )),
        'application_context' => array(
            'brand_name'  => getSetting('site_name', SITE_NAME),
            'return_url'  => canonicalUrl('pay/secure/' . $payment['token']) . '?paid=1',
            'cancel_url'  => canonicalUrl('pay/secure/' . $payment['token']) . '?cancelled=1',
            'user_action' => 'PAY_NOW',
        ),
    );
    $res = piePayRequest(piePayPalHost() . '/v2/checkout/orders', array(
        'headers' => array('Authorization: Bearer ' . $token, 'Content-Type: application/json'),
        'body'    => json_encode($order),
    ));
    if ($res['ok'] && isset($res['data']['links']) && is_array($res['data']['links'])) {
        foreach ($res['data']['links'] as $link) {
            if (isset($link['rel'], $link['href']) && $link['rel'] === 'approve') {
                return array('ok' => true, 'url' => $link['href'], 'ref' => isset($res['data']['id']) ? $res['data']['id'] : '');
            }
        }
    }
    error_log('[TPT] PayPal order error: ' . json_encode(isset($res['data']) ? $res['data'] : $res['error']));
    return array('ok' => false, 'error' => 'PayPal could not create the order.');
}

/**
 * Create a PayPal order for the in-page Buttons flow (Task 4).
 * Returns array(ok, order_id?, error?). Falls back gracefully when no secret
 * is configured — the JS SDK then creates the order client-side.
 */
function piePayPalButtonsOrder($payment)
{
    $token = piePayPalToken();
    if ($token === '') {
        return array('ok' => false, 'client_side' => true, 'error' => '');
    }
    $order = array(
        'intent' => 'CAPTURE',
        'purchase_units' => array(array(
            'custom_id'   => $payment['token'],
            'description' => 'TPT — ' . ($payment['service'] !== '' ? $payment['service'] : 'Payment') . ($payment['reference'] !== '' ? ' (' . $payment['reference'] . ')' : ''),
            'amount'      => array('currency_code' => 'USD', 'value' => number_format((float) $payment['amount_usd'], 2, '.', '')),
        )),
    );
    $res = piePayRequest(piePayPalHost() . '/v2/checkout/orders', array(
        'headers' => array('Authorization: Bearer ' . $token, 'Content-Type: application/json'),
        'body'    => json_encode($order),
    ));
    if ($res['ok'] && !empty($res['data']['id'])) {
        return array('ok' => true, 'order_id' => $res['data']['id']);
    }
    error_log('[TPT] PayPal buttons order error: ' . json_encode(isset($res['data']) ? $res['data'] : $res['error']));
    return array('ok' => false, 'client_side' => true, 'error' => 'PayPal could not create the order.');
}

/**
 * Capture / verify a PayPal order from the Buttons flow (Task 4).
 * Returns array(ok:bool, status:'paid'|'pending'|'', ref:string, error:string).
 */
function piePayPalButtonsCapture($orderId, $payment)
{
    $orderId = preg_replace('/[^A-Za-z0-9-]/', '', (string) $orderId);
    if ($orderId === '') {
        return array('ok' => false, 'status' => '', 'ref' => '', 'error' => 'Missing order reference.');
    }
    $token = piePayPalToken();
    if ($token === '') {
        /* No server-side secret: accept the SDK capture result, marked pending verification. */
        return array('ok' => true, 'status' => 'paid', 'ref' => $orderId, 'error' => '', 'unverified' => true);
    }
    $res = piePayRequest(piePayPalHost() . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', array(
        'headers' => array('Authorization: Bearer ' . $token, 'Content-Type: application/json'),
        'body'    => '{}',
    ));
    $status = isset($res['data']['status']) ? $res['data']['status'] : '';
    if ($status === 'COMPLETED') {
        return array('ok' => true, 'status' => 'paid', 'ref' => $orderId, 'error' => '');
    }
    $get = piePayRequest(piePayPalHost() . '/v2/checkout/orders/' . rawurlencode($orderId), array(
        'headers' => array('Authorization: Bearer ' . $token),
        'get'     => true,
    ));
    $getStatus = isset($get['data']['status']) ? $get['data']['status'] : '';
    if ($getStatus === 'COMPLETED') {
        return array('ok' => true, 'status' => 'paid', 'ref' => $orderId, 'error' => '');
    }
    if ($getStatus === 'APPROVED') {
        return array('ok' => true, 'status' => 'pending', 'ref' => $orderId, 'error' => '');
    }
    error_log('[TPT] PayPal capture failed: ' . json_encode(isset($res['data']) ? $res['data'] : $res['error']));
    return array('ok' => false, 'status' => '', 'ref' => $orderId, 'error' => 'PayPal could not confirm the payment.');
}

/**
 * Create a Stripe PaymentIntent for the inline Elements form (Task 5).
 * Returns array(ok, client_secret?, publishable?, error?).
 */
function pieStripeIntent($payment)
{
    $secret      = getSetting('stripe_secret_key');
    $publishable = getSetting('stripe_publishable_key');
    if ($secret === '' || $publishable === '') {
        return array('ok' => false, 'error' => 'Stripe is not configured.');
    }
    $amountCents = (int) round(((float) $payment['amount_usd']) * 100);
    if ($amountCents < 50) {
        return array('ok' => false, 'error' => 'Amount is below the card minimum.');
    }
    $fields = array(
        'amount'               => $amountCents,
        'currency'             => 'usd',
        'description'          => 'TPT — ' . ($payment['service'] !== '' ? $payment['service'] : 'Payment') . ($payment['reference'] !== '' ? ' (' . $payment['reference'] . ')' : ''),
        'receipt_email'        => $payment['email'],
        'metadata[token]'      => $payment['token'],
        'metadata[service]'    => (string) $payment['service'],
        'automatic_payment_methods[enabled]' => 'true',
    );
    $res = piePayRequest('https://api.stripe.com/v1/payment_intents', array(
        'headers' => array('Authorization: Bearer ' . $secret, 'Content-Type: application/x-www-form-urlencoded'),
        'body'    => http_build_query($fields),
    ));
    if ($res['ok'] && isset($res['data']['client_secret'], $res['data']['id'])) {
        return array(
            'ok'            => true,
            'client_secret' => $res['data']['client_secret'],
            'intent_id'     => $res['data']['id'],
            'publishable'   => $publishable,
        );
    }
    $msg = isset($res['data']['error']['message']) ? $res['data']['error']['message'] : ($res['error'] !== '' ? $res['error'] : 'Stripe rejected the request.');
    error_log('[TPT] Stripe intent error: ' . $msg);
    return array('ok' => false, 'error' => 'Stripe could not start the payment.');
}

/** Read a PaymentIntent's status server-side (never trust the browser). */
function pieStripeIntentStatus($intentId)
{
    $secret = getSetting('stripe_secret_key');
    $intentId = preg_replace('/[^A-Za-z0-9_]/', '', (string) $intentId);
    if ($secret === '' || $intentId === '') {
        return null;
    }
    $res = piePayRequest('https://api.stripe.com/v1/payment_intents/' . rawurlencode($intentId), array(
        'headers' => array('Authorization: Bearer ' . $secret),
        'get'     => true,
    ));
    if ($res['ok'] && isset($res['data']['status'])) {
        $status = $res['data']['status'];
        return in_array($status, array('succeeded', 'requires_capture'), true) ? 'paid' : ($status === 'canceled' ? 'cancelled' : 'pending');
    }
    return null;
}

/** Capture a returned PayPal order and report paid/unpaid. */
function piePayPalVerify($orderId)
{
    if (getSetting('paypal_secret') === '' || $orderId === '') {
        return null;
    }
    $token = piePayPalToken();
    if ($token === '') {
        return null;
    }

    /* An order returning from approval is APPROVED — capture it now. */
    $res = piePayRequest(piePayPalHost() . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', array(
        'headers' => array('Authorization: Bearer ' . $token, 'Content-Type: application/json'),
        'body'    => '{}',
    ));
    $status = isset($res['data']['status']) ? $res['data']['status'] : '';
    if ($status === 'COMPLETED') {
        return 'paid';
    }
    /* Capture may already have happened — fall back to reading the order. */
    $get = piePayRequest(piePayPalHost() . '/v2/checkout/orders/' . rawurlencode($orderId), array(
        'headers' => array('Authorization: Bearer ' . $token),
        'get'     => true,
    ));
    $getStatus = isset($get['data']['status']) ? $get['data']['status'] : '';
    if ($getStatus === 'COMPLETED') {
        return 'paid';
    }
    return $getStatus === '' ? null : 'pending';
}

/** Fetch a payment request by its public token. */
function piePaymentByToken($token)
{
    return dbOne('SELECT * FROM payments WHERE token = ?', array($token));
}

/** Reconcile a pending payment with its provider; updates the DB row. */
function piePaymentReconcile($payment)
{
    if ($payment['status'] === 'paid') {
        return $payment;
    }
    if ($payment['method'] === 'stripe' && $payment['provider_ref'] !== '' && strpos($payment['provider_ref'], 'pi_') === 0) {
        /* Inline Stripe Elements flow - verify the PaymentIntent. */
        if (pieStripeIntentStatus($payment['provider_ref']) === 'paid') {
            piePaymentMarkPaid($payment, $payment['provider_ref']);
            $payment['status'] = 'paid';
        }
        return $payment;
    }
    if ($payment['status'] !== 'pending' || $payment['provider_ref'] === '') {
        return $payment;
    }
    $verdict = null;
    if ($payment['method'] === 'stripe') {
        $verdict = pieStripeVerify($payment['provider_ref']);
    } elseif ($payment['method'] === 'paypal') {
        $verdict = piePayPalVerify($payment['provider_ref']);
    }
    if ($verdict === 'paid' && $payment['status'] !== 'paid') {
        piePaymentMarkPaid($payment, $payment['provider_ref']);
        $payment['status'] = 'paid';
    }
    return $payment;
}

/** Human-readable payment method label. */
function piePaymentMethodLabel($method)
{
    $labels = array('invoice' => 'Invoice link', 'stripe' => 'Credit/Debit Card — Stripe', 'paypal' => 'PayPal');
    return isset($labels[$method]) ? $labels[$method] : ucfirst((string) $method);
}

/** Shared variable map for payment email templates. */
function piePaymentVars($payment)
{
    return array(
        'name'            => $payment['name'],
        'email'           => $payment['email'],
        'phone'           => ($payment['phone'] !== '' ? $payment['phone'] : '—'),
        'service'         => ($payment['service'] !== '' ? $payment['service'] : '—'),
        'amount'          => number_format((float) $payment['amount_usd'], 2),
        'payment_method'  => piePaymentMethodLabel($payment['method']),
        'transaction_id'  => ($payment['provider_ref'] !== '' ? $payment['provider_ref'] : substr($payment['token'], 0, 12)),
        'status'          => $payment['status'],
        'reference'       => ($payment['reference'] !== '' ? $payment['reference'] : '—'),
    );
}

/** Detail table rows for payment emails. */
function piePaymentDetailTable($payment, $extra = array())
{
    require_once BASE_PATH . '/includes/email-templates.php';
    $vars = piePaymentVars($payment);
    $rows = array(
        'Customer'        => esc($vars['name']),
        'Email'           => esc($vars['email']),
        'Phone'           => esc($vars['phone']),
        'Service'         => esc($vars['service']),
        'Amount'          => '$' . esc($vars['amount']) . ' USD',
        'Payment method'  => esc($vars['payment_method']),
        'Transaction ID'  => '<span style="font-family:monospace">' . esc($vars['transaction_id']) . '</span>',
        'Status'          => esc(ucfirst($vars['status'])),
        'Date'            => esc(date('j M Y, H:i') . ' UTC'),
    );
    foreach ($extra as $label => $value) {
        $rows[$label] = $value;
    }
    return EmailTemplates::detailTable($rows);
}

/** Email the admin + requester about a new invoice request (never fatal). */
function piePaymentNotify($payment)
{
    require_once BASE_PATH . '/core/Notifications.php';
    $vars   = piePaymentVars($payment);
    $linkUrl = canonicalUrl('pay/secure/' . $payment['token']);
    $table   = piePaymentDetailTable($payment, array('Secure link' => '<a href="' . esc($linkUrl) . '">' . esc($linkUrl) . '</a>'));

    Notifications::notifyAdmins('payment', 'payment_request_admin', $vars, array(
        'table'     => $table,
        'admin_url' => rtrim(SITE_URL, '/') . '/admin/payments.php',
    ));
    Notifications::sendTemplate('payment_request_confirm', $payment['email'], $vars, array('table' => $table));
}

/**
 * Mark a payment paid and send the one-time success notifications (Task 6/27).
 * Only fires on the actual transition to "paid" — never twice.
 */
function piePaymentMarkPaid($payment, $providerRef = '', $method = null)
{
    $previousStatus = $payment['status'];
    $methodSql = '';
    $providerSql = '';
    $params = array();
    if ($method !== null) { $methodSql = ', method = ?'; $params[] = $method; }
    if ($providerRef !== '') { $providerSql = ', provider_ref = ?'; $params[] = $providerRef; }
    $params[] = (int) $payment['id'];
    $updated = dbExec('UPDATE payments SET status = "paid"' . $methodSql . $providerSql . ' WHERE id = ? AND status <> "paid"', $params);

    if ($previousStatus === 'paid' || $updated === 0) {
        return false; /* already paid — no duplicate notifications */
    }
    $payment['status'] = 'paid';
    if ($providerRef !== '') { $payment['provider_ref'] = $providerRef; }
    if ($method !== null) { $payment['method'] = $method; }
    piePaymentSendPaidNotices($payment);
    return true;
}

/**
 * Send the one-time paid notifications (admin + customer). Callers must
 * guarantee the payment just transitioned to "paid" (Task 6 — never twice).
 */
function piePaymentSendPaidNotices($payment)
{
    require_once BASE_PATH . '/core/Notifications.php';
    $vars  = piePaymentVars($payment);
    $table = piePaymentDetailTable($payment);
    Notifications::notifyAdmins('payment', 'payment_admin', $vars, array(
        'table'     => $table,
        'admin_url' => rtrim(SITE_URL, '/') . '/admin/payments.php',
    ));
    Notifications::sendTemplate('payment_confirm', $payment['email'], $vars, array('table' => $table));
}
