<?php
/** PayPal server-side integration (REST Orders v2).
 *
 * The Pay Online page keeps rendering the administrator's own PayPal code,
 * but a payment is only ever reported as successful after THIS module has
 * confirmed it with PayPal on the server:
 *
 *   • The PayPal Secret lives in Admin → Payments and is read only here. It is
 *     never printed, echoed, logged or included in any response to a browser.
 *   • Orders and captures are created/verified with PayPal's REST API using an
 *     OAuth access token fetched over HTTPS.
 *   • Every order is created with shipping_preference = NO_SHIPPING, so PayPal
 *     never asks the buyer for a shipping address (digital / service payment).
 *   • Confirmed captures are recorded in the existing `payments` /
 *     `payment_events` tables when they exist, so the dashboard keeps an audit
 *     trail (never a failure for the buyer if that write fails).
 *
 * Nothing here trusts the browser: the client sends an order id, the amount is
 * taken from PayPal's own response, and the currency must match.
 */
if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

/* ===========================================================================
   Settings
   =========================================================================== */

/** PayPal REST API base URL for the configured environment. */
function piePayPalApiBase()
{
    return piePayPalEnv() === 'sandbox'
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';
}

/** 'sandbox' or 'live' — chosen by the administrator, default live. */
function piePayPalEnv()
{
    $env = strtolower(trim((string) getSetting('paypal_env', 'live')));
    return $env === 'sandbox' ? 'sandbox' : 'live';
}

/**
 * The PayPal Secret (Admin → Payments). SERVER-SIDE ONLY — this value is never
 * rendered into HTML, JavaScript, JSON or any API response.
 */
function piePayPalSecret()
{
    return trim((string) getSetting('paypal_secret', ''));
}

/** True when a Secret is stored (a Secret can never be shown, only replaced). */
function piePayPalSecretConfigured()
{
    return piePayPalSecret() !== '';
}

/** True when a server-side verification/capture flow can run. */
function piePayPalServerReady()
{
    return piePayPalClientIdConfigured() && piePayPalSecretConfigured();
}

/** PayPal secrets are API tokens; anything else is rejected before saving. */
function pieIsValidPayPalSecret($secret)
{
    return (bool) preg_match('/^[A-Za-z0-9_\-.~]{16,}$/', (string) $secret);
}

/* ===========================================================================
   Errors / logging (never leak the secret)
   =========================================================================== */

/** Strip anything that looks like the stored credentials from a message. */
function piePayPalScrub($text)
{
    $text  = (string) $text;
    $secret = piePayPalSecret();
    if ($secret !== '') {
        $text = str_replace($secret, '***', $text);
    }
    return $text;
}

/* ===========================================================================
   HTTP transport
   =========================================================================== */

/**
 * One HTTPS request, with cURL when available and a stream-socket fallback.
 *
 * The fallback exists so the module works on hosts without cURL, and it reads
 * the status line itself — PHP 8.5 deprecates the $http_response_header
 * variable, so it is never referenced.
 *
 * @return array{ok:bool,status:int,body:string,error:string}
 */
function piePayPalHttp($method, $url, array $headers = array(), $body = null)
{
    $method = strtoupper($method);
    $body   = $body === null ? '' : (string) $body;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
        ));
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        /* No curl_close(): it is a no-op since PHP 8.0 and deprecated in 8.5. */
        unset($ch);
        if ($raw === false) {
            return array('ok' => false, 'status' => 0, 'body' => '', 'error' => piePayPalScrub('PayPal is unreachable: ' . $error));
        }
        return array('ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => (string) $raw, 'error' => '');
    }

    $parts = parse_url($url);
    if (empty($parts['host'])) {
        return array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'PayPal URL is invalid.');
    }
    $secure = (isset($parts['scheme']) && $parts['scheme'] === 'https');
    $port   = isset($parts['port']) ? (int) $parts['port'] : ($secure ? 443 : 80);
    $path   = (isset($parts['path']) ? $parts['path'] : '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

    $remote = ($secure ? 'ssl://' : 'tcp://') . $parts['host'] . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return array('ok' => false, 'status' => 0, 'body' => '', 'error' => piePayPalScrub('PayPal is unreachable: ' . $errstr));
    }
    stream_set_timeout($socket, 25);

    $request = $method . ' ' . $path . " HTTP/1.1\r\n"
        . 'Host: ' . $parts['host'] . "\r\n"
        . "Connection: close\r\n";
    $hasContentType = false;
    foreach ($headers as $header) {
        if (stripos($header, 'content-type:') === 0) { $hasContentType = true; }
        $request .= $header . "\r\n";
    }
    if ($body !== '' && !$hasContentType) {
        $request .= "Content-Type: application/x-www-form-urlencoded\r\n";
    }
    $request .= 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body;

    @fwrite($socket, $request);
    $response = '';
    while (!feof($socket)) {
        $chunk = @fread($socket, 8192);
        if ($chunk === false || $chunk === '') { break; }
        $response .= $chunk;
    }
    @fclose($socket);

    $split = strpos($response, "\r\n\r\n");
    if ($split === false) {
        return array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'PayPal returned an unreadable response.');
    }
    $head = substr($response, 0, $split);
    $bodyText = substr($response, $split + 4);
    $status = 0;
    if (preg_match('#^HTTP/\d\.\d\s(\d{3})#', $head, $match)) {
        $status = (int) $match[1];
    }
    if (stripos($head, 'Transfer-Encoding: chunked') !== false) {
        $bodyText = piePayPalDechunk($bodyText);
    }
    return array('ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $bodyText, 'error' => '');
}

