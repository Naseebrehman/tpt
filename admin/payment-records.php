<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Payments (payment records)
 * ---------------------------------------------------------------------------
 *  Every payment a GATEWAY confirmed on the server is listed here, newest
 *  first: PayPal captures verified with the PayPal Secret and Stripe
 *  PaymentIntents / Checkout Sessions verified with the Stripe Secret Key —
 *  from the browser-confirmation endpoint and from the signature-verified
 *  Stripe webhook alike. Duplicate deliveries of the same gateway payment id
 *  are recorded only once.
 *
 *  Admin-only. Search (name / reference / service), provider, status and date
 *  filters, totals (count + USD sum), pagination, per-record delete with a
 *  confirmation prompt, bulk delete and a CSV export that respects the current
 *  filters. Deletes and the export are CSRF-protected, and every query uses
 *  prepared statements (LIMIT/OFFSET are cast to integers).
 * ---------------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once BASE_PATH . '/core/Schema.php';
require_once BASE_PATH . '/core/PaymentRecords.php';
Schema::ensure();
requireAdmin();

$adminPage  = 'payment-records';
$adminTitle = 'Payments';

/** The current filters, as a query string (used to keep the view after a POST). */
function paymentRecordsQueryString(array $filters, $page = 0, $perPage = 0)
{
    $query = array_filter(array(
        'q'        => $filters['q'],
        'provider' => $filters['provider'],
        'status'   => $filters['status'],
        'from'     => $filters['from'],
        'to'       => $filters['to'],
        'page'     => $page > 1 ? (string) $page : '',
        'per_page' => $perPage > 0 && $perPage !== 25 ? (string) $perPage : '',
    ), function ($value) { return $value !== '' && $value !== null; });
    return http_build_query($query);
}

/* ---------------------------------------------------------------------------
   Delete (single + bulk): POST only, CSRF-checked, then redirect back to the
   exact filtered view (POST → redirect → GET, so a refresh never re-deletes).
   --------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedFilters = piePaymentRecordFilters($_POST);
    $back = 'payment-records.php' . (paymentRecordsQueryString($postedFilters) !== '' ? '?' . paymentRecordsQueryString($postedFilters) : '');

    if (!validateCSRF()) {
        setFlash('err', 'Security token expired — refresh and try again.');
    } elseif (isset($_POST['record_action']) && $_POST['record_action'] === 'delete') {
        /* A row-level Delete button sends its own id (`single_id`) and wins over
           the bulk selection, so it can never delete more than that one row. */
        $singleId = isset($_POST['single_id']) ? (int) $_POST['single_id'] : 0;
        if ($singleId > 0) {
            $ids = array($singleId);
        } else {
            $ids = isset($_POST['ids']) ? $_POST['ids'] : array();
            if (!is_array($ids)) { $ids = array($ids); }
        }
        $ids = array_slice($ids, 0, 500);
        $deleted = piePaymentRecordDelete($ids);
        if ($deleted > 0) {
            setFlash('ok', $deleted === 1 ? 'Payment record deleted.' : ($deleted . ' payment records deleted.'));
        } else {
            setFlash('err', 'No matching payment record was deleted.');
        }
    } else {
        setFlash('err', 'Nothing was deleted.');
    }
    header('Location: ' . $back);
    exit;
}

/* ------------------------------- filters -------------------------------- */
$filters = piePaymentRecordFilters($_GET);
$page    = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 25;
if (!in_array($perPage, array(10, 25, 50, 100), true)) { $perPage = 25; }

$totals   = piePaymentRecordTotals($filters);
$total    = (int) $totals['count'];
$sumUsd   = (float) $totals['total_usd'];
$pages    = max(1, (int) ceil($total / $perPage));
$page     = min($page, $pages);
$offset   = ($page - 1) * $perPage;

$records  = piePaymentRecordList($filters, $perPage, $offset);
$ready    = piePaymentRecordsReady();
$filterQuery = paymentRecordsQueryString($filters);
$hasFilters  = ($filters['q'] !== '' || $filters['provider'] !== '' || $filters['status'] !== '' || $filters['from'] !== '' || $filters['to'] !== '');
$csrfToken   = generateCSRF();

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<?php if (!$ready): ?>
    <div class="a-card">
        <h3><?= icon('chart', 18) ?> Payment records</h3>
        <p class="hint" style="color:#fca5a5">The <code>payment_records</code> table is not available yet. Run <code>php bin/cli.php migrate</code> (or open this page again with the database user allowed to create tables) and confirmed payments will be listed here.</p>
    </div>
<?php endif; ?>

