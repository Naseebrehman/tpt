<?php
/** Stripe server-side integration (PaymentIntents + webhook verification).
 *
 * The Pay Online page keeps rendering the administrator's own Stripe code,
 * but when a Stripe Secret Key is stored a payment is only ever reported as
 * successful after THIS module has confirmed it with Stripe on the server:
 *
 *   • The Secret Key and the Webhook Secret live in Admin → Payments and are
 *     read only here. They are never printed, echoed, logged or included in
 *     any response to a browser — not even their first characters.
 *   • PaymentIntents are created and verified with Stripe's REST API over
 *     HTTPS, so the amount and the currency (always USD) come from the server,
 *     never from the visitor's browser.
 *   • A webhook endpoint (stripe-webhook.php) verifies the Stripe-Signature
 *     and records payments that were completed while the customer's tab was
 *     closed or redirected.
 *   • Every Stripe payment id is recorded exactly once (idempotent), from the
 *     browser-confirmation endpoint and from the webhook alike.
 *
 * Nothing here trusts the browser: the client sends a reference, the status and
 * the amount are read from Stripe's own response, and the currency must match.
 */
if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

/* ===========================================================================
   Settings
   =========================================================================== */

/** Stripe REST API base URL (live and test keys use the same host). */
function pieStripeApiBase()
{
    return 'https://api.stripe.com';
}

/**
 * The Stripe Secret Key (Admin → Payments). SERVER-SIDE ONLY — this value is
 * never rendered into HTML, JavaScript, JSON or any API response.
 */
function pieStripeSecret()
{
    return trim((string) getSetting('stripe_secret_key', ''));
}

/** True when a Secret Key is stored (a key can never be shown, only replaced). */
function pieStripeSecretConfigured()
{
    return pieStripeSecret() !== '';
}

/**
 * The Stripe Webhook Secret (`whsec_…`, Admin → Payments). SERVER-SIDE ONLY —
 * used for Stripe-Signature verification of the webhook endpoint.
 */
function pieStripeWebhookSecret()
{
    return trim((string) getSetting('stripe_webhook_secret', ''));
}

/** True when a Webhook Secret is stored. */
function pieStripeWebhookSecretConfigured()
{
    return pieStripeWebhookSecret() !== '';
}

/**
 * True when server-side Stripe confirmation can run. Mirrors
 * piePayPalServerReady(): the provider is only switched to server-verified
 * mode once its secret credential has been saved.
 */
function pieStripeServerReady()
{
    return pieStripeSecretConfigured();
}

/** 'test' or 'live' — read from the stored key prefix, never from the key text. */
function pieStripeKeyMode()
{
    return strpos(pieStripeSecret(), '_test_') !== false ? 'test' : 'live';
}

/**
 * Stripe secret keys are API tokens (`sk_…` / `rk_…`); anything else is
 * rejected before saving. The value itself is never echoed back.
 */
function pieIsValidStripeSecret($secret)
{
    return (bool) preg_match('/^(sk|rk)_(live|test)_[A-Za-z0-9]{10,}$/', (string) $secret);
}

/** Stripe webhook signing secrets are `whsec_…` tokens. */
function pieIsValidStripeWebhookSecret($secret)
{
    return (bool) preg_match('/^whsec_[A-Za-z0-9+\/=]{8,}$/', (string) $secret);
}

/* ===========================================================================
   Errors / logging (never leak a credential)
   =========================================================================== */

/** Strip anything that looks like a Stripe credential from a message.
 *
 *  The stored values are removed outright; on top of that, any key-shaped or
 *  signing-secret-shaped token is redacted, so a credential can never reach a
 *  log file or an API response — not even the partly masked form Stripe uses in
 *  its own error messages. */
function pieStripeScrub($text)
{
    $text = (string) $text;
    foreach (array(pieStripeSecret(), pieStripeWebhookSecret()) as $credential) {
        if ($credential !== '') {
            $text = str_replace($credential, '***', $text);
        }
    }
    $text = (string) preg_replace('/\b(?:sk|rk|pk)_(?:live|test)_[A-Za-z0-9_*]+/', '***', $text);
    $text = (string) preg_replace('/\bwhsec_[A-Za-z0-9+\/=*]+/', '***', $text);
    return $text;
}

/* ===========================================================================
   HTTP transport
   =========================================================================== */

/**
 * One HTTPS request to Stripe, with cURL when available and a stream-socket
 * fallback (the same approach as the PayPal module, so the integration works
 * on hosts without cURL — and never references the deprecated
 * $http_response_header variable).
 *
 * @return array{ok:bool,status:int,body:string,error:string}
 */
