<?php
/** Admin-managed services for the customer-owned payment form. */
require_once dirname(__DIR__) . '/includes/init.php';
require_once BASE_PATH . '/includes/payments.php';
require_once BASE_PATH . '/core/Schema.php';
Schema::ensure();
requireAdmin();
$adminPage = 'payments';
$adminTitle = 'Payment Services';

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
    $action = isset($_POST['service_action']) ? sanitize($_POST['service_action']) : '';
    $id = isset($_POST['service_id']) ? (int) $_POST['service_id'] : 0;
    if ($action === 'add' || $action === 'update') {
        $name = mb_substr(sanitize(isset($_POST['name']) ? $_POST['name'] : ''), 0, 150);
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($name === '') {
            setFlash('err', 'Enter a service name.');
        } elseif ($action === 'add') {
            $next = dbOne('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM payment_services');
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
        foreach ($items as $i => $item) if ((int) $item['id'] === $id) $index = $i;
        $next = $index + ((isset($_POST['direction']) && $_POST['direction'] === 'up') ? -1 : 1);
        if ($index >= 0 && isset($items[$next])) {
            dbExec('UPDATE payment_services SET sort_order = ? WHERE id = ?', array((int) $items[$next]['sort_order'], (int) $items[$index]['id']));
            dbExec('UPDATE payment_services SET sort_order = ? WHERE id = ?', array((int) $items[$index]['sort_order'], (int) $items[$next]['id']));
        }
    }
    paymentServicesRedirect();
}
$services = paymentServicesRows();
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>
<div class="a-card">
  <h2>Payment form services</h2>
  <p class="hint">Manage the options shown in the Pay Online service dropdown. PayPal and Stripe SDK code is customer-owned and is not configured or processed by this dashboard.</p>
  <form method="post" class="a-grid cols-2" style="align-items:end">
    <?= csrfField() ?><input type="hidden" name="service_action" value="add">
    <div class="a-field"><label for="newService">Service name</label><input id="newService" name="name" maxlength="150" required placeholder="Website Development"></div>
    <div><label class="a-check"><input type="checkbox" name="is_active" value="1" checked> Active in dropdown</label><button class="a-btn primary" type="submit">Add service</button></div>
  </form>
</div>
<div class="a-card">
  <h3>Services</h3>
  <?php if (!$services): ?><p class="hint">No services configured. Add one above.</p><?php else: ?>
  <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Order</th><th>Service</th><th>Status</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($services as $i => $service): ?><tr>
    <td><?= $i + 1 ?></td>
    <td><form method="post" style="display:flex;gap:8px;align-items:center"><?= csrfField() ?><input type="hidden" name="service_action" value="update"><input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>"><input name="name" maxlength="150" required value="<?= esc($service['name']) ?>"><label class="a-check"><input type="checkbox" name="is_active" value="1"<?= (int) $service['is_active'] ? ' checked' : '' ?>> Active</label><button class="a-btn small" type="submit">Save</button></form></td>
    <td><?= (int) $service['is_active'] ? 'Active' : 'Hidden' ?></td>
    <td><div class="row-actions">
      <?php foreach (array('up'=>'↑','down'=>'↓') as $direction=>$symbol): ?><form method="post"><?= csrfField() ?><input type="hidden" name="service_action" value="move"><input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>"><input type="hidden" name="direction" value="<?= $direction ?>"><button class="a-btn small" type="submit" aria-label="Move <?= $direction ?>"><?= $symbol ?></button></form><?php endforeach; ?>
      <form method="post" data-confirm="Delete this service?"><?= csrfField() ?><input type="hidden" name="service_action" value="delete"><input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>"><button class="a-btn small danger" type="submit">Delete</button></form>
    </div></td>
  </tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
