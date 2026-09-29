<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Subscriber Email Notifications
 * ---------------------------------------------------------------------------
 *  Manually send branded email notifications to newsletter subscribers for:
 *  - New Blog Posts
 *  - New Resources
 *
 *  Provides subscriber selection, "Select All", manual send trigger,
 *  professional branded email template, and detailed send status reporting.
 * ---------------------------------------------------------------------------
 */

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/email-templates.php';
require_once BASE_PATH . '/core/Schema.php';
Schema::ensure();
requireAdmin();

$adminPage  = 'subscribers';
$adminTitle = 'Subscriber Email Notifications';

$subscribers = dbAll('SELECT * FROM newsletter_subscribers WHERE is_active = 1 ORDER BY subscribed_at DESC');
$blogPosts   = dbAll("SELECT id, title, slug, excerpt, content, featured_image, author, reading_time, published_at FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC, id DESC");
$resources   = dbAll("SELECT id, title, slug, description, cover_image, category, resource_type, created_at FROM resources WHERE is_active = 1 ORDER BY id DESC");

/* Current selection parameters from GET */
$targetType = isset($_GET['type']) && in_array($_GET['type'], array('blog', 'resource'), true) ? $_GET['type'] : 'blog';
$targetId   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/* Resolve selected post or resource */
$selectedPost = null;
if ($blogPosts) {
    if ($targetType === 'blog' && $targetId > 0) {
        foreach ($blogPosts as $p) {
            if ((int) $p['id'] === $targetId) { $selectedPost = $p; break; }
        }
    }
    if (!$selectedPost) { $selectedPost = $blogPosts[0]; }
}

$selectedResource = null;
if ($resources) {
    if ($targetType === 'resource' && $targetId > 0) {
        foreach ($resources as $r) {
            if ((int) $r['id'] === $targetId) { $selectedResource = $r; break; }
        }
    }
    if (!$selectedResource) { $selectedResource = $resources[0]; }
}

$sendResult = null;

/* ---------------------------------------------------------------------------
   Handle Manual Send Request
   --------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    if (!validateCSRF()) {
        $sendResult = array('success' => false, 'message' => 'Security token expired. Please refresh and try again.');
    } else {
        $postType   = isset($_POST['notify_type']) && in_array($_POST['notify_type'], array('blog', 'resource'), true) ? $_POST['notify_type'] : 'blog';
        $itemId     = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
        $subject    = sanitize(isset($_POST['subject']) ? $_POST['subject'] : '');
        $customNote = sanitizeMultiline(isset($_POST['custom_message']) ? $_POST['custom_message'] : '');
        $subIds     = isset($_POST['subscriber_ids']) && is_array($_POST['subscriber_ids']) ? array_map('intval', $_POST['subscriber_ids']) : array();

        if (empty($subIds)) {
            $sendResult = array('success' => false, 'message' => 'Please select at least one subscriber to notify.');
        } elseif ($itemId <= 0) {
            $sendResult = array('success' => false, 'message' => 'Please choose a valid ' . ($postType === 'blog' ? 'blog post' : 'resource') . '.');
        } else {
            $targetItem = null;
            if ($postType === 'blog') {
                $targetItem = dbOne('SELECT * FROM blog_posts WHERE id = ?', array($itemId));
            } else {
                $targetItem = dbOne('SELECT * FROM resources WHERE id = ?', array($itemId));
            }

            if (!$targetItem) {
                $sendResult = array('success' => false, 'message' => 'The selected content item was not found.');
            } else {
                // Fetch only active selected subscribers
                $inList = implode(',', $subIds);
                $recipients = dbAll("SELECT * FROM newsletter_subscribers WHERE id IN ($inList) AND is_active = 1");

                if (!$recipients) {
                    $sendResult = array('success' => false, 'message' => 'No active subscribers found in your selection.');
                } else {
                    $sentCount   = 0;
                    $failedCount = 0;
                    $failures    = array();

                    foreach ($recipients as $recipient) {
                        if ($postType === 'blog') {
                            $emailData = emailBlogNotification($targetItem, $recipient, $customNote, $subject);
                        } else {
                            $emailData = emailResourceNotification($targetItem, $recipient, $customNote, $subject);
                        }

                        $sent = sendEmail($recipient['email'], $emailData['subject'], $emailData['html']);
                        if ($sent) {
                            $sentCount++;
                        } else {
                            $failedCount++;
                            $err = isset($GLOBALS['pieMailError']) && $GLOBALS['pieMailError'] !== '' ? $GLOBALS['pieMailError'] : 'SMTP delivery failed';
                            $failures[] = $recipient['email'] . ' (' . $err . ')';
                        }
                    }

                    if ($sentCount > 0 && $failedCount === 0) {
                        $sendResult = array(
                            'success' => true,
                            'message' => 'Successfully sent notification to ' . $sentCount . ' subscriber' . ($sentCount === 1 ? '' : 's') . '.',
                        );
                    } elseif ($sentCount > 0 && $failedCount > 0) {
                        $sendResult = array(
                            'success'  => false,
                            'message'  => 'Partially sent: ' . $sentCount . ' succeeded, ' . $failedCount . ' failed.',
                            'failures' => $failures,
                        );
                    } else {
                        $sendResult = array(
                            'success'  => false,
                            'message'  => 'Sending failed for all ' . count($recipients) . ' recipients. Please check SMTP configuration in Settings &rarr; Email / SMTP.',
                            'failures' => $failures,
                        );
                    }
                }
            }
        }
    }
}

/* Default subject depending on selected item */
$defaultSubject = '';
if ($targetType === 'blog' && $selectedPost) {
    $defaultSubject = 'New on the Blog: ' . $selectedPost['title'] . ' — ' . getSetting('site_name', SITE_NAME);
} elseif ($targetType === 'resource' && $selectedResource) {
    $defaultSubject = 'New Resource: ' . $selectedResource['title'] . ' — ' . getSetting('site_name', SITE_NAME);
}

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-toolbar">
    <a class="a-btn small" href="subscribers.php">&larr; Back to Subscribers</a>
    <span class="spacer"></span>
    <span class="text-muted"><?= count($subscribers) ?> active subscriber<?= count($subscribers) === 1 ? '' : 's' ?></span>
