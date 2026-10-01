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
 *
 *  Two dashboard values feed that code without the administrator ever editing
 *  it, and the service dropdown comes from the EXISTING Services system below
 *  (there is no second Services manager):
 *
 *      paypal_client_id   PayPal Client ID        → {{PAYPAL_CLIENT_ID}}
 *      paypal_secret      PayPal Secret           → server-side only (never
 *                                                   rendered to a browser)
 *      paypal_env         live / sandbox PayPal environment for the REST calls
 *      terms_url          Terms & Conditions URL  → {{TERMS_URL}} (shared)
 *      payment_services   Services               → {{SERVICES_OPTIONS}}
 *
 *  The PayPal Secret is used for ONE thing: creating and capturing/verifying
 *  orders on the server (core/PayPal.php + paypal-api.php). It is never echoed
 *  in this dashboard, never placed in HTML/JavaScript and never returned by any
 *  endpoint — the dashboard only ever shows whether one is stored.
 * ---------------------------------------------------------------------------
 */

require_once dirname(__DIR__) . '/includes/init.php';
require_once BASE_PATH . '/includes/payments.php';
require_once BASE_PATH . '/core/Schema.php';
require_once BASE_PATH . '/core/PaymentTemplates.php';
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

/** Upsert one settings row (the payment settings live in the shared table). */
function savePaymentSetting($key, $value)
{
    return dbExec(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        array($key, $value)
    );
}