<div class="a-toolbar">
    <span class="text-muted">
        <strong><?= (int) $total ?></strong> record<?= $total === 1 ? '' : 's' ?><?= $hasFilters ? ' (filtered)' : '' ?>
        · <strong>$<?= esc(number_format($sumUsd, 2)) ?></strong> USD
    </span>
    <span class="spacer"></span>
    <form method="get" action="payment-records-export.php" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= esc($csrfToken) ?>">
        <input type="hidden" name="q" value="<?= esc($filters['q']) ?>">
        <input type="hidden" name="provider" value="<?= esc($filters['provider']) ?>">
        <input type="hidden" name="status" value="<?= esc($filters['status']) ?>">
        <input type="hidden" name="from" value="<?= esc($filters['from']) ?>">
        <input type="hidden" name="to" value="<?= esc($filters['to']) ?>">
        <button class="a-btn small" type="submit"><?= icon('download', 15) ?> Export CSV<?= $hasFilters ? ' (filtered)' : '' ?></button>
    </form>
</div>

<form class="a-filters" method="get" action="payment-records.php">
    <input type="date" name="from" value="<?= esc($filters['from']) ?>" aria-label="From date" data-autosubmit="1">
    <input type="date" name="to" value="<?= esc($filters['to']) ?>" aria-label="To date" data-autosubmit="1">
    <select name="provider" aria-label="Filter by provider" data-autosubmit="1">
        <option value="">All providers</option>
        <?php foreach (piePaymentRecordProviders() as $providerOption): ?>
            <option value="<?= esc($providerOption) ?>"<?= $filters['provider'] === $providerOption ? ' selected' : '' ?>><?= esc(piePaymentProviderLabel($providerOption)) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" aria-label="Filter by status" data-autosubmit="1">
        <option value="">All statuses</option>
        <?php foreach (piePaymentRecordStatusOptions() as $statusOption): ?>
            <option value="<?= esc($statusOption) ?>"<?= $filters['status'] === $statusOption ? ' selected' : '' ?>><?= esc(ucfirst($statusOption)) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= esc($filters['q']) ?>" placeholder="Search name, reference, service…">
    <select name="per_page" aria-label="Rows per page" data-autosubmit="1">
        <?php foreach (array(10, 25, 50, 100) as $size): ?>
            <option value="<?= $size ?>"<?= $perPage === $size ? ' selected' : '' ?>><?= $size ?> / page</option>
        <?php endforeach; ?>
    </select>
    <button class="a-btn small" type="submit">Apply</button>
    <?php if ($hasFilters): ?><a class="a-btn small" href="payment-records.php">Reset</a><?php endif; ?>
</form>

<form method="post" action="payment-records.php<?= $filterQuery !== '' ? '?' . esc($filterQuery) : '' ?>">
    <?= csrfField() ?>
    <input type="hidden" name="record_action" value="delete">
    <input type="hidden" name="q" value="<?= esc($filters['q']) ?>">
    <input type="hidden" name="provider" value="<?= esc($filters['provider']) ?>">
    <input type="hidden" name="status" value="<?= esc($filters['status']) ?>">
    <input type="hidden" name="from" value="<?= esc($filters['from']) ?>">
    <input type="hidden" name="to" value="<?= esc($filters['to']) ?>">

    <div class="a-toolbar" id="bulkBar" style="display:none">
        <span class="text-muted" data-bulk-count>0 selected</span>
        <button class="a-btn small danger" type="submit" data-confirm="Delete the selected payment records permanently? The gateway payment itself is not affected."><?= icon('trash', 15) ?> Delete selected</button>
    </div>

    <div class="a-table-wrap">
        <table class="a-table">
            <thead>
                <tr>
                    <th style="width:34px"><input type="checkbox" id="selectAll" aria-label="Select all"></th>
                    <th>Recorded</th>
                    <th>Provider</th>
                    <th>Payer</th>
                    <th>Service</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Verification</th>
                    <th>Reference</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$records): ?>
                <tr>
                    <td colspan="10" class="text-muted">
                        <?= $hasFilters
                            ? 'No payment record matches these filters.'
                            : 'No confirmed payment has been recorded yet. Server-confirmed PayPal captures and Stripe payments (including webhook deliveries) appear here automatically.' ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($records as $record): ?>
                <tr>
                    <td><input type="checkbox" class="row-check" name="ids[]" value="<?= (int) $record['id'] ?>" aria-label="Select payment record <?= (int) $record['id'] ?>"></td>
                    <td class="td-sub"><div class="nowrap"><?= esc(formatDate($record['created_at'], 'j M Y')) ?></div><div class="td-sub"><?= esc(formatDate($record['created_at'], 'H:i')) ?></div></td>
                    <td><span class="badge <?= $record['provider'] === 'paypal' ? 'active' : 'inactive' ?>"><?= esc(piePaymentProviderLabel($record['provider'])) ?></span></td>
                    <td class="td-main">
                        <?= esc($record['payer_name'] !== '' ? $record['payer_name'] : '—') ?>
                        <div class="td-sub"><?= esc($record['payer_email'] !== '' ? $record['payer_email'] : '') ?></div>
                    </td>
                    <td><?= esc($record['service'] !== '' ? $record['service'] : '—') ?></td>
                    <td class="mono">$<?= esc(number_format((float) $record['amount'], 2)) ?> <span class="td-sub"><?= esc(strtoupper((string) $record['currency'])) ?></span></td>
                    <td><span class="badge <?= strtolower((string) $record['status']) === 'succeeded' ? 'active' : 'inactive' ?>"><?= esc(ucfirst((string) $record['status'])) ?></span></td>
                    <td class="td-sub"><?= esc(piePaymentVerificationLabel($record['verification_mode'])) ?></td>
                    <td class="mono td-sub" title="<?= esc($record['provider_transaction_id']) ?>"><?= esc(mb_strimwidth((string) $record['provider_transaction_id'], 0, 30, '…')) ?></td>
                    <td>
                        <div class="row-actions">
                            <?php if ($record['raw_reference'] !== '' && $record['raw_reference'] !== $record['provider_transaction_id']): ?>
                                <button class="a-btn small" type="button" data-modal="record-ref-<?= (int) $record['id'] ?>">View</button>
                            <?php endif; ?>
                            <button class="a-btn small danger" type="submit" name="single_id" value="<?= (int) $record['id'] ?>"
                                    data-confirm="Delete this payment record? The gateway payment itself is not affected.">Delete</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>

