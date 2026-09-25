<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'team';
$adminTitle = 'Team Manager';
$adminLibs  = array('sortable' => true);

$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = $editId ? dbOne('SELECT * FROM team_members WHERE id = ?', array($editId)) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired.');
    } else {
        $name = sanitize(isset($_POST['name']) ? $_POST['name'] : '');
        $role = sanitize(isset($_POST['role']) ? $_POST['role'] : '');
        $bio  = sanitizeMultiline(isset($_POST['bio']) ? $_POST['bio'] : '');
        $li   = sanitize(isset($_POST['linkedin']) ? $_POST['linkedin'] : '');
        $tw   = sanitize(isset($_POST['twitter']) ? $_POST['twitter'] : '');
        $ord  = isset($_POST['display_order']) ? (int) $_POST['display_order'] : 0;
        $act  = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            setFlash('err', 'Name is required.');
        } else {
            $photo = $editing && !empty($editing['photo']) ? $editing['photo'] : '';
            $up = uploadFile('photo', 'team', array('jpg', 'jpeg', 'png', 'webp'));
            if (!$up['ok']) {
                setFlash('err', $up['error']);
            } else {
                if ($up['path'] !== '') { if ($photo !== '') { deleteUpload($photo); } $photo = $up['path']; }
                if ($editing) {
                    dbExec('UPDATE team_members SET name=?, role=?, photo=?, bio=?, linkedin=?, twitter=?, display_order=?, is_active=? WHERE id=?',
                        array($name, $role, $photo, $bio, $li, $tw, $ord, $act, $editId));
                    setFlash('ok', 'Team member updated.');
                } else {
                    dbInsert('INSERT INTO team_members (name, role, photo, bio, linkedin, twitter, display_order, is_active) VALUES (?,?,?,?,?,?,?,?)',
                        array($name, $role, $photo, $bio, $li, $tw, $ord, $act));
                    setFlash('ok', 'Team member added.');
                }
                header('Location: team.php');
                exit;
            }
        }
    }
}

$members = dbAll('SELECT * FROM team_members ORDER BY display_order ASC, id ASC');

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-grid cols-2" style="align-items:start" id="add">
    <form method="post" enctype="multipart/form-data" class="a-card">
        <?= csrfField() ?>
        <h3><?= $editing ? 'Edit member #' . $editId : 'Add team member' ?></h3>
        <div class="a-field-row">
            <div class="a-field">
                <label for="tName">Name</label>
                <input id="tName" name="name" type="text" required maxlength="150" value="<?= esc($editing ? $editing['name'] : '') ?>">
            </div>
            <div class="a-field">
                <label for="tRole">Role</label>
                <input id="tRole" name="role" type="text" maxlength="150" value="<?= esc($editing ? $editing['role'] : '') ?>" placeholder="Head of Paid Media">
            </div>
        </div>
        <div class="a-field">
            <label for="tBio">Short bio</label>
            <textarea id="tBio" name="bio" style="min-height:80px"><?= esc($editing ? $editing['bio'] : '') ?></textarea>
        </div>
        <div class="a-field-row">
            <div class="a-field">
                <label for="tLi">LinkedIn URL</label>
                <input id="tLi" name="linkedin" type="url" maxlength="255" value="<?= esc($editing ? $editing['linkedin'] : '') ?>">
            </div>
            <div class="a-field">
                <label for="tTw">Twitter / X URL</label>
                <input id="tTw" name="twitter" type="url" maxlength="255" value="<?= esc($editing ? $editing['twitter'] : '') ?>">
            </div>
        </div>
        <div class="a-field-row">
            <div class="a-field">
                <label for="tPhoto">Photo</label>
                <input id="tPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="a-field">
                <label for="tOrder">Display order</label>
                <input id="tOrder" name="display_order" type="number" min="0" value="<?= (int) ($editing ? $editing['display_order'] : count($members)) ?>">
            </div>
        </div>
        <div class="a-field">
            <label class="a-check"><input type="checkbox" name="is_active" value="1"<?= !$editing || $editing['is_active'] ? ' checked' : '' ?>> Active (shown on About page)</label>
        </div>
        <div class="a-toolbar">
            <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> <?= $editing ? 'Update' : 'Add Member' ?></button>
            <?php if ($editing): ?><a class="a-btn" href="team.php">Cancel</a><?php endif; ?>
        </div>
    </form>

    <div class="a-card">
        <h3>Current team <span class="text-muted td-sub" id="sortNote"></span></h3>
        <div class="a-toolbar" style="margin-bottom:12px"><?= csrfField() ?><span class="text-muted td-sub">Drag to reorder on the site</span></div>
        <ul class="sortable-list" data-sortable="team">
            <?php foreach ($members as $member): ?>
            <li data-id="<?= (int) $member['id'] ?>">
                <span class="grip"><?= icon('menu', 18) ?></span>
                <?php if ($member['photo'] !== ''): ?><img src="<?= asset($member['photo']) ?>" alt=""><?php else: ?><span style="width:44px;height:44px;border-radius:10px;background:#15151d;display:inline-block"></span><?php endif; ?>
                <span style="flex:1"><strong><?= esc($member['name']) ?></strong><span class="td-sub" style="display:block"><?= esc($member['role']) ?></span></span>
                <span class="badge <?= $member['is_active'] ? 'active' : 'inactive' ?>"><?= $member['is_active'] ? 'active' : 'hidden' ?></span>
                <a class="a-btn small" href="team.php?edit=<?= (int) $member['id'] ?>">Edit</a>
                <form method="post" action="actions.php" style="display:inline" onsubmit="return confirm('Remove this team member?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="entity" value="team">
                    <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
                    <button class="a-btn small danger" type="submit">Delete</button>
                </form>
            </li>
            <?php endforeach; ?>
            <?php if (!$members): ?><li style="cursor:default" class="text-muted">No team members yet.</li><?php endif; ?>
        </ul>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
