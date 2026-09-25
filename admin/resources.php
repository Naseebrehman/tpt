<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'resources';
$adminTitle = 'Resources Manager';

$editId   = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing  = $editId ? dbOne('SELECT * FROM resources WHERE id = ?', array($editId)) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired.');
    } else {
        $title    = sanitize(isset($_POST['title']) ? $_POST['title'] : '');
        $desc     = sanitizeMultiline(isset($_POST['description']) ? $_POST['description'] : '');
        $category = sanitize(isset($_POST['category']) ? $_POST['category'] : '');
        $type     = isset($_POST['resource_type']) && in_array($_POST['resource_type'], array('guide', 'template', 'video'), true) ? $_POST['resource_type'] : 'guide';
        $videoUrl = sanitize(isset($_POST['video_url']) ? $_POST['video_url'] : '');
        $active   = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '') {
            setFlash('err', 'A title is required.');
        } else {
            $cover = $editing && !empty($editing['cover_image']) ? $editing['cover_image'] : '';
            $file  = $editing && !empty($editing['file_path']) ? $editing['file_path'] : '';

            $coverUp = uploadFile('cover_image', 'resources', array('jpg', 'jpeg', 'png', 'webp', 'svg'));
            $fileUp  = uploadFile('resource_file', 'resources', array('pdf', 'zip', 'xlsx', 'csv', 'docx'));

            if (!$coverUp['ok'] || !$fileUp['ok']) {
                setFlash('err', $coverUp['ok'] ? $fileUp['error'] : $coverUp['error']);
            } else {
                if ($coverUp['path'] !== '') { if ($cover !== '') { deleteUpload($cover); } $cover = $coverUp['path']; }
                if ($fileUp['path'] !== '')  { if ($file !== '')  { deleteUpload($file); }  $file  = $fileUp['path']; }

                if ($editing) {
                    dbExec('UPDATE resources SET title=?, description=?, cover_image=?, file_path=?, resource_type=?, video_url=?, category=?, is_active=? WHERE id=?',
                        array($title, $desc, $cover, $file, $type, $videoUrl, $category, $active, $editId));
                    setFlash('ok', 'Resource updated.');
                } else {
                    dbInsert('INSERT INTO resources (title, description, cover_image, file_path, resource_type, video_url, category, is_active) VALUES (?,?,?,?,?,?,?,?)',
                        array($title, $desc, $cover, $file, $type, $videoUrl, $category, $active));
                    setFlash('ok', 'Resource added.');
                }
                header('Location: resources.php');
                exit;
            }
        }
    }
}

$resources = dbAll('SELECT * FROM resources ORDER BY resource_type ASC, id DESC');

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-grid cols-2" style="align-items:start" id="add">
    <form method="post" enctype="multipart/form-data" class="a-card">
        <?= csrfField() ?>
        <h3><?= $editing ? 'Edit resource #' . $editId : 'Add a resource' ?></h3>
        <div class="a-field">
            <label for="rTitle">Title</label>
            <input id="rTitle" name="title" type="text" required maxlength="255" value="<?= esc($editing ? $editing['title'] : '') ?>">
        </div>
        <div class="a-field">
            <label for="rDesc">Description</label>
            <textarea id="rDesc" name="description"><?= esc($editing ? $editing['description'] : '') ?></textarea>
        </div>
        <div class="a-field-row">
            <div class="a-field">
                <label for="rType">Type</label>
                <select id="rType" name="resource_type">
                    <?php foreach (array('guide' => 'Guide', 'template' => 'Template', 'video' => 'Video') as $tKey => $tLabel): ?>
                    <option value="<?= $tKey ?>"<?= $editing && $editing['resource_type'] === $tKey ? ' selected' : '' ?>><?= $tLabel ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="a-field">
                <label for="rCat">Category</label>
                <input id="rCat" name="category" type="text" maxlength="100" value="<?= esc($editing ? $editing['category'] : '') ?>" placeholder="Paid Social">
            </div>
        </div>
        <div class="a-field">
            <label for="rVideo">Video URL (YouTube) — for type Video</label>
            <input id="rVideo" name="video_url" type="url" maxlength="500" value="<?= esc($editing ? $editing['video_url'] : '') ?>" placeholder="https://youtube.com/watch?v=…">
        </div>
        <div class="a-field-row">
            <div class="a-field">
                <label for="rCover">Cover image</label>
                <input id="rCover" name="cover_image" type="file" accept="image/*">
            </div>
            <div class="a-field">
                <label for="rFile">PDF / file</label>
                <input id="rFile" name="resource_file" type="file" accept=".pdf,.zip,.xlsx,.csv,.docx">
            </div>
        </div>
        <div class="a-field">
            <label class="a-check"><input type="checkbox" name="is_active" value="1"<?= !$editing || $editing['is_active'] ? ' checked' : '' ?>> Active (visible on site)</label>
        </div>
        <div class="a-toolbar">
            <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> <?= $editing ? 'Update' : 'Add Resource' ?></button>
            <?php if ($editing): ?><a class="a-btn" href="resources.php">Cancel edit</a><?php endif; ?>
        </div>
    </form>

    <div class="a-table-wrap">
        <table class="a-table" style="min-width:520px">
            <thead><tr><th></th><th>Title</th><th>Type</th><th>Downloads</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($resources as $res): ?>
            <tr>
                <td><?php if ($res['cover_image'] !== ''): ?><img class="thumb" src="<?= asset($res['cover_image']) ?>" alt=""><?php else: ?><span class="thumb" style="display:inline-block;background:#15151d"></span><?php endif; ?></td>
                <td class="td-main"><?= esc($res['title']) ?><div class="td-sub"><?= esc($res['category']) ?></div></td>
                <td><span class="badge <?= $res['resource_type'] === 'video' ? 'replied' : 'new' ?>"><?= esc($res['resource_type']) ?></span></td>
                <td class="mono"><?= number_format((int) $res['download_count']) ?></td>
                <td><span class="badge <?= $res['is_active'] ? 'active' : 'inactive' ?>"><?= $res['is_active'] ? 'active' : 'hidden' ?></span></td>
                <td>
                    <div class="row-actions">
                        <a class="a-btn small" href="resources.php?edit=<?= (int) $res['id'] ?>">Edit</a>
                        <form method="post" action="actions.php" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="entity" value="resources">
                            <input type="hidden" name="id" value="<?= (int) $res['id'] ?>">
                            <button class="a-btn small" type="submit"><?= $res['is_active'] ? 'Hide' : 'Show' ?></button>
                        </form>
                        <form method="post" action="actions.php" style="display:inline" onsubmit="return confirm('Delete this resource?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="entity" value="resources">
                            <input type="hidden" name="id" value="<?= (int) $res['id'] ?>">
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