<?php if ($pages > 1): ?>
<nav class="a-toolbar" style="justify-content:center;flex-wrap:wrap" aria-label="Pagination">
    <?php
    $windowStart = max(1, $page - 3);
    $windowEnd   = min($pages, $windowStart + 6);
    if ($windowStart > 1) {
        echo '<a class="a-btn small" href="?' . esc(paymentRecordsQueryString($filters, 1, $perPage)) . '">1</a><span class="text-muted">…</span>';
    }
    for ($p = $windowStart; $p <= $windowEnd; $p++):
    ?>
        <a class="a-btn small<?= $p === $page ? ' primary' : '' ?>" href="?<?= esc(paymentRecordsQueryString($filters, $p, $perPage)) ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($windowEnd < $pages): ?>
        <span class="text-muted">…</span><a class="a-btn small" href="?<?= esc(paymentRecordsQueryString($filters, $pages, $perPage)) ?>"><?= $pages ?></a>
    <?php endif; ?>
</nav>
<?php endif; ?>

<?php /* ------------------- details (raw reference) modals ------------------- */ ?>
<?php foreach ($records as $record): ?>
<template id="record-ref-<?= (int) $record['id'] ?>">
    <h3><?= esc(piePaymentProviderLabel($record['provider'])) ?> payment #<?= (int) $record['id'] ?></h3>
    <p class="hint">Recorded <?= esc(formatDate($record['created_at'], 'j M Y, H:i')) ?> · <?= esc(piePaymentVerificationLabel($record['verification_mode'])) ?></p>
    <div class="a-table-wrap">
        <table class="a-table">
            <tbody>
                <tr><th>Gateway payment id</th><td class="mono"><?= esc($record['provider_transaction_id']) ?></td></tr>
                <tr><th>Raw reference</th><td class="mono"><?= esc($record['raw_reference']) ?></td></tr>
                <tr><th>Payer</th><td><?= esc($record['payer_name'] !== '' ? $record['payer_name'] : '—') ?><?php if ($record['payer_email'] !== ''): ?><div class="td-sub"><?= esc($record['payer_email']) ?></div><?php endif; ?></td></tr>
                <tr><th>Service</th><td><?= esc($record['service'] !== '' ? $record['service'] : '—') ?></td></tr>
                <tr><th>Amount</th><td>$<?= esc(number_format((float) $record['amount'], 2)) ?> <?= esc(strtoupper((string) $record['currency'])) ?></td></tr>
                <tr><th>Status</th><td><?= esc(ucfirst((string) $record['status'])) ?></td></tr>
                <tr><th>IP address</th><td class="mono"><?= esc($record['ip_address'] !== '' ? $record['ip_address'] : '—') ?></td></tr>
            </tbody>
        </table>
    </div>
    <p class="hint" style="margin-top:12px">Card details are never stored — only the gateway reference, amount, buyer, service and time.</p>
</template>
<?php endforeach; ?>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