function pieStripeHttp($method, $url, array $headers = array(), $body = null)
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
        unset($ch);
        if ($raw === false) {
            return array('ok' => false, 'status' => 0, 'body' => '', 'error' => pieStripeScrub('Stripe is unreachable: ' . $error));
        }
        return array('ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => (string) $raw, 'error' => '');
    }

    $parts = parse_url($url);
    if (empty($parts['host'])) {
        return array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'Stripe URL is invalid.');
    }
    $secure = (isset($parts['scheme']) && $parts['scheme'] === 'https');
    $port   = isset($parts['port']) ? (int) $parts['port'] : ($secure ? 443 : 80);
    $path   = (isset($parts['path']) ? $parts['path'] : '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

    $remote = ($secure ? 'ssl://' : 'tcp://') . $parts['host'] . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return array('ok' => false, 'status' => 0, 'body' => '', 'error' => pieStripeScrub('Stripe is unreachable: ' . $errstr));
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
        return array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'Stripe returned an unreadable response.');
    }
    $head     = substr($response, 0, $split);
    $bodyText = substr($response, $split + 4);
    $status   = 0;
    if (preg_match('#^HTTP/\d\.\d\s(\d{3})#', $head, $match)) {
        $status = (int) $match[1];
    }
    if (stripos($head, 'Transfer-Encoding: chunked') !== false) {
        $bodyText = pieStripeDechunk($bodyText);
    }
    return array('ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $bodyText, 'error' => '');
}

/** Decode a chunked HTTP/1.1 body (stream-socket fallback only). */
function pieStripeDechunk($body)
{
    if (function_exists('piePayPalDechunk')) {
        return piePayPalDechunk($body);
    }
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
 * Flatten an array into Stripe's form-encoded parameter syntax
 * (`metadata[service]=…`, `payment_method_types[]=card`).
 */
function pieStripeFormEncode(array $data, $prefix = '')
{
    $pairs = array();
    foreach ($data as $key => $value) {
        $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
        if (is_array($value)) {
            $pairs[] = pieStripeFormEncode($value, $name);
            continue;
        }
        if ($value === null || $value === false) { continue; }
        if ($value === true) { $value = 'true'; }
        $pairs[] = rawurlencode($name) . '=' . rawurlencode((string) $value);
    }
    return implode('&', array_filter($pairs, function ($pair) { return $pair !== ''; }));
}

/**
 * One JSON request to the Stripe API.
 *
 * @return array{ok:bool,status:int,data:array,error:string}
 */
function pieStripeRequest($method, $path, $params = null)
{
    $params = is_array($params) ? $params : null;
    $result = array('ok' => false, 'status' => 0, 'data' => array(), 'error' => '');
    if (!pieStripeServerReady()) {
        $result['error'] = 'Stripe server-side confirmation is not configured.';
        return $result;
    }
    $headers = array('Accept: application/json', 'Authorization: Bearer ' . pieStripeSecret());
    $body    = null;
    if ($params !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $body      = pieStripeFormEncode($params);
    }
    $response = pieStripeHttp(strtoupper($method), pieStripeApiBase() . $path, $headers, $body);
    $result['status'] = (int) $response['status'];
    if ($response['error'] !== '') {
        $result['error'] = $response['error'];
        error_log('[TPT] Stripe ' . strtoupper($method) . ' ' . $path . ' failed: ' . $result['error']);
        return $result;
    }

    $decoded = json_decode($response['body'], true);
    $result['data'] = is_array($decoded) ? $decoded : array();
    $result['ok']   = $response['ok'];
    if (!$result['ok']) {
        $message = '';
        if (!empty($result['data']['error']['message'])) { $message = (string) $result['data']['error']['message']; }
        $result['error'] = pieStripeScrub($message !== '' ? $message : ('Stripe returned HTTP ' . $response['status'] . '.'));
        error_log('[TPT] Stripe ' . strtoupper($method) . ' ' . $path . ' failed (HTTP ' . $response['status'] . '): ' . $result['error']);
        /* The detail stays in the server log: Stripe echoes part of the key it
           rejected (and object ids) in its error text, and no credential may
           ever reach a browser. Callers get a plain, actionable message. */
        if ($response['status'] === 401 || $response['status'] === 403) {
            $result['error'] = 'Stripe rejected the stored Secret Key. Check the key in Admin → Payments.';
        } elseif ($response['status'] === 404) {
            $result['error'] = 'Stripe could not find that payment.';
        }
    }
    return $result;
}

/* ===========================================================================
   Amounts
   =========================================================================== */

/** Format a client-supplied amount as Stripe expects it (cents, > 0, bounded). */
function pieStripeAmountCents($value)
{
    $amount = round((float) $value, 2);
    if (!is_finite($amount) || $amount <= 0 || $amount > 1000000) {
        return 0;
    }
    return (int) round($amount * 100);
}

/** Render cents as a 2-decimal USD string ('25000' → '250.00'). */
function pieStripeFormatAmount($cents)
{
    return number_format(((int) $cents) / 100, 2, '.', '');
}

/* ===========================================================================
   PaymentIntents
   =========================================================================== */

/**
 * Create a PaymentIntent on the server. The amount and the currency are set
 * HERE — a browser can never talk the server into charging another amount.
 *
 * @return array{ok:bool,id:string,client_secret:string,amount_cents:int,currency:string,error:string}
 */
function pieStripeCreatePaymentIntent($amount, $service = '', $name = '', $email = '')
{
    $fail = array('ok' => false, 'id' => '', 'client_secret' => '', 'amount_cents' => 0, 'currency' => 'USD', 'error' => '');
    if (!pieStripeServerReady()) {
        $fail['error'] = 'Stripe server-side confirmation is not configured.';
        return $fail;
    }
    $cents = pieStripeAmountCents($amount);
    if ($cents <= 0) {
        $fail['error'] = 'Enter an amount greater than zero.';
        return $fail;
    }

    $description = trim((string) $service);
    if ($description === '') {
        $description = 'Payment to ' . getSetting('site_name', SITE_NAME);
    }
    $params = array(
        'amount'               => $cents,
        'currency'             => 'usd',
        'payment_method_types' => array('card'),
        'description'          => mb_substr($description, 0, 250),
        'metadata'             => array(
            'service' => mb_substr($description, 0, 250),
        ),
        /* Keep the visitor on the page: the Pay Online page shows the result. */
        'automatic_payment_methods' => array('enabled' => false),
    );
    if (trim((string) $name) !== '') {
        $params['metadata']['name'] = mb_substr(trim((string) $name), 0, 150);
    }
    if (trim((string) $email) !== '' && filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL)) {
        $params['metadata']['email'] = mb_substr(trim((string) $email), 0, 150);
        $params['receipt_email']     = mb_substr(trim((string) $email), 0, 150);
    }

    $response = pieStripeRequest('POST', '/v1/payment_intents', $params);
    if (!$response['ok'] || empty($response['data']['id']) || empty($response['data']['client_secret'])) {
        $fail['error'] = $response['error'] !== '' ? $response['error'] : 'Stripe could not start this payment.';
        return $fail;
    }
    return array(
        'ok'            => true,
        'id'            => (string) $response['data']['id'],
        'client_secret' => (string) $response['data']['client_secret'],
        'amount_cents'  => (int) ($response['data']['amount'] ?? $cents),
        'currency'      => strtoupper((string) ($response['data']['currency'] ?? 'usd')),
        'error'         => '',
    );
}

