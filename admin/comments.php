<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'comments';
$adminTitle = 'Blog Comments';

$comments = dbAll(
    'SELECT c.*, p.title AS post_title, p.slug AS post_slug FROM blog_comments c
     LEFT JOIN blog_posts p ON p.id = c.post_id ORDER BY c.created_at DESC'
);

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-table-wrap">
    <table class="a-table">
        <thead><tr><th>Author</th><th>Comment</th><th>Post</th><th>Date</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
        <?php if (!$comments): ?>
            <tr><td colspan="6" class="text-muted">No comments yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($comments as $comment): ?>
        <tr>
            <td class="td-main"><?= esc($comment['name']) ?><div class="td-sub"><?= esc($comment['email']) ?></div></td>
            <td style="max-width:340px"><span class="td-sub" style="font-size:.84rem;color:var(--muted)"><?= esc(mb_substr($comment['comment'], 0, 140)) ?><?= mb_strlen($comment['comment']) > 140 ? '…' : '' ?></span></td>
            <td class="td-sub"><?= esc($comment['post_title'] !== null ? $comment['post_title'] : '—') ?></td>
            <td class="td-sub"><?= esc(formatDate($comment['created_at'], 'j M, H:i')) ?></td>
            <td><span class="badge <?= esc($comment['status']) ?>"><?= esc($comment['status']) ?></span></td>
            <td>
                <div class="row-actions">
                    <?php if ($comment['status'] !== 'approved'): ?>
                    <form method="post" action="actions.php" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="comment_status">
                        <input type="hidden" name="status" value="approved">
                        <input type="hidden" name="id" value="<?= (int) $comment['id'] ?>">
                        <button class="a-btn small" type="submit">Approve</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($comment['status'] !== 'spam'): ?>
                    <form method="post" action="actions.php" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="comment_status">
                        <input type="hidden" name="status" value="spam">
                        <input type="hidden" name="id" value="<?= (int) $comment['id'] ?>">
                        <button class="a-btn small" type="submit">Spam</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" action="actions.php" style="display:inline" onsubmit="return confirm('Delete this comment?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="entity" value="comments">
                        <input type="hidden" name="id" value="<?= (int) $comment['id'] ?>">
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
