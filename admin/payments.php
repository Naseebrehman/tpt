<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Payment Management
 * ---------------------------------------------------------------------------
 *  Manage client-side payment SDK codes (PayPal & Stripe) and service options.
 *  Gateways can be independently enabled/disabled or configured with custom SDK code.
 * ---------------------------------------------------------------------------
 */

require_once dirname(__DIR__) . '/includes/init.php';
require_once BASE_PATH . '/includes/payments.php';
require_once BASE_PATH . '/core/Schema.php';
Schema::ensure();
requireAdmin();

$adminPage  = 'payments';
$adminTitle = 'Payment Management';

function paymentServicesRows()
{
    return Schema::hasTable('payment_services') ? dbAll('SELECT * FROM payment_services ORDER BY sort_order ASC, id ASC') : array();
}

function paymentServicesRedirect()
{
    header('Location: payments.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired — refresh and try again.');
        paymentServicesRedirect();
    }

    $paymentAction = isset($_POST['payment_action']) ? trim($_POST['payment_action']) : '';

    /* -----------------------------------------------------------------------
       Gateway SDK Code & Status Management (PayPal & Stripe)
       ----------------------------------------------------------------------- */
    if ($paymentAction === 'save_paypal' || $paymentAction === 'save_all_gateways') {
        $paypalEnabled = !empty($_POST['paypal_enabled']) ? '1' : '0';
        $paypalCode    = isset($_POST['paypal_sdk_code']) ? (string) $_POST['paypal_sdk_code'] : '';

        dbExec(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            array('paypal_enabled', $paypalEnabled)
        );
        dbExec(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            array('paypal_sdk_code', $paypalCode)
        );
        settingsCache(true);
        if ($paymentAction === 'save_paypal') {
            setFlash('ok', 'PayPal settings and SDK code saved successfully.');
            paymentServicesRedirect();
        }
    }

    if ($paymentAction === 'save_stripe' || $paymentAction === 'save_all_gateways') {
        $stripeEnabled = !empty($_POST['stripe_enabled']) ? '1' : '0';
        $stripeCode    = isset($_POST['stripe_sdk_code']) ? (string) $_POST['stripe_sdk_code'] : '';

        dbExec(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            array('stripe_enabled', $stripeEnabled)
        );
        dbExec(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            array('stripe_sdk_code', $stripeCode)
        );
        settingsCache(true);
        if ($paymentAction === 'save_stripe') {
            setFlash('ok', 'Stripe settings and SDK code saved successfully.');
            paymentServicesRedirect();
        }
    }

    if ($paymentAction === 'save_all_gateways') {
        setFlash('ok', 'All payment gateway settings and SDK codes saved successfully.');
        paymentServicesRedirect();
    }

    /* -----------------------------------------------------------------------
       Payment Services Management (Preserving Existing Dropdown Config)
       ----------------------------------------------------------------------- */
    $action = isset($_POST['service_action']) ? sanitize($_POST['service_action']) : '';
    $id     = isset($_POST['service_id']) ? (int) $_POST['service_id'] : 0;

    if ($action === 'add' || $action === 'update') {
        $name   = mb_substr(sanitize(isset($_POST['name']) ? $_POST['name'] : ''), 0, 150);
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($name === '') {
            setFlash('err', 'Enter a service name.');
        } elseif ($action === 'add') {
            $next  = dbOne('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM payment_services');
            $saved = dbInsert('INSERT INTO payment_services (name, sort_order, is_active) VALUES (?, ?, ?)', array($name, $next ? (int) $next['n'] : 1, $active));
            setFlash($saved > 0 ? 'ok' : 'err', $saved > 0 ? 'Service added.' : 'Could not add the service.');
        } else {
            $saved = dbExec('UPDATE payment_services SET name = ?, is_active = ? WHERE id = ?', array($name, $active, $id));
            setFlash($saved >= 0 ? 'ok' : 'err', $saved >= 0 ? 'Service updated.' : 'Could not update the service.');
        }
    } elseif ($action === 'toggle') {
        dbExec('UPDATE payment_services SET is_active = 1 - is_active WHERE id = ?', array($id));
        setFlash('ok', 'Service status updated.');
    } elseif ($action === 'delete') {
        dbExec('DELETE FROM payment_services WHERE id = ?', array($id));
        setFlash('ok', 'Service deleted.');
    } elseif ($action === 'move') {
        $items = paymentServicesRows();
        $index = -1;
        foreach ($items as $i => $item) {
            if ((int) $item['id'] === $id) {
                $index = $i;
            }
        }
        $next = $index + ((isset($_POST['direction']) && $_POST['direction'] === 'up') ? -1 : 1);
        if ($index >= 0 && isset($items[$next])) {
            dbExec('UPDATE payment_services SET sort_order = ? WHERE id = ?', array((int) $items[$next]['sort_order'], (int) $items[$index]['id']));
            dbExec('UPDATE payment_services SET sort_order = ? WHERE id = ?', array((int) $items[$index]['sort_order'], (int) $items[$next]['id']));
        }
    }
    paymentServicesRedirect();
}

