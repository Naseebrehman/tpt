<?php
/**
 * ---------------------------------------------------------------------------
 *  Admin → Alia → Chats
 * ---------------------------------------------------------------------------
 *  Conversations captured by the Alia growth assistant, listed one by one with
 *  customer/identifier, date, time, status, last message and actions
 *  (View · Edit · Change status · Delete). Opening a conversation shows the
 *  complete transcript chronologically.
 *  All mutations require a logged-in admin plus a valid CSRF token.
 * --------------------------------------------------------------------------- */
require_once dirname(__DIR__) . '/includes/init.php';
require_once BASE_PATH . '/core/Schema.php';
Schema::ensure();
requireAdmin();

$adminPage  = 'chats';
$adminTitle = 'Alia Chats';

$chatStatuses = array(
    'new'         => 'New',
    'in_progress' => 'In Progress',
    'replied'     => 'Replied',
    'closed'      => 'Closed',
);

$hasMessagesTable = Schema::hasTable('chatbot_messages');
$hasUpdatedAt     = Schema::hasColumn('chatbot_leads', 'updated_at');
$hasCount         = Schema::hasColumn('chatbot_leads', 'message_count');
$hasLastMessage   = Schema::hasColumn('chatbot_leads', 'last_message');

/** Column list that survives an install where the migration has not run. */
function chatsSelectColumns()
{
    $cols = array('id', 'session_id', 'name', 'email', 'phone', 'company', 'service', 'status', 'notes', 'conversation', 'created_at');
    if (Schema::hasColumn('chatbot_leads', 'updated_at'))    { $cols[] = 'updated_at'; }
    if (Schema::hasColumn('chatbot_leads', 'message_count')) { $cols[] = 'message_count'; }
    if (Schema::hasColumn('chatbot_leads', 'last_message'))  { $cols[] = 'last_message'; }
    return $cols;
}

/* ------------------------------- actions -------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired — refresh the page and try again.');
    } else {
        $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $action = isset($_POST['chat_action']) ? (string) $_POST['chat_action'] : '';
        $chat   = $id > 0 ? dbOne('SELECT * FROM chatbot_leads WHERE id = ?', array($id)) : null;

        if (!$chat) {
            setFlash('err', 'Conversation not found.');
        } elseif ($action === 'delete') {
            if ($hasMessagesTable) {
                dbExec('DELETE FROM chatbot_messages WHERE session_id = ?', array($chat['session_id']));
            }
            $ok = dbExec('DELETE FROM chatbot_leads WHERE id = ?', array($id));
            setFlash($ok >= 0 ? 'ok' : 'err', $ok >= 0 ? 'Conversation deleted.' : 'Could not delete the conversation.');
            if ($ok >= 0) {
                header('Location: chats.php');
                exit;
            }
        } elseif ($action === 'set_status') {
            $status = isset($_POST['status']) ? (string) $_POST['status'] : '';
            if (!isset($chatStatuses[$status])) {
                setFlash('err', 'Unknown status.');
            } else {
                $ok = dbExec('UPDATE chatbot_leads SET status = ? WHERE id = ?', array($status, $id));
                setFlash($ok >= 0 ? 'ok' : 'err', $ok >= 0 ? 'Status changed to “' . $chatStatuses[$status] . '”.' : 'Could not change the status.');
            }
        } elseif ($action === 'update') {
            $name  = mb_substr(sanitize(isset($_POST['name']) ? $_POST['name'] : ''), 0, 150);
            $email = mb_substr(sanitize(isset($_POST['email']) ? $_POST['email'] : ''), 0, 150);
            $notes = mb_substr(sanitizeMultiline(isset($_POST['notes']) ? $_POST['notes'] : ''), 0, 10000);
            $status = isset($_POST['status']) ? (string) $_POST['status'] : $chat['status'];
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                setFlash('err', 'Enter a valid email address (or leave it empty).');
            } elseif (!isset($chatStatuses[$status])) {
                setFlash('err', 'Unknown status.');
            } else {
                $ok = dbExec(
                    'UPDATE chatbot_leads SET name = ?, email = ?, notes = ?, status = ? WHERE id = ?',
                    array($name, $email, $notes, $status, $id)
                );
                setFlash($ok >= 0 ? 'ok' : 'err', $ok >= 0 ? 'Conversation updated.' : 'Could not save the changes.');
            }
        }
    }
    /* Stay on the same screen (list or detail) after the action. */
    $back = 'chats.php';
    if (isset($_POST['back']) && (string) $_POST['back'] !== '') {
        $back = 'chats.php?' . ltrim((string) $_POST['back'], '?&');
    }
    header('Location: ' . $back);
    exit;
}

