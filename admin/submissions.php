<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'submissions';
$adminTitle = 'Contact Submissions';

/* ------------------------------- filters -------------------------------- */
$dateFrom = isset($_GET['from']) ? sanitize($_GET['from']) : '';
$dateTo   = isset($_GET['to']) ? sanitize($_GET['to']) : '';
$fService = isset($_GET['service']) ? sanitize($_GET['service']) : '';
$fStatus  = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search   = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$page     = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage  = 25;

$where  = array('1=1');
$params = array();
if ($dateFrom !== '') { $where[] = 'DATE(created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '')   { $where[] = 'DATE(created_at) <= ?'; $params[] = $dateTo; }
if ($fService !== '') { $where[] = 'service = ?'; $params[] = $fService; }
if ($fStatus !== '')  { $where[] = 'status = ?'; $params[] = $fStatus; }
if ($search !== '')   {
    $where[] = '(name LIKE ? OR email LIKE ? OR company LIKE ? OR message LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, array($like, $like, $like, $like));
}
$whereSql = implode(' AND ', $where);

$countRow = dbOne("SELECT COUNT(*) c FROM contact_submissions WHERE $whereSql", $params);
$total    = $countRow ? (int) $countRow['c'] : 0;
$pages    = max(1, (int) ceil($total / $perPage));
$page     = min($page, $pages);
$offset   = ($page - 1) * $perPage;

$rows = dbAll("SELECT * FROM contact_submissions WHERE $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);

$serviceOptions = array('Meta Ads', 'Social Media Management', 'Google Ads', 'Digital Marketing', 'SEO', 'Local SEO', 'AI Business Optimization', 'Website Development', 'App Development', 'Graphic Design', 'Data Analytics & Reporting', 'Not Sure');
$statusOptions  = array('new' => 'New', 'in_progress' => 'In Progress', 'replied' => 'Replied', 'closed' => 'Closed');
$viewId         = isset($_GET['view']) ? (int) $_GET['view'] : 0;

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<form class="a-filters" method="get" action="submissions.php">
    <input type="date" name="from" value="<?= esc($dateFrom) ?>" aria-label="From date" data-autosubmit="1">
    <input type="date" name="to" value="<?= esc($dateTo) ?>" aria-label="To date" data-autosubmit="1">
    <select name="service" aria-label="Filter by service" data-autosubmit="1">
        <option value="">All services</option>
        <?php foreach ($serviceOptions as $opt): ?>
        <option value="<?= esc($opt) ?>"<?= $fService === $opt ? ' selected' : '' ?>><?= esc($opt) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" aria-label="Filter by status" data-autosubmit="1">
        <option value="">All statuses</option>
        <?php foreach ($statusOptions as $key => $label): ?>
        <option value="<?= esc($key) ?>"<?= $fStatus === $key ? ' selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= esc($search) ?>" placeholder="Search name, email, message…">
    <button class="a-btn small" type="submit">Apply</button>
    <span class="spacer"></span>
    <a class="a-btn small" href="export.php?type=submissions<?= $search !== '' ? '&q=' . rawurlencode($search) : '' ?>">Export All CSV</a>
</form>

<form method="post" action="actions.php" id="bulkForm" onsubmit="return false;">
    <?= csrfField() ?>
    <div class="a-toolbar" id="bulkBar" style="display:none">
        <span class="text-muted" data-bulk-count>0 selected</span>
        <button class="a-btn small" type="button" id="bulkRead">Mark as read</button>
        <button class="a-btn small" type="button" id="bulkExport">Export selected</button>
        <button class="a-btn small danger" type="button" id="bulkDelete" data-confirm="Delete the selected submissions permanently?">Delete selected</button>
    </div>

    <div class="a-table-wrap">
        <table class="a-table">
            <thead>
                <tr>
                    <th style="width:34px"><input type="checkbox" id="selectAll" aria-label="Select all"></th>
                    <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Service</th><th>Budget</th><th>Date</th><th>Status</th><th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="10" class="text-muted">No submissions match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><input type="checkbox" class="row-check" value="<?= (int) $row['id'] ?>" aria-label="Select submission <?= (int) $row['id'] ?>"></td>
                <td class="mono td-sub">#<?= (int) $row['id'] ?></td>
                <td class="td-main"><?= esc($row['name']) ?><div class="td-sub"><?= esc($row['company'] !== '' ? $row['company'] : '') ?></div></td>
                <td><?= esc($row['email']) ?></td>
                <td class="td-sub"><?= esc($row['phone'] !== '' ? $row['phone'] : '—') ?></td>
                <td><?= esc($row['service'] !== '' ? $row['service'] : '—') ?></td>
                <td class="td-sub"><?= esc($row['budget'] !== '' ? $row['budget'] : '—') ?></td>
                <td class="td-sub"><?= esc(formatDate($row['created_at'], 'j M Y, H:i')) ?></td>
                <td><span class="badge <?= esc($row['status']) ?>"><?= esc(str_replace('_', ' ', $row['status'])) ?></span></td>
                <td>
                    <div class="row-actions">
                        <button class="a-btn small" type="button" data-modal="sub-modal-<?= (int) $row['id'] ?>">View</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>

<?php if ($pages > 1): ?>
<nav class="a-toolbar" style="justify-content:center" aria-label="Pagination">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a class="a-btn small<?= $p === $page ? ' primary' : '' ?>" href="?page=<?= $p ?>&from=<?= rawurlencode($dateFrom) ?>&to=<?= rawurlencode($dateTo) ?>&service=<?= rawurlencode($fService) ?>&status=<?= rawurlencode($fStatus) ?>&q=<?= rawurlencode($search) ?>"><?= $p ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>

<?php /* -------- modal templates (one per row) -------- */ ?>
<?php foreach ($rows as $row): ?>
<template id="sub-modal-<?= (int) $row['id'] ?>">
    <h3 style="margin-bottom:4px"><?= esc($row['name']) ?></h3>
    <p class="text-muted" style="margin:0 0 18px;font-size:.84rem">Received <?= esc(formatDate($row['created_at'], 'j M Y \a\t H:i')) ?> · #<?= (int) $row['id'] ?></p>
    <dl class="detail-grid">
        <dt>Email</dt><dd><a href="mailto:<?= esc($row['email']) ?>" style="color:var(--violet-soft)"><?= esc($row['email']) ?></a></dd>
        <dt>Phone</dt><dd><?= esc($row['phone'] !== '' ? $row['phone'] : '—') ?></dd>
        <dt>Company</dt><dd><?= esc($row['company'] !== '' ? $row['company'] : '—') ?></dd>
        <dt>Service</dt><dd><?= esc($row['service'] !== '' ? $row['service'] : '—') ?></dd>
        <dt>Budget</dt><dd><?= esc($row['budget'] !== '' ? $row['budget'] : '—') ?></dd>
        <dt>Email notification</dt><dd><?= esc($row['notification_status'] ?? 'unknown') ?></dd>
        <dt>Source</dt><dd><?= esc($row['source'] !== '' ? $row['source'] : '—') ?></dd>
        <dt>IP / Agent</dt><dd class="td-sub"><?= esc($row['ip_address']) ?> · <?= esc(mb_substr((string) $row['user_agent'], 0, 90)) ?></dd>
        <dt>Message</dt><dd style="white-space:pre-wrap"><?= esc($row['message']) ?></dd>
    </dl>
    <form method="post" action="actions.php" data-modal-form style="margin-top:22px;display:grid;gap:14px">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_submission">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <div class="a-field" style="margin:0">
            <label for="status-<?= (int) $row['id'] ?>">Status</label>
            <select id="status-<?= (int) $row['id'] ?>" name="status">
                <?php foreach ($statusOptions as $key => $label): ?>
                <option value="<?= esc($key) ?>"<?= $row['status'] === $key ? ' selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="a-field" style="margin:0">
            <label for="notes-<?= (int) $row['id'] ?>">Internal notes</label>
            <textarea id="notes-<?= (int) $row['id'] ?>" name="notes"><?= esc($row['notes']) ?></textarea>
        </div>
        <div class="a-toolbar">
            <button class="a-btn primary" type="submit">Save Changes</button>
            <a class="a-btn" href="mailto:<?= esc($row['email']) ?>?subject=<?= rawurlencode('Re: your enquiry to ' . getSetting('site_name', SITE_NAME)) ?>">Reply by Email</a>
            <button class="a-btn danger" type="submit" name="action" value="delete_submission" data-confirm="Delete this submission permanently?">Delete</button>
        </div>
    </form>
</template>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function selectedIds() {
        return Array.prototype.map.call(document.querySelectorAll('input.row-check:checked'), function (cb) { return cb.value; }).join(',');
    }
    function postAction(action, ids) {
        var payload = new FormData();
        payload.append('action', action);
        payload.append('ids', ids);
        var token = document.querySelector('#bulkForm input[name="csrf_token"]');
        if (token) payload.append('csrf_token', token.value);
        fetch('actions.php', { method: 'POST', body: payload })
          .then(function (r) { return r.json(); })
          .then(function () { window.location.reload(); });
    }
    var read = document.getElementById('bulkRead');
    if (read) read.addEventListener('click', function () { postAction('bulk_mark_read', selectedIds()); });
    var del = document.getElementById('bulkDelete');
    if (del) del.addEventListener('click', function () {
        if (window.confirm('Delete the selected submissions permanently?')) postAction('bulk_delete', selectedIds());
    });
    var exp = document.getElementById('bulkExport');
    if (exp) exp.addEventListener('click', function () {
        window.location.href = 'export.php?type=submissions&ids=' + selectedIds();
    });
    <?php if ($viewId > 0): ?>
    var trigger = document.querySelector('[data-modal="sub-modal-<?= (int) $viewId ?>"]');
    if (trigger) trigger.click();
    <?php endif; ?>
});
</script>

<?php
/* ---------------- Alia leads captured from the site widget ---------------- */
$aliaLeads = dbAll('SELECT * FROM chatbot_leads ORDER BY created_at DESC LIMIT 50');
?>
<div class="a-card" style="margin-top:26px">
    <h3>Alia leads <span class="hint" style="font-weight:400">(captured by the growth assistant widget — latest 50)</span></h3>
    <?php if ($aliaLeads): ?>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Name</th><th>Email</th><th>Session</th><th>Captured</th></tr></thead>
            <tbody>
                <?php foreach ($aliaLeads as $aliaLead): ?>
                <tr>
                    <td><?= esc($aliaLead['name'] !== '' ? $aliaLead['name'] : '—') ?></td>
                    <td><?php if ($aliaLead['email'] !== ''): ?><a href="mailto:<?= esc($aliaLead['email']) ?>" style="color:var(--violet-soft)"><?= esc($aliaLead['email']) ?></a><?php else: ?>—<?php endif; ?></td>
                    <td class="mono" style="font-size:.72rem;color:var(--muted)"><?= esc($aliaLead['session_id']) ?></td>
                    <td><?= esc($aliaLead['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="hint">No leads captured by Alia yet. When a visitor shares their name or email with her, it lands here.</p>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
