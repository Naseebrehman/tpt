<?php
/** Admin → Payments: server-side PayPal / Stripe credentials, existing service
 * list, shared Terms URL and recent gateway-confirmed payment records.
 */
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();
require_once BASE_PATH . '/includes/payments.php';
require_once BASE_PATH . '/core/Schema.php';
require_once BASE_PATH . '/core/PaymentRecords.php';
Schema::ensure();

$adminPage = 'payments';
$adminTitle = 'Payment Management';

function paymentServicesRows()
{
    return piePaymentServiceRows();
}

function paymentServicesRedirect()
{
    header('Location: payments.php');
    exit;
}

function savePaymentSetting($key, $value)
{
    return dbExec(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        array((string) $key, (string) $value)
    );
}

function paymentCleanValue($value)
{
    return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $value));
}

function paymentSaveFailure($message)
{
    setFlash('err', $message);
    paymentServicesRedirect();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!validateCSRF()) {
        paymentSaveFailure('Security token expired — refresh and try again.');
    }
    $paymentAction = trim((string) ($_POST['payment_action'] ?? ''));

    if ($paymentAction === 'save_shared') {
        $termsUrl = paymentCleanValue($_POST['terms_url'] ?? '');
        if (!pieIsValidTermsUrl($termsUrl)) {
            paymentSaveFailure('Enter a valid HTTP(S) Terms & Conditions URL or a local site path starting with one /.');
        }
        if (savePaymentSetting('terms_url', $termsUrl) < 0) {
            paymentSaveFailure('Could not save the shared payment settings.');
        }
        settingsCache(true);
        setFlash('ok', 'Shared Terms & Conditions settings saved.');
        paymentServicesRedirect();
    }

    if ($paymentAction === 'save_paypal') {
        $paypalEnabled = !empty($_POST['paypal_enabled']) ? '1' : '0';
        $clientId = paymentCleanValue($_POST['paypal_client_id'] ?? '');
        $environment = (string) ($_POST['paypal_env'] ?? 'live') === 'sandbox' ? 'sandbox' : 'live';
        $secretInput = paymentCleanValue($_POST['paypal_secret'] ?? '');
        $clearSecret = !empty($_POST['paypal_secret_clear']) && $secretInput === '';

        if ($clientId !== '' && !pieIsValidPayPalClientId($clientId)) {
            paymentSaveFailure('The PayPal Client ID may contain letters, numbers, hyphens and underscores (at least 8 characters). Nothing was changed.');
        }
        if ($secretInput !== '' && !pieIsValidPayPalSecret($secretInput)) {
            paymentSaveFailure('That PayPal Secret does not look valid. Nothing was changed.');
        }
        if ($secretInput !== '' && !function_exists('openssl_encrypt')) {
            paymentSaveFailure('This server cannot encrypt payment credentials. Enable the PHP OpenSSL extension, then try again.');
        }

        $ok = savePaymentSetting('paypal_enabled', $paypalEnabled) >= 0
            && savePaymentSetting('paypal_client_id', $clientId) >= 0
            && savePaymentSetting('paypal_env', $environment) >= 0;
        if ($ok && $secretInput !== '') { $ok = pieSavePaymentCredential('paypal_secret', $secretInput) >= 0; }
        if ($ok && $clearSecret) { $ok = dbExec('DELETE FROM settings WHERE setting_key = ?', array('paypal_secret')) >= 0; }
        if (!$ok) { paymentSaveFailure('PayPal settings could not be saved. Please try again.'); }
        settingsCache(true);
        setFlash('ok', 'PayPal settings saved. The Client ID and Secret are used only by the PHP server.');
        paymentServicesRedirect();
    }

    if ($paymentAction === 'save_stripe') {
        $stripeEnabled = !empty($_POST['stripe_enabled']) ? '1' : '0';
        $secretInput = paymentCleanValue($_POST['stripe_secret_key'] ?? '');
        $clearSecret = !empty($_POST['stripe_secret_clear']) && $secretInput === '';
        $webhookInput = paymentCleanValue($_POST['stripe_webhook_secret'] ?? '');
        $clearWebhook = !empty($_POST['stripe_webhook_secret_clear']) && $webhookInput === '';

        if ($secretInput !== '' && !pieIsValidStripeSecret($secretInput)) {
            paymentSaveFailure('That Stripe Secret Key does not look valid (use an sk_live_, sk_test_, rk_live_ or rk_test_ key). Nothing was changed.');
        }
        if ($webhookInput !== '' && !pieIsValidStripeWebhookSecret($webhookInput)) {
            paymentSaveFailure('That Stripe Webhook Secret does not look valid (it should start with whsec_). Nothing was changed.');
        }
        if (($secretInput !== '' || $webhookInput !== '') && !function_exists('openssl_encrypt')) {
            paymentSaveFailure('This server cannot encrypt payment credentials. Enable the PHP OpenSSL extension, then try again.');
        }

        $ok = savePaymentSetting('stripe_enabled', $stripeEnabled) >= 0;
        if ($ok && $secretInput !== '') { $ok = pieSavePaymentCredential('stripe_secret_key', $secretInput) >= 0; }
        if ($ok && $clearSecret) { $ok = dbExec('DELETE FROM settings WHERE setting_key = ?', array('stripe_secret_key')) >= 0; }
        if ($ok && $webhookInput !== '') { $ok = pieSavePaymentCredential('stripe_webhook_secret', $webhookInput) >= 0; }
        if ($ok && $clearWebhook) { $ok = dbExec('DELETE FROM settings WHERE setting_key = ?', array('stripe_webhook_secret')) >= 0; }
        if (!$ok) { paymentSaveFailure('Stripe settings could not be saved. Please try again.'); }
        settingsCache(true);
        setFlash('ok', 'Stripe settings saved. The Secret Key and optional Webhook Secret are encrypted and used only by PHP.');
        paymentServicesRedirect();
    }

    /* Keep the existing Admin → Payments Services manager and its references. */
    $serviceAction = sanitize($_POST['service_action'] ?? '');
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    if ($serviceAction === 'add' || $serviceAction === 'update') {
        $name = mb_substr(sanitize($_POST['name'] ?? ''), 0, 150);
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($name === '') {
            setFlash('err', 'Enter a service name.');
        } elseif ($serviceAction === 'add') {
            $next = dbOne('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM payment_services');
            $saved = dbInsert(
                'INSERT INTO payment_services (name, sort_order, is_active) VALUES (?, ?, ?)',
                array($name, $next ? (int) $next['n'] : 1, $active)
            );
            setFlash($saved > 0 ? 'ok' : 'err', $saved > 0 ? 'Service added.' : 'Could not add the service.');
        } else {
            $saved = dbExec('UPDATE payment_services SET name = ?, is_active = ? WHERE id = ?', array($name, $active, $serviceId));
            setFlash($saved >= 0 ? 'ok' : 'err', $saved >= 0 ? 'Service updated.' : 'Could not update the service.');
        }
    } elseif ($serviceAction === 'toggle') {
        dbExec('UPDATE payment_services SET is_active = 1 - is_active WHERE id = ?', array($serviceId));
        setFlash('ok', 'Service status updated.');
    } elseif ($serviceAction === 'delete') {
        dbExec('DELETE FROM payment_services WHERE id = ?', array($serviceId));
        setFlash('ok', 'Service deleted.');
    } elseif ($serviceAction === 'move') {
        $items = paymentServicesRows();
        $index = -1;
        foreach ($items as $i => $item) {
            if ((int) $item['id'] === $serviceId) { $index = $i; break; }
        }
        $next = $index + (((string) ($_POST['direction'] ?? '') === 'up') ? -1 : 1);
        if ($index >= 0 && isset($items[$next])) {
            dbExec('UPDATE payment_services SET sort_order = ? WHERE id = ?', array((int) $items[$next]['sort_order'], (int) $items[$index]['id']));
            dbExec('UPDATE payment_services SET sort_order = ? WHERE id = ?', array((int) $items[$index]['sort_order'], (int) $items[$next]['id']));
        }
    } else {
        setFlash('err', 'Payment settings were not changed.');
    }
    paymentServicesRedirect();
}

