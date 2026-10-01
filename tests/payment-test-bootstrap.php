<?php
/** Shared no-network/no-database fixtures for payment unit tests. */
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '');
define('SITE_URL', 'https://example.test');
define('SITE_NAME', 'The Pie Technologies');
define('ADMIN_EMAIL', 'admin@example.test');
define('PRETTY_URLS', true);
define('DB_OK', true);
define('DB_HOST', 'db.example.test');
define('DB_NAME', 'tpt_test');
define('DB_USER', 'tpt_user');
define('DB_PASS', 'test-database-password');
define('PAYMENT_ENCRYPTION_KEY', 'unit-test-encryption-key-not-for-production');
define('UPLOAD_PATH', BASE_PATH . '/uploads/');

$GLOBALS['fake_settings'] = array();
$GLOBALS['fake_services'] = array();
$GLOBALS['fake_tables'] = array('payment_services' => true, 'payments' => true, 'payment_records' => true);
$GLOBALS['fake_payment_rows'] = array();
$GLOBALS['fake_record_rows'] = array();
$GLOBALS['fake_queries'] = array();
$GLOBALS['fake_next_payment_id'] = 1;
$GLOBALS['fake_next_record_id'] = 1;

function paymentTestRecordKey($provider, $reference)
{
    return strtolower((string) $provider) . '|' . (string) $reference;
}

function dbAll($sql, $params = array())
{
    $GLOBALS['fake_queries'][] = array($sql, $params);
    if (stripos($sql, 'FROM settings') !== false) {
        $rows = array();
        foreach ($GLOBALS['fake_settings'] as $key => $value) {
            $rows[] = array('setting_key' => $key, 'setting_value' => $value);
        }
        return $rows;
    }
    if (stripos($sql, 'FROM payment_services') !== false) {
        $rows = array_values($GLOBALS['fake_services']);
        if (stripos($sql, 'is_active = 1') !== false) {
            $rows = array_values(array_filter($rows, function ($row) { return (int) ($row['is_active'] ?? 0) === 1; }));
        }
        usort($rows, function ($a, $b) {
            $order = (int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0);
            return $order !== 0 ? $order : ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
        });
        return $rows;
    }
    if (stripos($sql, 'FROM payment_records') !== false) {
        return array_values($GLOBALS['fake_record_rows']);
    }
    if (stripos($sql, 'FROM payments') !== false) {
        return array_values($GLOBALS['fake_payment_rows']);
    }
    return array();
}

function dbOne($sql, $params = array())
{
    $GLOBALS['fake_queries'][] = array($sql, $params);
    if (stripos($sql, 'information_schema.TABLES') !== false) {
        $table = (string) ($params[0] ?? '');
        if ($table === '' && preg_match("/TABLE_NAME\s*=\s*'([a-z_]+)'/i", $sql, $match)) { $table = $match[1]; }
        return array('c' => !empty($GLOBALS['fake_tables'][$table]) ? 1 : 0);
    }
    if (stripos($sql, 'FROM settings') !== false) {
        $key = (string) ($params[0] ?? '');
        return array_key_exists($key, $GLOBALS['fake_settings'])
            ? array('setting_key' => $key, 'setting_value' => $GLOBALS['fake_settings'][$key]) : null;
    }
    if (stripos($sql, 'FROM payment_records') !== false && stripos($sql, 'COUNT(*)') !== false) {
        $total = 0.0;
        foreach ($GLOBALS['fake_record_rows'] as $row) {
            if (strtoupper((string) ($row['currency'] ?? '')) === 'USD') { $total += (float) ($row['amount'] ?? 0); }
        }
        return array('c' => count($GLOBALS['fake_record_rows']), 'total_usd' => $total);
    }
    if (stripos($sql, 'FROM payment_records') !== false && stripos($sql, 'provider_transaction_id') !== false) {
        $row = $GLOBALS['fake_record_rows'][paymentTestRecordKey($params[0] ?? '', $params[1] ?? '')] ?? null;
        return $row ? array('id' => (int) $row['id']) : null;
    }
    if (stripos($sql, 'FROM payments') !== false) {
        foreach ($GLOBALS['fake_payment_rows'] as $row) {
            if (isset($params[0], $params[1]) && strtolower((string) ($row['method'] ?? '')) === strtolower((string) $params[0])
                && ((string) ($row['reference'] ?? '') === (string) $params[1] || (string) ($row['provider_ref'] ?? '') === (string) $params[2])) {
                return $row;
            }
            if (isset($params[0]) && (string) ($row['token'] ?? '') === (string) $params[0]) { return $row; }
        }
    }
    return null;
}

