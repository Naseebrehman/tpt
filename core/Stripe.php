<?php
/** Stripe Checkout integration. Checkout Sessions are created, retrieved and
 * verified by PHP with the Stripe Secret Key; no Stripe.js or publishable key
 * is used on the customer-facing payment page.
 */
if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

function pieStripeApiBase()
{
    return 'https://api.stripe.com';
}

/** Decrypted only in this PHP process; credentials are encrypted in settings. */
function pieStripeSecret()
{
    return piePaymentCredential('stripe_secret_key');
}

function pieStripeSecretConfigured()
{
    return pieStripeSecret() !== '';
}

function pieStripeWebhookSecret()
{
    return piePaymentCredential('stripe_webhook_secret');
}

function pieStripeWebhookSecretConfigured()
{
    return pieIsValidStripeWebhookSecret(pieStripeWebhookSecret());
}

function pieStripeServerReady()
{
    return pieIsValidStripeSecret(pieStripeSecret());
}

function pieStripeKeyMode()
{
    return strpos(pieStripeSecret(), '_test_') !== false ? 'test' : 'live';
}

function pieIsValidStripeSecret($secret)
{
    return (bool) preg_match('/^(sk|rk)_(live|test)_[A-Za-z0-9]{10,240}$/D', (string) $secret);
}

function pieIsValidStripeWebhookSecret($secret)
{
    return (bool) preg_match('/^whsec_[A-Za-z0-9+\/=]{8,240}$/D', (string) $secret);
}

/** Remove all credential-shaped data before a provider error reaches a log. */
function pieStripeScrub($text)
{
    $text = (string) $text;
    foreach (array(pieStripeSecret(), pieStripeWebhookSecret()) as $credential) {
        if ($credential !== '') { $text = str_replace($credential, '***', $text); }
    }
    $text = (string) preg_replace('/\b(?:sk|rk|pk)_(?:live|test)_[A-Za-z0-9_*]+/', '***', $text);
    return (string) preg_replace('/\bwhsec_[A-Za-z0-9+\/=*]+/', '***', $text);
}

/** HTTPS transport with cURL and a stream-socket fallback. */
function pieStripeHttp($method, $url, array $headers = array(), $body = null)
{
    $method = strtoupper((string) $method);
    $body = $body === null ? '' : (string) $body;
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
        ));
        if ($body !== '') { curl_setopt($curl, CURLOPT_POSTFIELDS, $body); }
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        unset($curl);
        if ($response === false) {
            return array('ok' => false, 'status' => 0, 'body' => '', 'error' => pieStripeScrub('Stripe is unreachable: ' . $error));
        }
        return array('ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => (string) $response, 'error' => '');
    }

    $parts = parse_url($url);
    if (empty($parts['host']) || ($parts['scheme'] ?? '') !== 'https') {
        return array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'Stripe URL is invalid.');
    }
    $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    $socket = @stream_socket_client('ssl://' . $parts['host'] . ':' . (int) ($parts['port'] ?? 443), $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return array('ok' => false, 'status' => 0, 'body' => '', 'error' => pieStripeScrub('Stripe is unreachable: ' . $errstr));
    }
    stream_set_timeout($socket, 25);
    $request = $method . ' ' . $path . " HTTP/1.1\r\nHost: " . $parts['host'] . "\r\nConnection: close\r\n";
    foreach ($headers as $header) { $request .= $header . "\r\n"; }
    $request .= 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body;
    @fwrite($socket, $request);
    $raw = '';
    while (!feof($socket)) {
        $chunk = @fread($socket, 8192);
        if ($chunk === false || $chunk === '') { break; }
        $raw .= $chunk;
    }
    @fclose($socket);
    $split = strpos($raw, "\r\n\r\n");
    if ($split === false) { return array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'Stripe returned an unreadable response.'); }
    $head = substr($raw, 0, $split);
    $bodyText = substr($raw, $split + 4);
    $status = preg_match('#^HTTP/\d\.\d\s(\d{3})#', $head, $m) ? (int) $m[1] : 0;
    if (stripos($head, 'Transfer-Encoding: chunked') !== false) { $bodyText = pieStripeDechunk($bodyText); }
    return array('ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $bodyText, 'error' => '');
}

