<?php
/**
 * Dependency-free tests for the Payments dashboard (payment_records).
 *
 *   • the table is created by the migration AND by a fresh install, with a
 *     UNIQUE (provider, provider_transaction_id) key so a gateway payment can
 *     never be counted twice
 *   • the installer knows the table (it is an application table, not a foreign
 *     one) and the dashboard is reachable from the admin navigation
 *   • filters are normalised and every value is bound — no filter text ever
 *     reaches the SQL string
 *   • the totals, the list and the CSV export all use the same filter set
 *   • delete and export are admin-only and CSRF-protected, deletes are POST-only
 *
 * Run: php tests/payment-records.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', ''); define('SITE_URL', 'https://example.test');
define('SITE_NAME', 'The Pie Technologies'); define('ADMIN_EMAIL', 'admin@example.test');
define('PRETTY_URLS', true); define('DB_OK', true); define('UPLOAD_PATH', BASE_PATH . '/uploads/');

$GLOBALS['fake_settings'] = array();
$GLOBALS['fake_tables'] = array('payment_records' => true);
$GLOBALS['fake_rows'] = array();
$GLOBALS['queries'] = array();

function dbAll($sql, $params = array())
{
    $GLOBALS['queries'][] = array($sql, $params);
    if (strpos($sql, 'FROM settings') !== false) {
        $rows = array();
        foreach ($GLOBALS['fake_settings'] as $key => $value) { $rows[] = array('setting_key' => $key, 'setting_value' => $value); }
        return $rows;
    }
    if (strpos($sql, 'FROM payment_records') !== false) { return array_values($GLOBALS['fake_rows']); }
    return array();
}
function dbOne($sql, $params = array())
{
    $GLOBALS['queries'][] = array($sql, $params);
    if (strpos($sql, 'information_schema.TABLES') !== false) {
        $table = isset($params[0]) ? (string) $params[0] : '';
        return array('c' => !empty($GLOBALS['fake_tables'][$table]) ? 1 : 0);
    }
    if (strpos($sql, 'FROM payment_records') !== false && stripos($sql, 'COUNT(*)') !== false) {
        $sum = 0.0;
        foreach ($GLOBALS['fake_rows'] as $row) { $sum += (float) $row['amount']; }
        return array('c' => count($GLOBALS['fake_rows']), 'total_usd' => $sum);
    }
    if (strpos($sql, 'FROM settings') !== false) {
        $key = $params[0] ?? '';
        return isset($GLOBALS['fake_settings'][$key]) ? array('setting_key' => $key, 'setting_value' => $GLOBALS['fake_settings'][$key]) : null;
    }
    return null;
}
function dbExec($sql, $params = array()) { $GLOBALS['queries'][] = array($sql, $params); return count($params); }
function dbInsert($sql, $params = array()) { $GLOBALS['queries'][] = array($sql, $params); return 1; }

require BASE_PATH . '/includes/functions.php';
require BASE_PATH . '/core/PaymentRecords.php';
require BASE_PATH . '/core/Installer.php';

$count = 0;
function check($condition, $message) { global $count; $count++; if (!$condition) { throw new RuntimeException('FAIL: ' . $message); } echo 'PASS: ' . $message . PHP_EOL; }
function source($relativePath) { return (string) file_get_contents(BASE_PATH . '/' . $relativePath); }

/* ------------------------------ the schema -------------------------------- */
$migration = source('database/migrations/009_stripe_server_and_payment_records.php');
foreach (array('provider', 'provider_transaction_id', 'payer_name', 'payer_email', 'service', 'amount', 'currency',
               'status', 'verification_mode', 'raw_reference', 'ip_address', 'created_at') as $column) {
    check(strpos($migration, $column) !== false, 'the migration creates the payment_records column: ' . $column);
}
check(strpos($migration, 'UNIQUE KEY') !== false && strpos($migration, 'provider, provider_transaction_id') !== false,
    'a provider payment id is UNIQUE per provider');
