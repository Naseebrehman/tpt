<?php
/** Admin → Payment Settings: the PayPal Client ID used by the Pay Online page
 * and the existing services list shown in the payment form.
 */
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();
require_once BASE_PATH . '/includes/payments.php';
require_once BASE_PATH . '/core/Schema.php';
Schema::ensure();

$adminPage = 'payments';
$adminTitle = 'Payment Settings';

function paymentServicesRedirect()
{
    header('Location: payments.php');
    exit;
}

function paymentSaveSetting($key, $value)
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired — refresh and try again.');
        paymentServicesRedirect();
    }

    if (trim((string) ($_POST['payment_action'] ?? '')) === 'save_paypal') {
        $clientId = paymentCleanValue($_POST['paypal_client_id'] ?? '');
        if ($clientId !== '' && !pieIsValidPayPalClientId($clientId)) {
            setFlash('err', 'The PayPal Client ID may contain letters, numbers, hyphens and underscores (at least 4 characters). Nothing was changed.');
            paymentServicesRedirect();
        }
        if (paymentSaveSetting('paypal_client_id', $clientId) < 0) {
            setFlash('err', 'The PayPal Client ID could not be saved. Please try again.');
            paymentServicesRedirect();
        }
        settingsCache(true);
        setFlash('ok', $clientId === ''
            ? 'PayPal Client ID removed. Online payments are disabled until a Client ID is saved.'
            : 'PayPal Client ID saved. The Pay Online page now uses it.');
        paymentServicesRedirect();
    }

    /* Keep the existing Admin services manager and its references. */
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
        $items = piePaymentServiceRows();
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

$services = piePaymentServiceRows();
$paypalClientId = piePayPalClientId();
$paypalReady = piePayPalClientIdConfigured();

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="a-card payment-admin-overview">
    <div>
        <p class="eyebrow">Payment settings</p>
        <h2>PayPal</h2>
        <p class="hint">The Pay Online page loads the PayPal JavaScript SDK with the Client ID saved below. Payments are completed on PayPal’s secure checkout — no secret or other credential is stored on this server.</p>
    </div>
    <div class="payment-admin-status">
        <span>PayPal <span class="badge <?= $paypalReady ? 'active' : 'inactive' ?>"><?= $paypalReady ? 'Configured' : 'Not configured' ?></span></span>
        <a class="a-btn" href="<?= esc(url('pay-online')) ?>" target="_blank" rel="noopener">Preview Pay Online</a>
    </div>
</div>

<section class="a-card payment-settings-card">
    <div class="payment-settings-title">
        <h3><?= icon('card', 18) ?> PayPal Client ID</h3>
    </div>
    <p class="hint">Create (or open) a REST app in the PayPal Developer Dashboard and copy its <strong>Client ID</strong>. The Client ID is public and is used by the browser SDK; a Client Secret is not needed.</p>
    <form method="post" class="payment-admin-form">
        <?= csrfField() ?>
        <input type="hidden" name="payment_action" value="save_paypal">
        <div class="a-field">
            <label for="paypal_client_id">PayPal Client ID</label>
            <input id="paypal_client_id" name="paypal_client_id" type="text" value="<?= esc($paypalClientId) ?>" placeholder="AXxxxxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="off" spellcheck="false">
            <div class="hint">Saved in the application settings. The Pay Online page picks up a new Client ID immediately.</div>
        </div>
        <div class="a-toolbar"><button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save</button></div>
    </form>
</section>

<section class="a-card">
    <div class="payment-settings-title">
        <div>
            <h3><?= icon('list', 18) ?> Payment form services</h3>
            <p class="hint">Manage the services shown in the Pay Online form.</p>
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
