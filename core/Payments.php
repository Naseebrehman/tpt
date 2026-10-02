<?php
/**
 * Server-side PayPal and Stripe checkout support.
 *
 * Provider credentials stay in deployment configuration. The browser only
 * requests a checkout URL from this application and then leaves for the
 * provider's hosted checkout; order capture and payment confirmation happen
 * server-side before a payment is marked complete.
 */
if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

/* ===========================================================================
   Payment services and configuration
   =========================================================================== */

/** Active service names in the order configured by Admin → Payment Settings. */
function piePaymentServices()
{
    $table = dbOne(
        "SELECT COUNT(*) AS c FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_services'"
    );
    $names = array();
    if ($table && (int) $table['c'] > 0) {
        foreach (dbAll('SELECT name FROM payment_services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC') as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') { $names[] = $name; }
        }
        return $names;
    }
    return array('AI Optimization', 'Web Development', 'Digital Marketing', 'Business Consultation', 'G-W-M Services', 'Monthly Marketing Charges', 'Others');
}

/** Service rows for the existing Admin services manager. */
function piePaymentServiceRows()
{
    require_once BASE_PATH . '/core/Schema.php';
    return Schema::hasTable('payment_services')
        ? dbAll('SELECT * FROM payment_services ORDER BY sort_order ASC, id ASC')
        : array();
}

/** Recent payment ledger rows for Admin → Payment Settings. */
function piePaymentRecordRows($limit = 100)
{
    if (!DB_OK) { return array(); }
    $table = dbOne(
        "SELECT COUNT(*) AS c FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_records'"
    );
    if (!$table || (int) $table['c'] < 1) { return array(); }
    $limit = max(1, min(250, (int) $limit));
    return dbAll('SELECT reference, provider, provider_transaction_id, name, service, amount, currency, status, created_at, confirmed_at FROM payment_records ORDER BY id DESC LIMIT ' . $limit);
}

/** The PayPal REST Client ID remains managed in the existing Admin screen. */
function piePayPalClientId()
{
    return trim((string) getSetting('paypal_client_id', ''));
}

/** PayPal Client IDs contain letters, numbers, hyphens and underscores. */
function pieIsValidPayPalClientId($clientId)
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{4,255}$/D', (string) $clientId);
}

/** Whether server-side PayPal checkout can be used. */
function piePayPalClientIdConfigured()
{
    return pieIsValidPayPalClientId(piePayPalClientId());
}
function piePayPalConfigured()
{
    return piePayPalClientIdConfigured()
        && defined('PAYPAL_CLIENT_SECRET')
        && trim((string) PAYPAL_CLIENT_SECRET) !== '';
}

/** Whether the Stripe secret key is present in server-only configuration. */
function pieStripeConfigured()
{
    if (!defined('STRIPE_SECRET_KEY')) { return false; }
    return (bool) preg_match('/^(?:sk|rk)_(?:test|live)_[A-Za-z0-9_]+$/D', trim((string) STRIPE_SECRET_KEY));
}

/** Public Terms & Conditions URL linked from the payment page. */
function pieTermsUrl()
{
    return rtrim(SITE_URL, '/') . url('terms');
}

/* ===========================================================================
   Provider operations and payment confirmation
   =========================================================================== */

class PaymentGateway
{
    private static $testTransport = null;
    private static $paypalToken = '';
    private static $paypalTokenExpiresAt = 0;

    /** A transport hook for isolated tests; production uses HTTPS cURL/streams. */
    public static function setTransportForTests($transport = null)
    {
        self::$testTransport = is_callable($transport) ? $transport : null;
    }