$services       = paymentServicesRows();
$paypalEnabled  = (getSetting('paypal_enabled', '0') === '1');
$stripeEnabled  = (getSetting('stripe_enabled', '0') === '1');
$paypalSdkCode  = getSetting('paypal_sdk_code', '');
$stripeSdkCode  = getSetting('stripe_sdk_code', '');

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<!-- Payment Gateways Overview Status -->
<div class="a-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <h2>Payment Gateways &amp; SDK Management</h2>
            <p class="hint">Enable or disable payment methods for the public Pay Online page and supply your own client-side SDK integration code for each provider.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <span>PayPal: <span class="badge <?= $paypalEnabled ? 'active' : 'inactive' ?>"><?= $paypalEnabled ? 'Enabled' : 'Disabled' ?></span></span>
            <span>Stripe: <span class="badge <?= $stripeEnabled ? 'active' : 'inactive' ?>"><?= $stripeEnabled ? 'Enabled' : 'Disabled' ?></span></span>
        </div>
    </div>
</div>

<div class="a-grid cols-2" style="align-items:start;">
    <!-- ======================== PayPal Section ======================== -->
    <div class="a-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <h3><?= icon('card', 18) ?> PayPal Integration</h3>
            <span class="badge <?= $paypalEnabled ? 'active' : 'inactive' ?>"><?= $paypalEnabled ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <p class="hint">Add your client-side PayPal JavaScript SDK script and button rendering code. When enabled, this code will be embedded on the Pay Online page.</p>

        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="payment_action" value="save_paypal">

            <div class="a-field">
                <label class="a-check">
                    <input type="checkbox" name="paypal_enabled" value="1" <?= $paypalEnabled ? 'checked' : '' ?>>
                    <span><strong>Enable PayPal</strong> as an active payment method on Pay Online</span>
                </label>
            </div>

            <div class="a-field">
                <label for="paypal_sdk_code">PayPal SDK / Integration Code</label>
                <textarea id="paypal_sdk_code" name="paypal_sdk_code" class="mono" style="min-height:180px;font-size:0.83rem;line-height:1.5;" placeholder="<!-- Paste your PayPal SDK code here -->
<script src=&quot;https://www.paypal.com/sdk/js?client-id=YOUR_CLIENT_ID&amp;currency=USD&quot;></script>
<div id=&quot;paypal-button-container&quot;></div>
<script>
  paypal.Buttons({
    createOrder: function(data, actions) { ... },
    onApprove: function(data, actions) { ... }
  }).render('#paypal-button-container');