/** Retrieve one PaymentIntent (used by the confirmation endpoint and webhook). */
function pieStripeRetrievePaymentIntent($intentId)
{
    return pieStripeRequest('GET', '/v1/payment_intents/' . rawurlencode((string) $intentId) . '?expand[]=latest_charge');
}

/** Retrieve one Checkout Session (a session reference is accepted too). */
function pieStripeRetrieveCheckoutSession($sessionId)
{
    return pieStripeRequest('GET', '/v1/checkout/sessions/' . rawurlencode((string) $sessionId));
}

/** True when a reference looks like a Stripe object id we may look up. */
function pieIsValidStripeReference($reference)
{
    return (bool) preg_match('/^(pi|cs)_[A-Za-z0-9_]{6,160}$/', (string) $reference);
}

/**
 * The buyer-facing confirmation built ONLY from Stripe's verified response.
 *
 * @return array{confirmed:bool,reference:string,provider_transaction_id:string,amount:string,currency:string,status:string,name:string,email:string,service:string,raw:array}
 */
function pieStripeConfirmation(array $data)
{
    $status   = strtolower((string) ($data['status'] ?? ''));
    $cents    = (int) ($data['amount_received'] ?? 0);
    if ($cents <= 0) { $cents = (int) ($data['amount'] ?? 0); }
    $currency = strtoupper((string) ($data['currency'] ?? ''));
    $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : array();
    $name     = trim((string) ($metadata['name'] ?? ''));
    $email    = trim((string) ($metadata['email'] ?? ($data['receipt_email'] ?? '')));
    if ($name === '' && !empty($data['latest_charge']['billing_details']['name'])) {
        $name = trim((string) $data['latest_charge']['billing_details']['name']);
    }
    if ($email === '' && !empty($data['latest_charge']['billing_details']['email'])) {
        $email = trim((string) $data['latest_charge']['billing_details']['email']);
    }
    $reference = (string) ($data['id'] ?? '');
    return array(
        'confirmed'               => $status === 'succeeded' && $cents > 0 && $currency === 'USD',
        'reference'               => $reference,
        'provider_transaction_id' => $reference,
        'amount'                  => $cents > 0 ? pieStripeFormatAmount($cents) : '',
        'currency'                => $currency !== '' ? $currency : 'USD',
        'status'                  => strtoupper($status),
        'name'                    => $name,
        'email'                   => $email,
        'service'                 => trim((string) ($metadata['service'] ?? ($data['description'] ?? ''))),
        'raw'                     => $data,
    );
}

