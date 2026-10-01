<?php
/** Payment records — the dashboard's audit trail of confirmed payments.
 *
 * Every payment that a GATEWAY has confirmed (PayPal capture verified on the
 * server, Stripe PaymentIntent/Checkout Session confirmed on the server, or a
 * signature-verified Stripe webhook) is written here exactly once. The table
 * is deliberately provider-neutral:
 *
 *   provider                 'paypal' | 'stripe' (lower-case)
 *   provider_transaction_id  the gateway's own payment id (capture id / pi_…)
 *                            UNIQUE together with provider → duplicate
 *                            deliveries of the same payment are ignored
 *   payer_name / payer_email  buyer details when the gateway reports them
 *   service                  what the payment was for (Admin Services list)
 *   amount / currency        always as the gateway reported them (USD)
 *   status                   the gateway's status, lower-case ('succeeded')
 *   verification_mode        'server' | 'browser'
 *   raw_reference            the gateway's raw reference / order id
 *   ip_address / created_at  who paid, and when we recorded it
 *
 * Nothing here trusts the browser: callers only reach this module after a
 * server-side confirmation (or a signature-verified webhook).
 */
if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

/** The canonical table name for recorded payments. */
function piePaymentRecordsTable()
{
    return 'payment_records';
}

/** True when the payment_records table exists in the current database. */
function piePaymentRecordsReady()
{
    if (!DB_OK) {
        return false;
    }
    $row = dbOne(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        array(piePaymentRecordsTable())
    );
    return $row && (int) $row['c'] > 0;
}

/** Providers a record can belong to. */
function piePaymentRecordProviders()
{
    return array('paypal', 'stripe');
}

/** Every status a record may carry, for the dashboard filter. */
function piePaymentRecordStatuses()
{
    return array('succeeded', 'completed', 'paid', 'pending', 'failed', 'cancelled', 'refunded');
}

/** Human label for one provider. */
function piePaymentProviderLabel($provider)
{
    $provider = strtolower(trim((string) $provider));
    if ($provider === 'paypal') { return 'PayPal'; }
    if ($provider === 'stripe') { return 'Stripe'; }
    return $provider === '' ? '—' : ucfirst($provider);
}

/** Human label for one verification mode. */
function piePaymentVerificationLabel($mode)
{
    return strtolower(trim((string) $mode)) === 'browser' ? 'Browser (SDK)' : 'Server-verified';
}

/**
 * Clean one incoming record. Amounts are normalised to a 2-decimal string and
 * every value is bounded, so no gateway response can bloat a column.
 */
function piePaymentRecordNormalize(array $record)
{
    $provider = strtolower(trim((string) ($record['provider'] ?? '')));
    if (!in_array($provider, piePaymentRecordProviders(), true)) {
        $provider = $provider === '' ? '' : mb_substr($provider, 0, 20);
    }
    $amount = round((float) ($record['amount'] ?? 0), 2);
    if (!is_finite($amount) || $amount < 0 || $amount > 1000000) {
        $amount = 0.0;
    }
    $status = strtolower(trim((string) ($record['status'] ?? '')));
    $mode   = strtolower(trim((string) ($record['verification_mode'] ?? 'server'))) === 'browser' ? 'browser' : 'server';

    return array(
        'provider'                => mb_substr($provider, 0, 20),
        'provider_transaction_id' => mb_substr(trim((string) ($record['provider_transaction_id'] ?? '')), 0, 150),
        'payer_name'              => mb_substr(trim((string) ($record['payer_name'] ?? '')), 0, 191),
        'payer_email'             => mb_substr(trim((string) ($record['payer_email'] ?? '')), 0, 191),
        'service'                 => mb_substr(trim((string) ($record['service'] ?? '')), 0, 191),
        'amount'                  => number_format($amount, 2, '.', ''),
        'currency'                => strtoupper(mb_substr(trim((string) ($record['currency'] ?? 'USD')), 0, 10)) ?: 'USD',
        'status'                  => mb_substr($status !== '' ? $status : 'succeeded', 0, 30),
        'verification_mode'       => $mode,
        'raw_reference'           => mb_substr(trim((string) ($record['raw_reference'] ?? '')), 0, 255),
        'ip_address'              => mb_substr(trim((string) ($record['ip_address'] ?? pieClientIp())), 0, 45),
    );
}