function pieStripeDechunk($body)
{
    if (function_exists('piePayPalDechunk')) { return piePayPalDechunk($body); }
    $decoded = '';
    $offset = 0;
    $length = strlen((string) $body);
    while ($offset < $length) {
        $lineEnd = strpos($body, "\r\n", $offset);
        if ($lineEnd === false) { break; }
        $size = hexdec(trim(substr($body, $offset, $lineEnd - $offset)));
        if ($size <= 0) { break; }
        $decoded .= substr($body, $lineEnd + 2, $size);
        $offset = $lineEnd + 2 + $size + 2;
    }
    return $decoded === '' ? (string) $body : $decoded;
}

/** Encode nested parameters in Stripe's standard form format. */
function pieStripeFormEncode(array $data, $prefix = '')
{
    $pairs = array();
    foreach ($data as $key => $value) {
        $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
        if (is_array($value)) {
            $nested = pieStripeFormEncode($value, $name);
            if ($nested !== '') { $pairs[] = $nested; }
            continue;
        }
        if ($value === null || $value === false) { continue; }
        if ($value === true) { $value = 'true'; }
        $pairs[] = rawurlencode($name) . '=' . rawurlencode((string) $value);
    }
    return implode('&', $pairs);
}

/** One server-authenticated Stripe API request. */
function pieStripeRequest($method, $path, $params = null, $idempotencyKey = '')
{
    $result = array('ok' => false, 'status' => 0, 'data' => array(), 'error' => '');
    if (!pieStripeServerReady()) {
        $result['error'] = 'Stripe server-side credentials are not configured.';
        return $result;
    }
    $headers = array('Accept: application/json', 'Authorization: Bearer ' . pieStripeSecret());
    $body = null;
    if (is_array($params)) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $body = pieStripeFormEncode($params);
    }
    if ($idempotencyKey !== '') {
        $headers[] = 'Idempotency-Key: ' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $idempotencyKey);
    }
    $response = pieStripeHttp($method, pieStripeApiBase() . $path, $headers, $body);
    $result['status'] = (int) ($response['status'] ?? 0);
    if (!empty($response['error'])) {
        $result['error'] = pieStripeScrub((string) $response['error']);
        error_log('[TPT] Stripe ' . strtoupper((string) $method) . ' request failed.');
        return $result;
    }
    $data = json_decode((string) ($response['body'] ?? ''), true);
    $result['data'] = is_array($data) ? $data : array();
    $result['ok'] = !empty($response['ok']);
    if (!$result['ok']) {
        $message = (string) ($result['data']['error']['message'] ?? 'Stripe could not complete the request.');
        error_log('[TPT] Stripe ' . strtoupper((string) $method) . ' request failed (HTTP ' . $result['status'] . '): ' . pieStripeScrub($message));
        if (in_array($result['status'], array(401, 403), true)) {
            $result['error'] = 'Stripe rejected the stored Secret Key. Check Admin → Payments.';
        } elseif ($result['status'] === 404) {
            $result['error'] = 'Stripe could not find that payment.';
        } else {
            $result['error'] = pieStripeScrub($message);
        }
    }
    return $result;
}

/** Strict USD amount in cents. */
function pieStripeAmountCents($value)
{
    $amount = function_exists('piePaymentNormalizeAmount') ? piePaymentNormalizeAmount($value) : '';
    if ($amount === '') { return 0; }
    return (int) round((float) $amount * 100);
}

function pieStripeFormatAmount($cents)
{
    return number_format(((int) $cents) / 100, 2, '.', '');
}

function pieIsValidStripeCheckoutSessionId($id)
{
    return (bool) preg_match('/^cs_[A-Za-z0-9_]{6,160}$/D', (string) $id);
}

function pieIsValidStripePaymentIntentId($id)
{
    return (bool) preg_match('/^pi_[A-Za-z0-9_]{6,160}$/D', (string) $id);
}

/** Accept only absolute HTTP(S) return URLs and preserve Stripe's literal token. */
function pieStripeReturnUrlsValid($successUrl, $cancelUrl)
{
    $checkedSuccessUrl = str_replace('{CHECKOUT_SESSION_ID}', 'cs_test_placeholder', (string) $successUrl);
    $checkedCancelUrl = (string) $cancelUrl;
    return filter_var($checkedSuccessUrl, FILTER_VALIDATE_URL)
        && filter_var($checkedCancelUrl, FILTER_VALIDATE_URL)
        && in_array(parse_url($checkedSuccessUrl, PHP_URL_SCHEME), array('http', 'https'), true)
        && in_array(parse_url($checkedCancelUrl, PHP_URL_SCHEME), array('http', 'https'), true);
}

