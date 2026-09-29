<?php
/**
 * ---------------------------------------------------------------------------
 *  Admin → Payments
 * ---------------------------------------------------------------------------
 *  Four sections:
 *    Requests  — every payment request / transaction (statuses, links, delete)
 *    Services  — the list offered in the Pay Online dropdown (add, edit,
 *                delete, enable/disable, reorder)
 *    PayPal    — enable/disable + your own PayPal SDK / integration code
 *    Stripe    — enable/disable + your own Stripe SDK / integration code
 *  All mutations require a logged-in admin plus a valid CSRF token.
 * --------------------------------------------------------------------------- */
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/payments.php';
require_once BASE_PATH . '/core/Schema.php';
Schema::ensure();
requireAdmin();

$adminPage  = 'payments';
$adminTitle = 'Payments';

$tabs = array('requests' => 'Payment Requests', 'services' => 'Services', 'paypal' => 'PayPal', 'stripe' => 'Stripe');
$tab  = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'requests';
if (!isset($tabs[$tab])) { $tab = 'requests'; }

/* ------------------------------ helpers --------------------------------- */
if (!function_exists('pieSaveSetting')) {
    function pieSaveSetting($key, $value)
    {
        return dbExec(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            array($key, $value)
        ) >= 0;
    }
}

/** Services offered in the Pay Online dropdown (admin-managed). */
function pieAdminServiceList()
{
    if (!Schema::hasTable('payment_services')) {
        return array();
    }
    return dbAll('SELECT * FROM payment_services ORDER BY sort_order ASC, id ASC');
}

function pieRedirectTab($tab)
{
    header('Location: payments.php?tab=' . rawurlencode($tab));
    exit;
}