/**
 * Fetch a payment (PaymentIntent or Checkout Session) from Stripe with the
 * stored Secret Key and confirm it: status = succeeded, amount matches and the
 * currency is USD. Nothing is ever reported as successful on trust alone.
 *
 * @return array{ok:bool,error:string,confirmation:array}
 */
function pieStripeConfirmPayment($reference, $expectedAmount = '')
{
    $reference = trim((string) $reference);
    $empty     = array(
        'confirmed' => false, 'reference' => '', 'provider_transaction_id' => '',
        'amount' => '', 'currency' => 'USD', 'status' => '', 'name' => '',
        'email' => '', 'service' => '', 'raw' => array(),
    );
    if (!pieStripeServerReady()) {
        return array('ok' => false, 'error' => 'Stripe server-side confirmation is not configured.', 'confirmation' => $empty);
    }
    if (!pieIsValidStripeReference($reference)) {
        return array('ok' => false, 'error' => 'That payment reference is not valid.', 'confirmation' => $empty);
    }

    $data = array();
    if (strpos($reference, 'cs_') === 0) {
        $session = pieStripeRetrieveCheckoutSession($reference);
        if (!$session['ok']) {
            return array('ok' => false, 'error' => $session['error'] !== '' ? $session['error'] : 'Stripe could not find this payment.', 'confirmation' => $empty);
        }
        $data = $session['data'];
        /* Prefer the PaymentIntent behind the session: it carries the
           authoritative status, amount and currency. */
        $intentId = isset($data['payment_intent']) ? (string) $data['payment_intent'] : '';
        if ($intentId !== '' && strpos($intentId, 'pi_') === 0) {
            $intent = pieStripeRetrievePaymentIntent($intentId);
            if ($intent['ok']) {
                $data = $intent['data'];
            }
        }
        $confirmation = pieStripeConfirmation($data);
        if (!$confirmation['confirmed'] && strtolower((string) ($data['payment_status'] ?? '')) === 'paid') {
            /* A session may be paid while its intent is not expanded. */
            $confirmation = pieStripeConfirmation(array(
                'id'              => $intentId !== '' ? $intentId : $reference,
                'status'          => 'succeeded',
                'amount'          => (int) ($data['amount_total'] ?? 0),
                'currency'        => (string) ($data['currency'] ?? ''),
                'metadata'        => is_array($data['metadata'] ?? null) ? $data['metadata'] : array(),
                'receipt_email'   => (string) ($data['customer_details']['email'] ?? ''),
            ));
        }
    } else {
        $intent = pieStripeRetrievePaymentIntent($reference);
        if (!$intent['ok']) {
            return array('ok' => false, 'error' => $intent['error'] !== '' ? $intent['error'] : 'Stripe could not find this payment.', 'confirmation' => $empty);
        }
        $confirmation = pieStripeConfirmation($intent['data']);
    }

    if (!$confirmation['confirmed']) {
        $status = $confirmation['status'] !== '' ? strtolower($confirmation['status']) : 'unknown';
        return array('ok' => false, 'error' => 'Stripe has not completed this payment yet (status: ' . $status . ').', 'confirmation' => $confirmation);
    }
    if ($confirmation['currency'] !== 'USD') {
        return array('ok' => false, 'error' => 'Stripe returned an unexpected currency.', 'confirmation' => $confirmation);
    }

    /* The amount is read from Stripe's own response — never from the browser. */
    $expectedCents = pieStripeAmountCents($expectedAmount);
    if ($expectedCents > 0 && pieStripeAmountCents($confirmation['amount']) !== $expectedCents) {
        error_log('[TPT] Stripe amount mismatch for ' . $reference . ': expected ' . $expectedCents . ', charged ' . pieStripeAmountCents($confirmation['amount']));
        return array('ok' => false, 'error' => 'The charged amount does not match the amount requested.', 'confirmation' => $confirmation);
    }

    return array('ok' => true, 'error' => '', 'confirmation' => $confirmation);
}

