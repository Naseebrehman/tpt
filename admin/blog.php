<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'blog';
$adminTitle = 'Blog Manager';

$posts = dbAll(
    "SELECT p.*, c.name AS category_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id
     ORDER BY p.created_at DESC"
);

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-toolbar">
    <a class="a-btn primary" href="blog-edit.php"><?= icon('plus', 16) ?> New Post</a>
    <span class="spacer"></span>
    <span class="text-muted"><?= count($posts) ?> post<?= count($posts) === 1 ? '' : 's' ?></span>
</div>

<div class="a-table-wrap">
    <table class="a-table">
        <thead><tr><th>Title</th><th>Category</th><th>Date</th><th>Status</th><th>Views</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
        <?php if (!$posts): ?>
            <tr><td colspan="6" class="text-muted">No posts yet — write your first one.</td></tr>
        <?php endif; ?>
        <?php foreach ($posts as $post): ?>
        <tr>
            <td class="td-main"><?= esc($post['title']) ?><div class="td-sub mono">/<?= esc($post['slug']) ?></div></td>
            <td><?= esc($post['category_name'] !== null ? $post['category_name'] : '—') ?></td>
            <td class="td-sub"><?= esc(formatDate($post['published_at'] !== null ? $post['published_at'] : $post['created_at'], 'j M Y')) ?></td>
            <td><span class="badge <?= esc($post['status']) ?>"><?= esc($post['status']) ?></span></td>
            <td class="mono"><?= number_format((int) $post['views']) ?></td>
            <td>
                <div class="row-actions">
                    <a class="a-btn small" href="<?= url('blog/' . $post['slug']) ?>" target="_blank" rel="noopener">View</a>
                    <?php if ($post['status'] === 'published'): ?>
                        <a class="a-btn small" href="subscriber-notify.php?type=blog&id=<?= (int) $post['id'] ?>" title="Send email notification to subscribers"><?= icon('send', 13) ?> Notify</a>
                    <?php endif; ?>
                    <a class="a-btn small" href="blog-edit.php?id=<?= (int) $post['id'] ?>">Edit</a>
                    <form method="post" action="actions.php" style="display:inline" onsubmit="return confirm('Delete this post permanently?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="entity" value="blog">
                        <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
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