/* ------------------------- self-contained actions ------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired — refresh the page and try again.');
        pieRedirectTab($tab);
    }

    $action = isset($_POST['pay_action']) ? sanitize($_POST['pay_action']) : '';

    /* ===================== payment request actions ===================== */
    if (in_array($action, array('set_status', 'recheck', 'copy_link', 'delete'), true)) {
        $paymentId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $row       = $paymentId > 0 ? dbOne('SELECT * FROM payments WHERE id = ?', array($paymentId)) : null;
        if (!$row) {
            setFlash('err', 'Payment request not found.');
        } elseif ($action === 'set_status' && isset($_POST['status'])) {
            $allowed = array('requested', 'pending', 'paid', 'failed', 'cancelled');
            $status  = sanitize($_POST['status']);
            if (in_array($status, $allowed, true)) {
                if ($status === 'paid' && $row['status'] !== 'paid') {
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
        pieRedirectTab('requests');
    }

    /* ========================= service actions ========================= */
    if (in_array($action, array('service_add', 'service_update', 'service_delete', 'service_toggle', 'service_move'), true)) {
        if (!Schema::hasTable('payment_services')) {
            setFlash('err', 'The payment services table is missing — run: php bin/cli.php migrate');
            pieRedirectTab('services');
        }
        $serviceId = isset($_POST['service_id']) ? (int) $_POST['service_id'] : 0;

        if ($action === 'service_add' || $action === 'service_update') {
            $name = mb_substr(sanitize(isset($_POST['name']) ? $_POST['name'] : ''), 0, 150);
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($name === '') {
                setFlash('err', 'Enter a service name.');
            } elseif ($action === 'service_add') {
                $next = dbOne('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM payment_services');
                $ok = dbInsert('INSERT INTO payment_services (name, sort_order, is_active) VALUES (?, ?, ?)',
                    array($name, $next ? (int) $next['n'] : 1, $active));
                setFlash($ok > 0 ? 'ok' : 'err', $ok > 0 ? 'Service added.' : 'Could not add the service (it may already exist).');
            } else {
                $ok = dbExec('UPDATE payment_services SET name = ?, is_active = ? WHERE id = ?', array($name, $active, $serviceId));
                setFlash($ok >= 0 ? 'ok' : 'err', $ok >= 0 ? 'Service updated.' : 'Could not update the service.');
            }
        } elseif ($action === 'service_delete') {
            $ok = dbExec('DELETE FROM payment_services WHERE id = ?', array($serviceId));
            setFlash($ok >= 0 ? 'ok' : 'err', $ok >= 0 ? 'Service deleted.' : 'Could not delete the service.');
        } elseif ($action === 'service_toggle') {
            $ok = dbExec('UPDATE payment_services SET is_active = 1 - is_active WHERE id = ?', array($serviceId));
            setFlash($ok >= 0 ? 'ok' : 'err', $ok >= 0 ? 'Service status changed.' : 'Could not change the service status.');
        } elseif ($action === 'service_move') {
            $dir = (isset($_POST['dir']) && $_POST['dir'] === 'up') ? 'up' : 'down';
            $services = pieAdminServiceList();
            $index = -1;
            foreach ($services as $i => $svc) { if ((int) $svc['id'] === $serviceId) { $index = $i; } }
            $swap = $dir === 'up' ? $index - 1 : $index + 1;
            if ($index >= 0 && isset($services[$swap])) {
                dbExec('UPDATE payment_services SET sort_order = ? WHERE id = ?', array((int) $services[$swap]['sort_order'], (int) $services[$index]['id']));
                dbExec('UPDATE payment_services SET sort_order = ? WHERE id = ?', array((int) $services[$index]['sort_order'], (int) $services[$swap]['id']));
                setFlash('ok', 'Order saved.');
            }
        }
        pieRedirectTab('services');
    }

    /* ====================== PayPal / Stripe SDK code ==================== */
    if ($action === 'gateway_save') {
        $gateway = isset($_POST['gateway']) ? sanitize($_POST['gateway']) : '';
        if (!in_array($gateway, array('paypal', 'stripe', 'invoice'), true)) {
            setFlash('err', 'Unknown payment method.');
            pieRedirectTab('paypal');
        }
        /* The integration code is admin-authored and rendered only inside the
           Pay Online page — never in a JS/CSS asset, never in the admin UI. */
        $code = isset($_POST['sdk_code']) ? (string) $_POST['sdk_code'] : '';
        $code = str_replace("\0", '', $code);
        $ok  = pieSaveSetting($gateway . '_enabled', isset($_POST['enabled']) ? '1' : '0');
        if ($gateway === 'invoice') {
            $ok2 = pieSaveSetting('invoice_note', mb_substr(sanitizeMultiline(isset($_POST['invoice_note']) ? $_POST['invoice_note'] : ''), 0, 2000));
        } else {
            $ok2 = pieSaveSetting($gateway . '_sdk_code', $code);
        }
        setFlash($ok && $ok2 ? 'ok' : 'err', $ok && $ok2 ? ucfirst($gateway) . ' settings saved.' : 'Could not save the settings.');
        pieRedirectTab($gateway);
    }

    pieRedirectTab($tab);
}

/* ----------------------------- request list ------------------------------ */
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

$totalPaid    = 0;
$totalPending = 0;
foreach ($payments as $payRow) {
    if ($payRow['status'] === 'paid')    { $totalPaid    += (float) $payRow['amount_usd']; }
    if ($payRow['status'] === 'pending') { $totalPending += (float) $payRow['amount_usd']; }
}
$statusOptions = array('requested', 'pending', 'paid', 'failed', 'cancelled');

/* ------------------------------ services --------------------------------- */
$services = pieAdminServiceList();

/* ------------------------------ gateways --------------------------------- */
$gateways = array(
    'paypal'  => array(
        'label'    => 'PayPal',
        'enabled'  => getSetting('paypal_enabled', '0') === '1',
        'code'     => (string) getSetting('paypal_sdk_code', ''),
        'keys'     => getSetting('paypal_client_id') !== '',
        'docs'     => 'Paste the PayPal Buttons SDK snippet (or your own checkout form). It is rendered inside the themed payment panel on the Pay Online page when a visitor chooses PayPal.',
    ),
    'stripe'  => array(
        'label'    => 'Stripe',
        'enabled'  => getSetting('stripe_enabled', '0') === '1',
        'code'     => (string) getSetting('stripe_sdk_code', ''),
        'keys'     => getSetting('stripe_publishable_key') !== '' && getSetting('stripe_secret_key') !== '',
        'docs'     => 'Paste the Stripe Elements / Checkout snippet (or your own card form). It is rendered inside the themed payment panel on the Pay Online page when a visitor chooses card payment.',
    ),
    'invoice' => array(
        'label'    => 'Request an invoice',
        'enabled'  => getSetting('invoice_enabled', '1') === '1',
        'code'     => '',
        'keys'     => true,
        'docs'     => 'The built-in third option: the visitor submits the form, we create the request and email them a secure payment link. No SDK code is needed.',
    ),
);

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-tabs">
    <?php foreach ($tabs as $key => $label): ?>
    <button class="a-tab<?= $tab === $key ? ' active' : '' ?>" type="button" data-tab="<?= esc($key) ?>"><?= esc($label) ?></button>
    <?php endforeach; ?>
</div>

<!-- ============================ REQUESTS ================================ -->
<div class="a-tabpanel<?= $tab === 'requests' ? ' active' : '' ?>" data-panel="requests">
    <div class="a-toolbar">
        <span class="text-muted"><?= count($payments) ?> request<?= count($payments) === 1 ? '' : 's' ?> · $<?= esc(number_format($totalPaid, 2)) ?> paid · $<?= esc(number_format($totalPending, 2)) ?> pending</span>
        <span class="spacer"></span>
        <form class="a-filters" method="get" action="payments.php" style="display:flex;gap:8px;margin:0">
            <input type="hidden" name="tab" value="requests">
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
                <tr><td colspan="7" class="text-muted">No payment requests yet — the form lives on the Pay Online page. Methods appear there once enabled in Payments → PayPal / Stripe.</td></tr>
            <?php endif; ?>
            <?php foreach ($payments as $payRow): ?>
            <tr>
                <td class="td-sub"><?= esc(formatDate($payRow['created_at'], 'j M Y, H:i')) ?></td>
                <td class="td-main"><?= esc($payRow['name']) ?><br><span class="td-sub"><?= esc($payRow['email']) ?><?php if (!empty($payRow['phone'])): ?> · <?= esc($payRow['phone']) ?><?php endif; ?><?php if (!empty($payRow['service'])): ?><br><?= esc($payRow['service']) ?><?php endif; ?></span></td>
                <td class="mono" style="font-size:.78rem"><?= esc($payRow['reference'] !== '' ? $payRow['reference'] : '—') ?></td>
                <td style="text-align:right"><strong>$<?= esc(number_format((float) $payRow['amount_usd'], 2)) ?></strong></td>
                <td><?= esc(piePaymentMethodLabel($payRow['method'])) ?></td>
                <td><span class="badge <?= $payRow['status'] === 'paid' ? 'active' : ($payRow['status'] === 'pending' || $payRow['status'] === 'requested' ? '' : 'inactive') ?><?= ' ' . esc($payRow['status']) ?>"><?= esc(ucfirst($payRow['status'])) ?></span></td>
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
                            <button class="a-btn small" type="submit">Re-check</button>
                        </form>
                        <?php endif; ?>
                        <a class="a-btn small" target="_blank" rel="noopener" href="<?= esc(canonicalUrl('pay/secure/' . $payRow['token'])) ?>">Open link</a>
                        <form method="post" style="display:inline" data-confirm="Delete this payment request permanently?">
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
</div>

<!-- ============================ SERVICES ================================ -->
<div class="a-tabpanel<?= $tab === 'services' ? ' active' : '' ?>" data-panel="services">
    <div class="a-grid cols-2" style="align-items:start">
        <form method="post" class="a-card">
            <?= csrfField() ?>
            <input type="hidden" name="pay_action" value="service_add">
            <h3>Add service</h3>
            <div class="a-field">
                <label for="svcName">Service name</label>
                <input id="svcName" name="name" type="text" maxlength="150" required placeholder="Website Development">
            </div>
            <label class="a-check"><input type="checkbox" name="is_active" value="1" checked> Enabled — shown in the Pay Online dropdown</label>
            <div class="a-toolbar" style="margin-top:16px">
                <button class="a-btn primary" type="submit"><?= icon('check', 15) ?> Add service</button>
            </div>
        </form>

        <div class="a-card">
            <h3>Payment services</h3>
            <p class="hint" style="margin-top:-8px">These options fill the “Service” dropdown on the Pay Online page. Disabled services stay stored but are not offered. Use the arrows to change the order.</p>
            <?php if (!$services): ?>
            <p class="hint">No services configured yet — add one on the left.</p>
            <?php else: ?>
            <div class="a-table-wrap">
                <table class="a-table" style="min-width:520px">
                    <thead><tr><th>Order</th><th>Name</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
                    <tbody>
                        <?php $lastIndex = count($services) - 1; ?>
                        <?php foreach ($services as $i => $svc): ?>
                        <tr>
                            <td>
                                <div class="row-actions" style="justify-content:flex-start">
                                    <form method="post" style="display:inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="pay_action" value="service_move">
                                        <input type="hidden" name="service_id" value="<?= (int) $svc['id'] ?>">
                                        <input type="hidden" name="dir" value="up">
                                        <button class="a-btn small" type="submit"<?= $i === 0 ? ' disabled' : '' ?> aria-label="Move up">↑</button>
                                    </form>
                                    <form method="post" style="display:inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="pay_action" value="service_move">
                                        <input type="hidden" name="service_id" value="<?= (int) $svc['id'] ?>">
                                        <input type="hidden" name="dir" value="down">
                                        <button class="a-btn small" type="submit"<?= $i === $lastIndex ? ' disabled' : '' ?> aria-label="Move down">↓</button>
                                    </form>
                                </div>
                            </td>
                            <td>
                                <form method="post" style="display:flex;gap:8px;align-items:center">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="pay_action" value="service_update">
                                    <input type="hidden" name="service_id" value="<?= (int) $svc['id'] ?>">
                                    <input type="text" name="name" value="<?= esc($svc['name']) ?>" maxlength="150" aria-label="Service name" style="flex:1;min-width:140px">
                                    <label class="a-check" style="white-space:nowrap"><input type="checkbox" name="is_active" value="1"<?= (int) $svc['is_active'] === 1 ? ' checked' : '' ?>> On</label>
                                    <button class="a-btn small" type="submit">Save</button>
                                </form>
                            </td>
                            <td><span class="badge <?= (int) $svc['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int) $svc['is_active'] === 1 ? 'enabled' : 'disabled' ?></span></td>
                            <td>
                                <div class="row-actions">
                                    <form method="post" style="display:inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="pay_action" value="service_toggle">
                                        <input type="hidden" name="service_id" value="<?= (int) $svc['id'] ?>">
                                        <button class="a-btn small" type="submit"><?= (int) $svc['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
                                    </form>
                                    <form method="post" style="display:inline" data-confirm="Delete this service?">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="pay_action" value="service_delete">
                                        <input type="hidden" name="service_id" value="<?= (int) $svc['id'] ?>">
                                        <button class="a-btn small danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================= PAYPAL / STRIPE ============================ -->
<?php foreach (array('paypal', 'stripe', 'invoice') as $gatewayKey): $gw = $gateways[$gatewayKey]; ?>
<div class="a-tabpanel<?= $tab === $gatewayKey ? ' active' : '' ?>" data-panel="<?= esc($gatewayKey) ?>">
    <form method="post" class="a-card">
        <?= csrfField() ?>
        <input type="hidden" name="pay_action" value="gateway_save">
        <input type="hidden" name="gateway" value="<?= esc($gatewayKey) ?>">

        <div class="a-toolbar" style="align-items:flex-start">
            <div>
                <h3 style="margin-bottom:4px"><?= esc($gw['label']) ?></h3>
                <p class="text-muted" style="font-size:.84rem;margin:0">
                    <?php if ($gw['enabled']): ?>
                    <span class="badge active">Offered to customers</span>
                    <?php else: ?>
                    <span class="badge inactive">Hidden on Pay Online</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <label class="a-check" style="margin-top:18px">
            <input type="checkbox" name="enabled" value="1"<?= $gw['enabled'] ? ' checked' : '' ?>>
            Enable <?= esc($gw['label']) ?> as a payment method
        </label>

        <p class="hint" style="margin-top:10px"><?= esc($gw['docs']) ?></p>

        <?php if ($gatewayKey === 'invoice'): ?>
        <div class="a-field" style="margin-top:16px">
            <label for="invoiceNote">Note shown to the customer for this option</label>
            <textarea id="invoiceNote" name="invoice_note" maxlength="2000" style="min-height:90px"><?= esc(getSetting('invoice_note', 'Request a secure payment link by email. We send an invoice you can pay online in a couple of taps.')) ?></textarea>
            <div class="hint">Flow: the visitor submits the form → the request is saved → a secure link is emailed automatically.</div>
        </div>
        <?php else: ?>
        <div class="a-field" style="margin-top:16px">
            <label for="sdk-<?= esc($gatewayKey) ?>">Your <?= esc($gw['label']) ?> SDK / integration code</label>
            <textarea id="sdk-<?= esc($gatewayKey) ?>" name="sdk_code" spellcheck="false"
                      style="min-height:220px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.8rem;direction:ltr"
                      placeholder="<script src=&quot;https://www.paypal.com/sdk/js?client-id=YOUR_CLIENT_ID&currency=USD&quot;></script>&#10;<div id=&quot;paypal-button-container&quot;></div>"><?= esc($gw['code']) ?></textarea>
            <div class="hint">
                Saved in the database and rendered only inside the Pay Online payment panel — never inside a .js/.css asset, so your keys are not exposed in static files.
                Leave empty to let the site use the built-in <?= esc($gw['label']) ?> integration configured in
                <a href="settings.php#payments" style="color:var(--violet-soft)">Settings → Payments</a>
                <?= $gw['keys'] ? '(credentials are already saved).' : '(no credentials saved yet).' ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="a-toolbar" style="margin-top:16px">
            <button class="a-btn primary" type="submit"><?= icon('check', 15) ?> Save <?= esc($gw['label']) ?> settings</button>
            <a class="a-btn" href="<?= esc(url('pay-online')) ?>" target="_blank" rel="noopener">Preview Pay Online</a>
        </div>
    </form>
</div>
<?php endforeach; ?>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