</script>"><?= esc($paypalSdkCode) ?></textarea>
                <div class="hint">Paste your own PayPal JS SDK script tag and initialization code. The dashboard stores and renders your code without any backend API call.</div>
            </div>

            <div class="a-toolbar">
                <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save PayPal Settings</button>
            </div>
        </form>
    </div>

    <!-- ======================== Stripe Section ======================== -->
    <div class="a-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <h3><?= icon('card', 18) ?> Stripe Integration</h3>
            <span class="badge <?= $stripeEnabled ? 'active' : 'inactive' ?>"><?= $stripeEnabled ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <p class="hint">Add your client-side Stripe.js SDK script and Elements rendering code. When enabled, this code will be embedded on the Pay Online page.</p>

        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="payment_action" value="save_stripe">

            <div class="a-field">
                <label class="a-check">
                    <input type="checkbox" name="stripe_enabled" value="1" <?= $stripeEnabled ? 'checked' : '' ?>>
                    <span><strong>Enable Stripe</strong> as an active payment method on Pay Online</span>
                </label>
            </div>

            <div class="a-field">
                <label for="stripe_sdk_code">Stripe SDK / Integration Code</label>
                <textarea id="stripe_sdk_code" name="stripe_sdk_code" class="mono" style="min-height:180px;font-size:0.83rem;line-height:1.5;" placeholder="<!-- Paste your Stripe SDK code here -->
<script src=&quot;https://js.stripe.com/v3/&quot;></script>
<div id=&quot;stripe-payment-container&quot;></div>
<script>
  const stripe = Stripe('YOUR_PUBLISHABLE_KEY');
  // Initialize Stripe Elements or Payment Request button here
</script>"><?= esc($stripeSdkCode) ?></textarea>
                <div class="hint">Paste your own Stripe.js SDK script tag and initialization code. The dashboard stores and renders your code without any backend API call.</div>
            </div>

            <div class="a-toolbar">
                <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save Stripe Settings</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================== Form Services Section ======================== -->
<div class="a-card">
    <h2>Payment Form Services</h2>
    <p class="hint">Manage the services shown in the Pay Online service dropdown menu.</p>
    <form method="post" class="a-grid cols-2" style="align-items:end">
        <?= csrfField() ?>
        <input type="hidden" name="service_action" value="add">
        <div class="a-field">
            <label for="newService">Service name</label>
            <input id="newService" name="name" maxlength="150" required placeholder="Website Development">
        </div>
        <div>
            <label class="a-check"><input type="checkbox" name="is_active" value="1" checked> Active in dropdown</label>
            <button class="a-btn primary" type="submit">Add service</button>
        </div>
    </form>
</div>

<div class="a-card">
    <h3>Services</h3>
    <?php if (!$services): ?>
        <p class="hint">No services configured. Add one above.</p>
    <?php else: ?>
        <div class="a-table-wrap">
            <table class="a-table">
                <thead>
                    <tr>
                        <th style="width:50px">Order</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($services as $i => $service): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <form method="post" style="display:flex;gap:8px;align-items:center">
                                <?= csrfField() ?>
                                <input type="hidden" name="service_action" value="update">
                                <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                                <input name="name" maxlength="150" required value="<?= esc($service['name']) ?>">
                                <label class="a-check">
                                    <input type="checkbox" name="is_active" value="1" <?= (int) $service['is_active'] ? ' checked' : '' ?>> Active
                                </label>
                                <button class="a-btn small" type="submit">Save</button>
                            </form>
                        </td>
                        <td>
                            <span class="badge <?= (int) $service['is_active'] ? 'active' : 'inactive' ?>">
                                <?= (int) $service['is_active'] ? 'Active' : 'Hidden' ?>
                            </span>
                        </td>
                        <td>
                            <div class="row-actions">
                                <?php foreach (array('up' => '↑', 'down' => '↓') as $direction => $symbol): ?>
                                    <form method="post" style="display:inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="service_action" value="move">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                                        <input type="hidden" name="direction" value="<?= $direction ?>">
                                        <button class="a-btn small" type="submit" aria-label="Move <?= $direction ?>"><?= $symbol ?></button>
                                    </form>
                                <?php endforeach; ?>
                                <form method="post" data-confirm="Delete this service?" style="display:inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="service_action" value="delete">
                                    <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
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

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
