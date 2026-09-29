<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payments.php';
requireAdmin();

$adminPage  = 'payments';
$adminTitle = 'Payments';

/* ------------------------- self-contained actions ------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired.');
    } else {
        $paymentId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $action    = isset($_POST['pay_action']) ? sanitize($_POST['pay_action']) : '';
        $row       = $paymentId > 0 ? dbOne('SELECT * FROM payments WHERE id = ?', array($paymentId)) : null;
        if (!$row) {
            setFlash('err', 'Payment request not found.');
        } elseif ($action === 'set_status' && isset($_POST['status'])) {
            $allowed = array('requested', 'pending', 'paid', 'failed', 'cancelled');
            $status  = sanitize($_POST['status']);
            if (in_array($status, $allowed, true)) {
                if ($status === 'paid' && $row['status'] !== 'paid') {
                    /* Fires the one-time paid notifications (Task 6). */
                    piePaymentMarkPaid($row);
                } else {
                    dbExec('UPDATE payments SET status = ? WHERE id = ?', array($status, $paymentId));
                }
                setFlash('ok', 'Status updated to “' . $status . '”.');
            }
        } elseif ($action === 'recheck' && $row['status'] === 'pending' && $row['provider_ref'] !== '') {
            $updated = piePaymentReconcile($row);
            setFlash($updated['status'] === 'paid' ? 'ok' : 'err', $updated['status'] === 'paid' ? 'Provider confirmed: payment received.' : 'Provider has not confirmed payment yet.');
        } elseif ($action === 'copy_link') {
            setFlash('ok', 'Secure link: ' . canonicalUrl('pay/secure/' . $row['token']));
        } elseif ($action === 'delete') {
            dbExec('DELETE FROM payments WHERE id = ?', array($paymentId));
            setFlash('ok', 'Payment request deleted.');
        }
    }
    header('Location: payments.php');
    exit;
}

$fStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search  = isset($_GET['q']) ? sanitize($_GET['q']) : '';

$where  = array('1=1');
$params = array();
if ($fStatus !== '') { $where[] = 'status = ?'; $params[] = $fStatus; }
if ($search !== '')  {
    $where[] = '(name LIKE ? OR email LIKE ? OR reference LIKE ? OR token LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, array($like, $like, $like, $like));
}
$whereSql = implode(' AND ', $where);
$payments = dbAll("SELECT * FROM payments WHERE $whereSql ORDER BY created_at DESC LIMIT 200", $params);

$totalPaid   = 0;
$totalPending = 0;
foreach ($payments as $payRow) {
    if ($payRow['status'] === 'paid')    { $totalPaid    += (float) $payRow['amount_usd']; }
    if ($payRow['status'] === 'pending') { $totalPending += (float) $payRow['amount_usd']; }
}

$statusOptions = array('requested', 'pending', 'paid', 'failed', 'cancelled');

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-toolbar">
    <span class="text-muted"><?= count($payments) ?> request<?= count($payments) === 1 ? '' : 's' ?> · $<?= esc(number_format($totalPaid, 2)) ?> paid · $<?= esc(number_format($totalPending, 2)) ?> pending</span>
    <span class="spacer"></span>
    <form class="a-filters" method="get" action="payments.php" style="display:flex;gap:8px;margin:0">
        <select name="status" aria-label="Filter by status" data-autosubmit="1">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $statusOpt): ?>
            <option value="<?= esc($statusOpt) ?>"<?= $fStatus === $statusOpt ? ' selected' : '' ?>><?= esc(ucfirst($statusOpt)) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="search" name="q" value="<?= esc($search) ?>" placeholder="Search name, email, reference, token…">
        <button class="a-btn small" type="submit">Search</button>
    </form>
</div>

<div class="a-table-wrap">
    <table class="a-table">
        <thead><tr><th>When</th><th>Who</th><th>Reference</th><th style="text-align:right">Amount</th><th>Method</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
        <?php if (!$payments): ?>
            <tr><td colspan="7" class="text-muted">No payment requests yet — the form lives on the Pay Online page. Providers appear there only once enabled in Settings → Payments.</td></tr>
        <?php endif; ?>
        <?php foreach ($payments as $payRow): ?>
        <tr>
            <td class="td-sub"><?= esc(formatDate($payRow['created_at'], 'j M Y, H:i')) ?></td>
            <td class="td-main"><?= esc($payRow['name']) ?><br><span class="td-sub"><?= esc($payRow['email']) ?><?php if (!empty($payRow['phone'])): ?> · <?= esc($payRow['phone']) ?><?php endif; ?><?php if (!empty($payRow['service'])): ?><br><?= esc($payRow['service']) ?><?php endif; ?></span></td>
            <td class="mono" style="font-size:.78rem"><?= esc($payRow['reference'] !== '' ? $payRow['reference'] : '—') ?></td>
            <td style="text-align:right"><strong>$<?= esc(number_format((float) $payRow['amount_usd'], 2)) ?></strong></td>
            <td><?= esc(piePaymentMethodLabel($payRow['method'])) ?></td>
            <td><span class="badge <?= $payRow['status'] === 'paid' ? 'active' : ($payRow['status'] === 'pending' || $payRow['status'] === 'requested' ? '' : 'inactive') ?>"><?= esc(ucfirst($payRow['status'])) ?></span></td>
            <td>
                <div class="row-actions">
                    <form method="post" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="pay_action" value="set_status">
                        <input type="hidden" name="id" value="<?= (int) $payRow['id'] ?>">
                        <select name="status" aria-label="Set status" style="padding:5px 8px" onchange="this.form.submit()">
                            <?php foreach ($statusOptions as $statusOpt): ?>
                            <option value="<?= esc($statusOpt) ?>"<?= $payRow['status'] === $statusOpt ? ' selected' : '' ?>><?= esc(ucfirst($statusOpt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php if ($payRow['status'] === 'pending' && $payRow['provider_ref'] !== ''): ?>
                    <form method="post" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="pay_action" value="recheck">
                        <input type="hidden" name="id" value="<?= (int) $payRow['id'] ?>">
                        <button class="a-btn small" type="submit">Re-check provider</button>
                    </form>
                    <?php endif; ?>
                    <a class="a-btn small" target="_blank" rel="noopener" href="<?= esc(canonicalUrl('pay/secure/' . $payRow['token'])) ?>">Open link</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this payment request permanently?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="pay_action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $payRow['id'] ?>">
                        <button class="a-btn small danger" type="submit">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p class="hint" style="margin-top:14px">Card and PayPal credentials never pass through this dashboard — only tokens, amounts and statuses. Provider keys live in Settings → Payments and are used server-side only.</p>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
