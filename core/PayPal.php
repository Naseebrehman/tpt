<?php
/** PayPal REST Orders v2 integration. Every provider request is server-to-server;
 * the Pay Online page never loads the PayPal SDK or handles order callbacks.
 */
if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

/** Sandbox or live API host selected in Admin → Payments. */
function piePayPalEnv()
{
    return strtolower(trim((string) getSetting('paypal_env', 'live'))) === 'sandbox' ? 'sandbox' : 'live';
}

function piePayPalApiBase()
{
    return piePayPalEnv() === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
}

/** Decrypted only in this PHP process; the settings row is AES-GCM ciphertext. */
function piePayPalSecret()
{
    return piePaymentCredential('paypal_secret');
}

function piePayPalSecretConfigured()
{
    return piePayPalSecret() !== '';
}

function piePayPalServerReady()
{
    return function_exists('piePayPalClientIdConfigured')
        && piePayPalClientIdConfigured()
        && pieIsValidPayPalSecret(piePayPalSecret());
}

function pieIsValidPayPalSecret($secret)
{
    return (bool) preg_match('/^[^\x00-\x20\x7F]{16,512}$/D', (string) $secret);
}

/** Redact the stored credentials from transport/provider errors. */
function piePayPalScrub($text)
{
    $text = (string) $text;
    foreach (array(piePayPalSecret(), function_exists('piePayPalClientId') ? piePayPalClientId() : '') as $credential) {
        if ($credential !== '') { $text = str_replace($credential, '***', $text); }
    }
    return $text;
}

/** HTTPS transport with cURL and a stream-socket fallback. */
function piePayPalHttp($method, $url, array $headers = array(), $body = null)
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
            return array('ok' => false, 'status' => 0, 'body' => '', 'error' => piePayPalScrub('PayPal is unreachable: ' . $error));
        }
        return array('ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => (string) $response, 'error' => '');
    }

    $parts = parse_url($url);
    if (empty($parts['host']) || ($parts['scheme'] ?? '') !== 'https') {
        return array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'PayPal URL is invalid.');
    }
    $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    $socket = @stream_socket_client('ssl://' . $parts['host'] . ':' . (int) ($parts['port'] ?? 443), $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return array('ok' => false, 'status' => 0, 'body' => '', 'error' => piePayPalScrub('PayPal is unreachable: ' . $errstr));
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
    if ($split === false) { return array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'PayPal returned an unreadable response.'); }
    $head = substr($raw, 0, $split);
    $bodyText = substr($raw, $split + 4);
    $status = preg_match('#^HTTP/\d\.\d\s(\d{3})#', $head, $m) ? (int) $m[1] : 0;
    if (stripos($head, 'Transfer-Encoding: chunked') !== false) { $bodyText = piePayPalDechunk($bodyText); }
    return array('ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $bodyText, 'error' => '');
}

function piePayPalDechunk($body)
{
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

/** One authenticated PayPal JSON request. */
function piePayPalRequest($method, $path, $body = null, $accessToken = '', $requestId = '')
{
    $headers = array('Accept: application/json', 'Content-Type: application/json');
    if ($accessToken !== '') { $headers[] = 'Authorization: Bearer ' . $accessToken; }
    if ($requestId !== '') { $headers[] = 'PayPal-Request-Id: ' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $requestId); }
    $encodedBody = $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES);
    $response = piePayPalHttp($method, piePayPalApiBase() . $path, $headers, $encodedBody);
    $data = json_decode((string) ($response['body'] ?? ''), true);
    $result = array('ok' => !empty($response['ok']), 'status' => (int) ($response['status'] ?? 0), 'data' => is_array($data) ? $data : array(), 'error' => '');
    if (!empty($response['error'])) {
        $result['error'] = piePayPalScrub((string) $response['error']);
    } elseif (!$result['ok']) {
        $message = (string) ($result['data']['message'] ?? $result['data']['error_description'] ?? $result['data']['details'][0]['description'] ?? '');
        $result['error'] = piePayPalScrub($message !== '' ? $message : 'PayPal could not complete the request.');
        error_log('[TPT] PayPal ' . strtoupper((string) $method) . ' request failed (HTTP ' . $result['status'] . '): ' . $result['error']);
    }
    return $result;
}

/** Obtain a fresh OAuth token using the server-side Client ID and Secret. */
function piePayPalAccessToken()
{
    static $cache = null;
    if (is_array($cache)) { return $cache; }
    $cache = array('ok' => false, 'token' => '', 'error' => 'PayPal is not configured.');
    if (!piePayPalServerReady()) { return $cache; }
    $response = piePayPalHttp(
        'POST',
        piePayPalApiBase() . '/v1/oauth2/token',
        array(
            'Authorization: Basic ' . base64_encode(piePayPalClientId() . ':' . piePayPalSecret()),
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ),
        'grant_type=client_credentials'
    );
    $data = json_decode((string) ($response['body'] ?? ''), true);
    if (!empty($response['ok']) && is_array($data) && !empty($data['access_token'])) {
        $cache = array('ok' => true, 'token' => (string) $data['access_token'], 'error' => '');
        return $cache;
    }
    $cache['error'] = piePayPalScrub((string) ($data['error_description'] ?? 'PayPal rejected the stored credentials.'));
    error_log('[TPT] PayPal OAuth token request failed (HTTP ' . (int) ($response['status'] ?? 0) . ').');
    return $cache;
}

