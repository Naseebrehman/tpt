<?php
/** Shared, server-only checkout utilities for PayPal and Stripe.
 *
 * The public payment page contains ordinary POST forms only. Each gateway
 * owns a separate PHP endpoint and credential set; this module validates the
 * form, stores pending attempts in the existing `payments` table, and records
 * a success in `payment_records` only after the provider confirms it.
 */
if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}
require_once __DIR__ . '/PaymentCredentials.php';
require_once __DIR__ . '/PaymentRecords.php';

/* ===========================================================================
   Existing Admin → Payments services list
   =========================================================================== */

/** Active service names in the order configured by Admin → Payments. */
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

/** Service rows for the existing Admin Services manager. */
function piePaymentServiceRows()
{
    return piePaymentTableExists('payment_services')
        ? dbAll('SELECT * FROM payment_services ORDER BY sort_order ASC, id ASC')
        : array();
}

/** Build safe option markup for the payment form. */
function pieServicesOptionsHtml()
{
    $html = '';
    foreach (piePaymentServices() as $service) {
        $html .= '<option value="' . esc($service) . '">' . esc($service) . "</option>\n";
    }
    return $html;
}

/* ===========================================================================
   Gateway settings (never publish credentials to the public page)
   =========================================================================== */

function pieIsPayPalEnabled()
{
    return getSetting('paypal_enabled', '0') === '1';
}

function pieIsStripeEnabled()
{
    return getSetting('stripe_enabled', '0') === '1';
}

/** PayPal Client ID: server-side OAuth credential, not used by browser SDKs. */
function piePayPalClientId()
{
    return trim((string) getSetting('paypal_client_id', ''));
}

function piePayPalClientIdConfigured()
{
    return pieIsValidPayPalClientId(piePayPalClientId());
}

function pieIsValidPayPalClientId($clientId)
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{8,255}$/D', (string) $clientId);
}

/** Accept a local absolute path or an absolute HTTP(S) Terms URL only. */
function pieIsValidTermsUrl($url)
{
    $url = trim((string) $url);
    if ($url === '') { return true; }
    if (preg_match('/[\x00-\x20\x7F]/', $url) || strpos($url, chr(92)) !== false) { return false; }
    if (substr($url, 0, 1) === '/') {
        return substr($url, 0, 2) !== '//';
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) { return false; }
    return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), array('http', 'https'), true)
        && (string) parse_url($url, PHP_URL_HOST) !== '';
}

/** Shared Terms & Conditions URL used on the payment form. */
function pieTermsUrl()
{
    $url = trim((string) getSetting('terms_url', ''));
    return $url !== '' && pieIsValidTermsUrl($url) ? $url : rtrim(SITE_URL, '/') . url('terms');
}

/* Gateway modules use the settings helpers above and decrypt secrets only in
   the PHP process. No browser-facing config object or payment-code bridge is
   provided by this module. */
require_once __DIR__ . '/PayPal.php';
require_once __DIR__ . '/Stripe.php';

/* ===========================================================================
   Server validation
   =========================================================================== */

/** Normalize a USD amount without accepting exponents, signs or >2 decimals. */
function piePaymentNormalizeAmount($value)
{
    if (!is_scalar($value)) { return ''; }
    $value = trim((string) $value);
    if ($value === '' || !preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $value)) {
        return '';
    }
    $amount = (float) $value;
    if (!is_finite($amount) || $amount < 0.01 || $amount > 1000000) {
        return '';
    }
    return number_format($amount, 2, '.', '');
}

/** Validate and normalize the fields posted by the customer. */
function piePaymentValidateSubmission(array $input)
{
    $fields = array('name', 'email', 'phone', 'service', 'amount', 'notes');
    foreach ($fields as $field) {
        if (isset($input[$field]) && !is_scalar($input[$field])) {
            return array('ok' => false, 'error' => 'One or more payment details are invalid.', 'data' => array());
        }
    }

    $name = mb_substr(sanitize((string) ($input['name'] ?? '')), 0, 150);
    $email = mb_substr(strtolower(sanitize((string) ($input['email'] ?? ''))), 0, 150);
    $phone = mb_substr(sanitize((string) ($input['phone'] ?? '')), 0, 30);
    $service = mb_substr(sanitize((string) ($input['service'] ?? '')), 0, 150);
    $amount = piePaymentNormalizeAmount($input['amount'] ?? '');
    $notes = mb_substr(sanitizeMultiline((string) ($input['notes'] ?? '')), 0, 2000);

    if ($name === '') {
        return array('ok' => false, 'error' => 'Enter your name or business name.', 'data' => array());
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return array('ok' => false, 'error' => 'Enter a valid email address.', 'data' => array());
    }
    if ($service === '' || !in_array($service, piePaymentServices(), true)) {
        return array('ok' => false, 'error' => 'Choose a service from the list.', 'data' => array());
    }
    if ($amount === '') {
        return array('ok' => false, 'error' => 'Enter a valid amount from $0.01 to $1,000,000.00 USD (up to two decimal places).', 'data' => array());
    }

    return array('ok' => true, 'error' => '', 'data' => array(
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'service' => $service,
        'amount' => $amount,
        'notes' => $notes,
    ));
}