/** Decode a chunked HTTP/1.1 body (stream-socket fallback only). */
function piePayPalDechunk($body)
{
    $decoded = '';
    $offset  = 0;
    $length  = strlen($body);
    while ($offset < $length) {
        $lineEnd = strpos($body, "\r\n", $offset);
        if ($lineEnd === false) { break; }
        $size = hexdec(trim(substr($body, $offset, $lineEnd - $offset)));
        if ($size <= 0) { break; }
        $decoded .= substr($body, $lineEnd + 2, $size);
        $offset = $lineEnd + 2 + $size + 2;
    }
    return $decoded === '' ? $body : $decoded;
}

/**
 * One JSON request to PayPal.
 *
 * @return array{ok:bool,status:int,data:array,error:string}
 */
function piePayPalRequest($method, $path, $body = null, $accessToken = '')
{
    $headers = array('Accept: application/json', 'Content-Type: application/json');
    if ($accessToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }
    $response = piePayPalHttp($method, piePayPalApiBase() . $path, $headers, $body === null ? null : json_encode($body));

    $result = array('ok' => false, 'status' => $response['status'], 'data' => array(), 'error' => '');
    if ($response['error'] !== '') {
        $result['error'] = $response['error'];
        return $result;
    }

    $decoded = json_decode($response['body'], true);
    $result['data'] = is_array($decoded) ? $decoded : array();
    $result['ok']   = $response['ok'];
    if (!$result['ok']) {
        $message = '';
        if (!empty($result['data']['message']))                  { $message = $result['data']['message']; }
        if (!empty($result['data']['error_description']))        { $message = $result['data']['error_description']; }
        if ($message === '' && !empty($result['data']['details'][0]['description'])) { $message = $result['data']['details'][0]['description']; }
        $result['error'] = piePayPalScrub($message !== '' ? $message : ('PayPal returned HTTP ' . $response['status'] . '.'));
        error_log('[TPT] PayPal ' . strtoupper($method) . ' ' . $path . ' failed (HTTP ' . $response['status'] . '): ' . $result['error']);
    }
    return $result;
}

/** Fetch an OAuth2 access token (cached for the request only). */
function piePayPalAccessToken()
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $cache = array('ok' => false, 'token' => '', 'error' => 'PayPal is not configured.');
    if (!piePayPalServerReady()) {
        return $cache;
    }

    $auth = base64_encode(piePayPalClientId() . ':' . piePayPalSecret());
    $response = piePayPalHttp(
        'POST',
        piePayPalApiBase() . '/v1/oauth2/token',
        array('Authorization: Basic ' . $auth, 'Content-Type: application/x-www-form-urlencoded'),
        'grant_type=client_credentials'
    );

    if ($response['error'] !== '') {
        $cache['error'] = $response['error'];
        error_log('[TPT] PayPal token error: ' . $cache['error']);
        return $cache;
    }

    $decoded = json_decode($response['body'], true);
    if ($response['ok'] && !empty($decoded['access_token'])) {
        $cache = array('ok' => true, 'token' => (string) $decoded['access_token'], 'error' => '');
        return $cache;
    }
    $cache['error'] = piePayPalScrub(!empty($decoded['error_description'])
        ? $decoded['error_description']
        : 'PayPal rejected the stored Client ID / Secret (HTTP ' . $response['status'] . ').');
    error_log('[TPT] PayPal token failed (HTTP ' . $response['status'] . '): ' . $cache['error']);
    return $cache;
}

/* ===========================================================================
   Orders
   =========================================================================== */

/** Format a client-supplied amount as PayPal expects it (2 decimals, > 0). */
function piePayPalAmount($value)
{
    $amount = round((float) $value, 2);
    if (!is_finite($amount) || $amount <= 0 || $amount > 1000000) {
        return '';
    }
    return number_format($amount, 2, '.', '');
}