/* ===========================================================================
   Webhook signature verification
   =========================================================================== */

/**
 * Verify a `Stripe-Signature` header against the stored Webhook Secret:
 *   t=<timestamp>,v1=<hmac-sha256 of "<timestamp>.<body>">
 * Returns false for a missing/forged/expired signature.
 */
function pieStripeVerifyWebhookSignature($payload, $signatureHeader, $tolerance = 300)
{
    if (!pieStripeWebhookSecretConfigured()) {
        return false;
    }
    $signatureHeader = trim((string) $signatureHeader);
    $payload         = (string) $payload;
    if ($signatureHeader === '' || $payload === '') {
        return false;
    }

    $timestamp  = '';
    $signatures = array();
    foreach (explode(',', $signatureHeader) as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) !== 2) { continue; }
        if ($pair[0] === 't')  { $timestamp = trim($pair[1]); }
        if ($pair[0] === 'v1') { $signatures[] = trim($pair[1]); }
    }
    if ($timestamp === '' || !$signatures || !ctype_digit($timestamp)) {
        return false;
    }
    if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, pieStripeWebhookSecret());
    foreach ($signatures as $signature) {
        if ($signature !== '' && hash_equals($expected, $signature)) {
            return true;
        }
    }
    return false;
}

/**
 * Turn a verified Stripe webhook event into a normalised payment record.
 * Only events that represent a completed, USD payment are accepted.
 *
 * @return array{ok:bool,error:string,confirmation:array,event_type:string}
 */
function pieStripeWebhookPayment(array $event)
{
    $type = isset($event['type']) ? (string) $event['type'] : '';
    $object = isset($event['data']['object']) && is_array($event['data']['object']) ? $event['data']['object'] : array();
    $empty = array(
        'confirmed' => false, 'reference' => '', 'provider_transaction_id' => '',
        'amount' => '', 'currency' => 'USD', 'status' => '', 'name' => '',
        'email' => '', 'service' => '', 'raw' => array(),
    );

    if ($type === 'payment_intent.succeeded') {
        $confirmation = pieStripeConfirmation($object);
    } elseif ($type === 'checkout.session.completed') {
        $intentId     = isset($object['payment_intent']) ? (string) $object['payment_intent'] : '';
        $confirmation = array('confirmed' => false) + $empty;
        if ($intentId !== '' && strpos($intentId, 'pi_') === 0) {
            $intent = pieStripeRetrievePaymentIntent($intentId);
            if ($intent['ok']) {
                $confirmation = pieStripeConfirmation($intent['data']);
            }
        }
        if (!$confirmation['confirmed'] && strtolower((string) ($object['payment_status'] ?? '')) === 'paid') {
            $confirmation = pieStripeConfirmation(array(
                'id'            => $intentId !== '' ? $intentId : (string) ($object['id'] ?? ''),
                'status'        => 'succeeded',
                'amount'        => (int) ($object['amount_total'] ?? 0),
                'currency'      => (string) ($object['currency'] ?? ''),
                'metadata'      => is_array($object['metadata'] ?? null) ? $object['metadata'] : array(),
                'receipt_email' => (string) ($object['customer_details']['email'] ?? ''),
            ));
        }
    } else {
        return array('ok' => false, 'error' => 'This event is not a completed payment.', 'confirmation' => $empty, 'event_type' => $type);
    }

    if (!$confirmation['confirmed']) {
        return array('ok' => false, 'error' => 'The event does not describe a completed USD payment.', 'confirmation' => $confirmation, 'event_type' => $type);
    }
    return array('ok' => true, 'error' => '', 'confirmation' => $confirmation, 'event_type' => $type);
}

/* ===========================================================================
   Browser-facing payload
   =========================================================================== */

/**
 * Payment flags safe to publish to the browser: no key, no webhook secret and
 * no server paths beyond the public endpoint. Used by the Pay Online page and
 * by the rendered payment code.
 */
function pieStripeClientConfig()
{
    return array(
        'serverVerification' => pieStripeServerReady(),
        'currency'           => 'USD',
        'endpoint'           => rtrim(SITE_URL, '/') . url('stripe-api'),
        'termsUrl'           => pieTermsUrl(),
    );
}
