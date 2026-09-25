<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'testimonials';
$adminTitle = 'Testimonials Manager';

$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = $editId ? dbOne('SELECT * FROM testimonials WHERE id = ?', array($editId)) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired.');
    } else {
        $name    = sanitize(isset($_POST['name']) ? $_POST['name'] : '');
        $company = sanitize(isset($_POST['company']) ? $_POST['company'] : '');
        $role    = sanitize(isset($_POST['role']) ? $_POST['role'] : '');
        $content = sanitizeMultiline(isset($_POST['content']) ? $_POST['content'] : '');
        $rating  = isset($_POST['rating']) ? max(1, min(5, (int) $_POST['rating'])) : 5;
        $service = sanitize(isset($_POST['service']) ? $_POST['service'] : '');
        $act     = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $content === '') {
            setFlash('err', 'Name and testimonial text are required.');
        } else {
            $photo = $editing && !empty($editing['photo']) ? $editing['photo'] : '';
            $up = uploadFile('photo', 'testimonials', array('jpg', 'jpeg', 'png', 'webp'));
            if (!$up['ok']) {
                setFlash('err', $up['error']);
            } else {
                if ($up['path'] !== '') { if ($photo !== '') { deleteUpload($photo); } $photo = $up['path']; }
                if ($editing) {
                    dbExec('UPDATE testimonials SET name=?, company=?, role=?, content=?, rating=?, photo=?, service=?, is_active=? WHERE id=?',
                        array($name, $company, $role, $content, $rating, $photo, $service, $act, $editId));
                    setFlash('ok', 'Testimonial updated.');
                } else {
                    dbInsert('INSERT INTO testimonials (name, company, role, content, rating, photo, service, is_active) VALUES (?,?,?,?,?,?,?,?)',
                        array($name, $company, $role, $content, $rating, $photo, $service, $act));
                    setFlash('ok', 'Testimonial added.');
                }
                header('Location: testimonials.php');
                exit;
            }
        }
    }
}

$list = dbAll('SELECT * FROM testimonials ORDER BY id DESC');

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-grid cols-2" style="align-items:start" id="add">
    <form method="post" enctype="multipart/form-data" class="a-card">
        <?= csrfField() ?>
        <h3><?= $editing ? 'Edit testimonial #' . $editId : 'Add testimonial' ?></h3>
        <div class="a-field-row">
            <div class="a-field">
                <label for="xName">Client name</label>
                <input id="xName" name="name" type="text" required maxlength="150" value="<?= esc($editing ? $editing['name'] : '') ?>">
            </div>
            <div class="a-field">
                <label for="xCompany">Company</label>
                <input id="xCompany" name="company" type="text" maxlength="150" value="<?= esc($editing ? $editing['company'] : '') ?>">
            </div>
        </div>
        <div class="a-field-row">
            <div class="a-field">
                <label for="xRole">Role</label>
                <input id="xRole" name="role" type="text" maxlength="150" value="<?= esc($editing ? $editing['role'] : '') ?>" placeholder="Founder">
            </div>
            <div class="a-field">
                <label for="xRating">Rating</label>
                <select id="xRating" name="rating">
                    <?php for ($r = 5; $r >= 1; $r--): ?>
                    <option value="<?= $r ?>"<?= $editing && (int) $editing['rating'] === $r ? ' selected' : '' ?>><?= $r ?> star<?= $r === 1 ? '' : 's' ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="a-field">
            <label for="xService">Service (used to match service pages)</label>
            <input id="xService" name="service" type="text" maxlength="100" value="<?= esc($editing ? $editing['service'] : '') ?>" placeholder="Meta Ads">
        </div>
        <div class="a-field">
            <label for="xContent">Testimonial text</label>
            <textarea id="xContent" name="content" required><?= esc($editing ? $editing['content'] : '') ?></textarea>
        </div>
        <div class="a-field-row">
            <div class="a-field">
                <label for="xPhoto">Photo (optional)</label>
                <input id="xPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="a-field" style="align-self:end">
                <label class="a-check"><input type="checkbox" name="is_active" value="1"<?= !$editing || $editing['is_active'] ? ' checked' : '' ?>> Active</label>
            </div>
        </div>
        <div class="a-toolbar">
            <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> <?= $editing ? 'Update' : 'Add Testimonial' ?></button>
            <?php if ($editing): ?><a class="a-btn" href="testimonials.php">Cancel</a><?php endif; ?>
        </div>
    </form>

    <div class="a-table-wrap">
        <table class="a-table" style="min-width:480px">
            <thead><tr><th>Client</th><th>Rating</th><th>Service</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($list as $t): ?>
            <tr>
                <td class="td-main"><?= esc($t['name']) ?><div class="td-sub"><?= esc($t['company']) ?> · <?= esc($t['role']) ?></div></td>
                <td style="color:#f5b400"><?= str_repeat('★', (int) $t['rating']) ?></td>
                <td class="td-sub"><?= esc($t['service'] !== '' ? $t['service'] : '—') ?></td>
                <td><span class="badge <?= $t['is_active'] ? 'active' : 'inactive' ?>"><?= $t['is_active'] ? 'active' : 'hidden' ?></span></td>
                <td>
                    <div class="row-actions">
                        <a class="a-btn small" href="testimonials.php?edit=<?= (int) $t['id'] ?>">Edit</a>
                        <form method="post" action="actions.php" style="display:inline" onsubmit="return confirm('Delete this testimonial?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="entity" value="testimonials">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button class="a-btn small danger" type="submit">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