/**
 * Create a PayPal order on the server.
 *
 * @param string $amount  e.g. '500.00'
 * @param string $service what the buyer is paying for (order description)
 * @param string $name    buyer name captured by the payment form (custom_id)
 * @param string $email   buyer email captured by the payment form
 * @return array{ok:bool,id:string,error:string}
 */
function piePayPalCreateOrder($amount, $service = '', $name = '', $email = '')
{
    $fail = array('ok' => false, 'id' => '', 'error' => '');
    if (!piePayPalServerReady()) {
        $fail['error'] = 'PayPal server-side verification is not configured.';
        return $fail;
    }
    $amount = piePayPalAmount($amount);
    if ($amount === '') {
        $fail['error'] = 'Enter an amount greater than zero.';
        return $fail;
    }

    $token = piePayPalAccessToken();
    if (!$token['ok']) {
        $fail['error'] = $token['error'];
        return $fail;
    }

    $description = trim((string) $service);
    if ($description === '') {
        $description = 'Payment to ' . getSetting('site_name', SITE_NAME);
    }
    $customId = trim((string) ($email !== '' ? $email : $name));
    if (mb_strlen($customId) > 127) {
        $customId = mb_substr($customId, 0, 127);
    }

    $order = array(
        'intent'         => 'CAPTURE',
        'purchase_units' => array(array(
            'amount'      => array('currency_code' => 'USD', 'value' => $amount),
            'description' => mb_substr($description, 0, 127),
            'custom_id'   => $customId,
        )),
        'application_context' => array(
            /* Digital / service payment — never request a shipping address. */
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'         => 'PAY_NOW',
            'brand_name'          => mb_substr((string) getSetting('site_name', SITE_NAME), 0, 127),
        ),
    );

    $response = piePayPalRequest('POST', '/v2/checkout/orders', $order, $token['token']);
    if (!$response['ok'] || empty($response['data']['id'])) {
        $fail['error'] = $response['error'] !== '' ? $response['error'] : 'PayPal could not create the order.';
        return $fail;
    }
    return array('ok' => true, 'id' => (string) $response['data']['id'], 'error' => '');
}

/**
 * Capture an approved order server-side (SECRET never reaches the browser).
 *
 * @return array{ok:bool,error:string,data:array}
 */
function piePayPalCaptureOrder($orderId, $expectedAmount = '')
{
    $orderId = trim((string) $orderId);
    if (!preg_match('/^[A-Za-z0-9\-]{6,40}$/', $orderId)) {
        return array('ok' => false, 'error' => 'That payment reference is not valid.', 'data' => array());
    }
    if (!piePayPalServerReady()) {
        return array('ok' => false, 'error' => 'PayPal server-side verification is not configured.', 'data' => array());
    }
    $token = piePayPalAccessToken();
    if (!$token['ok']) {
        return array('ok' => false, 'error' => $token['error'], 'data' => array());
    }

    $response = piePayPalRequest('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', new stdClass(), $token['token']);
    if (!$response['ok']) {
        return array('ok' => false, 'error' => $response['error'] !== '' ? $response['error'] : 'PayPal could not capture the payment.', 'data' => array());
    }

    $data     = $response['data'];
    $capture  = piePayPalCapture($data);
    $status   = strtoupper((string) ($capture['status'] ?? ($data['status'] ?? '')));
    if ($status !== 'COMPLETED' && $status !== 'PENDING') {
        return array('ok' => false, 'error' => 'PayPal reports the payment as ' . ($status !== '' ? strtolower($status) : 'incomplete') . '.', 'data' => array());
    }

    /* The amount is read from PayPal's own response — never from the browser. */
    $amount   = piePayPalAmount(isset($capture['amount']['value']) ? $capture['amount']['value'] : '');
    $currency = strtoupper((string) ($capture['amount']['currency_code'] ?? ''));
    if ($amount === '' || ($currency !== '' && $currency !== 'USD')) {
        return array('ok' => false, 'error' => 'PayPal returned an unexpected amount or currency.', 'data' => array());
    }
    $expected = piePayPalAmount($expectedAmount);
    if ($expected !== '' && $expected !== $amount) {
        error_log('[TPT] PayPal amount mismatch for order ' . $orderId . ': expected ' . $expected . ', captured ' . $amount);
        return array('ok' => false, 'error' => 'The captured amount does not match the amount requested.', 'data' => array());
    }

    return array('ok' => true, 'error' => '', 'data' => $data);
}

/** Read (never capture) an order — used when a button calls actions.order.get(). */
function piePayPalGetOrder($orderId)
{
    $orderId = trim((string) $orderId);
    if (!preg_match('/^[A-Za-z0-9\-]{6,40}$/', $orderId) || !piePayPalServerReady()) {
        return array('ok' => false, 'error' => 'Not available.', 'data' => array());
    }
    $token = piePayPalAccessToken();
    if (!$token['ok']) {
        return array('ok' => false, 'error' => $token['error'], 'data' => array());
    }
    $response = piePayPalRequest('GET', '/v2/checkout/orders/' . rawurlencode($orderId), null, $token['token']);
    if (!$response['ok']) {
        return array('ok' => false, 'error' => $response['error'], 'data' => array());
    }
    return array('ok' => true, 'error' => '', 'data' => $response['data']);
}