/* ===========================================================================
   Existing Payments table: pending attempt → server-confirmed success
   =========================================================================== */

/** True only when a named app table exists in the connected database. */
function piePaymentTableExists($tableName)
{
    if (!DB_OK || !preg_match('/^[a-z_]+$/D', (string) $tableName)) {
        return false;
    }
    $row = dbOne(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        array((string) $tableName)
    );
    return $row && (int) $row['c'] > 0;
}

/** New checkout is unavailable unless both existing payment tables are ready. */
function piePaymentStorageReady()
{
    return DB_OK && piePaymentTableExists('payments') && piePaymentRecordsReady();
}

/** Create a pending row in the existing Payments table before contacting a gateway. */
function piePaymentCreateAttempt($provider, array $customer)
{
    $provider = strtolower(trim((string) $provider));
    if (!in_array($provider, array('paypal', 'stripe'), true) || !piePaymentStorageReady()) {
        return array('ok' => false, 'error' => 'Payment storage is not available.', 'attempt' => array());
    }
    $amount = piePaymentNormalizeAmount($customer['amount'] ?? '');
    if ($amount === '') {
        return array('ok' => false, 'error' => 'The payment amount is invalid.', 'attempt' => array());
    }

    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable $error) {
        return array('ok' => false, 'error' => 'A secure payment reference could not be created.', 'attempt' => array());
    }
    $rowId = dbInsert(
        'INSERT INTO payments
            (token, name, email, phone, service, reference, amount_usd, notes, method, status, provider_ref, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        array(
            $token,
            mb_substr((string) ($customer['name'] ?? ''), 0, 150),
            mb_substr((string) ($customer['email'] ?? ''), 0, 150),
            mb_substr((string) ($customer['phone'] ?? ''), 0, 30),
            mb_substr((string) ($customer['service'] ?? ''), 0, 191),
            '',
            $amount,
            mb_substr((string) ($customer['notes'] ?? ''), 0, 2000),
            $provider,
            'pending',
            '',
            pieClientIp(),
        )
    );
    if ((int) $rowId <= 0) {
        return array('ok' => false, 'error' => 'The payment could not be started. Please try again.', 'attempt' => array());
    }

    return array('ok' => true, 'error' => '', 'attempt' => array_merge($customer, array(
        'id' => (int) $rowId,
        'token' => $token,
        'method' => $provider,
        'status' => 'pending',
        'reference' => '',
        'provider_ref' => '',
        'amount_usd' => $amount,
        'ip_address' => pieClientIp(),
    )));
}

/** Save the gateway's server-created Order / Checkout Session id on its pending row. */
function piePaymentSetAttemptReference(array $attempt, $gatewayReference)
{
    $reference = trim((string) $gatewayReference);
    if ($reference === '' || strlen($reference) > 150) { return false; }
    $result = dbExec(
        "UPDATE payments SET reference = ?, provider_ref = ?
         WHERE token = ? AND method = ? AND status = 'pending'",
        array($reference, $reference, (string) ($attempt['token'] ?? ''), (string) ($attempt['method'] ?? ''))
    );
    return $result >= 0;
}

/** Find only a provider's own pending/confirmed attempt by its server reference. */
function piePaymentFindAttemptByReference($provider, $reference)
{
    $provider = strtolower(trim((string) $provider));
    $reference = trim((string) $reference);
    if (!in_array($provider, array('paypal', 'stripe'), true) || $reference === '' || strlen($reference) > 150) {
        return null;
    }
    return dbOne(
        'SELECT * FROM payments WHERE method = ? AND (reference = ? OR provider_ref = ?)
         ORDER BY id DESC LIMIT 1',
        array($provider, $reference, $reference)
    );
}

/** Find a Stripe attempt by the random server-generated token stored in metadata. */
function piePaymentFindAttemptByToken($token)
{
    $token = strtolower(trim((string) $token));
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) { return null; }
    return dbOne('SELECT * FROM payments WHERE token = ? LIMIT 1', array($token));
}

/** Update a pending attempt to a terminal failure/cancellation (never success). */
function piePaymentMarkAttempt($attempt, $status)
{
    $status = strtolower(trim((string) $status));
    if (!in_array($status, array('failed', 'cancelled'), true) || empty($attempt['id'])) { return false; }
    return dbExec(
        "UPDATE payments SET status = ? WHERE id = ? AND method = ? AND status = 'pending'",
        array($status, (int) $attempt['id'], (string) ($attempt['method'] ?? ''))
    ) >= 0;
}

/**
 * Record a gateway-confirmed payment exactly once, then update the existing
 * Payments row. The amount/customer/service/notes all come from the server's
 * pending row, not from a callback or browser-supplied values.
 */
