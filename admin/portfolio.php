<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'portfolio';
$adminTitle = 'Portfolio Manager';
$adminLibs  = array('sortable' => true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (validateCSRF()) {
        dbExec('DELETE FROM portfolio WHERE id = ?', array((int) $_POST['delete_id']));
        setFlash('ok', 'Case study deleted.');
    }
    header('Location: portfolio.php');
    exit;
}

$items = dbAll('SELECT * FROM portfolio ORDER BY display_order ASC, id DESC');

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-toolbar">
    <a class="a-btn primary" href="portfolio-edit.php"><?= icon('plus', 16) ?> New Case Study</a>
    <span class="spacer"></span>
    <span class="text-muted" id="sortNote"></span>
    <span class="text-muted">Drag <?= icon('menu', 14) ?> to reorder on the site</span>
    <?= csrfField() ?>
</div>

<ul class="sortable-list" data-sortable="portfolio">
    <?php foreach ($items as $item): ?>
    <li data-id="<?= (int) $item['id'] ?>">
        <span class="grip"><?= icon('menu', 18) ?></span>
        <?php if ($item['thumbnail'] !== ''): ?><img src="<?= asset($item['thumbnail']) ?>" alt=""><?php endif; ?>
        <span style="flex:1">
            <strong><?= esc($item['client_name']) ?></strong>
            <span class="td-sub" style="display:block"><?= esc($item['service_category']) ?> · /portfolio/<?= esc($item['slug']) ?></span>
        </span>
        <span class="badge <?= $item['is_active'] ? 'active' : 'inactive' ?>"><?= $item['is_active'] ? 'live' : 'hidden' ?></span>
        <a class="a-btn small" href="portfolio-edit.php?id=<?= (int) $item['id'] ?>">Edit</a>
        <form method="post" action="portfolio.php" style="display:inline" onsubmit="return confirm('Delete this case study?');">
            <?= csrfField() ?>
            <input type="hidden" name="delete_id" value="<?= (int) $item['id'] ?>">
            <button class="a-btn small danger" type="submit">Delete</button>
        </form>
    </li>
    <?php endforeach; ?>
    <?php if (!$items): ?><li style="cursor:default" class="text-muted">No case studies yet.</li><?php endif; ?>
</ul>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