/** The relevant capture inside a PayPal order/capture response. */
function piePayPalCapture(array $data)
{
    if (!empty($data['purchase_units'][0]['payments']['captures'][0])) {
        return $data['purchase_units'][0]['payments']['captures'][0];
    }
    if (isset($data['status'], $data['amount'])) {
        return $data; /* already a capture object */
    }
    return array();
}

/** Payer's display name from a PayPal response (falls back to ''.). */
function piePayPalPayerName(array $data)
{
    $name = array();
    if (!empty($data['payer']['name']) && is_array($data['payer']['name'])) {
        $name = $data['payer']['name'];
    }
    $combined = trim(($name['given_name'] ?? '') . ' ' . ($name['surname'] ?? ''));
    if ($combined === '' && !empty($name['full_name'])) {
        $combined = (string) $name['full_name'];
    }
    if ($combined === '' && !empty($data['payer']['email_address'])) {
        $combined = (string) $data['payer']['email_address'];
    }
    return trim($combined);
}

/**
 * The buyer-facing confirmation built ONLY from PayPal's verified response.
 *
 * @return array{confirmed:bool,name:string,amount:string,currency:string,reference:string,status:string}
 */
function piePayPalConfirmation(array $data)
{
    $capture = piePayPalCapture($data);
    $status  = strtoupper((string) ($capture['status'] ?? ($data['status'] ?? '')));
    $amount  = piePayPalAmount(isset($capture['amount']['value']) ? $capture['amount']['value'] : '');
    return array(
        'confirmed' => $status === 'COMPLETED' && $amount !== '',
        'name'      => piePayPalPayerName($data),
        'amount'    => $amount,
        'currency'  => strtoupper((string) ($capture['amount']['currency_code'] ?? 'USD')),
        'reference' => (string) ($capture['id'] ?? ($data['id'] ?? '')),
        'status'    => $status,
    );
}

/* ===========================================================================
   Audit trail (existing tables, best effort)
   =========================================================================== */

/** True when a table exists in the current database. */
function piePayPalTableExists($table)
{
    $row = dbOne(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        array((string) $table)
    );
    return $row && (int) $row['c'] > 0;
}

/**
 * Record a confirmed capture in the existing `payments` + `payment_events`
 * tables. Never throws: a logging failure must not affect the buyer.
 *
 * @return int payment row id (0 when not stored)
 */
function piePayPalRecord(array $data, $service = '', $name = '', $email = '', $phone = '')
{
    try {
        if (!DB_OK || !piePayPalTableExists('payments')) {
            return 0;
        }
        $confirmation = piePayPalConfirmation($data);
        if (!$confirmation['confirmed']) {
            return 0;
        }
        $token = bin2hex(random_bytes(16));
        $paymentId = dbInsert(
            'INSERT INTO payments (token, name, email, phone, service, reference, amount_usd, method, status, provider_ref, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                $token,
                mb_substr((string) ($name !== '' ? $name : $confirmation['name']), 0, 150),
                mb_substr((string) $email, 0, 150),
                mb_substr((string) $phone, 0, 30),
                mb_substr((string) $service, 0, 100),
                mb_substr('PayPal ' . $confirmation['reference'], 0, 150),
                (float) $confirmation['amount'],
                'paypal',
                'paid',
                mb_substr($confirmation['reference'], 0, 255),
                pieClientIp(),
            )
        );
        if ($paymentId > 0 && piePayPalTableExists('payment_events')) {
            dbExec(
                'INSERT IGNORE INTO payment_events (event_id, payment_id, event_type) VALUES (?, ?, ?)',
                array('paypal:' . $confirmation['reference'], $paymentId, 'paypal.capture.completed')
            );
        }
        return $paymentId > 0 ? (int) $paymentId : 0;
    } catch (Throwable $error) {
        error_log('[TPT] Could not record PayPal payment: ' . piePayPalScrub($error->getMessage()));
        return 0;
    }
}

/* ===========================================================================
   Browser-facing payload
   =========================================================================== */

/**
 * Payment flags safe to publish to the browser: no secret, no server paths.
 * Used by the Pay Online page and by the rendered payment code.
 */
function piePayPalClientConfig()
{
    return array(
        'serverVerification' => piePayPalServerReady(),
        'clientId'           => piePayPalClientId(),
        'currency'           => 'USD',
        'endpoint'           => rtrim(SITE_URL, '/') . url('paypal-api'),
        'termsUrl'           => pieTermsUrl(),
    );
}