/** Strict amount formatter shared with server-side validation. */
function piePayPalAmount($value)
{
    return function_exists('piePaymentNormalizeAmount')
        ? piePaymentNormalizeAmount($value)
        : ((is_scalar($value) && preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', trim((string) $value)) && (float) $value >= 0.01 && (float) $value <= 1000000)
            ? number_format((float) $value, 2, '.', '')
            : '');
}

/**
 * Create a PayPal Order on the server. Returns the provider's approved checkout
 * link; the only browser action is a normal HTTP redirect to that link.
 */
function piePayPalCreateOrder($amount, $service = '', $name = '', $email = '', $returnUrl = '', $cancelUrl = '', $attemptToken = '')
{
    $fail = array('ok' => false, 'id' => '', 'approval_url' => '', 'error' => '');
    $amount = piePayPalAmount($amount);
    $attemptToken = strtolower(trim((string) $attemptToken));
    if (!piePayPalServerReady()) { $fail['error'] = 'PayPal server-side credentials are not configured.'; return $fail; }
    if ($amount === '' || !preg_match('/^[a-f0-9]{64}$/D', $attemptToken)) { $fail['error'] = 'The payment details are invalid.'; return $fail; }
    if (!filter_var($returnUrl, FILTER_VALIDATE_URL) || !filter_var($cancelUrl, FILTER_VALIDATE_URL)
        || !in_array(parse_url($returnUrl, PHP_URL_SCHEME), array('http', 'https'), true)
        || !in_array(parse_url($cancelUrl, PHP_URL_SCHEME), array('http', 'https'), true)) {
        $fail['error'] = 'PayPal return URLs are not configured correctly.';
        return $fail;
    }

    $oauth = piePayPalAccessToken();
    if (!$oauth['ok']) { $fail['error'] = $oauth['error']; return $fail; }
    $description = mb_substr(trim((string) $service), 0, 127);
    if ($description === '') { $description = mb_substr('Payment to ' . getSetting('site_name', SITE_NAME), 0, 127); }
    $order = array(
        'intent' => 'CAPTURE',
        'purchase_units' => array(array(
            'reference_id' => substr($attemptToken, 0, 36),
            'amount' => array('currency_code' => 'USD', 'value' => $amount),
            'description' => $description,
            'custom_id' => $attemptToken,
            'invoice_id' => $attemptToken,
        )),
        'application_context' => array(
            'brand_name' => mb_substr((string) getSetting('site_name', SITE_NAME), 0, 127),
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'PAY_NOW',
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ),
    );
    $response = piePayPalRequest('POST', '/v2/checkout/orders', $order, $oauth['token'], substr($attemptToken, 0, 32));
    if (!$response['ok'] || empty($response['data']['id'])) {
        $fail['error'] = $response['error'] !== '' ? $response['error'] : 'PayPal could not start this payment.';
        return $fail;
    }
    $approvalUrl = '';
    foreach ((array) ($response['data']['links'] ?? array()) as $link) {
        if (($link['rel'] ?? '') !== 'approve' && ($link['rel'] ?? '') !== 'payer-action') { continue; }
        $candidate = (string) ($link['href'] ?? '');
        $parts = parse_url($candidate);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') === 'https' && preg_match('/(?:^|\.)paypal\.com$/D', $host)) {
            $approvalUrl = $candidate;
            break;
        }
    }
    if ($approvalUrl === '') {
        $fail['error'] = 'PayPal did not return a secure checkout link.';
        return $fail;
    }
    return array('ok' => true, 'id' => (string) $response['data']['id'], 'approval_url' => $approvalUrl, 'error' => '');
}

/** Retrieve one order using PayPal's server-side API. */
function piePayPalGetOrder($orderId)
{
    $orderId = trim((string) $orderId);
    if (!preg_match('/^[A-Za-z0-9-]{6,50}$/D', $orderId) || !piePayPalServerReady()) {
        return array('ok' => false, 'error' => 'The PayPal order reference is invalid.', 'data' => array());
    }
    $oauth = piePayPalAccessToken();
    if (!$oauth['ok']) { return array('ok' => false, 'error' => $oauth['error'], 'data' => array()); }
    $response = piePayPalRequest('GET', '/v2/checkout/orders/' . rawurlencode($orderId), null, $oauth['token']);
    return array('ok' => $response['ok'], 'error' => $response['error'], 'data' => $response['data']);
}

