<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Gallery & Media Library
 * ---------------------------------------------------------------------------
 *  Upload, organize, preview, search, and manage media files:
 *  - Images (jpg, jpeg, png, webp, gif, svg)
 *  - Videos (mp4, webm, ogv, mov)
 *  - Documents (pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv, zip)
 *
 *  Secure uploads with MIME verification, random filename generation,
 *  and directory traversal protection.
 * ---------------------------------------------------------------------------
 */

require_once dirname(__DIR__) . '/includes/init.php';
require_once BASE_PATH . '/core/Schema.php';
require_once BASE_PATH . '/core/Upload.php';
Schema::ensure();
requireAdmin();

$adminPage  = 'media';
$adminTitle = 'Media Library';

/* ---------------------------------------------------------------------------
   POST Actions: Upload & Delete
   --------------------------------------------------------------------------- */
$fType = isset($_GET['type']) && in_array($_GET['type'], array('image', 'video', 'document'), true) ? $_GET['type'] : 'all';
$searchQuery = isset($_GET['q']) ? trim(sanitize($_GET['q'])) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired — please refresh and try again.');
        header('Location: media.php' . ($fType !== 'all' ? '?type=' . $fType : ''));
        exit;
    }

    $action = isset($_POST['action']) ? trim($_POST['action']) : '';

    /* 1. Upload Media File(s) */
    if ($action === 'upload') {
        if (!isset($_FILES['media_files'])) {
            setFlash('err', 'No file was selected.');
        } else {
            $files = $_FILES['media_files'];
            $fileList = array();

            if (is_array($files['name'])) {
                for ($i = 0; $i < count($files['name']); $i++) {
                    if (!empty($files['name'][$i])) {
                        $fileList[] = array(
                            'name'     => $files['name'][$i],
                            'type'     => isset($files['type'][$i]) ? $files['type'][$i] : '',
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i],
                        );
                    }
                }
            } else {
                if (!empty($files['name'])) {
                    $fileList[] = $files;
                }
            }

            if (empty($fileList)) {
                setFlash('err', 'Please select at least one file to upload.');
            } else {
                $uploadedCount = 0;
                $errors = array();

                foreach ($fileList as $f) {
                    $res = uploadMediaItem($f);
                    if ($res['ok']) {
                        $inserted = dbInsert(
                            'INSERT INTO media_library (file_name, original_name, file_path, file_type, mime_type, file_size, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, NOW())',
                            array(
                                $res['file_name'],
                                $res['original_name'],
                                $res['path'],
                                $res['file_type'],
                                $res['mime_type'],
                                $res['file_size'],
                            )
                        );
                        if ($inserted > 0) {
                            $uploadedCount++;
                        } else {
                            $errors[] = $f['name'] . ': Database insert failed.';
                        }
                    } else {
                        $errors[] = $f['name'] . ': ' . $res['error'];
                    }
                }

                if ($uploadedCount > 0 && empty($errors)) {
                    setFlash('ok', $uploadedCount . ' media file' . ($uploadedCount === 1 ? '' : 's') . ' uploaded successfully.');
                } elseif ($uploadedCount > 0 && !empty($errors)) {
                    setFlash('ok', $uploadedCount . ' file' . ($uploadedCount === 1 ? '' : 's') . ' uploaded. (Some files failed: ' . implode('; ', $errors) . ')');
                } else {
                    setFlash('err', 'Upload failed: ' . implode('; ', $errors));
                }
            }
        }
        header('Location: media.php' . ($fType !== 'all' ? '?type=' . $fType : ''));
        exit;
    }

    /* 2. Delete Media File */
    if ($action === 'delete') {
        $mediaId = isset($_POST['media_id']) ? (int) $_POST['media_id'] : 0;
        $target = dbOne('SELECT * FROM media_library WHERE id = ?', array($mediaId));

        if (!$target) {
            setFlash('err', 'Media item not found.');
        } else {
            deleteUpload($target['file_path']);
            dbExec('DELETE FROM media_library WHERE id = ?', array($mediaId));
            setFlash('ok', 'Media item "' . esc($target['original_name']) . '" deleted successfully.');
        }
        header('Location: media.php' . ($fType !== 'all' ? '?type=' . $fType : ''));
        exit;
    }
}

/* ---------------------------------------------------------------------------
   Counts & Query
   --------------------------------------------------------------------------- */