    /** Validate and normalize an amount without floating-point rounding. */
    public static function normalizeAmount($value)
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $value)) { return false; }
        $parts = explode('.', $value, 2);
        $dollars = (int) $parts[0];
        $cents = isset($parts[1]) ? (int) str_pad($parts[1], 2, '0') : 0;
        $totalCents = ($dollars * 100) + $cents;
        if ($totalCents < 1 || $totalCents > 100000000) { return false; }
        return array(
            'decimal' => sprintf('%d.%02d', intdiv($totalCents, 100), $totalCents % 100),
            'cents' => $totalCents,
        );
    }

    /** Create one local pending record and a hosted provider checkout. */
    public static function createCheckout($provider, array $input)
    {
        $provider = strtolower(trim((string) $provider));
        if (!in_array($provider, array('paypal', 'stripe'), true)) { throw new RuntimeException('Choose a supported payment method.'); }
        if (($provider === 'paypal' && !piePayPalConfigured()) || ($provider === 'stripe' && !pieStripeConfigured())) {
            throw new RuntimeException('This payment method is not available right now.');
        }
        if (!DB_OK) { throw new RuntimeException('Online payments are temporarily unavailable.'); }

        $name = sanitize(isset($input['name']) ? $input['name'] : '');
        $service = sanitize(isset($input['service']) ? $input['service'] : '');
        $amount = self::normalizeAmount(isset($input['amount']) ? $input['amount'] : '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) { throw new RuntimeException('Please enter a valid name or business name.'); }
        if (!in_array($service, piePaymentServices(), true)) { throw new RuntimeException('Please choose a service from the list.'); }
        if ($amount === false) { throw new RuntimeException('Please enter a valid amount in USD.'); }

        $record = self::createPendingRecord($provider, $name, $service, $amount['decimal']);
        try {
            if ($provider === 'paypal') {
                return self::createPayPalOrder($record, $amount);
            }
            return self::createStripeSession($record, $amount);
        } catch (Throwable $error) {
            self::markFailed($record['reference']);
            throw $error;
        }
    }

    /** Process the PayPal/Stripe return on the Pay Online page. */
    public static function processReturn(array $query)
    {
        if (isset($query['payment_cancelled'])) {
            return self::processCancellation($query);
        }
        $provider = isset($query['payment_return']) ? strtolower((string) $query['payment_return']) : '';
        if ($provider === '') { return array('status' => 'none'); }
        if (!in_array($provider, array('paypal', 'stripe'), true)) {
            return array('status' => 'error', 'message' => 'We could not verify the payment return.');
        }

        $reference = isset($query['payment_id']) ? (string) $query['payment_id'] : '';
        $state = isset($query['state']) ? (string) $query['state'] : '';
        $record = self::findRecord($reference);
        if (!$record || !self::validReturnState($record, $state) || $record['provider'] !== $provider) {
            return array('status' => 'error', 'payment_id' => $reference, 'message' => 'We could not verify the payment return. Please contact us before trying again.');
        }

        if ((string) $record['status'] === 'completed') { return self::publicResult($record); }
        if ((string) $record['status'] !== 'pending') {
            return array('status' => 'error', 'payment_id' => $reference, 'message' => 'The payment has not been confirmed. Your form details have been kept so you can try again.');
        }

        try {
            if ($provider === 'paypal') {
                $orderId = isset($query['token']) ? (string) $query['token'] : '';
                return self::confirmPayPalReturn($record, $state, $orderId);
            }
            $sessionId = isset($query['session_id']) ? (string) $query['session_id'] : '';
            return self::confirmStripeReturn($record, $state, $sessionId);
        } catch (Throwable $error) {
            error_log('[TPT] Payment return confirmation failed for ' . $provider . '.');
            return array('status' => 'error', 'payment_id' => $reference, 'message' => 'We could not confirm the payment yet. Your form details have been kept. Please contact us before attempting another payment.');
        }
    }

    /** Validate a signed Stripe webhook and record a successful Checkout event. */
    public static function handleStripeWebhook($payload, $signatureHeader)
    {
        if (!pieStripeConfigured() || !defined('STRIPE_WEBHOOK_SECRET') || trim((string) STRIPE_WEBHOOK_SECRET) === '') {
            return false;
        }
        if (!self::validStripeSignature((string) $payload, (string) $signatureHeader, (string) STRIPE_WEBHOOK_SECRET)) {
            return false;
        }
        $event = json_decode((string) $payload, true);
        if (!is_array($event) || empty($event['id']) || empty($event['type'])) { return false; }
        $type = (string) $event['type'];
        if (!in_array($type, array('checkout.session.completed', 'checkout.session.async_payment_succeeded'), true)) {
            return true;
        }

        $session = isset($event['data']['object']) && is_array($event['data']['object']) ? $event['data']['object'] : array();
        $reference = isset($session['metadata']['tpt_reference']) ? (string) $session['metadata']['tpt_reference'] : '';
        if ($reference === '' && isset($session['client_reference_id'])) { $reference = (string) $session['client_reference_id']; }
        $record = self::findRecord($reference);
        if (!$record || (string) $record['provider'] !== 'stripe') { return false; }
        if (!self::stripeSessionMatchesRecord($session, $record, true)) { return false; }
        if (strtolower((string) ($session['payment_status'] ?? '')) !== 'paid') { return true; }

        $transactionId = $session['payment_intent'] ?? '';
        if (is_array($transactionId)) { $transactionId = $transactionId['id'] ?? ''; }
        if (!is_string($transactionId) || $transactionId === '') { $transactionId = (string) ($session['id'] ?? ''); }
        if (!self::markCompleted($record, $transactionId)) { return false; }
        self::recordEvent($record, 'stripe', (string) $event['id'], $type, (string) $payload);
        return true;
    }

    /** Check a Stripe webhook signature with a five-minute replay window. */
    public static function validStripeSignature($payload, $header, $secret, $now = null)
    {
        $timestamp = 0;
        $signatures = array();
        foreach (explode(',', (string) $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) { continue; }
            if ($pair[0] === 't' && ctype_digit($pair[1])) { $timestamp = (int) $pair[1]; }
            if ($pair[0] === 'v1' && preg_match('/^[a-f0-9]{64}$/iD', $pair[1])) { $signatures[] = strtolower($pair[1]); }
        }
        $now = $now === null ? time() : (int) $now;
        if ($timestamp < 1 || abs($now - $timestamp) > 300 || !$signatures || $secret === '') { return false; }
        $expected = hash_hmac('sha256', $timestamp . '.' . (string) $payload, (string) $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) { return true; }
        }
        return false;
    }

    private static function createPendingRecord($provider, $name, $service, $amount)
    {
        $reference = 'TPT-' . strtoupper(bin2hex(random_bytes(8)));
        $state = bin2hex(random_bytes(32));
        $id = dbInsert(
            "INSERT INTO payment_records
                (reference, provider, name, service, amount, currency, status, state_hash, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'USD', 'pending', ?, NOW(), NOW())",
            array($reference, $provider, $name, $service, $amount, hash('sha256', $state))
        );
        if ($id < 1) { throw new RuntimeException('The payment could not be recorded. Please try again.'); }
        return array('id' => $id, 'reference' => $reference, 'state' => $state, 'provider' => $provider,
            'name' => $name, 'service' => $service, 'amount' => $amount);
    }

    private static function createPayPalOrder(array $record, array $amount)
    {
        $returnUrl = self::paymentPageUrl(array(
            'payment_return' => 'paypal', 'payment_id' => $record['reference'], 'state' => $record['state'],
        ));
        $cancelUrl = self::paymentPageUrl(array(
            'payment_cancelled' => 'paypal', 'payment_id' => $record['reference'], 'state' => $record['state'],
        ));
        $response = self::paypalApi('POST', '/v2/checkout/orders', array(
            'intent' => 'CAPTURE',
            'purchase_units' => array(array(
                'reference_id' => $record['reference'],
                'custom_id' => $record['reference'],
                'description' => mb_substr($record['service'], 0, 127),
                'amount' => array('currency_code' => 'USD', 'value' => $amount['decimal']),
            )),
            'application_context' => array(
                'brand_name' => mb_substr(getSetting('site_name', SITE_NAME), 0, 127),
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ),
        ));
        if (!self::isSuccessful($response) || empty($response['json']['id'])) {
            throw new RuntimeException('PayPal could not start checkout.');
        }
        $orderId = (string) $response['json']['id'];
        $approvalUrl = '';
        foreach (($response['json']['links'] ?? array()) as $link) {
            if (in_array((string) ($link['rel'] ?? ''), array('approve', 'payer-action'), true)) {
                $approvalUrl = (string) ($link['href'] ?? '');
                break;
            }
        }
        if (!self::isPayPalApprovalUrl($approvalUrl)) { throw new RuntimeException('PayPal returned an invalid checkout link.'); }
        if (dbExec('UPDATE payment_records SET provider_order_id = ?, updated_at = NOW() WHERE id = ? AND status = \'pending\'', array($orderId, $record['id'])) < 0) {
            throw new RuntimeException('The payment could not be recorded.');
        }
        return array('redirect_url' => $approvalUrl, 'payment_id' => $record['reference'], 'state' => $record['state']);
    }

    private static function createStripeSession(array $record, array $amount)
    {
        $successUrl = self::paymentPageUrl(array(
            'payment_return' => 'stripe', 'payment_id' => $record['reference'], 'state' => $record['state'],
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ));
        $cancelUrl = self::paymentPageUrl(array(
            'payment_cancelled' => 'stripe', 'payment_id' => $record['reference'], 'state' => $record['state'],
        ));
        $params = array(
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $record['reference'],
            'line_items' => array(array(
                'price_data' => array(
                    'currency' => 'usd',
                    'product_data' => array('name' => $record['service']),
                    'unit_amount' => $amount['cents'],
                ),
                'quantity' => 1,
            )),
            'metadata' => array('tpt_reference' => $record['reference'], 'service' => $record['service']),
            'payment_intent_data' => array('metadata' => array('tpt_reference' => $record['reference'])),
        );
        $response = self::stripeApi('POST', '/v1/checkout/sessions', $params, 'TPT-' . $record['reference']);
        if (!self::isSuccessful($response) || empty($response['json']['id']) || empty($response['json']['url'])) {
            throw new RuntimeException('Stripe could not start checkout.');
        }
        $sessionId = (string) $response['json']['id'];
        $checkoutUrl = (string) $response['json']['url'];
        if (!self::isStripeCheckoutUrl($checkoutUrl)) { throw new RuntimeException('Stripe returned an invalid checkout link.'); }
        if (dbExec('UPDATE payment_records SET provider_order_id = ?, updated_at = NOW() WHERE id = ? AND status = \'pending\'', array($sessionId, $record['id'])) < 0) {
            throw new RuntimeException('The payment could not be recorded.');
        }
        return array('redirect_url' => $checkoutUrl, 'payment_id' => $record['reference'], 'state' => $record['state']);
    }

    private static function confirmPayPalReturn(array $record, $state, $orderId)
    {
        $expectedOrderId = (string) ($record['provider_order_id'] ?? '');
        if ($orderId === '' || $expectedOrderId === '' || !hash_equals($expectedOrderId, $orderId)) {
            return array('status' => 'error', 'payment_id' => $record['reference'], 'message' => 'We could not match this PayPal return to the pending payment. Your form details have been kept.');
        }
        $response = self::paypalApi('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture');
        $confirmed = self::paypalConfirmationDetails($response['json'] ?? array(), $record);
        if (!$confirmed) {
            /* A browser refresh may repeat a return after PayPal captured the
               order. Read the order from PayPal; never trust the query string. */
            $orderResponse = self::paypalApi('GET', '/v2/checkout/orders/' . rawurlencode($orderId));
            $confirmed = self::paypalConfirmationDetails($orderResponse['json'] ?? array(), $record);
        }
        if (!$confirmed || !self::markCompleted($record, $confirmed['transaction_id'])) {
            return array('status' => 'error', 'payment_id' => $record['reference'], 'message' => 'We could not confirm the PayPal payment yet. Your form details have been kept. Please contact us before trying again.');
        }
        self::recordEvent($record, 'paypal', $confirmed['transaction_id'], 'payment.capture.completed', json_encode($response['json'] ?? array()));
        return self::publicResult(array_merge($record, array('provider_transaction_id' => $confirmed['transaction_id'], 'status' => 'completed')));
    }

    private static function paypalConfirmationDetails(array $json, array $record)
    {
        if (strtoupper((string) ($json['status'] ?? '')) !== 'COMPLETED') { return false; }
        foreach (($json['purchase_units'] ?? array()) as $unit) {
            if (!is_array($unit)) { continue; }
            $customId = (string) ($unit['custom_id'] ?? '');
            if ($customId !== '' && !hash_equals((string) $record['reference'], $customId)) { continue; }
            foreach (($unit['payments']['captures'] ?? array()) as $capture) {
                if (!is_array($capture) || strtoupper((string) ($capture['status'] ?? '')) !== 'COMPLETED') { continue; }
                $captureAmount = self::normalizeAmount($capture['amount']['value'] ?? '');
                $recordAmount = self::normalizeAmount($record['amount'] ?? '');
                if (!$captureAmount || !$recordAmount || $captureAmount['cents'] !== $recordAmount['cents']) { continue; }
                if (strtoupper((string) ($capture['amount']['currency_code'] ?? '')) !== 'USD') { continue; }
                $transactionId = (string) ($capture['id'] ?? '');
                if ($transactionId === '' || strlen($transactionId) > 128) { continue; }
                return array('transaction_id' => $transactionId);
            }
        }
        return false;
    }

    private static function confirmStripeReturn(array $record, $state, $sessionId)
    {
        $expectedSessionId = (string) ($record['provider_order_id'] ?? '');
        if ($sessionId === '' || $expectedSessionId === '' || !hash_equals($expectedSessionId, $sessionId)) {
            return array('status' => 'error', 'payment_id' => $record['reference'], 'message' => 'We could not match this Stripe return to the pending payment. Your form details have been kept.');
        }
        $response = self::stripeApi('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId));
        if (!self::isSuccessful($response) || !self::stripeSessionMatchesRecord($response['json'] ?? array(), $record, true)) {
            return array('status' => 'error', 'payment_id' => $record['reference'], 'message' => 'We could not confirm the Stripe payment yet. Your form details have been kept. Please contact us before trying again.');
        }
        $session = $response['json'];
        if (strtolower((string) ($session['payment_status'] ?? '')) !== 'paid') {
            return array('status' => 'processing', 'payment_id' => $record['reference'], 'message' => 'Stripe has not confirmed the payment yet. Your form details have been kept.');
        }
        $transactionId = $session['payment_intent'] ?? '';
        if (is_array($transactionId)) { $transactionId = $transactionId['id'] ?? ''; }
        if (!is_string($transactionId) || $transactionId === '') { $transactionId = $sessionId; }
        if (!self::markCompleted($record, $transactionId)) {
            return array('status' => 'error', 'payment_id' => $record['reference'], 'message' => 'The payment provider confirmed payment, but the site could not save the confirmation. Please contact us before paying again.');
        }
        return self::publicResult(array_merge($record, array('provider_transaction_id' => $transactionId, 'status' => 'completed')));
    }

    private static function stripeSessionMatchesRecord(array $session, array $record, $requireSessionId)
    {
        $sessionId = (string) ($session['id'] ?? '');
        $reference = (string) ($session['metadata']['tpt_reference'] ?? ($session['client_reference_id'] ?? ''));
        if ($reference === '' || !hash_equals((string) $record['reference'], $reference)) { return false; }
        if ($requireSessionId && ($sessionId === '' || !hash_equals((string) ($record['provider_order_id'] ?? ''), $sessionId))) { return false; }
        if (strtolower((string) ($session['currency'] ?? '')) !== 'usd') { return false; }
        $amount = self::normalizeAmount($record['amount'] ?? '');
        if (!$amount || !isset($session['amount_total']) || (int) $session['amount_total'] !== $amount['cents']) { return false; }
        return true;
    }

    private static function processCancellation(array $query)
    {
        $provider = strtolower((string) ($query['payment_cancelled'] ?? ''));
        $reference = isset($query['payment_id']) ? (string) $query['payment_id'] : '';
        $state = isset($query['state']) ? (string) $query['state'] : '';
        $record = self::findRecord($reference);
        if (!$record || !in_array($provider, array('paypal', 'stripe'), true)
            || (string) $record['provider'] !== $provider || !self::validReturnState($record, $state)) {
            return array('status' => 'error', 'payment_id' => $reference, 'message' => 'We could not verify the payment return. Your form details have been kept.');
        }
        if ((string) $record['status'] === 'completed') { return self::publicResult($record); }
        dbExec("UPDATE payment_records SET status = 'cancelled', updated_at = NOW() WHERE reference = ? AND provider = ? AND status = 'pending'", array($reference, $provider));
        return array('status' => 'cancelled', 'payment_id' => $reference, 'message' => 'Checkout was cancelled. Your form details have been kept if you would like to try again.');
    }

    private static function validReturnState(array $record, $state)
    {
        if (!preg_match('/^[a-f0-9]{64}$/iD', (string) $state)) { return false; }
        $stored = (string) ($record['state_hash'] ?? '');
        return $stored !== '' && hash_equals($stored, hash('sha256', (string) $state));
    }

    private static function findRecord($reference)
    {
        $reference = trim((string) $reference);
        if ($reference === '' || strlen($reference) > 64 || !DB_OK) { return null; }
        return dbOne('SELECT * FROM payment_records WHERE reference = ? LIMIT 1', array($reference));
    }

    private static function markCompleted(array $record, $transactionId)
    {
        $transactionId = trim((string) $transactionId);
        if ($transactionId === '' || strlen($transactionId) > 128) { return false; }
        $saved = dbExec(
            "UPDATE payment_records SET status = 'completed', provider_transaction_id = ?, confirmed_at = NOW(), updated_at = NOW()
             WHERE reference = ? AND provider = ? AND status <> 'completed'",
            array($transactionId, $record['reference'], $record['provider'])
        );
        if ($saved < 0) { return false; }
        $current = self::findRecord($record['reference']);
        return $current && (string) $current['status'] === 'completed';
    }

    private static function markFailed($reference)
    {
        dbExec("UPDATE payment_records SET status = 'failed', updated_at = NOW() WHERE reference = ? AND status = 'pending'", array($reference));
    }

    private static function publicResult(array $record)
    {
        $amount = self::normalizeAmount($record['amount'] ?? '');
        return array(
            'status' => 'confirmed',
            'payment_id' => (string) $record['reference'],
            'amount' => $amount ? $amount['decimal'] : (string) ($record['amount'] ?? ''),
            'service' => (string) ($record['service'] ?? ''),
            'provider' => strtolower((string) ($record['provider'] ?? '')) === 'stripe' ? 'Stripe' : 'PayPal',
            'reference' => (string) (($record['provider_transaction_id'] ?? '') ?: $record['reference']),
        );
    }

    private static function recordEvent(array $record, $provider, $eventId, $type, $payload)
    {
        $eventId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $eventId);
        if ($eventId === '' || strlen($eventId) > 128) { return; }
        dbExec(
            'INSERT IGNORE INTO payment_events (payment_id, provider, provider_event_id, event_type, payload_sha256, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            array((int) $record['id'], $provider, $eventId, mb_substr((string) $type, 0, 100), hash('sha256', (string) $payload))
        );
    }

    private static function paymentPageUrl(array $query)
    {
        return rtrim(SITE_URL, '/') . url('pay-online') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private static function isPayPalApprovalUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') { return false; }
        return in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), array('www.paypal.com', 'www.sandbox.paypal.com', 'sandbox.paypal.com'), true);
    }

    private static function isStripeCheckoutUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'checkout.stripe.com';
    }

    private static function paypalBaseUrl()
    {
        return defined('PAYPAL_ENVIRONMENT') && strtolower((string) PAYPAL_ENVIRONMENT) === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private static function paypalAccessToken()
    {
        if (self::$paypalToken !== '' && self::$paypalTokenExpiresAt > time() + 30) { return self::$paypalToken; }
        if (!piePayPalConfigured()) { throw new RuntimeException('PayPal is not configured.'); }
        $auth = base64_encode(piePayPalClientId() . ':' . (string) PAYPAL_CLIENT_SECRET);
        $response = self::httpRequest('POST', self::paypalBaseUrl() . '/v1/oauth2/token', array(
            'Authorization: Basic ' . $auth,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ), 'grant_type=client_credentials');
        if (!self::isSuccessful($response) || empty($response['json']['access_token'])) {
            throw new RuntimeException('PayPal authentication failed.');
        }
        self::$paypalToken = (string) $response['json']['access_token'];
        self::$paypalTokenExpiresAt = time() + max(60, (int) ($response['json']['expires_in'] ?? 300));
        return self::$paypalToken;
    }

    private static function paypalApi($method, $path, $payload = null)
    {
        $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES);
        return self::httpRequest($method, self::paypalBaseUrl() . $path, array(
            'Authorization: Bearer ' . self::paypalAccessToken(),
            'Content-Type: application/json',
            'Accept: application/json',
        ), $body);
    }

    private static function stripeApi($method, $path, array $params = array(), $idempotencyKey = '')
    {
        if (!pieStripeConfigured()) { throw new RuntimeException('Stripe is not configured.'); }
        $headers = array('Authorization: Bearer ' . (string) STRIPE_SECRET_KEY, 'Accept: application/json');
        $body = null;
        if (strtoupper((string) $method) === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            if ($idempotencyKey !== '') { $headers[] = 'Idempotency-Key: ' . $idempotencyKey; }
            $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        return self::httpRequest($method, 'https://api.stripe.com' . $path, $headers, $body);
    }

    /** HTTPS-only HTTP client with TLS checks and no automatic redirects. */
    private static function httpRequest($method, $url, array $headers, $body = null)
    {
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') { throw new RuntimeException('Payment provider URL rejected.'); }
        if (self::$testTransport !== null) {
            $result = call_user_func(self::$testTransport, strtoupper((string) $method), $url, $headers, $body);
            if (!is_array($result)) { return array('status' => 0, 'json' => array(), 'raw' => ''); }
            $json = $result['json'] ?? array();
            $raw = $result['raw'] ?? '';
            if (!$json && isset($result['body'])) {
                if (is_array($result['body'])) { $json = $result['body']; }
                else { $raw = (string) $result['body']; $json = json_decode($raw, true) ?: array(); }
            }
            return array('status' => (int) ($result['status'] ?? 200), 'json' => is_array($json) ? $json : array(), 'raw' => (string) $raw);
        }

        $method = strtoupper((string) $method);
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            );
            if ($body !== null) { $options[CURLOPT_POSTFIELDS] = $body; }
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) { $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS; }
            curl_setopt_array($curl, $options);
            $raw = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $failed = $raw === false;
            curl_close($curl);
            if ($failed) { return array('status' => 0, 'json' => array(), 'raw' => ''); }
        } else {
            $headerText = implode("\r\n", $headers);
            $context = stream_context_create(array(
                'http' => array(
                    'method' => $method,
                    'header' => $headerText,
                    'content' => $body === null ? '' : $body,
                    'timeout' => 25,
                    'ignore_errors' => true,
                    'follow_location' => 0,
                    'max_redirects' => 0,
                ),
                'ssl' => array('verify_peer' => true, 'verify_peer_name' => true),
            ));
            $raw = @file_get_contents($url, false, $context);
            $status = 0;
            foreach (($http_response_header ?? array()) as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) { $status = (int) $match[1]; }
            }
            if ($raw === false) { return array('status' => 0, 'json' => array(), 'raw' => ''); }
        }
        $decoded = json_decode((string) $raw, true);
        return array('status' => $status, 'json' => is_array($decoded) ? $decoded : array(), 'raw' => (string) $raw);
    }

    private static function isSuccessful(array $response)
    {
        return isset($response['status']) && (int) $response['status'] >= 200 && (int) $response['status'] < 300;
    }
}