/** Create one hosted card Checkout Session for a server-created payment attempt. */
function pieStripeCreateCheckoutSession(array $attempt, $successUrl, $cancelUrl)
{
    $fail = array('ok' => false, 'id' => '', 'url' => '', 'error' => '');
    $cents = pieStripeAmountCents($attempt['amount_usd'] ?? ($attempt['amount'] ?? ''));
    $token = strtolower(trim((string) ($attempt['token'] ?? '')));
    if (!pieStripeServerReady()) { $fail['error'] = 'Stripe server-side credentials are not configured.'; return $fail; }
    if ($cents <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $token)) { $fail['error'] = 'The payment details are invalid.'; return $fail; }
    if (!pieStripeReturnUrlsValid($successUrl, $cancelUrl)) {
        $fail['error'] = 'Stripe return URLs are not configured correctly.';
        return $fail;
    }

    $service = mb_substr(trim((string) ($attempt['service'] ?? 'Service payment')), 0, 150);
    $marker = 'tpt-pay-online-v1';
    $params = array(
        'mode' => 'payment',
        'success_url' => (string) $successUrl,
        'cancel_url' => (string) $cancelUrl,
        'client_reference_id' => $token,
        'customer_email' => (string) ($attempt['email'] ?? ''),
        'payment_method_types' => array('card'),
        'line_items' => array(array(
            'quantity' => 1,
            'price_data' => array(
                'currency' => 'usd',
                'unit_amount' => $cents,
                'product_data' => array('name' => $service),
            ),
        )),
        'metadata' => array(
            'integration' => $marker,
            'payment_token' => $token,
            'service' => $service,
        ),
        'payment_intent_data' => array(
            'metadata' => array(
                'integration' => $marker,
                'payment_token' => $token,
            ),
        ),
    );
    $response = pieStripeRequest('POST', '/v1/checkout/sessions', $params, $token);
    if (!$response['ok'] || !pieIsValidStripeCheckoutSessionId($response['data']['id'] ?? '')) {
        $fail['error'] = $response['error'] !== '' ? $response['error'] : 'Stripe could not start this payment.';
        return $fail;
    }
    $checkoutUrl = (string) ($response['data']['url'] ?? '');
    $parts = parse_url($checkoutUrl);
    if (($parts['scheme'] ?? '') !== 'https' || strtolower((string) ($parts['host'] ?? '')) !== 'checkout.stripe.com') {
        $fail['error'] = 'Stripe did not return a secure checkout link.';
        return $fail;
    }
    return array('ok' => true, 'id' => (string) $response['data']['id'], 'url' => $checkoutUrl, 'error' => '');
}