/** Existing record id for one gateway transaction (0 when it is new). */
function piePaymentRecordFind($provider, $transactionId)
{
    $transactionId = trim((string) $transactionId);
    if ($transactionId === '' || !piePaymentRecordsReady()) {
        return 0;
    }
    $row = dbOne(
        'SELECT id FROM payment_records WHERE provider = ? AND provider_transaction_id = ? LIMIT 1',
        array(strtolower(trim((string) $provider)), $transactionId)
    );
    return $row ? (int) $row['id'] : 0;
}

/**
 * Record one confirmed payment. Idempotent: the same gateway transaction is
 * never stored twice, and the id of the first record is returned instead.
 *
 * @return int payment_records row id (0 when it could not be stored)
 */
function piePaymentRecord(array $record)
{
    try {
        if (!piePaymentRecordsReady()) {
            return 0;
        }
        $clean = piePaymentRecordNormalize($record);
        if ($clean['provider'] === '' || $clean['provider_transaction_id'] === '') {
            return 0;
        }

        $existing = piePaymentRecordFind($clean['provider'], $clean['provider_transaction_id']);
        if ($existing > 0) {
            return $existing;
        }

        $id = dbInsert(
            'INSERT INTO payment_records
                (provider, provider_transaction_id, payer_name, payer_email, service, amount, currency, status, verification_mode, raw_reference, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                $clean['provider'],
                $clean['provider_transaction_id'],
                $clean['payer_name'],
                $clean['payer_email'],
                $clean['service'],
                $clean['amount'],
                $clean['currency'],
                $clean['status'],
                $clean['verification_mode'],
                $clean['raw_reference'],
                $clean['ip_address'],
            )
        );
        if ($id > 0) {
            return (int) $id;
        }
        /* Two confirmations racing: the UNIQUE key kept one row — reuse it. */
        $existing = piePaymentRecordFind($clean['provider'], $clean['provider_transaction_id']);
        return $existing > 0 ? $existing : 0;
    } catch (Throwable $error) {
        error_log('[TPT] Could not record a payment: ' . $error->getMessage());
        return 0;
    }
}

/* ===========================================================================
   Dashboard queries (list, totals, delete, export)
   =========================================================================== */

/**
 * A dashboard date filter: 'YYYY-MM-DD' and a real calendar date, or ''.
 * Anything else (an impossible date, a time, SQL) is dropped before it can
 * reach a query.
 */
function piePaymentRecordDate($value)
{
    $value = trim((string) $value);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match)) {
        return '';
    }
    return checkdate((int) $match[2], (int) $match[3], (int) $match[1]) ? $value : '';
}

/**
 * Normalise the dashboard filters from a request array.
 *
 * @return array{q:string,provider:string,status:string,from:string,to:string}
 */
function piePaymentRecordFilters($input)
{
    $provider = strtolower(trim((string) ($input['provider'] ?? '')));
    if (!in_array($provider, piePaymentRecordProviders(), true)) {
        $provider = '';
    }
    $status = strtolower(trim((string) ($input['status'] ?? '')));
    if ($status !== '' && !preg_match('/^[a-z0-9_]{1,30}$/', $status)) {
        $status = '';
    }
    $from = piePaymentRecordDate($input['from'] ?? '');
    $to   = piePaymentRecordDate($input['to'] ?? '');

    return array(
        'q'        => mb_substr(trim((string) ($input['q'] ?? '')), 0, 120),
        'provider' => $provider,
        'status'   => $status,
        'from'     => $from,
        'to'       => $to,
    );
}

/**
 * Build the WHERE clause and its bound parameters for a filter set.
 * Every value is bound — no filter text ever reaches the SQL string.
 *
 * @return array{sql:string,params:array}
 */
function piePaymentRecordWhere(array $filters)
{
    $where  = array('1=1');
    $params = array();

    if (($filters['q'] ?? '') !== '') {
        $where[] = '(payer_name LIKE ? OR provider_transaction_id LIKE ? OR raw_reference LIKE ? OR service LIKE ? OR payer_email LIKE ?)';
        $like    = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), (string) $filters['q']) . '%';
        for ($i = 0; $i < 5; $i++) { $params[] = $like; }
    }
    if (($filters['provider'] ?? '') !== '') {
        $where[]  = 'provider = ?';
        $params[] = $filters['provider'];
    }
    if (($filters['status'] ?? '') !== '') {
        $where[]  = 'status = ?';
        $params[] = $filters['status'];
    }
    if (($filters['from'] ?? '') !== '') {
        $where[]  = 'created_at >= ?';
        $params[] = $filters['from'] . ' 00:00:00';
    }
    if (($filters['to'] ?? '') !== '') {
        $where[]  = 'created_at <= ?';
        $params[] = $filters['to'] . ' 23:59:59';
    }

    return array('sql' => implode(' AND ', $where), 'params' => $params);
}