$services = paymentServicesRows();
$paypalEnabled = pieIsPayPalEnabled();
$stripeEnabled = pieIsStripeEnabled();
$paypalClientId = piePayPalClientId();
$paypalEnv = piePayPalEnv();
$paypalSecretSet = piePayPalSecretConfigured();
$paypalReady = piePayPalServerReady();
$stripeSecretSet = pieStripeSecretConfigured();
$stripeWebhookSet = pieStripeWebhookSecretConfigured();
$stripeReady = pieStripeServerReady();
$stripeMode = pieStripeKeyMode();
$stripeWebhookUrl = rtrim(SITE_URL, '/') . url('stripe-webhook');
$termsUrl = getSetting('terms_url', '');
$recordsReady = piePaymentRecordsReady();
$recentRecords = $recordsReady ? piePaymentRecordList(piePaymentRecordFilters(array()), 10, 0) : array();

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="a-card payment-admin-overview">
    <div>
        <p class="eyebrow">Payment operations</p>
        <h2>Server-side payment gateways</h2>
        <p class="hint">PayPal and Stripe use separate server-to-server checkout flows. Payment credentials are encrypted in the database and are never rendered to the customer-facing page. A payment is recorded only after the provider confirms it.</p>
    </div>
    <div class="payment-admin-status">
        <span>PayPal <span class="badge <?= $paypalReady ? 'active' : 'inactive' ?>"><?= $paypalReady ? 'Configured' : 'Needs credentials' ?></span></span>
        <span>Stripe <span class="badge <?= $stripeReady ? 'active' : 'inactive' ?>"><?= $stripeReady ? 'Configured' : 'Needs credentials' ?></span></span>
        <a class="a-btn primary" href="payment-records.php"><?= icon('chart', 15) ?> All payment records</a>
    </div>