/** Capture after approval, and verify order, capture id, final status, amount and currency. */
function piePayPalCaptureOrder($orderId, $expectedAmount, $expectedAttemptToken = '')
{
    $orderId = trim((string) $orderId);
    $expected = piePayPalAmount($expectedAmount);
    $expectedAttemptToken = strtolower(trim((string) $expectedAttemptToken));
    if (!preg_match('/^[A-Za-z0-9-]{6,50}$/D', $orderId) || $expected === ''
        || ($expectedAttemptToken !== '' && !preg_match('/^[a-f0-9]{64}$/D', $expectedAttemptToken))) {
        return array('ok' => false, 'error' => 'The PayPal order or amount is invalid.', 'data' => array());
    }
    if (!piePayPalServerReady()) {
        return array('ok' => false, 'error' => 'PayPal server-side credentials are not configured.', 'data' => array());
    }
    $oauth = piePayPalAccessToken();
    if (!$oauth['ok']) { return array('ok' => false, 'error' => $oauth['error'], 'data' => array()); }

    $response = piePayPalRequest('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', new stdClass(), $oauth['token']);
    if ($response['ok']) {
        $data = $response['data'];
    } else {
        /* A customer can revisit the return URL after a successful capture.
           Fetch the order to make the operation safe to retry. */
        $existing = piePayPalGetOrder($orderId);
        if (!$existing['ok']) {
            return array('ok' => false, 'error' => $response['error'] !== '' ? $response['error'] : 'PayPal could not capture the payment.', 'data' => array());
        }
        $data = $existing['data'];
    }

    if ((string) ($data['id'] ?? '') !== $orderId) {
        return array('ok' => false, 'error' => 'PayPal returned a different order reference.', 'data' => array());
    }
    $confirmation = piePayPalConfirmation($data);
    if (!$confirmation['confirmed']) {
        $status = $confirmation['status'] !== '' ? strtolower($confirmation['status']) : 'incomplete';
        return array('ok' => false, 'error' => 'PayPal has not completed this payment (status: ' . $status . ').', 'data' => $data);
    }
    if ($confirmation['amount'] !== $expected || $confirmation['currency'] !== 'USD') {
        error_log('[TPT] PayPal amount/currency mismatch for order ' . $orderId . '.');
        return array('ok' => false, 'error' => 'PayPal returned an unexpected amount or currency.', 'data' => $data);
    }
    if ($expectedAttemptToken !== ''
        && (!hash_equals($expectedAttemptToken, strtolower((string) ($confirmation['custom_id'] ?? '')))
            || !hash_equals($expectedAttemptToken, strtolower((string) ($confirmation['invoice_id'] ?? ''))))) {
        error_log('[TPT] PayPal order metadata mismatch for order ' . $orderId . '.');
        return array('ok' => false, 'error' => 'PayPal returned an order that does not match this payment request.', 'data' => $data);
    }
    return array('ok' => true, 'error' => '', 'data' => $data, 'confirmation' => $confirmation);
}

/** Locate the capture object in an Orders API response. */
function piePayPalCapture(array $data)
{
    if (!empty($data['purchase_units'][0]['payments']['captures'][0]) && is_array($data['purchase_units'][0]['payments']['captures'][0])) {
        return $data['purchase_units'][0]['payments']['captures'][0];
    }
    if (isset($data['status'], $data['amount'])) { return $data; }
    return array();
}

function piePayPalPayerName(array $data)
{
    $name = is_array($data['payer']['name'] ?? null) ? $data['payer']['name'] : array();
    $combined = trim((string) ($name['given_name'] ?? '') . ' ' . (string) ($name['surname'] ?? ''));
    if ($combined === '') { $combined = trim((string) ($name['full_name'] ?? '')); }
    return $combined;
}

/** Normalize only fields returned by PayPal after a completed server capture. */
function piePayPalConfirmation(array $data)
{
    $capture = piePayPalCapture($data);
    $status = strtoupper(trim((string) ($capture['status'] ?? '')));
    $amount = piePayPalAmount($capture['amount']['value'] ?? '');
    $currency = strtoupper(trim((string) ($capture['amount']['currency_code'] ?? '')));
    $captureId = trim((string) ($capture['id'] ?? ''));
    $purchaseUnit = is_array($data['purchase_units'][0] ?? null) ? $data['purchase_units'][0] : array();
    $payerEmail = trim((string) ($data['payer']['email_address'] ?? ''));
    return array(
        'confirmed' => $status === 'COMPLETED' && $amount !== '' && $currency === 'USD' && $captureId !== '',
        'status' => $status,
        'amount' => $amount,
        'currency' => $currency,
        'reference' => $captureId,
        'order_id' => trim((string) ($data['id'] ?? '')),
        'name' => piePayPalPayerName($data),
        'email' => $payerEmail,
        'custom_id' => trim((string) ($purchaseUnit['custom_id'] ?? '')),
        'invoice_id' => trim((string) ($purchaseUnit['invoice_id'] ?? '')),
        'raw' => $data,
    );
}
