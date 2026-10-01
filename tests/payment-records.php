<?php
/** Dependency-free tests for the existing Admin payment-record ledger.
 * Run: php tests/payment-records.php
 */
require __DIR__ . '/payment-test-bootstrap.php';

$migration = paymentSource('database/migrations/010_server_side_payment_checkout.php');
foreach (array('payments', 'phone', 'service', 'payment_records', 'payer_phone', 'notes', 'provider_transaction_id', 'created_at', 'piePaymentCredentialEncrypt', 'paypal_sdk_code', 'stripe_sdk_code') as $columnOrBehavior) {
    paymentCheck(strpos($migration, $columnOrBehavior) !== false, 'upgrade migration covers ' . $columnOrBehavior);
}
paymentCheck(strpos($migration, "'stripe_publishable_key'") !== false, 'upgrade removes the unused Stripe publishable-key setting');
foreach (array('database.sql', 'database/schema-mysql.sql', 'database-upgrade.sql') as $schemaFile) {
    $schema = paymentSource($schemaFile);
    paymentCheck(strpos($schema, 'CREATE TABLE IF NOT EXISTS payment_records') !== false, $schemaFile . ' creates the existing payment-records table');
    foreach (array('payer_name', 'payer_email', 'payer_phone', 'service', 'notes', 'amount', 'currency', 'status', 'provider_transaction_id', 'created_at') as $field) {
        paymentCheck(strpos($schema, $field) !== false, $schemaFile . ' includes record field ' . $field);
    }
    paymentCheck(strpos($schema, 'uniq_provider_transaction') !== false, $schemaFile . ' protects against duplicate provider transactions');
}
$seed = paymentSource('database/seed.php');
paymentCheck(strpos($seed, "('paypal_enabled', '0')") !== false && strpos($seed, "('stripe_enabled', '0')") !== false, 'fresh installs default gateways to disabled');

$normalized = piePaymentRecordNormalize(array(
    'provider' => 'Stripe', 'provider_transaction_id' => 'pi_1234567890',
    'payer_name' => ' Ada Lovelace ', 'payer_email' => 'ADA@example.test', 'payer_phone' => '+1 555 123 4567',
    'service' => 'Web Development', 'notes' => "First line\nSecond line", 'amount' => '250.25',
    'currency' => 'usd', 'status' => 'SUCCEEDED', 'raw_reference' => 'cs_test_abcdef',
    'verification_mode' => 'server', 'ip_address' => '203.0.113.20',
));
paymentCheck($normalized['provider'] === 'stripe' && $normalized['provider_transaction_id'] === 'pi_1234567890', 'record provider/reference normalize to safe values');
paymentCheck($normalized['payer_name'] === 'Ada Lovelace' && $normalized['payer_email'] === 'ADA@example.test' && $normalized['payer_phone'] === '+1 555 123 4567', 'record normalization preserves customer name, email and phone');
paymentCheck($normalized['service'] === 'Web Development' && $normalized['notes'] === "First line\nSecond line", 'record normalization preserves service and notes safely');
paymentCheck($normalized['amount'] === '250.25' && $normalized['currency'] === 'USD' && $normalized['status'] === 'succeeded', 'record amount, currency and status normalize');

$recordId = piePaymentRecord(array(
    'provider' => 'stripe', 'provider_transaction_id' => 'pi_1234567890',
    'payer_name' => 'Ada Lovelace', 'payer_email' => 'ada@example.test', 'payer_phone' => '+1 555 123 4567',
    'service' => 'Web Development', 'notes' => 'Project invoice', 'amount' => '250.25',
    'currency' => 'USD', 'status' => 'succeeded', 'verification_mode' => 'server',
    'raw_reference' => 'cs_test_abcdef', 'ip_address' => '203.0.113.20',
));
paymentCheck($recordId === 1, 'a confirmed provider transaction is inserted into the existing payment_records table');
$stored = $GLOBALS['fake_record_rows']['stripe|pi_1234567890'];
paymentCheck($stored['payer_phone'] === '+1 555 123 4567' && $stored['service'] === 'Web Development' && $stored['notes'] === 'Project invoice', 'the saved row retains phone, service and customer notes');
paymentCheck($stored['amount'] === '250.25' && $stored['currency'] === 'USD' && $stored['status'] === 'succeeded', 'the saved row retains exact amount, currency and confirmed status');
$duplicate = piePaymentRecord(array('provider' => 'stripe', 'provider_transaction_id' => 'pi_1234567890', 'amount' => '250.25'));
paymentCheck($duplicate === $recordId && count($GLOBALS['fake_record_rows']) === 1, 'duplicate provider delivery reuses its original record');