/** Total records and the USD sum for the current filters. */
function piePaymentRecordTotals(array $filters)
{
    if (!piePaymentRecordsReady()) {
        return array('count' => 0, 'total_usd' => 0.0);
    }
    $where = piePaymentRecordWhere($filters);
    $row   = dbOne(
        "SELECT COUNT(*) AS c, COALESCE(SUM(CASE WHEN currency = 'USD' THEN amount ELSE 0 END), 0) AS total_usd
         FROM payment_records WHERE " . $where['sql'],
        $where['params']
    );
    return array(
        'count'     => $row ? (int) $row['c'] : 0,
        'total_usd' => $row ? (float) $row['total_usd'] : 0.0,
    );
}

/**
 * One page of records, newest first.
 *
 * @return array
 */
function piePaymentRecordList(array $filters, $limit = 25, $offset = 0)
{
    if (!piePaymentRecordsReady()) {
        return array();
    }
    $limit  = max(1, min(200, (int) $limit));
    $offset = max(0, (int) $offset);
    $where  = piePaymentRecordWhere($filters);
    return dbAll(
        'SELECT * FROM payment_records WHERE ' . $where['sql'] . '
         ORDER BY created_at DESC, id DESC
         LIMIT ' . $limit . ' OFFSET ' . $offset,
        $where['params']
    );
}

/** Every matching record (used by the CSV export — no pagination). */
function piePaymentRecordAll(array $filters, $max = 20000)
{
    if (!piePaymentRecordsReady()) {
        return array();
    }
    $where = piePaymentRecordWhere($filters);
    return dbAll(
        'SELECT * FROM payment_records WHERE ' . $where['sql'] . '
         ORDER BY created_at DESC, id DESC
         LIMIT ' . max(1, (int) $max),
        $where['params']
    );
}

/** Distinct statuses currently stored (so the filter only offers real ones). */
function piePaymentRecordStatusOptions()
{
    $statuses = array();
    if (piePaymentRecordsReady()) {
        foreach (dbAll('SELECT DISTINCT status FROM payment_records ORDER BY status ASC') as $row) {
            $status = strtolower(trim((string) $row['status']));
            if ($status !== '') { $statuses[] = $status; }
        }
    }
    return array_values(array_unique(array_merge($statuses, array('succeeded'))));
}

/**
 * Delete records by id. Every id is bound, so nothing user-supplied reaches
 * the SQL string. Returns the number of deleted rows.
 */
function piePaymentRecordDelete(array $ids)
{
    if (!piePaymentRecordsReady()) {
        return 0;
    }
    $clean = array();
    foreach ($ids as $id) {
        $value = (int) $id;
        if ($value > 0) { $clean[] = $value; }
    }
    $clean = array_values(array_unique($clean));
    if (!$clean) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($clean), '?'));
    return (int) dbExec('DELETE FROM payment_records WHERE id IN (' . $placeholders . ')', $clean);
}

/** One record as a CSV row (headers match payment-records-export.php). */
function piePaymentRecordCsvRow(array $row)
{
    return array(
        (int) $row['id'],
        piePaymentProviderLabel($row['provider'] ?? ''),
        (string) ($row['provider_transaction_id'] ?? ''),
        (string) ($row['payer_name'] ?? ''),
        (string) ($row['payer_email'] ?? ''),
        (string) ($row['service'] ?? ''),
        number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
        strtoupper((string) ($row['currency'] ?? 'USD')),
        (string) ($row['status'] ?? ''),
        piePaymentVerificationLabel($row['verification_mode'] ?? 'server'),
        (string) ($row['raw_reference'] ?? ''),
        (string) ($row['ip_address'] ?? ''),
        (string) ($row['created_at'] ?? ''),
    );
}

/** CSV header row for the export. */
function piePaymentRecordCsvHeaders()
{
    return array('ID', 'Provider', 'Transaction ID', 'Payer name', 'Payer email', 'Service',
        'Amount', 'Currency', 'Status', 'Verification', 'Reference', 'IP address', 'Recorded at');
}