function piePaymentCompleteAttempt(array $attempt, $provider, array $confirmation, $rawReference)
{
    $provider = strtolower(trim((string) $provider));
    if (!in_array($provider, array('paypal', 'stripe'), true)
        || strtolower((string) ($attempt['method'] ?? '')) !== $provider
        || empty($confirmation['confirmed'])) {
        return array('ok' => false, 'error' => 'The gateway has not confirmed this payment.', 'record_id' => 0);
    }

    $expectedAmount = piePaymentNormalizeAmount($attempt['amount_usd'] ?? ($attempt['amount'] ?? ''));
    $confirmedAmount = piePaymentNormalizeAmount($confirmation['amount'] ?? '');
    $currency = strtoupper(trim((string) ($confirmation['currency'] ?? '')));
    $transactionId = trim((string) ($confirmation['reference'] ?? ($confirmation['provider_transaction_id'] ?? '')));
    $validTransactionId = $provider === 'paypal'
        ? (bool) preg_match('/^[A-Za-z0-9_-]{6,150}$/D', $transactionId)
        : (bool) preg_match('/^pi_[A-Za-z0-9_]{6,160}$/D', $transactionId);

    if ($expectedAmount === '' || $confirmedAmount === '' || $expectedAmount !== $confirmedAmount) {
        error_log('[TPT] Refused a ' . $provider . ' payment record because the confirmed amount did not match its pending payment.');
        return array('ok' => false, 'error' => 'The confirmed amount does not match the payment request.', 'record_id' => 0);
    }
    if ($currency !== 'USD' || !$validTransactionId) {
        error_log('[TPT] Refused a ' . $provider . ' payment record because its currency or transaction reference was invalid.');
        return array('ok' => false, 'error' => 'The gateway returned an invalid payment reference or currency.', 'record_id' => 0);
    }

    $rawReference = trim((string) $rawReference);
    $recordId = piePaymentRecord(array(
        'provider' => $provider,
        'provider_transaction_id' => $transactionId,
        'payer_name' => (string) ($attempt['name'] ?? ''),
        'payer_email' => (string) ($attempt['email'] ?? ''),
        'payer_phone' => (string) ($attempt['phone'] ?? ''),
        'service' => (string) ($attempt['service'] ?? ''),
        'notes' => (string) ($attempt['notes'] ?? ''),
        'amount' => $confirmedAmount,
        'currency' => $currency,
        'status' => 'succeeded',
        'verification_mode' => 'server',
        'raw_reference' => $rawReference,
        'ip_address' => (string) ($attempt['ip_address'] ?? pieClientIp()),
    ));
    if ($recordId <= 0) {
        return array('ok' => false, 'error' => 'The payment was confirmed but its record could not be saved yet. Please contact support with the gateway reference; do not pay again.', 'record_id' => 0);
    }

    $updated = dbExec(
        'UPDATE payments SET status = ?, provider_ref = ?, amount_usd = ?
         WHERE id = ? AND method = ?',
        array('paid', $transactionId, $confirmedAmount, (int) ($attempt['id'] ?? 0), $provider)
    );
    if ($updated < 0) {
        /* The canonical confirmed record is already durable and visible in
           Admin → Payments; leave a server log to reconcile the legacy row. */
        error_log('[TPT] Payment record #' . (int) $recordId . ' was saved, but its existing payments row could not be marked paid.');
    }

    return array('ok' => true, 'error' => '', 'record_id' => (int) $recordId, 'transaction_id' => $transactionId);
}

/* ===========================================================================
   Post/Redirect/Get notices for the public payment page
   =========================================================================== */

function piePaymentSetNotice($type, $message = '', array $details = array())
{
    if (session_status() !== PHP_SESSION_ACTIVE) { return; }
    $type = in_array($type, array('success', 'error', 'cancelled'), true) ? $type : 'error';
    $_SESSION['tpt_payment_notice'] = array(
        'type' => $type,
        'message' => mb_substr(sanitize((string) $message), 0, 500),
        'name' => mb_substr(sanitize((string) ($details['name'] ?? '')), 0, 150),
        'amount' => piePaymentNormalizeAmount($details['amount'] ?? ''),
        'reference' => mb_substr(sanitize((string) ($details['reference'] ?? '')), 0, 150),
    );
}

function piePaymentConsumeNotice()
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['tpt_payment_notice']) || !is_array($_SESSION['tpt_payment_notice'])) {
        return null;
    }
    $notice = $_SESSION['tpt_payment_notice'];
    unset($_SESSION['tpt_payment_notice']);
    $type = in_array($notice['type'] ?? '', array('success', 'error', 'cancelled'), true) ? $notice['type'] : 'error';
    return array(
        'type' => $type,
        'message' => mb_substr(sanitize((string) ($notice['message'] ?? '')), 0, 500),
        'name' => mb_substr(sanitize((string) ($notice['name'] ?? '')), 0, 150),
        'amount' => piePaymentNormalizeAmount($notice['amount'] ?? ''),
        'reference' => mb_substr(sanitize((string) ($notice['reference'] ?? '')), 0, 150),
    );
}

function piePaymentRedirectToForm()
{
    header('Location: ' . url('pay-online'), true, 303);
    exit;
}