function pieStripeRetrieveCheckoutSession($sessionId)
{
    $sessionId = trim((string) $sessionId);
    if (!pieIsValidStripeCheckoutSessionId($sessionId)) {
        return array('ok' => false, 'status' => 0, 'data' => array(), 'error' => 'The Stripe Checkout reference is invalid.');
    }
    return pieStripeRequest('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId) . '?expand[]=payment_intent');
}

function pieStripeRetrievePaymentIntent($intentId)
{
    $intentId = trim((string) $intentId);
    if (!pieIsValidStripePaymentIntentId($intentId)) {
        return array('ok' => false, 'status' => 0, 'data' => array(), 'error' => 'The Stripe transaction reference is invalid.');
    }
    return pieStripeRequest('GET', '/v1/payment_intents/' . rawurlencode($intentId));
}

/** Build a verified result from server-retrieved Checkout Session + Intent data. */
function pieStripeCheckoutConfirmation(array $session, array $intent)
{
    $empty = array(
        'confirmed' => false, 'reference' => '', 'amount' => '', 'currency' => '',
        'status' => '', 'name' => '', 'email' => '', 'service' => '',
        'session_id' => '', 'payment_token' => '',
    );
    $sessionId = trim((string) ($session['id'] ?? ''));
    $sessionMeta = is_array($session['metadata'] ?? null) ? $session['metadata'] : array();
    $intentMeta = is_array($intent['metadata'] ?? null) ? $intent['metadata'] : array();
    $token = strtolower(trim((string) ($session['client_reference_id'] ?? '')));
    $metaToken = strtolower(trim((string) ($sessionMeta['payment_token'] ?? '')));
    $intentToken = strtolower(trim((string) ($intentMeta['payment_token'] ?? '')));
    $sessionCents = (int) ($session['amount_total'] ?? 0);
    $receivedCents = (int) ($intent['amount_received'] ?? 0);
    $currency = strtoupper((string) ($session['currency'] ?? ''));
    $intentCurrency = strtoupper((string) ($intent['currency'] ?? ''));
    $integrationOk = (string) ($sessionMeta['integration'] ?? '') === 'tpt-pay-online-v1'
        && (string) ($intentMeta['integration'] ?? '') === 'tpt-pay-online-v1';
    $tokenOk = preg_match('/^[a-f0-9]{64}$/D', $token)
        && hash_equals($token, $metaToken)
        && hash_equals($token, $intentToken);
    $intentId = trim((string) ($intent['id'] ?? ''));
    $complete = strtolower((string) ($session['status'] ?? '')) === 'complete'
        && strtolower((string) ($session['payment_status'] ?? '')) === 'paid'
        && strtolower((string) ($intent['status'] ?? '')) === 'succeeded';

    if (!pieIsValidStripeCheckoutSessionId($sessionId) || !pieIsValidStripePaymentIntentId($intentId)
        || !$integrationOk || !$tokenOk || !$complete || $sessionCents <= 0
        || $receivedCents <= 0 || $sessionCents !== $receivedCents
        || $currency !== 'USD' || $intentCurrency !== 'USD') {
        return $empty;
    }

    $customer = is_array($session['customer_details'] ?? null) ? $session['customer_details'] : array();
    return array(
        'confirmed' => true,
        'reference' => $intentId,
        'amount' => pieStripeFormatAmount($receivedCents),
        'currency' => 'USD',
        'status' => 'SUCCEEDED',
        'name' => trim((string) ($customer['name'] ?? '')),
        'email' => trim((string) ($customer['email'] ?? ($session['customer_email'] ?? ''))),
        'service' => trim((string) ($sessionMeta['service'] ?? '')),
        'session_id' => $sessionId,
        'payment_token' => $token,
    );
}

/** Retrieve and verify a Checkout Session against its pending database attempt. */
function pieStripeConfirmCheckoutSession($sessionId, array $attempt)
{
    $empty = array('ok' => false, 'error' => 'Stripe has not confirmed this payment.', 'confirmation' => array('confirmed' => false));
    $sessionId = trim((string) $sessionId);
    if (!pieIsValidStripeCheckoutSessionId($sessionId) || !pieStripeServerReady()) {
        $empty['error'] = 'The Stripe Checkout reference is invalid or Stripe is not configured.';
        return $empty;
    }
    $sessionResponse = pieStripeRetrieveCheckoutSession($sessionId);
    if (!$sessionResponse['ok']) {
        $empty['error'] = $sessionResponse['error'] !== '' ? $sessionResponse['error'] : 'Stripe could not retrieve this Checkout Session.';
        return $empty;
    }
    $session = $sessionResponse['data'];
    if ((string) ($session['id'] ?? '') !== $sessionId
        || (string) ($attempt['reference'] ?? '') !== $sessionId
        || strtolower((string) ($session['client_reference_id'] ?? '')) !== strtolower((string) ($attempt['token'] ?? ''))) {
        $empty['error'] = 'The Stripe Checkout Session does not match this payment request.';
        return $empty;
    }

    $intent = $session['payment_intent'] ?? null;
    if (is_string($intent)) {
        $intentResponse = pieStripeRetrievePaymentIntent($intent);
        if (!$intentResponse['ok']) {
            $empty['error'] = $intentResponse['error'] !== '' ? $intentResponse['error'] : 'Stripe could not retrieve the transaction.';
            return $empty;
        }
        $intent = $intentResponse['data'];
    }
    if (!is_array($intent)) {
        $empty['error'] = 'Stripe has not completed this payment yet.';
        return $empty;
    }

    $confirmation = pieStripeCheckoutConfirmation($session, $intent);
    $expected = function_exists('piePaymentNormalizeAmount') ? piePaymentNormalizeAmount($attempt['amount_usd'] ?? '') : '';
    if (!$confirmation['confirmed']) {
        $empty['error'] = 'Stripe has not completed this payment. No successful payment was recorded.';
        return array('ok' => false, 'error' => $empty['error'], 'confirmation' => $confirmation);
    }
    if ($confirmation['payment_token'] !== strtolower((string) ($attempt['token'] ?? ''))
        || $expected === '' || $confirmation['amount'] !== $expected) {
        error_log('[TPT] Stripe session amount/token mismatch for ' . $sessionId . '.');
        return array('ok' => false, 'error' => 'The Stripe payment does not match the original payment request.', 'confirmation' => $confirmation);
    }
    return array('ok' => true, 'error' => '', 'confirmation' => $confirmation);
}

/** Verify a PaymentIntent webhook by retrieving the Intent directly from Stripe. */
function pieStripeConfirmPaymentIntent($intentId, array $attempt)
{
    $response = pieStripeRetrievePaymentIntent($intentId);
    if (!$response['ok']) {
        return array('ok' => false, 'error' => $response['error'] !== '' ? $response['error'] : 'Stripe could not retrieve the transaction.', 'confirmation' => array('confirmed' => false));
    }
    $intent = $response['data'];
    $metadata = is_array($intent['metadata'] ?? null) ? $intent['metadata'] : array();
    $token = strtolower(trim((string) ($metadata['payment_token'] ?? '')));
    $cents = (int) ($intent['amount_received'] ?? 0);
    $currency = strtoupper((string) ($intent['currency'] ?? ''));
    $expected = function_exists('piePaymentNormalizeAmount') ? piePaymentNormalizeAmount($attempt['amount_usd'] ?? '') : '';
    $amount = $cents > 0 ? pieStripeFormatAmount($cents) : '';
    $confirmation = array(
        'confirmed' => (string) ($metadata['integration'] ?? '') === 'tpt-pay-online-v1'
            && $token !== ''
            && hash_equals(strtolower((string) ($attempt['token'] ?? '')), $token)
            && strtolower((string) ($intent['status'] ?? '')) === 'succeeded'
            && $currency === 'USD'
            && $cents > 0
            && $expected !== ''
            && $amount === $expected
            && pieIsValidStripePaymentIntentId($intent['id'] ?? ''),
        'reference' => (string) ($intent['id'] ?? ''),
        'amount' => $amount,
        'currency' => $currency,
        'status' => strtoupper((string) ($intent['status'] ?? '')),
        'name' => (string) ($attempt['name'] ?? ''),
        'email' => (string) ($attempt['email'] ?? ''),
        'service' => (string) ($attempt['service'] ?? ''),
        'session_id' => (string) ($attempt['reference'] ?? ''),
        'payment_token' => $token,
    );
    if (!$confirmation['confirmed']) {
        return array('ok' => false, 'error' => 'Stripe has not confirmed a matching successful payment.', 'confirmation' => $confirmation);
    }
    return array('ok' => true, 'error' => '', 'confirmation' => $confirmation);
}

/** Verify Stripe's timestamped HMAC signature using the server-side webhook secret. */
function pieStripeVerifyWebhookSignature($payload, $signatureHeader, $tolerance = 300)
{
    $secret = pieStripeWebhookSecret();
    $payload = (string) $payload;
    $signatureHeader = trim((string) $signatureHeader);
    if ($secret === '' || $payload === '' || $signatureHeader === '') { return false; }
    $timestamp = '';
    $signatures = array();
    foreach (explode(',', $signatureHeader) as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) !== 2) { continue; }
        if ($pair[0] === 't') { $timestamp = trim($pair[1]); }
        if ($pair[0] === 'v1') { $signatures[] = trim($pair[1]); }
    }
    if ($timestamp === '' || !$signatures || !ctype_digit($timestamp)) { return false; }
    if ((int) $tolerance > 0 && abs(time() - (int) $timestamp) > (int) $tolerance) { return false; }
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) {
        if ($signature !== '' && hash_equals($expected, $signature)) { return true; }
    }
    return false;
}