check(substr_count($migration, 'CREATE TABLE IF NOT EXISTS payment_records') === 1, 'the payment_records table is created once');
check(strpos($migration, "'stripe_secret_key'") !== false && strpos($migration, "'stripe_webhook_secret'") !== false,
    'the migration adds both Stripe credential settings');
foreach (array('database.sql', 'database/schema-mysql.sql', 'database-upgrade.sql') as $schemaFile) {
    $schema = source($schemaFile);
    check(strpos($schema, 'CREATE TABLE IF NOT EXISTS payment_records') !== false,
        $schemaFile . ' ships the payment_records table');
    check(strpos($schema, 'uniq_provider_transaction') !== false, $schemaFile . ' ships the duplicate-protection index');
}
check(in_array('payment_records', Installer::$appTables, true), 'the installer recognises payment_records as an application table');

/* ------------------------------ navigation -------------------------------- */
$header = source('includes/admin-header.php');
check(strpos($header, "'payment-records'") !== false && strpos($header, 'payment-records.php') !== false,
    'the admin navigation links the Payments dashboard');
$dashboard = source('admin/payment-records.php');
check(strpos($dashboard, 'requireAdmin()') !== false, 'the dashboard is admin-only');
check(strpos($dashboard, 'piePaymentRecordDelete') !== false, 'records are deleted through the record module');
check(strpos($dashboard, 'validateCSRF') !== false && strpos($dashboard, 'csrfField()') !== false, 'deletes are CSRF-protected');
check(strpos($dashboard, 'piePaymentRecordFilters') !== false && strpos($dashboard, 'piePaymentRecordTotals') !== false,
    'the dashboard lists and totals with the shared filters');
check(strpos($dashboard, 'data-confirm=') !== false, 'deleting a record asks for confirmation first');
check(strpos($dashboard, 'name="ids[]"') !== false && strpos($dashboard, 'payment-records-export.php') !== false,
    'the dashboard offers bulk selection and the CSV export');
$export = source('admin/payment-records-export.php');
check(strpos($export, 'requireAdmin()') !== false, 'the export is admin-only');
check(strpos($export, 'validateCSRF') !== false, 'the export requires a CSRF token');
check(strpos($export, 'csvDownload(') !== false, 'the export streams CSV through the shared writer');
check(strpos($export, 'piePaymentRecordAll') !== false, 'the export honours the current filters');
check(strpos($export, 'piePaymentRecordFilters($_GET)') !== false, 'the export normalises its filters through the shared helper');
check(!preg_match("/[\"']\s*\.\s*\$_GET/", $export), 'no raw request value is concatenated into a query');

/* ------------------------------ the filters ------------------------------- */
$filters = piePaymentRecordFilters(array(
    'q' => str_repeat('a', 200), 'provider' => 'PayPal', 'status' => 'SUCCEEDED',
    'from' => '2026-01-01', 'to' => '2026-12-31',
));
check(strlen($filters['q']) === 120, 'a long search term is bounded');
check($filters['provider'] === 'paypal', 'the provider filter is normalised to lower case');
check($filters['status'] === 'succeeded', 'the status filter is normalised to lower case');
check($filters['from'] === '2026-01-01' && $filters['to'] === '2026-12-31', 'valid date filters are kept');

$bad = piePaymentRecordFilters(array('provider' => 'mysql; DROP', 'status' => 'x y', 'from' => 'yesterday', 'to' => '2026-13-45'));
check($bad['provider'] === '' && $bad['status'] === '' && $bad['from'] === '' && $bad['to'] === '',
    'unknown providers, statuses and dates are dropped instead of reaching the query');

$where = piePaymentRecordWhere(piePaymentRecordFilters(array('q' => "100% _x'; DROP TABLE payment_records; --", 'provider' => 'stripe')));
check(strpos($where['sql'], 'DROP') === false && strpos($where['sql'], "100%") === false,
    'no filter text is interpolated into the WHERE clause');