$cAll   = dbOne('SELECT COUNT(*) AS c FROM media_library');
$cImg   = dbOne("SELECT COUNT(*) AS c FROM media_library WHERE file_type = 'image'");
$cVid   = dbOne("SELECT COUNT(*) AS c FROM media_library WHERE file_type = 'video'");
$cDoc   = dbOne("SELECT COUNT(*) AS c FROM media_library WHERE file_type = 'document'");

$countAll   = $cAll ? (int) $cAll['c'] : 0;
$countImg   = $cImg ? (int) $cImg['c'] : 0;
$countVid   = $cVid ? (int) $cVid['c'] : 0;
$countDoc   = $cDoc ? (int) $cDoc['c'] : 0;

$whereClauses = array('1=1');
$params       = array();

if ($fType !== 'all') {
    $whereClauses[] = 'file_type = ?';
    $params[]       = $fType;
}

if ($searchQuery !== '') {
    $whereClauses[] = '(original_name LIKE ? OR file_name LIKE ?)';
    $params[]       = '%' . $searchQuery . '%';
    $params[]       = '%' . $searchQuery . '%';
}

$whereSql = implode(' AND ', $whereClauses);
$mediaItems = dbAll("SELECT * FROM media_library WHERE $whereSql ORDER BY created_at DESC, id DESC", $params);