function dbExec($sql, $params = array())
{
    $GLOBALS['fake_queries'][] = array($sql, $params);
    if (stripos($sql, 'INSERT INTO settings') === 0) {
        $GLOBALS['fake_settings'][(string) ($params[0] ?? '')] = (string) ($params[1] ?? '');
        settingsCache(true);
        return 1;
    }
    if (stripos($sql, 'DELETE FROM settings') === 0) {
        unset($GLOBALS['fake_settings'][(string) ($params[0] ?? '')]);
        settingsCache(true);
        return 1;
    }
    if (stripos($sql, 'UPDATE payments SET') === 0) {
        foreach ($GLOBALS['fake_payment_rows'] as &$row) {
            if (stripos($sql, 'WHERE token =') !== false && (string) ($row['token'] ?? '') === (string) ($params[2] ?? '')) {
                if (stripos($sql, 'reference =') !== false) { $row['reference'] = $params[0]; $row['provider_ref'] = $params[1]; }
                if (stripos($sql, 'status =') !== false && isset($params[0])) { $row['status'] = (string) $params[0]; }
            } elseif (stripos($sql, 'WHERE id =') !== false) {
                $idIndex = count($params) >= 4 ? 3 : 1;
                if ((int) ($row['id'] ?? 0) === (int) ($params[$idIndex] ?? 0)) {
                    if (stripos($sql, 'status =') !== false) { $row['status'] = (string) ($params[0] ?? $row['status']); }
                    if (stripos($sql, 'provider_ref =') !== false) { $row['provider_ref'] = (string) ($params[1] ?? ''); }
                    if (stripos($sql, 'amount_usd =') !== false && count($params) >= 4) { $row['amount_usd'] = (string) ($params[2] ?? ''); }
                }
            }
        }
        unset($row);
        return 1;
    }
    if (stripos($sql, 'DELETE FROM payment_records') === 0) {
        $count = 0;
        foreach ($params as $id) {
            foreach ($GLOBALS['fake_record_rows'] as $key => $row) {
                if ((int) ($row['id'] ?? 0) === (int) $id) { unset($GLOBALS['fake_record_rows'][$key]); $count++; }
            }
        }
        return $count;
    }
    return 1;
}

function dbInsert($sql, $params = array())
{
    $GLOBALS['fake_queries'][] = array($sql, $params);
    if (stripos($sql, 'INSERT INTO payments') === 0) {
        $id = $GLOBALS['fake_next_payment_id']++;
        $row = array(
            'id' => $id, 'token' => $params[0], 'name' => $params[1], 'email' => $params[2],
            'phone' => $params[3], 'service' => $params[4], 'reference' => $params[5],
            'amount_usd' => $params[6], 'notes' => $params[7], 'method' => $params[8],
            'status' => $params[9], 'provider_ref' => $params[10], 'ip_address' => $params[11],
        );
        $GLOBALS['fake_payment_rows'][$id] = $row;
        return $id;
    }
    if (stripos($sql, 'INSERT INTO payment_records') === 0) {
        $key = paymentTestRecordKey($params[0] ?? '', $params[1] ?? '');
        if (isset($GLOBALS['fake_record_rows'][$key])) { return -1; }
        $id = $GLOBALS['fake_next_record_id']++;
        $row = array(
            'id' => $id, 'provider' => $params[0], 'provider_transaction_id' => $params[1],
            'payer_name' => $params[2], 'payer_email' => $params[3], 'payer_phone' => $params[4],
            'service' => $params[5], 'notes' => $params[6], 'amount' => $params[7],
            'currency' => $params[8], 'status' => $params[9], 'verification_mode' => $params[10],
            'raw_reference' => $params[11], 'ip_address' => $params[12], 'created_at' => '2026-10-01 12:00:00',
        );
        $GLOBALS['fake_record_rows'][$key] = $row;
        return $id;
    }
    return 1;
}

require BASE_PATH . '/includes/functions.php';
require BASE_PATH . '/core/Payments.php';

$GLOBALS['paymentTestCount'] = 0;
function paymentCheck($condition, $message)
{
    $GLOBALS['paymentTestCount']++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
function paymentSetSetting($key, $value)
{
    $GLOBALS['fake_settings'][(string) $key] = (string) $value;
    settingsCache(true);
}
function paymentSetServices(array $rows)
{
    $GLOBALS['fake_services'] = array_values($rows);
}
function paymentSource($relativePath)
{
    return (string) file_get_contents(BASE_PATH . '/' . ltrim((string) $relativePath, '/'));
}
function paymentTestSummary()
{
    echo "\n" . (int) $GLOBALS['paymentTestCount'] . " payment checks passed.\n";
}