check(count($where['params']) === 6, 'every filter value is bound');
check(strpos($where['sql'], 'payer_name LIKE ?') !== false && strpos($where['sql'], 'provider_transaction_id LIKE ?') !== false
    && strpos($where['sql'], 'service LIKE ?') !== false, 'the search covers name, reference and service');
check(strpos($where['params'][0], '\\%') !== false && strpos($where['params'][0], '\\_') !== false,
    'LIKE wildcards in the search term are escaped');

/* --------------------------- list, totals, export -------------------------- */
$GLOBALS['fake_rows'] = array(
    array('id' => 2, 'provider' => 'stripe', 'provider_transaction_id' => 'pi_1', 'payer_name' => 'Grace Hopper',
        'payer_email' => 'grace@example.test', 'service' => 'SEO', 'amount' => '99.50', 'currency' => 'USD',
        'status' => 'succeeded', 'verification_mode' => 'server', 'raw_reference' => 'pi_1',
        'ip_address' => '203.0.113.1', 'created_at' => '2026-02-02 10:00:00'),
    array('id' => 1, 'provider' => 'paypal', 'provider_transaction_id' => 'CAPTURE1', 'payer_name' => 'Ada Lovelace',
        'payer_email' => 'ada@example.test', 'service' => 'Web Development', 'amount' => '250.00', 'currency' => 'USD',
        'status' => 'succeeded', 'verification_mode' => 'server', 'raw_reference' => 'ORDER1',
        'ip_address' => '203.0.113.2', 'created_at' => '2026-01-01 09:00:00'),
);
$list = piePaymentRecordList(piePaymentRecordFilters(array()), 25, 0);
check(count($list) === 2, 'the dashboard lists every stored record');
$totals = piePaymentRecordTotals(piePaymentRecordFilters(array()));
check($totals['count'] === 2 && abs($totals['total_usd'] - 349.50) < 0.001, 'the totals report the count and the USD sum');
$query = $GLOBALS['queries'][count($GLOBALS['queries']) - 1];
check(strpos($query[0], 'SUM(CASE WHEN currency') !== false, 'the USD sum ignores other currencies');

$headers = piePaymentRecordCsvHeaders();
check(count($headers) === 13 && $headers[0] === 'ID' && $headers[1] === 'Provider', 'the CSV export has a header row');
$row = piePaymentRecordCsvRow($GLOBALS['fake_rows'][1]);
check(count($row) === count($headers), 'every CSV data row matches the header row');
check($row[1] === 'PayPal' && $row[5] === 'Web Development' && $row[6] === '250.00' && $row[9] === 'Server-verified',
    'CSV cells carry the provider, service, amount and verification mode');
$formula = piePaymentRecordCsvRow(array_merge($GLOBALS['fake_rows'][0], array('raw_reference' => '=HYPERLINK("http://evil")')));
check($formula[10] === '=HYPERLINK("http://evil")', 'the CSV row keeps the raw reference readable');
check(strpos(source('includes/functions.php'), '[=+@-]') !== false
    && strpos(source('includes/functions.php'), "?" . " \"'\" . \$value : \$value") !== false,
    'the CSV writer neutralises formula cells (= + - @) with a leading quote');

/* ----------------------------- deletion ----------------------------------- */
$GLOBALS['fake_rows'] = array();
check(piePaymentRecordDelete(array(0, -3, 'x')) === 0, 'no id means no delete');
check(piePaymentRecordDelete(array(4, 4, '4')) === 1, 'duplicate ids are deleted once, as integers');
$last = $GLOBALS['queries'][count($GLOBALS['queries']) - 1];
check(strpos($last[0], 'id IN (?)') !== false && $last[1] === array(4), 'delete ids are bound as integers');
$GLOBALS['fake_tables']['payment_records'] = false;
check(piePaymentRecordDelete(array(1)) === 0, 'deleting is safe while the table does not exist yet');
check(piePaymentRecordList(piePaymentRecordFilters(array()), 25, 0) === array(), 'listing is safe while the table does not exist yet');

echo "\n$count checks passed.\n";