$records = piePaymentRecordList(piePaymentRecordFilters(array()), 25, 0);
paymentCheck(count($records) === 1 && $records[0]['provider_transaction_id'] === 'pi_1234567890', 'Admin list reads the confirmed payment record');
$totals = piePaymentRecordTotals(piePaymentRecordFilters(array()));
paymentCheck($totals['count'] === 1 && abs($totals['total_usd'] - 250.25) < 0.001, 'Admin totals include confirmed USD payments');
$filters = piePaymentRecordFilters(array('q' => 'Ada', 'provider' => 'STRIPE', 'status' => 'SUCCEEDED', 'from' => '2026-10-01', 'to' => '2026-10-31'));
$where = piePaymentRecordWhere($filters);
paymentCheck($filters['provider'] === 'stripe' && $filters['status'] === 'succeeded', 'record filters normalize provider and status');
paymentCheck(count($where['params']) === 11, 'search/date/provider/status filter values are all bound');
paymentCheck(strpos($where['sql'], 'payer_phone LIKE ?') !== false && strpos($where['sql'], 'notes LIKE ?') !== false, 'Admin search includes customer phone and notes');
$injection = piePaymentRecordWhere(piePaymentRecordFilters(array('q' => "100% _x'; DROP TABLE payment_records; --")));
paymentCheck(strpos($injection['sql'], 'DROP') === false && count($injection['params']) === 7, 'search text cannot become SQL and every match is parameter-bound');

$headers = piePaymentRecordCsvHeaders();
$row = piePaymentRecordCsvRow($stored);
paymentCheck(count($headers) === 15 && count($row) === count($headers), 'CSV header and data rows include all customer and payment fields');
paymentCheck($headers[5] === 'Payer phone' && $headers[6] === 'Service' && $headers[7] === 'Notes', 'CSV exports phone, service and notes');
paymentCheck($row[1] === 'Stripe' && $row[2] === 'pi_1234567890' && $row[8] === '250.25' && $row[13] === '203.0.113.20', 'CSV exports provider, gateway ID, amount and IP');

$admin = paymentSource('admin/payment-records.php');
$export = paymentSource('admin/payment-records-export.php');
$nav = paymentSource('includes/admin-header.php');
$dashboard = paymentSource('admin/index.php');
paymentCheck(strpos($admin, 'requireAdmin()') !== false && strpos($admin, 'validateCSRF') !== false, 'payment-record views/deletes are admin-only and CSRF-protected');
paymentCheck(strpos($admin, 'requireAdmin()') < strpos($admin, 'Schema::ensure()') && strpos($export, 'requireAdmin()') < strpos($export, 'Schema::ensure()'), 'payment-record migration/export paths authenticate before performing work');
paymentCheck(strpos($admin, "['payer_phone']") !== false && strpos($admin, "['notes']") !== false, 'Admin record details display customer phone and notes');
paymentCheck(strpos($export, 'validateCSRF') !== false && strpos($export, 'piePaymentRecordAll') !== false, 'CSV export remains protected and uses the existing record module');
paymentCheck(strpos($nav, 'payment-records.php') !== false, 'existing Admin navigation retains Payment records');
paymentCheck(strpos($dashboard, 'Recent confirmed payments') !== false && strpos($dashboard, 'piePaymentRecordList') !== false && strpos($dashboard, 'piePaymentRecordTotals') !== false, 'existing Admin Dashboard shows confirmed payment records and totals');

$GLOBALS['fake_tables']['payment_records'] = false;
paymentCheck(piePaymentRecord(array('provider' => 'stripe', 'provider_transaction_id' => 'pi_missing')) === 0, 'record writes fail closed if the table is unavailable');
paymentCheck(piePaymentRecordList(piePaymentRecordFilters(array()), 25, 0) === array(), 'Admin list remains safe if the table is unavailable');

paymentTestSummary();