/* -------------------------------- filters ------------------------------- */
$search  = mb_substr(sanitize(isset($_GET['q']) ? $_GET['q'] : ''), 0, 150);
$fStatus = isset($_GET['status']) ? (string) $_GET['status'] : '';
if (!isset($chatStatuses[$fStatus])) { $fStatus = ''; }
$page    = max(1, (int) (isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;
$viewId  = isset($_GET['view']) ? (int) $_GET['view'] : 0;

$where  = array('1=1');
$params = array();
if ($search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR session_id LIKE ?' . ($hasLastMessage ? ' OR last_message LIKE ?' : '') . ')';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    if ($hasLastMessage) { $params[] = $like; }
}
if ($fStatus !== '') { $where[] = 'status = ?'; $params[] = $fStatus; }
$whereSql = implode(' AND ', $where);

$orderBy = $hasUpdatedAt ? 'updated_at DESC, created_at DESC' : 'created_at DESC';

$countRow = dbOne('SELECT COUNT(*) AS c FROM chatbot_leads WHERE ' . $whereSql, $params);
$total    = $countRow ? (int) $countRow['c'] : 0;
$pages    = max(1, (int) ceil($total / $perPage));
$page     = min($page, $pages);
$offset   = ($page - 1) * $perPage;

$chats = dbAll(
    'SELECT ' . implode(', ', chatsSelectColumns()) . ' FROM chatbot_leads WHERE ' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);

/* ------------------------------- detail --------------------------------- */
$viewChat = null;
$turns    = array();
if ($viewId > 0) {
    $viewChat = dbOne('SELECT ' . implode(', ', chatsSelectColumns()) . ' FROM chatbot_leads WHERE id = ?', array($viewId));
    if ($viewChat) {
        $sessionId = $viewChat['session_id'];
        if ($hasMessagesTable) {
            $rows = dbAll('SELECT role, content, created_at FROM chatbot_messages WHERE session_id = ? ORDER BY id ASC, created_at ASC LIMIT 500', array($sessionId));
            foreach ($rows as $row) {
                $turns[] = array(
                    'role'    => $row['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => (string) $row['content'],
                    'time'    => (string) $row['created_at'],
                );
            }
        }
        /* Fallback for conversations recorded before the transcript table existed. */
        if (!$turns && !empty($viewChat['conversation'])) {
            $legacy = json_decode((string) $viewChat['conversation'], true);
            if (is_array($legacy)) {
                foreach ($legacy as $turn) {
                    if (!is_array($turn)) { continue; }
                    $content = isset($turn['content']) ? (string) $turn['content'] : '';
                    if ($content === '' && isset($turn['parts'][0]['text'])) { $content = (string) $turn['parts'][0]['text']; }
                    if ($content === '') { continue; }
                    $turns[] = array(
                        'role'    => isset($turn['role']) && ($turn['role'] === 'model' || $turn['role'] === 'assistant') ? 'assistant' : 'user',
                        'content' => $content,
                        'time'    => '',
                    );
                }
            }
        }
    }
}

/** Display name for a conversation: real name, email, or a session identifier. */
function chatIdentity($chat)
{
    $name = trim((string) ($chat['name'] ?? ''));
    if ($name !== '') { return $name; }
    $email = trim((string) ($chat['email'] ?? ''));
    if ($email !== '') { return $email; }
    $session = (string) ($chat['session_id'] ?? '');
    return 'Visitor #' . mb_substr($session, 0, 8);
}

function chatActivityTime($chat)
{
    foreach (array('updated_at', 'created_at') as $key) {
        if (!empty($chat[$key]) && $chat[$key] !== '0000-00-00 00:00:00') {
            return (string) $chat[$key];
        }
    }
    return '';
}

$baseQuery = array_filter(array('q' => $search, 'status' => $fStatus, 'page' => $page > 1 ? $page : ''), function ($v) { return $v !== ''; });

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-toolbar" style="margin-bottom:6px">
    <a class="a-btn" href="leads.php"><?= icon('users', 16) ?> Alia Leads</a>
    <a class="a-btn primary" href="chats.php"><?= icon('chat', 16) ?> Alia Chats</a>
    <span class="spacer"></span>
    <span class="text-muted" style="font-size:.82rem"><?= (int) $total ?> conversation<?= $total === 1 ? '' : 's' ?></span>
</div>

<?php if ($viewChat): ?>
<!-- ============================ SINGLE CONVERSATION ===================== -->
<div class="a-card">
    <div class="a-toolbar" style="align-items:flex-start;gap:16px">
        <div>
            <h3 style="margin-bottom:4px"><?= esc(chatIdentity($viewChat)) ?></h3>
            <p class="text-muted" style="font-size:.82rem;margin:0">
                <?= esc(formatDate($viewChat['created_at'], 'j M Y')) ?> at <?= esc(formatDate($viewChat['created_at'], 'H:i')) ?>
                · <?= (int) ($viewChat['message_count'] ?? count($turns)) ?> message<?= (int) ($viewChat['message_count'] ?? count($turns)) === 1 ? '' : 's' ?>
                · session <span class="mono"><?= esc(mb_substr((string) $viewChat['session_id'], 0, 16)) ?></span>
            </p>
            <?php if (!empty($viewChat['email'])): ?>
            <p class="text-muted" style="font-size:.82rem;margin:4px 0 0">
                <a href="mailto:<?= esc($viewChat['email']) ?>" style="color:var(--violet-soft)"><?= esc($viewChat['email']) ?></a>
                <?php if (!empty($viewChat['phone'])): ?> · <?= esc($viewChat['phone']) ?><?php endif; ?>
                <?php if (!empty($viewChat['company'])): ?> · <?= esc($viewChat['company']) ?><?php endif; ?>
                <?php if (!empty($viewChat['service'])): ?> · <?= esc($viewChat['service']) ?><?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
        <span class="spacer"></span>
        <span class="badge <?= esc($viewChat['status']) ?>"><?= esc($chatStatuses[$viewChat['status']] ?? ucfirst((string) $viewChat['status'])) ?></span>
        <a class="a-btn small" href="chats.php?<?= esc(http_build_query($baseQuery)) ?>"><?= icon('close', 14) ?> Back to list</a>
    </div>

    <div class="chat-transcript" style="margin-top:18px">
        <?php if (!$turns): ?>
        <p class="text-muted">No messages recorded for this conversation yet.</p>
        <?php else: ?>
        <?php foreach ($turns as $turn): ?>
        <div class="chat-line <?= $turn['role'] === 'assistant' ? 'assistant' : 'user' ?>">
            <div class="chat-line-head">
                <span class="chat-line-who"><?= $turn['role'] === 'assistant' ? esc(getSetting('chatbot_name', 'Alia')) : esc(chatIdentity($viewChat)) ?></span>
                <?php if ($turn['time'] !== ''): ?><span class="chat-line-time"><?= esc(formatDate($turn['time'], 'j M Y, H:i')) ?></span><?php endif; ?>
            </div>
            <div class="chat-line-body"><?= nl2br(esc($turn['content'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="a-toolbar" style="margin-top:20px;gap:10px;flex-wrap:wrap">
        <form method="post" style="display:inline-flex;gap:8px;align-items:center">
            <?= csrfField() ?>
            <input type="hidden" name="chat_action" value="set_status">
            <input type="hidden" name="id" value="<?= (int) $viewChat['id'] ?>">
            <input type="hidden" name="back" value="<?= esc(http_build_query(array_merge($baseQuery, array('view' => (int) $viewChat['id'])))) ?>">
            <select name="status" aria-label="Change status">
                <?php foreach ($chatStatuses as $key => $label): ?>
                <option value="<?= esc($key) ?>"<?= $viewChat['status'] === $key ? ' selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="a-btn small" type="submit">Change status</button>
        </form>
        <form method="post" style="display:inline" data-confirm="Delete this conversation permanently?">
            <?= csrfField() ?>
            <input type="hidden" name="chat_action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $viewChat['id'] ?>">
            <input type="hidden" name="back" value="<?= esc(http_build_query($baseQuery)) ?>">
            <button class="a-btn small danger" type="submit"><?= icon('close', 14) ?> Delete</button>
        </form>
        <?php if (!empty($viewChat['email'])): ?>
        <a class="a-btn small" href="mailto:<?= esc($viewChat['email']) ?>?subject=<?= rawurlencode('Re: your conversation with ' . getSetting('site_name', SITE_NAME)) ?>">Reply by email</a>
        <?php endif; ?>
    </div>

    <form method="post" style="margin-top:20px;display:grid;gap:14px">
        <?= csrfField() ?>
        <input type="hidden" name="chat_action" value="update">
        <input type="hidden" name="id" value="<?= (int) $viewChat['id'] ?>">
        <input type="hidden" name="back" value="<?= esc(http_build_query(array_merge($baseQuery, array('view' => (int) $viewChat['id'])))) ?>">
        <h3 style="margin:0">Edit conversation</h3>
        <div class="a-field-row">
            <div class="a-field">
                <label for="chatName">Customer name</label>
                <input id="chatName" name="name" type="text" maxlength="150" value="<?= esc((string) $viewChat['name']) ?>" placeholder="Visitor name (optional)">
            </div>
            <div class="a-field">
                <label for="chatEmail">Email</label>
                <input id="chatEmail" name="email" type="email" maxlength="150" value="<?= esc((string) $viewChat['email']) ?>" placeholder="name@company.com (optional)">
            </div>
        </div>
        <div class="a-field">
            <label for="chatNotes">Internal notes</label>
            <textarea id="chatNotes" name="notes" maxlength="10000" style="min-height:90px"><?= esc((string) $viewChat['notes']) ?></textarea>
        </div>
        <div class="a-toolbar">
            <button class="a-btn primary" type="submit"><?= icon('check', 15) ?> Save changes</button>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ============================== ALL CHATS ============================= -->
<form class="a-filters" method="get" action="chats.php">
    <input type="search" name="q" value="<?= esc($search) ?>" placeholder="Search name, email, session or message…" style="min-width:240px">
    <select name="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <?php foreach ($chatStatuses as $key => $label): ?>
        <option value="<?= esc($key) ?>"<?= $fStatus === $key ? ' selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="a-btn small" type="submit">Apply</button>
    <?php if ($search !== '' || $fStatus !== ''): ?><a class="a-btn small" href="chats.php">Reset</a><?php endif; ?>
</form>

<div class="a-table-wrap">
    <table class="a-table">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Date</th>
                <th>Time</th>
                <th style="text-align:center">Messages</th>
                <th>Status</th>
                <th>Last message</th>
                <th style="text-align:right">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$chats): ?>
            <tr><td colspan="7" class="text-muted">No conversations yet. When a visitor chats with <?= esc(getSetting('chatbot_name', 'Alia')) ?>, the conversation appears here.</td></tr>
        <?php endif; ?>
        <?php foreach ($chats as $chat): ?>
        <?php
            $activity = chatActivityTime($chat);
            $count    = (int) ($chat['message_count'] ?? 0);
            $last     = trim((string) ($chat['last_message'] ?? ''));
            if ($last === '' && $count === 0) { $last = ''; }
        ?>
        <tr>
            <td class="td-main">
                <a href="chats.php?<?= esc(http_build_query(array_merge($baseQuery, array('view' => (int) $chat['id'])))) ?>" style="color:inherit">
                    <?= esc(chatIdentity($chat)) ?>
                </a>
                <?php if (!empty($chat['email'])): ?><div class="td-sub"><?= esc($chat['email']) ?></div><?php endif; ?>
            </td>
            <td class="td-sub"><?= esc(formatDate($chat['created_at'], 'j M Y')) ?></td>
            <td class="td-sub"><?= esc(formatDate($activity !== '' ? $activity : $chat['created_at'], 'H:i')) ?></td>
            <td style="text-align:center"><?= $count ?></td>
            <td><span class="badge <?= esc($chat['status']) ?>"><?= esc($chatStatuses[$chat['status']] ?? ucfirst((string) $chat['status'])) ?></span></td>
            <td class="td-sub" style="max-width:320px"><?= $last !== '' ? esc(mb_substr($last, 0, 120)) . (mb_strlen($last) > 120 ? '…' : '') : '<span class="text-muted">—</span>' ?></td>
            <td>
                <div class="row-actions">
                    <a class="a-btn small" href="chats.php?<?= esc(http_build_query(array_merge($baseQuery, array('view' => (int) $chat['id'])))) ?>">View</a>
                    <form method="post" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="chat_action" value="set_status">
                        <input type="hidden" name="id" value="<?= (int) $chat['id'] ?>">
                        <input type="hidden" name="back" value="<?= esc(http_build_query($baseQuery)) ?>">
                        <select name="status" aria-label="Change status" style="padding:5px 8px;font-size:.76rem" onchange="this.form.submit()">
                            <?php foreach ($chatStatuses as $key => $label): ?>
                            <option value="<?= esc($key) ?>"<?= $chat['status'] === $key ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <form method="post" style="display:inline" data-confirm="Delete this conversation permanently?">
                        <?= csrfField() ?>
                        <input type="hidden" name="chat_action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $chat['id'] ?>">
                        <input type="hidden" name="back" value="<?= esc(http_build_query($baseQuery)) ?>">
                        <button class="a-btn small danger" type="submit">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
<nav class="a-toolbar" style="justify-content:center;margin-top:18px" aria-label="Pagination">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
    <a class="a-btn small<?= $p === $page ? ' primary' : '' ?>" href="?<?= esc(http_build_query(array('q' => $search, 'status' => $fStatus, 'page' => $p))) ?>"><?= $p ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>
<?php endif; ?>

<p class="hint" style="margin-top:14px">
    Every conversation is stored with its full transcript. Visitors who only ask questions are listed by session id;
    once they share a name or email the conversation shows their details. Leads with contact details also appear under <a href="leads.php" style="color:var(--violet-soft)">Alia Leads</a>.
</p>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