/** Strip control characters only — nothing else touches a stored URL/id. */
function paymentCleanValue($value)
{
    return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $value));
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
       Shared payment settings — ONE Terms & Conditions URL for BOTH gateways
       ----------------------------------------------------------------------- */
    if ($paymentAction === 'save_shared') {
        $termsUrl = paymentCleanValue(isset($_POST['terms_url']) ? $_POST['terms_url'] : '');
        if ($termsUrl !== '' && !preg_match('#^(https?://|/)#i', $termsUrl)) {
            setFlash('err', 'Enter a full Terms & Conditions URL (https://…) or a site path starting with /.');
        } elseif (savePaymentSetting('terms_url', $termsUrl) < 0) {
            setFlash('err', 'Could not save the shared payment settings.');
        } else {
            settingsCache(true);
            setFlash('ok', 'Shared settings saved — the PayPal and Stripe forms now both use this Terms & Conditions URL.');
        }
        paymentServicesRedirect();
    }

    /* -----------------------------------------------------------------------
       Custom Payment Code & Status Management (PayPal & Stripe)
       ----------------------------------------------------------------------- */
    if ($paymentAction === 'save_paypal' || $paymentAction === 'save_all_gateways') {
        $paypalEnabled  = !empty($_POST['paypal_enabled']) ? '1' : '0';
        $paypalCode     = paymentCodeFromRequest('paypal_sdk_code');
        $paypalClientId = paymentCleanValue(isset($_POST['paypal_client_id']) ? $_POST['paypal_client_id'] : '');
        $paypalEnv      = (isset($_POST['paypal_env']) && $_POST['paypal_env'] === 'sandbox') ? 'sandbox' : 'live';
        /* The Secret is never sent back to the browser: an empty field keeps
           the stored value, the explicit button clears it. */
        $paypalSecretInput = isset($_POST['paypal_secret']) ? paymentCleanValue($_POST['paypal_secret']) : '';
        $clearPaypalSecret = !empty($_POST['paypal_secret_clear']) && $paypalSecretInput === '';

        /* Validate the Client ID before anything is written, so a typo can
           never overwrite the saved implementation. */
        if ($paypalClientId !== '' && !pieIsValidPayPalClientId($paypalClientId)) {
            setFlash('err', 'The PayPal Client ID may only contain letters, numbers, hyphens and underscores (at least 8 characters). Nothing was changed.');
            paymentServicesRedirect();
        }
        if ($paypalSecretInput !== '' && !pieIsValidPayPalSecret($paypalSecretInput)) {
            setFlash('err', 'That PayPal Secret does not look valid (at least 16 characters, letters/numbers/-/_/. only). Nothing was changed.');
            paymentServicesRedirect();
        }

        dbExec(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            array('paypal_enabled', $paypalEnabled)
        );
        dbExec(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            array('paypal_client_id', $paypalClientId)
        );
        dbExec(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            array('paypal_sdk_code', $paypalCode)
        );
        dbExec(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            array('paypal_env', $paypalEnv)
        );
        /* Write the Secret only when a new one was typed, or clear it when the
           administrator explicitly asked. The stored value is never displayed. */
        if ($paypalSecretInput !== '') {
            dbExec(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                array('paypal_secret', $paypalSecretInput)
            );
        } elseif ($clearPaypalSecret) {
            dbExec('DELETE FROM settings WHERE setting_key = ?', array('paypal_secret'));
        }
        settingsCache(true);
        if ($paymentAction === 'save_paypal') {
            setFlash('ok', 'PayPal settings saved — payment code stored exactly as provided'
                . ($paypalSecretInput !== '' ? ', and the Secret is stored server-side only.' : '.'));
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
$paypalClientId = piePayPalClientId();
$paypalEnv      = piePayPalEnv();
$paypalSecretSet = piePayPalSecretConfigured();
$paypalServerReady = piePayPalServerReady();
$termsUrl       = getSetting('terms_url', '');
$idConflicts    = piePaymentIdConflicts(array('paypal' => $paypalSdkCode, 'stripe' => $stripeSdkCode));
$unlinkedSelects = array_merge(
    array_map(function ($id) { return 'PayPal → ' . $id; }, piePaymentUnlinkedSelects($paypalSdkCode)),
    array_map(function ($id) { return 'Stripe → ' . $id; }, piePaymentUnlinkedSelects($stripeSdkCode))
);
$serverPostMax  = (string) ini_get('post_max_size');

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

<!-- ==================== Shared Payment Settings ==================== -->
<div class="a-card">
    <h3><?= icon('lock', 18) ?> Shared Payment Settings</h3>
    <p class="hint">These values are shared by <strong>both</strong> the PayPal and the Stripe form. There is only one setting of each — change it here and every payment form follows. Your saved payment code never has to be edited to use them.</p>

    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="payment_action" value="save_shared">

        <div class="a-field">
            <label for="terms_url">Terms &amp; Conditions URL <span style="color:var(--muted)">(shared by PayPal + Stripe)</span></label>
            <input id="terms_url" name="terms_url" type="text" value="<?= esc($termsUrl) ?>" placeholder="https://yourdomain.com/terms">
            <div class="hint">Used by both payment forms through the <code>{{TERMS_URL}}</code> placeholder. Leave empty to link this site's own Terms page (<?= esc(rtrim(SITE_URL, '/') . url('terms')) ?>).</div>
        </div>

        <div class="a-toolbar">
            <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save Shared Settings</button>
        </div>
    </form>
</div>

<!-- ======================== PayPal Section ======================== -->
<div class="a-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <h3><?= icon('card', 18) ?> PayPal — Payment Code</h3>
        <span class="badge <?= $paypalEnabled ? 'active' : 'inactive' ?>"><?= $paypalEnabled ? 'Enabled' : 'Disabled' ?></span>
    </div>
    <p class="hint">Paste your <strong>complete</strong> self-contained PayPal page/component code: <code>&lt;style&gt;</code> blocks, HTML, <code>&lt;form&gt;</code>s, input fields, the PayPal SDK <code>&lt;script&gt;</code>, buttons, validation and any other frontend code. Everything is kept exactly as provided and executes once when the Pay Online page loads.</p>

    <?php if ($paypalEnabled && $paypalClientId === ''): ?>
        <p class="hint" style="color:#fca5a5">PayPal is enabled but no Client ID is saved. The PayPal SDK cannot start without it — add it below.</p>
    <?php endif; ?>
    <?php if ($paypalEnabled && $paypalClientId !== '' && !$paypalSecretSet): ?>
        <p class="hint" style="color:#fcd34d">PayPal has no Secret saved, so payments are captured in the browser instead of being verified on the server. Add the PayPal Secret below to switch on server-side verification (recommended).</p>
    <?php endif; ?>

    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="payment_action" value="save_paypal">

        <label class="a-switch">
            <input type="checkbox" name="paypal_enabled" value="1" <?= $paypalEnabled ? 'checked' : '' ?>>
            <span class="track" aria-hidden="true"></span>
            <span><strong>Enable / Disable</strong> — when enabled, your saved PayPal code is rendered on the Pay Online page.</span>
        </label>

        <div class="a-field">
            <label for="paypal_client_id">PayPal Client ID <span style="color:var(--muted)">(public — used by the SDK in the browser)</span></label>
            <input id="paypal_client_id" name="paypal_client_id" type="text" value="<?= esc($paypalClientId) ?>" placeholder="AxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxX" autocomplete="off" spellcheck="false">
            <div class="hint">The PayPal SDK is loaded with <code>?client-id=</code> from this field. Use <code>{{PAYPAL_CLIENT_ID}}</code> (or <code>{{PAYPAL_SDK_URL}}</code>) inside your code and you never have to touch the code again to change the ID. Letters, numbers, hyphens and underscores only.</div>
        </div>

        <div class="a-field">
            <label for="paypal_secret">PayPal Secret <span style="color:var(--muted)">(server-side only — never sent to a browser)</span></label>
            <?php if ($paypalSecretSet): ?>
            <div class="a-toolbar" style="margin:0 0 8px">
                <span class="badge active"><?= icon('lock', 14) ?> A Secret is stored</span>
                <span class="badge <?= $paypalServerReady ? 'active' : 'inactive' ?>"><?= $paypalServerReady ? 'Server-side capture active' : 'Add a Client ID to activate' ?></span>
            </div>
            <?php endif; ?>
            <input id="paypal_secret" name="paypal_secret" type="password" value="" placeholder="<?= $paypalSecretSet ? 'Enter a new Secret to replace the stored one' : 'E... your PayPal REST API Secret' ?>" autocomplete="new-password" spellcheck="false">
            <div class="hint">
                Used only on the server: orders are created and captured/verified with PayPal’s REST API in <code>core/PayPal.php</code>, and the Secret is never printed, logged, escaped into the page or returned by any endpoint.
                <?php if ($paypalSecretSet): ?>Leave this field empty to keep the stored Secret.<?php endif; ?>
            </div>
            <?php if ($paypalSecretSet): ?>
            <label class="a-check" style="margin-top:8px"><input type="checkbox" name="paypal_secret_clear" value="1"> Remove the stored Secret (falls back to client-side capture in the browser)</label>
            <?php endif; ?>
        </div>

        <div class="a-field">
            <label for="paypal_env">PayPal environment</label>
            <select id="paypal_env" name="paypal_env">
                <option value="live"<?= $paypalEnv === 'live' ? ' selected' : '' ?>>Live (api-m.paypal.com)</option>
                <option value="sandbox"<?= $paypalEnv === 'sandbox' ? ' selected' : '' ?>>Sandbox (api-m.sandbox.paypal.com)</option>
            </select>
            <div class="hint">Where the server-side create/capture calls go. Use Sandbox with PayPal test credentials while you are testing.</div>
        </div>

        <div class="a-field">
            <label for="paypal_sdk_code">PayPal payment code (HTML / CSS / JavaScript)</label>
            <textarea id="paypal_sdk_code" name="paypal_sdk_code" class="mono a-code-editor" readonly spellcheck="false" autocapitalize="off" autocorrect="off" placeholder="<!-- Paste your complete PayPal implementation here -->
<style> ... your styles ... </style>
<div id=&quot;paypal-button-container&quot;></div>
<script src=&quot;https://www.paypal.com/sdk/js?client-id={{PAYPAL_CLIENT_ID}}&amp;currency=USD&quot;></script>
<script>
  paypal.Buttons({ /* createOrder, onApprove, ... */ }).render(&quot;#paypal-button-container&quot;);
</script>"><?= esc($paypalSdkCode) ?></textarea>
            <div class="hint">Read-only until you click <strong>Edit Code</strong>. The code is saved and served byte-for-byte — script tags, styles, newlines, quotes and all — with no escaping or rewriting and no character limit. This server accepts posts up to <strong><?= esc($serverPostMax !== '' ? $serverPostMax : 'the PHP default') ?></strong>.</div>
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
            <div class="hint">Read-only until you click <strong>Edit Code</strong>. The code is saved and served byte-for-byte — script tags, styles, newlines, quotes and all — with no escaping or rewriting and no character limit. This server accepts posts up to <strong><?= esc($serverPostMax !== '' ? $serverPostMax : 'the PHP default') ?></strong>.</div>
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

<?php if ($idConflicts): ?>
<!-- Duplicate IDs would make PayPal and Stripe target each other's elements. -->
<div class="a-card">
    <h3><?= icon('shield', 18) ?> Duplicate element IDs found</h3>
    <p class="hint">PayPal and Stripe can both run on the same Pay Online page, so every HTML <code>id</code> must be unique across the two code boxes. These ids appear more than once and should be prefixed (for example <code>paypal-</code> in the PayPal code and <code>stripe-</code> in the Stripe code):</p>
    <p class="hint" style="margin-top:10px"><?php foreach ($idConflicts as $conflictId): ?><code style="display:inline-block;margin:0 8px 6px 0"><?= esc($conflictId) ?></code><?php endforeach; ?></p>
    <p class="hint">Third-party containers that a SDK requires (such as <code>paypal-button-container</code> or the Stripe Elements mount point) keep their id — only the shared ids need renaming.</p>
</div>
<?php endif; ?>

<?php if ($unlinkedSelects): ?>
<!-- A service dropdown that is not connected to the Services system. -->
<div class="a-card">
    <h3><?= icon('filter', 18) ?> Service dropdown not connected</h3>
    <p class="hint">These dropdowns do not read the Services system, so changes in Admin &rarr; Payments &rarr; Services would not appear in them. Add <code>{{SERVICES_OPTIONS}}</code> inside the <code>&lt;select&gt;</code>, or add <code>data-tpt-services</code> to it, and the shared bridge fills it from the same list:</p>
    <p class="hint" style="margin-top:10px"><?php foreach ($unlinkedSelects as $unlinked): ?><code style="display:inline-block;margin:0 8px 6px 0"><?= esc($unlinked) ?></code><?php endforeach; ?></p>
</div>
<?php endif; ?>

<!-- ============= Server-verified PayPal payments (read-only) ============= -->
<?php
/* Audit trail of captures that the SERVER verified with PayPal. Rendered only
   when the existing payments table is present; nothing is editable here. */
$verifiedPayments = Schema::hasTable('payments')
    ? dbAll("SELECT id, name, email, service, amount_usd, reference, provider_ref, created_at
             FROM payments WHERE method = 'paypal' AND status = 'paid'
             ORDER BY id DESC LIMIT 10")
    : array();
?>
<div class="a-card">
    <h3><?= icon('shield', 18) ?> Server-verified PayPal payments</h3>
    <?php if (!$verifiedPayments): ?>
        <p class="hint">No server-verified PayPal payment has been recorded yet. Each capture that the server confirms with PayPal is listed here (amount, buyer, service and payment reference) in the existing <code>payments</code> table.</p>
    <?php else: ?>
        <div class="a-table-wrap">
            <table class="a-table">
                <thead>
                    <tr><th>When</th><th>Buyer</th><th>Service</th><th>Amount</th><th>PayPal reference</th></tr>
                </thead>
                <tbody>
                <?php foreach ($verifiedPayments as $verified): ?>
                    <tr>
                        <td><?= esc(date('Y-m-d H:i', strtotime((string) $verified['created_at']))) ?></td>
                        <td><?= esc($verified['name'] !== '' ? $verified['name'] : '—') ?><br><span style="color:var(--muted);font-size:.82rem"><?= esc($verified['email'] !== '' ? $verified['email'] : '—') ?></span></td>
                        <td><?= esc($verified['service'] !== '' ? $verified['service'] : '—') ?></td>
                        <td>$<?= esc(number_format((float) $verified['amount_usd'], 2)) ?> USD</td>
                        <td><code><?= esc($verified['provider_ref'] !== '' ? $verified['provider_ref'] : $verified['reference']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="hint" style="margin-top:12px">Shown for your records. Payment card details are never stored — only the PayPal reference, amount, buyer and service.</p>
    <?php endif; ?>
</div>

<!-- ======================== Form Services Section ======================== -->
<div class="a-card">
    <h2>Payment Form Services</h2>
    <p class="hint">Manage the services shown in the Pay Online service dropdown menu. This is the single Services system used by <strong>both</strong> the PayPal and the Stripe form — add, edit, reorder or hide a service here and both dropdowns follow automatically.</p>
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
        <p class="hint" style="margin-top:12px">Active services appear in this order inside both payment forms, through the <code>{{SERVICES_OPTIONS}}</code> placeholder.</p>
    <?php endif; ?>
</div>

<!-- ==================== Starter implementations ==================== -->
<div class="a-card">
    <h3><?= icon('edit', 18) ?> Ready-to-paste implementations</h3>
    <p class="hint">Complete PayPal and Stripe forms that are already wired to the settings above. Copy one into the matching code box and press <strong>Save Code</strong> — or use them as a reference while writing your own. They use unique ids (<code>paypal-*</code> / <code>stripe-*</code>) so both can run on the same page, read the service list from the Services system, link the shared Terms &amp; Conditions URL, and never request a shipping address.</p>

    <div class="a-field">
        <label for="paypal_starter_code">PayPal starter code</label>
        <textarea id="paypal_starter_code" class="mono a-code-editor" readonly spellcheck="false" style="min-height:280px"><?= esc(piePayPalStarterCode()) ?></textarea>
        <div class="a-toolbar" style="margin-top:10px;">
            <button class="a-btn" type="button" data-copy-code="paypal_starter_code"><?= icon('copy', 15) ?> Copy PayPal code</button>
        </div>
    </div>

    <div class="a-field">
        <label for="stripe_starter_code">Stripe starter code</label>
        <textarea id="stripe_starter_code" class="mono a-code-editor" readonly spellcheck="false" style="min-height:280px"><?= esc(pieStripeStarterCode()) ?></textarea>
        <div class="a-toolbar" style="margin-top:10px;">
            <button class="a-btn" type="button" data-copy-code="stripe_starter_code"><?= icon('copy', 15) ?> Copy Stripe code</button>
        </div>
    </div>
</div>

<div class="a-card">
    <h3>Integration points</h3>
    <p class="hint">Only these placeholders are replaced when the Pay Online page renders your code — the rest of your implementation is untouched.</p>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Placeholder</th><th>Replaced with</th></tr></thead>
            <tbody>
                <tr><td><code>{{PAYPAL_CLIENT_ID}}</code></td><td>The PayPal Client ID saved above (also applied to any <code>paypal.com/sdk/js</code> URL in your code).</td></tr>
                <tr><td><code>{{PAYPAL_SDK_URL}}</code></td><td>The full PayPal SDK URL built from that Client ID.</td></tr>
                <tr><td><code>{{TERMS_URL}}</code></td><td>The shared Terms &amp; Conditions URL — the same value in the PayPal and Stripe forms.</td></tr>
                <tr><td><code>{{SERVICES_OPTIONS}}</code></td><td>The <code>&lt;option&gt;</code> list from the Services system, in the order you set.</td></tr>
                <tr><td><code>{{SERVICES_JSON}}</code></td><td>The same list as JSON, for dropdowns built in JavaScript.</td></tr>
            </tbody>
        </table>
    </div>
    <p class="hint" style="margin-top:12px">Elements marked <code>data-tpt-services</code> (empty selects) and <code>data-tpt-terms</code> (links) are also filled from the same settings when the page loads, so a custom implementation can use them without the placeholders.</p>
</div>

<script>
/* Edit Code — unlock the saved-code editor so the admin can paste/modify.
   The same button locks it again. No character limit is applied anywhere. */
(function () {
    document.querySelectorAll('[data-edit-code]').forEach(function (button) {
        button.addEventListener('click', function () {
            var editor = document.getElementById(button.getAttribute('data-edit-code'));
            if (!editor) { return; }
            var editing = editor.getAttribute('data-editing') === '1';
            if (editing) {
                editor.readOnly = true;
                editor.setAttribute('readonly', 'readonly');
                editor.removeAttribute('data-editing');
                button.textContent = 'Edit Code';
                return;
            }
            editor.readOnly = false;
            editor.removeAttribute('readonly');
            editor.setAttribute('data-editing', '1');
            button.textContent = 'Lock Code';
            editor.focus();
            try { editor.setSelectionRange(editor.value.length, editor.value.length); } catch (err) {}
        });
    });

    /* Copy a starter implementation to the clipboard. */
    document.querySelectorAll('[data-copy-code]').forEach(function (button) {
        button.addEventListener('click', function () {
            var source = document.getElementById(button.getAttribute('data-copy-code'));
            if (!source) { return; }
            var text = typeof source.value === 'string' ? source.value : source.textContent;
            var original = button.textContent;
            var confirmCopy = function () {
                button.textContent = 'Copied';
                window.setTimeout(function () { button.textContent = original; }, 1600);
            };
            var fallbackCopy = function () {
                var wasReadOnly = source.readOnly;
                source.readOnly = false;
                source.removeAttribute('readonly');
                source.select();
                try { document.execCommand('copy'); } catch (err) {}
                source.readOnly = wasReadOnly;
                if (wasReadOnly) { source.setAttribute('readonly', 'readonly'); }
                confirmCopy();
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(confirmCopy, fallbackCopy);
            } else {
                fallbackCopy();
            }
        });
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
