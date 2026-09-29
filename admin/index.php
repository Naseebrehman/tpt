<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'dashboard';
$adminTitle = 'Dashboard';
$adminLibs  = array('chart' => true);

/* ------------------------------- stats ---------------------------------- */
$today     = dbOne("SELECT COUNT(*) c FROM contact_submissions WHERE DATE(created_at) = CURDATE()");
$week      = dbOne("SELECT COUNT(*) c FROM contact_submissions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$total     = dbOne("SELECT COUNT(*) c FROM contact_submissions");
$unread    = dbOne("SELECT COUNT(*) c FROM contact_submissions WHERE status = 'new'");
$statToday  = $today ? (int) $today['c'] : 0;
$statWeek   = $week ? (int) $week['c'] : 0;
$statTotal  = $total ? (int) $total['c'] : 0;
$statUnread = $unread ? (int) $unread['c'] : 0;

/* --------------------- submissions per day (30 days) --------------------- */
$perDay = dbAll(
    "SELECT DATE(created_at) d, COUNT(*) c FROM contact_submissions
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY DATE(created_at) ORDER BY d ASC"
);
$dayMap = array();
foreach ($perDay as $row) { $dayMap[$row['d']] = (int) $row['c']; }
$labels = array();
$values = array();
for ($i = 29; $i >= 0; $i--) {
    $d        = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('j M', strtotime($d));
    $values[] = isset($dayMap[$d]) ? $dayMap[$d] : 0;
}

/* ------------------------ submissions by service ------------------------ */
$byService = dbAll(
    "SELECT COALESCE(NULLIF(service,''), 'Unspecified') s, COUNT(*) c FROM contact_submissions
     GROUP BY s ORDER BY c DESC LIMIT 8"
);
$svcLabels = array();
$svcValues = array();
foreach ($byService as $row) { $svcLabels[] = $row['s']; $svcValues[] = (int) $row['c']; }

/* --------------------------- recent submissions ------------------------- */
$recent = dbAll('SELECT * FROM contact_submissions ORDER BY created_at DESC LIMIT 10');

/* ----------------------------- system status ---------------------------- */
$smtpOk    = getSetting('smtp_host') !== '';
$geminiOk  = getSetting('gemini_api_key') !== '';
$mysqlOk   = DB_OK;
$phpVersion = PHP_VERSION;

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-grid cols-4">
    <div class="stat-tile"><div class="label">New Submissions Today</div><div class="value"><?= $statToday ?></div></div>
    <div class="stat-tile"><div class="label">This Week</div><div class="value"><?= $statWeek ?></div></div>
    <div class="stat-tile"><div class="label">Total All Time</div><div class="value"><?= $statTotal ?></div></div>
    <div class="stat-tile"><div class="label">Unread</div><div class="value"><?= $statUnread ?><?php if ($statUnread > 0): ?> <small>need replies</small><?php endif; ?></div></div>
</div>

<div class="a-grid cols-2">
    <div class="a-card">
        <h3>Submissions — last 30 days</h3>
        <div class="chart-box"><canvas data-chart="<?= esc(json_encode(array('type' => 'line', 'data' => array('labels' => $labels, 'datasets' => array(array('label' => 'Submissions', 'data' => $values)))))) ?>"></canvas></div>
    </div>
    <div class="a-card">
        <h3>Submissions by service</h3>
        <div class="chart-box"><canvas data-chart="<?= esc(json_encode(array('type' => 'bar', 'data' => array('labels' => $svcLabels, 'datasets' => array(array('label' => 'Submissions', 'data' => $svcValues)))))) ?>"></canvas></div>
    </div>
</div>

<div class="a-card">
    <h3>Recent submissions</h3>
    <div class="a-table-wrap">
        <table class="a-table">
            <thead><tr><th>Name</th><th>Email</th><th>Service</th><th>Date</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$recent): ?>
                <tr><td colspan="6" class="text-muted">No submissions yet — share your website link to start collecting enquiries.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $row): ?>
            <tr>
                <td class="td-main"><?= esc($row['name']) ?></td>
                <td><?= esc($row['email']) ?></td>
                <td><?= esc($row['service'] !== '' ? $row['service'] : '—') ?></td>
                <td class="td-sub"><?= esc(formatDate($row['created_at'], 'j M, H:i')) ?></td>
                <td><span class="badge <?= esc($row['status']) ?>"><?= esc(str_replace('_', ' ', $row['status'])) ?></span></td>
                <td style="text-align:right"><a class="a-btn small" href="submissions.php?view=<?= (int) $row['id'] ?>">View</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="a-card">
    <h3>Quick links</h3>
    <div class="quick-grid">
        <a href="submissions.php"><?= icon('mail', 20) ?>Manage Submissions</a>
        <a href="media.php"><?= icon('image', 20) ?>Media Library</a>
        <a href="admins.php"><?= icon('shield', 20) ?>Admin Management</a>
        <a href="payments.php"><?= icon('card', 20) ?>Payment Settings</a>
        <a href="settings.php"><?= icon('cpu', 20) ?>Settings</a>
    </div>
</div>

<div class="a-card">
    <h3>System status</h3>
    <ul class="status-list">
        <li><span>SMTP email delivery</span><span class="<?= $smtpOk ? 'ok' : 'bad' ?>"><?= $smtpOk ? '✅ Connected (' . esc(getSetting('smtp_host')) . ')' : '❌ Not configured' ?></span></li>
        <li><span>Gemini API (Alia)</span><span class="<?= $geminiOk ? 'ok' : 'bad' ?>"><?= $geminiOk ? '✅ Key set' : '❌ Missing' ?></span></li>
        <li><span>PHP version</span><span class="ok"><?= esc($phpVersion) ?></span></li>
        <li><span>MySQL database</span><span class="<?= $mysqlOk ? 'ok' : 'bad' ?>"><?= $mysqlOk ? '✅ Connected' : '❌ Not connected — check includes/config.php' ?></span></li>
        <li><span>Maintenance mode</span><span class="<?= getSetting('maintenance_mode', '0') === '1' ? 'bad' : 'ok' ?>"><?= getSetting('maintenance_mode', '0') === '1' ? '⚠️ ON — visitors see maintenance page' : '✅ Off' ?></span></li>
    </ul>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
