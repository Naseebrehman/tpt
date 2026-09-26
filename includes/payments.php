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
    require_once __DIR__ . '/init.php';
}

/** Which providers can actually be offered right now? */
function piePaymentProviders()
{
    $providers = array();
    if (getSetting('stripe_enabled', '0') === '1' && getSetting('stripe_secret_key') !== '') {
        $providers['stripe'] = array(
            'label' => 'Card — secure Stripe checkout',
            'note'  => 'You’ll be redirected to Stripe’s hosted checkout. Card details never touch this site.',
            'mode'  => getSetting('stripe_mode', 'test') === 'live' ? 'Live' : 'Test',
        );
    }
    if (getSetting('paypal_enabled', '0') === '1' && getSetting('paypal_client_id') !== '' && getSetting('paypal_secret') !== '') {
        $providers['paypal'] = array(
            'label' => 'PayPal — secure hosted approval',
            'note'  => 'You’ll approve the payment on PayPal’s site, then return here.',
            'mode'  => getSetting('paypal_mode', 'sandbox') === 'live' ? 'Live' : 'Sandbox',
        );
    }
    return $providers;
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

/** Create a PayPal order and return its approval URL. */
function piePayPalOrder($payment)
{
    $clientId = getSetting('paypal_client_id');
    $secret   = getSetting('paypal_secret');
    if ($clientId === '' || $secret === '') {
        return array('ok' => false, 'error' => 'PayPal is not configured.');
    }
    $host = getSetting('paypal_mode', 'sandbox') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

    $auth = piePayRequest($host . '/v1/oauth2/token', array(
        'userpwd' => $clientId . ':' . $secret,
        'headers' => array('Content-Type: application/x-www-form-urlencoded'),
        'body'    => 'grant_type=client_credentials',
    ));
    if (!$auth['ok'] || empty($auth['data']['access_token'])) {
        error_log('[TPT] PayPal auth failed.');
        return array('ok' => false, 'error' => 'PayPal authentication failed — check the mode and credentials.');
    }
    $token = $auth['data']['access_token'];

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
    $res = piePayRequest($host . '/v2/checkout/orders', array(
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

/** Capture a returned PayPal order and report paid/unpaid. */
function piePayPalVerify($orderId)
{
    $clientId = getSetting('paypal_client_id');
    $secret   = getSetting('paypal_secret');
    if ($clientId === '' || $secret === '' || $orderId === '') {
        return null;
    }
    $host = getSetting('paypal_mode', 'sandbox') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    $auth = piePayRequest($host . '/v1/oauth2/token', array(
        'userpwd' => $clientId . ':' . $secret,
        'headers' => array('Content-Type: application/x-www-form-urlencoded'),
        'body'    => 'grant_type=client_credentials',
    ));
    if (!$auth['ok'] || empty($auth['data']['access_token'])) {
        return null;
    }
    $token = $auth['data']['access_token'];

    /* An order returning from approval is APPROVED — capture it now. */
    $res = piePayRequest($host . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', array(
        'headers' => array('Authorization: Bearer ' . $token, 'Content-Type: application/json'),
        'body'    => '{}',
    ));
    $status = isset($res['data']['status']) ? $res['data']['status'] : '';
    if ($status === 'COMPLETED') {
        return 'paid';
    }
    /* Capture may already have happened — fall back to reading the order. */
    $get = piePayRequest($host . '/v2/checkout/orders/' . rawurlencode($orderId), array(
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
        dbExec('UPDATE payments SET status = "paid" WHERE id = ?', array((int) $payment['id']));
        $payment['status'] = 'paid';
    }
    return $payment;
}

/** Email the admin + requester about a new payment request (never fatal). */
function piePaymentNotify($payment)
{
    $amount  = number_format((float) $payment['amount_usd'], 2);
    $linkUrl = canonicalUrl('pay/secure/' . $payment['token']);
    $methodLabels = array('invoice' => 'Invoice link requested', 'stripe' => 'Stripe checkout', 'paypal' => 'PayPal');
    $method = isset($methodLabels[$payment['method']]) ? $methodLabels[$payment['method']] : $payment['method'];

    $adminBody = '<h2>New payment request</h2>'
        . '<p><strong>' . esc($payment['name']) . '</strong> (' . esc($payment['email']) . ') submitted a payment of <strong>$' . $amount . ' USD</strong> via ' . esc($method) . '.</p>'
        . '<p>Reference: ' . esc($payment['reference'] !== '' ? $payment['reference'] : '—') . '<br>Notes: ' . nl2br(esc($payment['notes'] !== '' ? $payment['notes'] : '—')) . '</p>'
        . '<p><a href="' . esc($linkUrl) . '">Open the secure payment link</a> · Manage it in Admin → Payments.</p>';

    $clientBody = '<h2>We received your payment request</h2>'
        . '<p>Hi ' . esc($payment['name']) . ',</p>'
        . '<p>Thanks — your request for <strong>$' . $amount . ' USD</strong>' . ($payment['reference'] !== '' ? ' (reference ' . esc($payment['reference']) . ')' : '') . ' is with our team.</p>'
        . '<p>Your secure payment link: <a href="' . esc($linkUrl) . '">' . esc($linkUrl) . '</a><br>'
        . 'Card details are handled by the payment provider’s hosted checkout — never by this website.</p>'
        . '<p>Questions? Reply to this email or write to ' . esc(getSetting('site_email', 'info@thepietechnologies.com')) . '.</p>';

    try {
        sendEmail(getSetting('site_email', ADMIN_EMAIL), 'Payment request — $' . $amount . ' from ' . $payment['name'], $adminBody);
        sendEmail($payment['email'], 'Your TPT secure payment link', $clientBody);
    } catch (Throwable $paymentMailError) {
        error_log('[TPT] Payment mail error: ' . $paymentMailError->getMessage());
    }
}
