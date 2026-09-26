<?php
require BASE_PATH . '/includes/admin-header.php';
?>
<div class="a-card"><p>Source: Alia. <a href="<?= esc(url('admin/submissions')) ?>">Contact and website leads</a> remain in the existing submissions dashboard.</p>
<form method="get" class="a-filters"><input name="q" value="<?= esc($q) ?>" placeholder="Search name/email"><select name="status"><option value="">All statuses</option><?php foreach ($statuses as $choice): ?><option<?= $choice === $status ? ' selected' : '' ?>><?= esc($choice) ?></option><?php endforeach; ?></select><button class="a-btn">Filter</button><button class="a-btn" name="export" value="1">Export CSV (up to 5,000)</button></form></div>
<?php foreach ($rows as $row): ?>
<div class="a-card"><h3><?= esc($row['name']) ?></h3><p><?= esc($row['created_at'] . ' · ' . $row['email']) ?></p>
<p><?= esc(implode(' · ', array_filter(array($row['phone'] ?? '', $row['company'] ?? '', $row['service'] ?? '')))) ?></p>
<details><summary>Conversation snapshot</summary><pre style="white-space:pre-wrap"><?= esc($row['conversation'] ?? 'No snapshot recorded.') ?></pre></details>
<form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><div class="a-field"><label>Status</label><select name="status"><?php foreach ($statuses as $choice): ?><option<?= $choice === ($row['status'] ?? 'new') ? ' selected' : '' ?>><?= esc($choice) ?></option><?php endforeach; ?></select></div>
<div class="a-field"><label>Notes</label><textarea name="notes" maxlength="10000"><?= esc($row['notes'] ?? '') ?></textarea></div><button class="a-btn primary">Save</button> <button class="a-btn" name="delete" value="1" onclick="return confirm('Delete this lead permanently?')">Delete</button></form></div>
<?php endforeach; ?>
<div class="a-toolbar"><?php if ($page > 1): ?><a class="a-btn" href="?<?= esc(http_build_query(array('page'=>$page-1,'q'=>$q,'status'=>$status))) ?>">Previous</a><?php endif; ?><?php if (count($rows) === 25): ?><a class="a-btn" href="?<?= esc(http_build_query(array('page'=>$page+1,'q'=>$q,'status'=>$status))) ?>">Next</a><?php endif; ?></div>
<?php require BASE_PATH . '/includes/admin-footer.php'; ?>