</div>

<div class="a-grid cols-2 payment-settings-grid">
    <section class="a-card payment-settings-card">
        <div class="payment-settings-title">
            <h3><?= icon('card', 18) ?> PayPal</h3>
            <span class="badge <?= $paypalReady && $paypalEnabled ? 'active' : 'inactive' ?>"><?= $paypalReady && $paypalEnabled ? 'Available to customers' : ($paypalEnabled ? 'Missing credentials' : 'Disabled') ?></span>
        </div>
        <p class="hint">The PHP backend obtains an OAuth token, creates an order, redirects the customer to PayPal, then captures and verifies the returned order.</p>
        <form method="post" class="payment-admin-form">
            <?= csrfField() ?>
            <input type="hidden" name="payment_action" value="save_paypal">
            <label class="a-switch">
                <input type="checkbox" name="paypal_enabled" value="1" <?= $paypalEnabled ? 'checked' : '' ?>>
                <span class="track" aria-hidden="true"></span>
                <span><strong>Enable PayPal</strong><small>Show PayPal checkout when the server credentials are complete.</small></span>
            </label>
            <div class="a-field">
                <label for="paypal_client_id">PayPal Client ID</label>
                <input id="paypal_client_id" name="paypal_client_id" type="text" value="<?= esc($paypalClientId) ?>" placeholder="PayPal REST application Client ID" autocomplete="off" spellcheck="false">
                <div class="hint">Used by PHP with the Secret to obtain a PayPal OAuth access token. It is not used in browser code.</div>
            </div>
            <div class="a-field">
                <label for="paypal_secret">PayPal Secret</label>
                <?php if ($paypalSecretSet): ?><span class="badge active"><?= icon('lock', 14) ?> Encrypted Secret is stored</span><?php endif; ?>
                <input id="paypal_secret" name="paypal_secret" type="password" value="" placeholder="<?= $paypalSecretSet ? 'Enter a new Secret to replace the stored one' : 'PayPal REST API Secret' ?>" autocomplete="new-password" spellcheck="false">
                <div class="hint">Encrypted at rest with AES-256-GCM. The value is never read into HTML or returned to a browser. Leave blank to keep the current Secret.</div>
                <?php if ($paypalSecretSet): ?><label class="a-check payment-clear-secret"><input type="checkbox" name="paypal_secret_clear" value="1"> Remove the stored Secret</label><?php endif; ?>
            </div>
            <div class="a-field">
                <label for="paypal_env">Environment</label>
                <select id="paypal_env" name="paypal_env">
                    <option value="sandbox"<?= $paypalEnv === 'sandbox' ? ' selected' : '' ?>>Sandbox</option>
                    <option value="live"<?= $paypalEnv === 'live' ? ' selected' : '' ?>>Live</option>
                </select>
                <div class="hint">Use Sandbox with PayPal sandbox app credentials when testing.</div>
            </div>
            <div class="a-toolbar"><button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save PayPal settings</button></div>
        </form>
    </section>

    <section class="a-card payment-settings-card">
        <div class="payment-settings-title">
            <h3><?= icon('card', 18) ?> Stripe</h3>
            <span class="badge <?= $stripeReady && $stripeEnabled ? 'active' : 'inactive' ?>"><?= $stripeReady && $stripeEnabled ? 'Available to customers' : ($stripeEnabled ? 'Missing credentials' : 'Disabled') ?></span>
        </div>
        <p class="hint">Stripe Checkout is hosted by Stripe. PHP creates the Checkout Session and verifies the returned Session and PaymentIntent. A publishable key is not needed.</p>
        <form method="post" class="payment-admin-form">
            <?= csrfField() ?>
            <input type="hidden" name="payment_action" value="save_stripe">
            <label class="a-switch">
                <input type="checkbox" name="stripe_enabled" value="1" <?= $stripeEnabled ? 'checked' : '' ?>>
                <span class="track" aria-hidden="true"></span>
                <span><strong>Enable Stripe</strong><small>Show hosted card checkout when a Secret Key is stored.</small></span>
            </label>
            <div class="a-field">
                <label for="stripe_secret_key">Stripe Secret Key</label>
                <?php if ($stripeSecretSet): ?>
                <div class="a-toolbar payment-secret-status">
                    <span class="badge active"><?= icon('lock', 14) ?> Encrypted Secret is stored</span>
                    <span class="badge <?= $stripeMode === 'test' ? 'inactive' : 'active' ?>"><?= $stripeMode === 'test' ? 'Test mode' : 'Live mode' ?></span>
                </div>
                <?php endif; ?>
                <input id="stripe_secret_key" name="stripe_secret_key" type="password" value="" placeholder="<?= $stripeSecretSet ? 'Enter a new Secret Key to replace the stored one' : 'Stripe Secret Key (sk_test_… or sk_live_…) ' ?>" autocomplete="new-password" spellcheck="false">
                <div class="hint">Encrypted at rest with AES-256-GCM and used only in PHP API requests. No Stripe.js, publishable key or client-side payment processing is used.</div>
                <?php if ($stripeSecretSet): ?><label class="a-check payment-clear-secret"><input type="checkbox" name="stripe_secret_clear" value="1"> Remove the stored Secret Key</label><?php endif; ?>
            </div>
            <div class="a-field">
                <label for="stripe_webhook_secret">Stripe Webhook Secret <span class="optional">Optional</span></label>
                <?php if ($stripeWebhookSet): ?><span class="badge active"><?= icon('lock', 14) ?> Encrypted Webhook Secret is stored</span><?php endif; ?>
                <input id="stripe_webhook_secret" name="stripe_webhook_secret" type="password" value="" placeholder="<?= $stripeWebhookSet ? 'Enter a new Webhook Secret to replace the stored one' : 'Webhook signing secret (whsec_…) ' ?>" autocomplete="new-password" spellcheck="false">
                <div class="hint">Optional webhook fallback for recording confirmed payments if the customer closes the return page. Register <code><?= esc($stripeWebhookUrl) ?></code> in Stripe and subscribe to <code>checkout.session.completed</code>, <code>checkout.session.async_payment_succeeded</code> and <code>payment_intent.succeeded</code>.</div>
                <?php if ($stripeWebhookSet): ?><label class="a-check payment-clear-secret"><input type="checkbox" name="stripe_webhook_secret_clear" value="1"> Remove the stored Webhook Secret</label><?php endif; ?>
            </div>
            <div class="a-toolbar"><button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save Stripe settings</button></div>
        </form>
    </section>
