<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'subscribers';
$adminTitle = 'Newsletter Subscribers';

$subscribers = dbAll('SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC');

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-toolbar">
    <span class="text-muted"><?= count($subscribers) ?> subscriber<?= count($subscribers) === 1 ? '' : 's' ?> · <?= count(array_filter($subscribers, function ($s) { return (int) $s['is_active'] === 1; })) ?> active</span>
    <span class="spacer"></span>
    <a class="a-btn small" href="export.php?type=subscribers"><?= icon('download', 15) ?> Export CSV</a>
</div>

<div class="a-table-wrap">
    <table class="a-table">
        <thead><tr><th>Email</th><th>Name</th><th>Subscribed</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
        <?php if (!$subscribers): ?>
            <tr><td colspan="5" class="text-muted">No subscribers yet — the signup form lives on the Resources page.</td></tr>
        <?php endif; ?>
        <?php foreach ($subscribers as $sub): ?>
        <tr>
            <td class="td-main"><?= esc($sub['email']) ?></td>
            <td><?= esc($sub['name'] !== '' ? $sub['name'] : '—') ?></td>
            <td class="td-sub"><?= esc(formatDate($sub['subscribed_at'], 'j M Y, H:i')) ?></td>
            <td><span class="badge <?= $sub['is_active'] ? 'active' : 'inactive' ?>"><?= $sub['is_active'] ? 'active' : 'unsubscribed' ?></span></td>
            <td>
                <div class="row-actions">
                    <form method="post" action="actions.php" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="entity" value="subscribers">
                        <input type="hidden" name="id" value="<?= (int) $sub['id'] ?>">
                        <button class="a-btn small" type="submit"><?= $sub['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
                    </form>
                    <form method="post" action="actions.php" style="display:inline" onsubmit="return confirm('Delete this subscriber?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="entity" value="subscribers">
                        <input type="hidden" name="id" value="<?= (int) $sub['id'] ?>">
                        <button class="a-btn small danger" type="submit">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
