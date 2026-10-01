<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Payments CSV export
 * ---------------------------------------------------------------------------
 *  Streams every payment record matching the filters currently applied on
 *  Admin → Payments → Payment records (search, provider, status, date range).
 *  Admin-only, CSRF-protected, and the CSV writer prefixes any cell that could
 *  be read as a formula (= + - @) with a quote so a spreadsheet cannot execute
 *  it (includes/functions.php → csvDownload()).
 * ---------------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();
require_once BASE_PATH . '/core/Schema.php';
require_once BASE_PATH . '/core/PaymentRecords.php';
Schema::ensure();

/* The export is generated from a GET form, so the CSRF token travels with the
   filters and is validated before anything is read. */
$token = isset($_GET['csrf_token']) ? (string) $_GET['csrf_token'] : '';
if (!validateCSRF($token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Security token expired — refresh the Payments page and export again.';
    exit;
}

$filters = piePaymentRecordFilters($_GET);

$headers = piePaymentRecordCsvHeaders();
$rows    = array();
foreach (piePaymentRecordAll($filters) as $record) {
    $rows[] = piePaymentRecordCsvRow($record);
}

csvDownload('payment-records-' . date('Y-m-d') . '.csv', $headers, $rows);
