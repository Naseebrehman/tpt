<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Payment Management
 * ---------------------------------------------------------------------------
 *  Admin-controlled custom payment code. Paste the COMPLETE PayPal and Stripe
 *  frontend implementations (HTML, CSS, JavaScript, SDK script tags, buttons,
 *  forms, validation). Each code block is stored exactly as provided — never
 *  sanitised, escaped, rewritten or restructured — and the public Pay Online
 *  page renders it verbatim. Gateways are independently enabled/disabled.
 *  No gateway API calls or secret keys exist anywhere in this project.
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

/** Raw payment code from the request — stored byte-for-byte, never rewritten. */
function paymentCodeFromRequest($field)
{
    return (isset($_POST[$field]) && is_string($_POST[$field])) ? $_POST[$field] : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* A paste larger than PHP's post_max_size arrives as an empty $_POST.
       Explain the cause instead of silently wiping the stored code. */
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        setFlash('err', 'The submitted payment code is larger than this server allows (PHP post_max_size). Ask your host to raise that limit, then try again — nothing was changed.');
        paymentServicesRedirect();
    }

    if (!validateCSRF()) {
        setFlash('err', 'Security token expired — refresh and try again.');
        paymentServicesRedirect();
    }

    $paymentAction = isset($_POST['payment_action']) ? trim($_POST['payment_action']) : '';

    /* -----------------------------------------------------------------------
       Custom Payment Code & Status Management (PayPal & Stripe)
       ----------------------------------------------------------------------- */
    if ($paymentAction === 'save_paypal' || $paymentAction === 'save_all_gateways') {
        $paypalEnabled = !empty($_POST['paypal_enabled']) ? '1' : '0';
        $paypalCode    = paymentCodeFromRequest('paypal_sdk_code');

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
            setFlash('ok', 'PayPal payment code saved — stored exactly as provided.');
            paymentServicesRedirect();
        }
    }

    if ($paymentAction === 'save_stripe' || $paymentAction === 'save_all_gateways') {
        $stripeEnabled = !empty($_POST['stripe_enabled']) ? '1' : '0';
        $stripeCode    = paymentCodeFromRequest('stripe_sdk_code');

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
            setFlash('ok', 'Stripe payment code saved — stored exactly as provided.');
            paymentServicesRedirect();
        }
    }

    if ($paymentAction === 'clear_paypal') {
        dbExec('DELETE FROM settings WHERE setting_key = ?', array('paypal_sdk_code'));
        settingsCache(true);
        setFlash('ok', 'PayPal payment code cleared.');
        paymentServicesRedirect();
    }

    if ($paymentAction === 'clear_stripe') {
        dbExec('DELETE FROM settings WHERE setting_key = ?', array('stripe_sdk_code'));
        settingsCache(true);
        setFlash('ok', 'Stripe payment code cleared.');
        paymentServicesRedirect();
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
            <h2>Payment Gateways &amp; Custom Code</h2>
            <p class="hint">Paste your complete PayPal and Stripe implementations — HTML, CSS, JavaScript, SDK script tags, buttons, forms and validation. Saved code is stored exactly as you provide it and rendered verbatim on the Pay Online page. This site never makes gateway API calls and holds no secret keys.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <span>PayPal: <span class="badge <?= $paypalEnabled ? 'active' : 'inactive' ?>"><?= $paypalEnabled ? 'Enabled' : 'Disabled' ?></span></span>
            <span>Stripe: <span class="badge <?= $stripeEnabled ? 'active' : 'inactive' ?>"><?= $stripeEnabled ? 'Enabled' : 'Disabled' ?></span></span>
        </div>
    </div>
</div>

<!-- ======================== PayPal Section ======================== -->
<div class="a-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <h3><?= icon('card', 18) ?> PayPal — Payment Code</h3>
        <span class="badge <?= $paypalEnabled ? 'active' : 'inactive' ?>"><?= $paypalEnabled ? 'Enabled' : 'Disabled' ?></span>
    </div>
    <p class="hint">Paste your <strong>complete</strong> self-contained PayPal page/component code: <code>&lt;style&gt;</code> blocks, HTML, <code>&lt;form&gt;</code>s, input fields, the PayPal SDK <code>&lt;script&gt;</code>, buttons, validation and any other frontend code. Everything is kept exactly as provided and executes once when the Pay Online page loads.</p>

    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="payment_action" value="save_paypal">

        <label class="a-switch">
            <input type="checkbox" name="paypal_enabled" value="1" <?= $paypalEnabled ? 'checked' : '' ?>>
            <span class="track" aria-hidden="true"></span>
            <span><strong>Enable / Disable</strong> — when enabled, your saved PayPal code is rendered on the Pay Online page.</span>
        </label>

        <div class="a-field">
            <label for="paypal_sdk_code">PayPal payment code (HTML / CSS / JavaScript)</label>
            <textarea id="paypal_sdk_code" name="paypal_sdk_code" class="mono a-code-editor" readonly spellcheck="false" autocapitalize="off" autocorrect="off" placeholder="<!-- Paste your complete PayPal implementation here -->
<style> ... your styles ... </style>
<div id=&quot;paypal-button-container&quot;></div>
<script src=&quot;https://www.paypal.com/sdk/js?client-id=YOUR_CLIENT_ID&amp;currency=USD&quot;></script>
<script>
  paypal.Buttons({ /* createOrder, onApprove, ... */ }).render(&quot;#paypal-button-container&quot;);
</script>"><?= esc($paypalSdkCode) ?></textarea>
            <div class="hint">Read-only until you click <strong>Edit Code</strong>. The code is saved and served byte-for-byte — script tags, styles and all — with no escaping or rewriting.</div>
        </div>

        <div class="a-toolbar">
            <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save Code</button>
            <button class="a-btn" type="button" data-edit-code="paypal_sdk_code"><?= icon('edit', 15) ?> Edit Code</button>
        </div>
    </form>

    <form method="post" data-confirm="Delete the saved PayPal payment code? The Pay Online page will stop rendering PayPal until new code is saved.">
        <?= csrfField() ?>
        <input type="hidden" name="payment_action" value="clear_paypal">
        <div class="a-toolbar" style="margin-top:12px;">
            <button class="a-btn danger" type="submit"><?= icon('trash', 15) ?> Clear / Delete Code</button>
        </div>
    </form>
</div>

<!-- ======================== Stripe Section ======================== -->
<div class="a-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <h3><?= icon('card', 18) ?> Stripe — Payment Code</h3>
        <span class="badge <?= $stripeEnabled ? 'active' : 'inactive' ?>"><?= $stripeEnabled ? 'Enabled' : 'Disabled' ?></span>
    </div>
    <p class="hint">Paste your <strong>complete</strong> self-contained Stripe page/component code: <code>&lt;style&gt;</code> blocks, HTML, <code>&lt;form&gt;</code>s, input fields, the Stripe SDK <code>&lt;script&gt;</code>, Elements, buttons, validation and any other frontend code. Everything is kept exactly as provided and executes once when the Pay Online page loads.</p>

    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="payment_action" value="save_stripe">

        <label class="a-switch">
            <input type="checkbox" name="stripe_enabled" value="1" <?= $stripeEnabled ? 'checked' : '' ?>>
            <span class="track" aria-hidden="true"></span>
            <span><strong>Enable / Disable</strong> — when enabled, your saved Stripe code is rendered on the Pay Online page.</span>
        </label>

        <div class="a-field">
            <label for="stripe_sdk_code">Stripe payment code (HTML / CSS / JavaScript)</label>
            <textarea id="stripe_sdk_code" name="stripe_sdk_code" class="mono a-code-editor" readonly spellcheck="false" autocapitalize="off" autocorrect="off" placeholder="<!-- Paste your complete Stripe implementation here -->
<style> ... your styles ... </style>
<div id=&quot;stripe-payment-container&quot;></div>
<script src=&quot;https://js.stripe.com/v3/&quot;></script>
<script>
  const stripe = Stripe(&quot;YOUR_PUBLISHABLE_KEY&quot;); /* Elements, forms, validation ... */
</script>"><?= esc($stripeSdkCode) ?></textarea>
            <div class="hint">Read-only until you click <strong>Edit Code</strong>. The code is saved and served byte-for-byte — script tags, styles and all — with no escaping or rewriting.</div>
        </div>

        <div class="a-toolbar">
            <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save Code</button>
            <button class="a-btn" type="button" data-edit-code="stripe_sdk_code"><?= icon('edit', 15) ?> Edit Code</button>
        </div>
    </form>

    <form method="post" data-confirm="Delete the saved Stripe payment code? The Pay Online page will stop rendering Stripe until new code is saved.">
        <?= csrfField() ?>
        <input type="hidden" name="payment_action" value="clear_stripe">
        <div class="a-toolbar" style="margin-top:12px;">
            <button class="a-btn danger" type="submit"><?= icon('trash', 15) ?> Clear / Delete Code</button>
        </div>
    </form>
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

<script>
/* Edit Code — unlock the saved-code editor so the admin can paste/modify. */
(function () {
    document.querySelectorAll('[data-edit-code]').forEach(function (button) {
        button.addEventListener('click', function () {
            var editor = document.getElementById(button.getAttribute('data-edit-code'));
            if (!editor) { return; }
            editor.removeAttribute('readonly');
            editor.focus();
            try { editor.setSelectionRange(editor.value.length, editor.value.length); } catch (err) {}
            button.textContent = 'Editing…';
        });
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