</div>

<?php if ($sendResult): ?>
    <div class="admin-alert <?= $sendResult['success'] ? 'ok' : 'err' ?>" style="margin-bottom:20px;">
        <strong><?= esc($sendResult['message']) ?></strong>
        <?php if (!empty($sendResult['failures'])): ?>
            <ul style="margin:8px 0 0;padding-left:18px;font-size:0.84rem;">
                <?php foreach ($sendResult['failures'] as $fail): ?>
                    <li><?= esc($fail) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="post" id="notifyForm">
    <?= csrfField() ?>
    <input type="hidden" name="send_notification" value="1">

    <div class="a-grid cols-2" style="align-items:start;">
        <!-- Left: Notification Content & Details -->
        <div class="a-grid" style="gap:20px;">
            <div class="a-card">
                <h3>1. Select Content to Announce</h3>

                <!-- Target Type Tabs -->
                <div class="a-field">
                    <label>Content Type</label>
                    <div style="display:flex;gap:12px;margin-top:6px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;font-size:0.9rem;text-transform:none;color:#fff;">
                            <input type="radio" name="notify_type" value="blog" <?= $targetType === 'blog' ? 'checked' : '' ?> onchange="window.location.href='subscriber-notify.php?type=blog'">
                            <span>New Blog Post</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;font-size:0.9rem;text-transform:none;color:#fff;">
                            <input type="radio" name="notify_type" value="resource" <?= $targetType === 'resource' ? 'checked' : '' ?> onchange="window.location.href='subscriber-notify.php?type=resource'">
                            <span>New Resource</span>
                        </label>
                    </div>
                </div>

                <!-- Item Selector -->
                <?php if ($targetType === 'blog'): ?>
                    <div class="a-field">
                        <label for="postSelector">Choose Published Blog Post</label>
                        <?php if (!$blogPosts): ?>
                            <p class="hint">No published blog posts found. <a href="blog-edit.php" style="color:var(--violet-soft);text-decoration:underline;">Write a post first &rarr;</a></p>
                        <?php else: ?>
                            <select id="postSelector" name="item_id" required onchange="window.location.href='subscriber-notify.php?type=blog&id=' + this.value">
                                <?php foreach ($blogPosts as $post): ?>
                                    <option value="<?= (int) $post['id'] ?>" <?= ($selectedPost && (int) $selectedPost['id'] === (int) $post['id']) ? 'selected' : '' ?>>
                                        <?= esc($post['title']) ?> (<?= esc(formatDate($post['published_at'], 'j M Y')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="a-field">
                        <label for="resourceSelector">Choose Active Resource</label>
                        <?php if (!$resources): ?>
                            <p class="hint">No active resources found. <a href="resources.php#add" style="color:var(--violet-soft);text-decoration:underline;">Add a resource first &rarr;</a></p>
                        <?php else: ?>
                            <select id="resourceSelector" name="item_id" required onchange="window.location.href='subscriber-notify.php?type=resource&id=' + this.value">
                                <?php foreach ($resources as $res): ?>
                                    <option value="<?= (int) $res['id'] ?>" <?= ($selectedResource && (int) $selectedResource['id'] === (int) $res['id']) ? 'selected' : '' ?>>
                                        <?= esc($res['title']) ?> (<?= esc($res['category'] ?: ucfirst($res['resource_type'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Subject Line -->
                <div class="a-field">
                    <label for="notifySubject">Email Subject Line</label>
                    <input id="notifySubject" name="subject" type="text" required maxlength="255" value="<?= esc($defaultSubject) ?>">
                    <div class="hint">Branded subject line sent to each recipient.</div>
                </div>

                <!-- Custom Personal Message (Optional) -->
                <div class="a-field">
                    <label for="customMessage">Custom Editor's Note (Optional)</label>
                    <textarea id="customMessage" name="custom_message" style="min-height:90px;" placeholder="Add an optional personal intro note to your subscribers before the content preview..."></textarea>
                </div>
            </div>

            <!-- Email Live Preview Card -->
            <div class="a-card">
                <h3>Branded Email Preview</h3>
                <div style="background:#08080a;padding:20px;border-radius:12px;border:1px solid #23232b;color:#f0f0f4;font-family:Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;">
                    <div style="padding-bottom:14px;border-bottom:1px solid #23232b;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-weight:800;color:#fff;font-size:18px;"><?= esc(getSetting('site_name', SITE_NAME)) ?></span>
                        <span style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:1px;">Growth Agency</span>
                    </div>

                    <?php if ($targetType === 'blog' && $selectedPost): ?>
                        <span style="display:inline-block;padding:2px 8px;border-radius:4px;background:rgba(124,58,237,.2);color:#a78bfa;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:10px;">New Blog Post</span>
                        <h2 style="color:#fff;font-size:20px;margin:0 0 10px;line-height:1.3;"><?= esc($selectedPost['title']) ?></h2>
                        <?php if (!empty($selectedPost['featured_image'])): ?>
                            <img src="<?= esc(url($selectedPost['featured_image'])) ?>" alt="" style="max-width:100%;height:auto;border-radius:8px;margin-bottom:14px;border:1px solid #23232b;">
                        <?php endif; ?>
                        <p style="color:#c7c7d1;font-size:14px;margin:0 0 16px;line-height:1.6;">
                            <?= esc(!empty($selectedPost['excerpt']) ? $selectedPost['excerpt'] : mb_substr(strip_tags($selectedPost['content']), 0, 180) . '...') ?>
                        </p>
                        <div style="display:inline-block;padding:10px 22px;background:#7c3aed;color:#fff;border-radius:6px;font-weight:700;font-size:13px;">Read Full Article &rarr;</div>
                    <?php elseif ($targetType === 'resource' && $selectedResource): ?>
                        <span style="display:inline-block;padding:2px 8px;border-radius:4px;background:rgba(34,211,238,.2);color:#22d3ee;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:10px;">New Resource</span>
                        <h2 style="color:#fff;font-size:20px;margin:0 0 10px;line-height:1.3;"><?= esc($selectedResource['title']) ?></h2>
                        <?php if (!empty($selectedResource['cover_image'])): ?>
                            <img src="<?= esc(url($selectedResource['cover_image'])) ?>" alt="" style="max-width:100%;height:auto;border-radius:8px;margin-bottom:14px;border:1px solid #23232b;">
                        <?php endif; ?>
                        <p style="color:#c7c7d1;font-size:14px;margin:0 0 16px;line-height:1.6;">
                            <?= esc($selectedResource['description']) ?>
                        </p>
                        <div style="display:inline-block;padding:10px 22px;background:#7c3aed;color:#fff;border-radius:6px;font-weight:700;font-size:13px;">Access Resource &rarr;</div>
                    <?php else: ?>
                        <p class="text-muted">Select an item above to see the email preview.</p>
                    <?php endif; ?>

                    <div style="margin-top:24px;padding-top:14px;border-top:1px solid #23232b;font-size:11px;color:#6b7280;">
                        <span>&copy; <?= date('Y') ?> <?= esc(getSetting('site_name', SITE_NAME)) ?> &middot; <a href="#" style="color:#6b7280;text-decoration:underline;">Unsubscribe</a></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Subscriber Selection & Send Button -->
        <div class="a-grid" style="gap:20px;">
            <div class="a-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                    <h3>2. Select Subscribers</h3>
                    <div style="font-size:0.83rem;color:var(--violet-soft);font-weight:600;">
                        <span id="subSelectedCount"><?= count($subscribers) ?></span> of <?= count($subscribers) ?> selected
                    </div>
                </div>

                <div class="a-toolbar" style="margin-bottom:14px;">
                    <button class="a-btn small" type="button" id="btnSelectAll"><?= icon('check', 14) ?> Select All</button>
                    <button class="a-btn small" type="button" id="btnDeselectAll">Deselect All</button>
                    <span class="spacer"></span>
                    <input type="search" id="subSearchInput" placeholder="Filter subscribers…" style="padding:6px 10px;font-size:0.82rem;width:170px;">
                </div>

                <div class="a-table-wrap" style="max-height:480px;overflow-y:auto;">
                    <table class="a-table" id="subsTable">
                        <thead>
                            <tr>
                                <th style="width:36px;"><input type="checkbox" id="headerCheckbox" checked aria-label="Toggle all"></th>
                                <th>Subscriber</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$subscribers): ?>
                            <tr><td colspan="3" class="text-muted">No active subscribers in your database.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($subscribers as $s): ?>
                            <tr class="sub-row">
                                <td>
                                    <input type="checkbox" name="subscriber_ids[]" value="<?= (int) $s['id'] ?>" class="sub-item-check" checked>
                                </td>
                                <td class="td-main sub-name"><?= esc($s['name'] !== '' ? $s['name'] : '—') ?></td>
                                <td class="td-sub sub-email"><?= esc($s['email']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:20px;">
                    <button class="a-btn primary block" type="submit" id="submitSendBtn" data-confirm="Are you sure you want to send this email notification to the selected subscribers?">
                        <?= icon('send', 16) ?> Send Notification to Selected Subscribers
                    </button>
                    <p class="hint" style="text-align:center;margin-top:10px;">
                        Notifications are <strong>never sent automatically</strong>. They are dispatched only when you manually click this button.
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var checkAll = document.getElementById('headerCheckbox');
    var btnSelectAll = document.getElementById('btnSelectAll');
    var btnDeselectAll = document.getElementById('btnDeselectAll');
    var subChecks = document.querySelectorAll('.sub-item-check');
    var countBadge = document.getElementById('subSelectedCount');
    var searchInput = document.getElementById('subSearchInput');
    var rows = document.querySelectorAll('.sub-row');

    function updateCount() {
        var checked = document.querySelectorAll('.sub-item-check:checked').length;
        if (countBadge) countBadge.textContent = checked;
        if (checkAll) {
            checkAll.checked = (checked === subChecks.length && subChecks.length > 0);
            checkAll.indeterminate = (checked > 0 && checked < subChecks.length);
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            subChecks.forEach(function (cb) {
                var row = cb.closest('tr');
                if (!row || row.style.display !== 'none') {
                    cb.checked = checkAll.checked;
                }
            });
            updateCount();
        });
    }

    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function () {
            subChecks.forEach(function (cb) { cb.checked = true; });
            updateCount();
        });
    }

    if (btnDeselectAll) {
        btnDeselectAll.addEventListener('click', function () {
            subChecks.forEach(function (cb) { cb.checked = false; });
            updateCount();
        });
    }

    subChecks.forEach(function (cb) {
        cb.addEventListener('change', updateCount);
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var q = searchInput.value.toLowerCase().trim();
            rows.forEach(function (row) {
                var text = (row.textContent || '').toLowerCase();
                row.style.display = text.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    updateCount();
});
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