function formatFileSize($bytes)
{
    $bytes = (int) $bytes;
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<!-- Toolbar: Upload Trigger & Stats -->
<div class="a-toolbar">
    <button class="a-btn primary" type="button" data-modal="modal-upload"><?= icon('plus', 16) ?> Upload Media</button>
    <span class="spacer"></span>
    <span class="text-muted"><?= count($mediaItems) ?> item<?= count($mediaItems) === 1 ? '' : 's' ?> shown (<?= $countAll ?> total in library)</span>
</div>

<!-- Type Filter Tabs & Search Bar -->
<div class="a-card" style="padding:16px 20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
        <div class="a-tabs" style="border-bottom:none;padding-bottom:0;margin-bottom:0;">
            <a href="media.php<?= $searchQuery !== '' ? '?q=' . rawurlencode($searchQuery) : '' ?>" class="a-tab <?= $fType === 'all' ? 'active' : '' ?>">
                All (<?= $countAll ?>)
            </a>
            <a href="media.php?type=image<?= $searchQuery !== '' ? '&q=' . rawurlencode($searchQuery) : '' ?>" class="a-tab <?= $fType === 'image' ? 'active' : '' ?>">
                <?= icon('image', 15) ?> Images (<?= $countImg ?>)
            </a>
            <a href="media.php?type=video<?= $searchQuery !== '' ? '&q=' . rawurlencode($searchQuery) : '' ?>" class="a-tab <?= $fType === 'video' ? 'active' : '' ?>">
                <?= icon('video', 15) ?> Videos (<?= $countVid ?>)
            </a>
            <a href="media.php?type=document<?= $searchQuery !== '' ? '&q=' . rawurlencode($searchQuery) : '' ?>" class="a-tab <?= $fType === 'document' ? 'active' : '' ?>">
                <?= icon('file', 15) ?> Documents (<?= $countDoc ?>)
            </a>
        </div>

        <form method="get" class="a-filters" style="margin:0;">
            <?php if ($fType !== 'all'): ?>
                <input type="hidden" name="type" value="<?= esc($fType) ?>">
            <?php endif; ?>
            <input type="search" name="q" value="<?= esc($searchQuery) ?>" placeholder="Search by filename…" style="min-width:200px;">
            <button class="a-btn small" type="submit"><?= icon('search', 14) ?> Search</button>
            <?php if ($searchQuery !== ''): ?>
                <a class="a-btn small" href="media.php<?= $fType !== 'all' ? '?type=' . $fType : '' ?>">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Media Grid -->
<?php if (!$mediaItems): ?>
    <div class="a-card" style="text-align:center;padding:50px 20px;">
        <p style="color:var(--muted);font-size:1.05rem;margin-bottom:14px;">No media files found<?= $searchQuery !== '' ? ' matching your search' : ' in this category' ?>.</p>
        <button class="a-btn primary" type="button" data-modal="modal-upload"><?= icon('plus', 16) ?> Upload First Media File</button>
    </div>
<?php else: ?>
    <div class="media-grid">
        <?php foreach ($mediaItems as $item):
            $fullUrl = url($item['file_path']);
            $fileExt = strtoupper(pathinfo($item['original_name'], PATHINFO_EXTENSION));
        ?>
            <div class="media-card">
                <!-- Media Thumbnail / Preview Area -->
                <div class="media-thumb-wrap">
                    <span class="media-type-badge <?= esc($item['file_type']) ?>"><?= esc($item['file_type']) ?></span>

                    <?php if ($item['file_type'] === 'image'): ?>
                        <a href="<?= esc($fullUrl) ?>" target="_blank" rel="noopener" style="width:100%;height:100%;display:block;">
                            <img src="<?= esc($fullUrl) ?>" alt="<?= esc($item['original_name']) ?>" loading="lazy">
                        </a>
                    <?php elseif ($item['file_type'] === 'video'): ?>
                        <video preload="metadata" controls style="width:100%;height:100%;">
                            <source src="<?= esc($fullUrl) ?>" type="<?= esc($item['mime_type']) ?>">
                            Your browser does not support HTML5 video.
                        </video>
                    <?php else: ?>
                        <div class="media-doc-preview">
                            <?= icon('file', 36) ?>
                            <span class="media-doc-ext"><?= esc($fileExt ?: 'DOC') ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Media Details -->
                <div class="media-body">
                    <div class="media-title" title="<?= esc($item['original_name']) ?>">
                        <?= esc($item['original_name']) ?>
                    </div>

                    <div class="media-meta">
                        <span><?= esc(formatFileSize($item['file_size'])) ?></span>
                        <span><?= esc(formatDate($item['created_at'], 'j M Y')) ?></span>
                    </div>

                    <!-- Media URL Display & Copy Button -->
                    <div class="media-url-box">
                        <input type="text" readonly class="media-url-input" value="<?= esc($fullUrl) ?>" title="Click copy button to copy URL">
                        <button class="a-btn small btn-copy-url" type="button" data-copy="<?= esc($fullUrl) ?>" title="Copy media URL to clipboard">
                            <?= icon('copy', 13) ?> Copy
                        </button>
                    </div>

                    <!-- Actions -->
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:8px;border-top:1px solid var(--line);">
                        <a class="a-btn small" href="<?= esc($fullUrl) ?>" target="_blank" rel="noopener" title="Open / Download full file">
                            <?= icon('eye', 13) ?> View
                        </a>
                        <form method="post" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                            <button class="a-btn small danger" type="submit" data-confirm="Are you sure you want to permanently delete this media file?" title="Delete file">
                                <?= icon('trash', 13) ?> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Hidden Template: Upload Modal -->
<div id="modal-upload" style="display:none">
    <h3>Upload to Media Library</h3>
    <p class="hint">Upload images, videos, or documents. Uploaded files are automatically organized and sanitized for security.</p>

    <form method="post" enctype="multipart/form-data" data-modal-form style="margin-top:16px;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload">

        <div class="a-field">
            <label for="media_files_input">Select File(s)</label>
            <input id="media_files_input" name="media_files[]" type="file" multiple required
                   accept="image/*,video/mp4,video/webm,video/ogg,video/quicktime,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip">
            <div class="hint" style="margin-top:8px;line-height:1.5;">
                <strong>Supported formats:</strong><br>
                &bull; <strong>Images:</strong> JPG, PNG, WebP, GIF, SVG<br>
                &bull; <strong>Videos:</strong> MP4, WebM, OGV, MOV<br>
                &bull; <strong>Documents:</strong> PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV, ZIP<br>
                &bull; Max file size: 50 MB per file.
            </div>
        </div>

        <div class="a-toolbar" style="margin-top:20px;">
            <button class="a-btn primary" type="submit"><?= icon('plus', 16) ?> Start Upload</button>
        </div>
    </form>
</div>

<!-- Toast notification for copied URL -->
<div class="media-toast" id="mediaToast">Media URL copied to clipboard!</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var toast = document.getElementById('mediaToast');
    var timeoutId = null;

    function showToast(text) {
        if (!toast) return;
        toast.textContent = text || 'Media URL copied to clipboard!';
        toast.classList.add('show');
        clearTimeout(timeoutId);
        timeoutId = setTimeout(function () {
            toast.classList.remove('show');
        }, 2500);
    }

    document.querySelectorAll('.btn-copy-url').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var url = btn.getAttribute('data-copy');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    showToast('Media URL copied to clipboard!');
                }).catch(function () {
                    promptCopy(url);
                });
            } else {
                promptCopy(url);
            }
        });
    });

    function promptCopy(url) {
        window.prompt('Copy media URL:', url);
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