</div>

<section class="a-card">
    <div class="payment-settings-title">
        <div>
            <h3><?= icon('lock', 18) ?> Shared payment settings</h3>
            <p class="hint">One Terms &amp; Conditions URL is shared by PayPal and Stripe.</p>
        </div>
    </div>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="payment_action" value="save_shared">
        <div class="a-field">
            <label for="terms_url">Terms &amp; Conditions URL</label>
            <input id="terms_url" name="terms_url" type="text" value="<?= esc($termsUrl) ?>" placeholder="https://yourdomain.com/terms">
            <div class="hint">Leave empty to use this site’s Terms page (<?= esc(rtrim(SITE_URL, '/') . url('terms')) ?>).</div>
        </div>
        <div class="a-toolbar"><button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save shared settings</button></div>
    </form>
</section>

<section class="a-card">
    <div class="payment-settings-title">
        <div>
            <h3><?= icon('chart', 18) ?> Recent confirmed payments</h3>
            <p class="hint">Only successful transactions verified by the gateway are shown here.</p>
        </div>
        <a class="a-btn" href="payment-records.php">View all records <?= icon('arrow-r', 15) ?></a>
    </div>
    <?php if (!$recordsReady): ?>
        <p class="hint" style="color:#fca5a5">The payment records table is not available. Run <code>php bin/cli.php migrate</code> before accepting payments.</p>
    <?php elseif (!$recentRecords): ?>
        <p class="hint">No confirmed payments have been recorded yet. Completed PayPal and Stripe payments will appear here automatically.</p>
    <?php else: ?>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>When</th><th>Gateway</th><th>Customer</th><th>Service</th><th>Amount</th><th>Reference</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentRecords as $record): ?>
            <tr>
                <td><?= esc(formatDate($record['created_at'], 'j M Y, H:i')) ?></td>
                <td><span class="badge <?= $record['provider'] === 'paypal' ? 'active' : 'inactive' ?>"><?= esc(piePaymentProviderLabel($record['provider'])) ?></span></td>
                <td><?= esc($record['payer_name'] !== '' ? $record['payer_name'] : '—') ?><div class="td-sub"><?= esc($record['payer_email'] ?? '') ?></div><?php if (($record['payer_phone'] ?? '') !== ''): ?><div class="td-sub"><?= esc($record['payer_phone']) ?></div><?php endif; ?></td>
                <td><?= esc($record['service'] !== '' ? $record['service'] : '—') ?></td>
                <td class="mono">$<?= esc(number_format((float) $record['amount'], 2)) ?> <?= esc(strtoupper((string) $record['currency'])) ?></td>
                <td class="mono td-sub"><?= esc(mb_strimwidth((string) $record['provider_transaction_id'], 0, 26, '…')) ?></td>
                <td><span class="badge active"><?= esc(ucfirst((string) $record['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="a-card">
    <div class="payment-settings-title">
        <div>
            <h3><?= icon('list', 18) ?> Payment form services</h3>
            <p class="hint">Manage the services shown in the Pay Online form. This is the existing Services system shared by both gateways.</p>
        </div>
    </div>
    <form method="post" class="a-grid cols-2 payment-service-add" style="align-items:end">
        <?= csrfField() ?>
        <input type="hidden" name="service_action" value="add">
        <div class="a-field">
            <label for="newService">Service name</label>
            <input id="newService" name="name" maxlength="150" required placeholder="Website Development">
        </div>
        <div class="payment-service-add-action">
            <label class="a-check"><input type="checkbox" name="is_active" value="1" checked> Active in payment form</label>
            <button class="a-btn primary" type="submit">Add service</button>
        </div>
    </form>
</section>

<section class="a-card">
    <h3>Services</h3>
    <?php if (!$services): ?>
        <p class="hint">No services configured. Add one above.</p>
    <?php else: ?>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th style="width:50px">Order</th><th>Service</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($services as $i => $service): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td>
                    <form method="post" class="payment-service-edit">
                        <?= csrfField() ?>
                        <input type="hidden" name="service_action" value="update">
                        <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                        <input name="name" maxlength="150" required value="<?= esc($service['name']) ?>">
                        <label class="a-check"><input type="checkbox" name="is_active" value="1" <?= (int) $service['is_active'] ? 'checked' : '' ?>> Active</label>
                        <button class="a-btn small" type="submit">Save</button>
                    </form>
                </td>
                <td><span class="badge <?= (int) $service['is_active'] ? 'active' : 'inactive' ?>"><?= (int) $service['is_active'] ? 'Active' : 'Hidden' ?></span></td>
                <td>
                    <div class="row-actions">
                        <?php foreach (array('up' => '↑', 'down' => '↓') as $direction => $symbol): ?>
                        <form method="post" class="payment-service-action">
                            <?= csrfField() ?>
                            <input type="hidden" name="service_action" value="move">
                            <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                            <input type="hidden" name="direction" value="<?= $direction ?>">
                            <button class="a-btn small" type="submit" aria-label="Move <?= $direction ?>"><?= $symbol ?></button>
                        </form>
                        <?php endforeach; ?>
                        <form method="post" class="payment-service-action" data-confirm="Delete this service?">
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
</section>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
