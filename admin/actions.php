<?php
/**
 * ---------------------------------------------------------------------------
 *  Admin AJAX / POST action router. Every action requires login + CSRF.
 * ---------------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/email-templates.php';
require_once dirname(__DIR__) . '/includes/chatbot-api.php';
requireAdmin();

function actionJson($ok, $message, $extra = array())
{
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(array('success' => $ok, 'message' => $message), $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    actionJson(false, 'Invalid request.');
}
if (!validateCSRF()) {
    actionJson(false, 'Security token expired — refresh the page.');
}

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
$id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;

switch ($action) {
    case 'seed_samples':
        if (!DB_OK) { actionJson(false, 'Connect the database first.'); }
        try { $added = SampleContent::seed($GLOBALS['pdo']); actionJson(true, $added . ' sample records added. Existing content was preserved.'); }
        catch (Throwable $error) { error_log('[TPT] Sample content import failed.'); actionJson(false, 'Could not add samples. Check the database schema.'); }
        break;

    /* ------------------------- submissions ------------------------- */
    case 'update_submission':
        $status = isset($_POST['status']) ? (string) $_POST['status'] : '';
        $notes  = sanitizeMultiline(isset($_POST['notes']) ? $_POST['notes'] : '');
        $allowed = array('new', 'in_progress', 'replied', 'closed');
        if (!in_array($status, $allowed, true)) { actionJson(false, 'Invalid status.'); }
        $ok = dbExec('UPDATE contact_submissions SET status = ?, notes = ? WHERE id = ?', array($status, $notes, $id));
        actionJson($ok >= 0, $ok >= 0 ? 'Submission updated.' : 'Update failed.');
        break;

    case 'bulk_mark_read':
        $ids = isset($_POST['ids']) ? array_map('intval', explode(',', (string) $_POST['ids'])) : array();
        if (!$ids) { actionJson(false, 'Nothing selected.'); }
        $in = implode(',', $ids);
        dbExec("UPDATE contact_submissions SET status = 'in_progress' WHERE id IN ($in) AND status = 'new'");
        actionJson(true, 'Marked as read.');
        break;

    case 'bulk_delete':
        $ids = isset($_POST['ids']) ? array_map('intval', explode(',', (string) $_POST['ids'])) : array();
        if (!$ids) { actionJson(false, 'Nothing selected.'); }
        $in = implode(',', $ids);
        dbExec("DELETE FROM contact_submissions WHERE id IN ($in)");
        actionJson(true, 'Deleted selected submissions.');
        break;

    case 'delete_submission':
        dbExec('DELETE FROM contact_submissions WHERE id = ?', array($id));
        actionJson(true, 'Submission deleted.');
        break;

    /* --------------------------- entities -------------------------- */
    case 'toggle_active':
        $entity = isset($_POST['entity']) ? (string) $_POST['entity'] : '';
        $tables = array(
            'portfolio'    => 'portfolio',
            'team'         => 'team_members',
            'testimonials' => 'testimonials',
            'resources'    => 'resources',
            'subscribers'  => 'newsletter_subscribers',
        );
        if (!isset($tables[$entity])) { actionJson(false, 'Unknown entity.'); }
        dbExec('UPDATE ' . $tables[$entity] . ' SET is_active = 1 - is_active WHERE id = ?', array($id));
        actionJson(true, 'Status toggled.');
        break;

    case 'delete':
        $entity = isset($_POST['entity']) ? (string) $_POST['entity'] : '';
        $tables = array(
            'blog'         => 'blog_posts',
            'portfolio'    => 'portfolio',
            'team'         => 'team_members',
            'testimonials' => 'testimonials',
            'resources'    => 'resources',
            'subscribers'  => 'newsletter_subscribers',
            'comments'     => 'blog_comments',
        );
        if (!isset($tables[$entity])) { actionJson(false, 'Unknown entity.'); }
        dbExec('DELETE FROM ' . $tables[$entity] . ' WHERE id = ?', array($id));
        if ($entity === 'comments') {
            /* deleting a comment is moderation; nothing else to clean */
        }
        actionJson(true, 'Deleted.');
        break;

    case 'comment_status':
        $status = isset($_POST['status']) ? (string) $_POST['status'] : '';
        if (!in_array($status, array('pending', 'approved', 'spam'), true)) { actionJson(false, 'Invalid status.'); }
        dbExec('UPDATE blog_comments SET status = ? WHERE id = ?', array($status, $id));
        actionJson(true, 'Comment ' . $status . '.');
        break;

    case 'reorder':
        $entity = isset($_POST['entity']) ? (string) $_POST['entity'] : '';
        $tables = array('team' => 'team_members', 'portfolio' => 'portfolio');
        if (!isset($tables[$entity])) { actionJson(false, 'Unknown entity.'); }
        $order = isset($_POST['order']) ? array_map('intval', explode(',', (string) $_POST['order'])) : array();
        foreach ($order as $position => $itemId) {
            if ($itemId > 0) {
                dbExec('UPDATE ' . $tables[$entity] . ' SET display_order = ? WHERE id = ?', array($position, $itemId));
            }
        }
        actionJson(true, 'Order saved.');
        break;

    /* ------------------------- settings tests ---------------------- */
    case 'test_email':
        $to = isset($_POST['test_email_to']) ? sanitize($_POST['test_email_to']) : getSetting('site_email', ADMIN_EMAIL);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { actionJson(false, 'Enter a valid test email address.'); }
        $sent = sendEmail($to, 'Test email from The Pie Technologies dashboard',
            emailShell('<h1 style="margin:0 0 10px;font-size:24px;color:#fff">SMTP is working 🎉</h1><p style="color:#c7c7d1;line-height:1.7">This test email was sent from your admin dashboard at ' . date('j M Y, H:i') . ' UTC.</p>'));
        actionJson($sent, $sent ? 'Test email sent to ' . $to . '.' : ('Sending failed: ' . ($GLOBALS['pieMailError'] ?? 'Check SMTP settings.')));
        break;

    case 'test_notification':
        /* Send a real notification through the configured recipient list, so
           the admin can confirm the saved addresses actually receive mail. */
        require_once BASE_PATH . '/core/Notifications.php';
        $category = isset($_POST['notif_test_category']) ? sanitize($_POST['notif_test_category'])
            : (isset($_POST['category']) ? sanitize($_POST['category']) : 'contact');
        if (!isset(Notifications::categories()[$category])) { actionJson(false, 'Unknown notification category.'); }
        if (!Notifications::categoryEnabled($category)) {
            actionJson(false, 'The “' . $category . '” category is switched off — enable it first.');
        }
        $to = Notifications::recipientsFor($category);
        $sent = Notifications::notifyAdmins($category, 'system', array(
            'name'    => getSetting('site_name', SITE_NAME),
            'message' => 'This is a test notification from your ' . getSetting('site_name', SITE_NAME)
                . ' dashboard, sent on ' . date('j M Y, H:i') . ' UTC.',
            'subject' => 'Test notification — ' . getSetting('site_name', SITE_NAME),
        ), array('table' => EmailTemplates::detailTable(array(
            'Category' => ucfirst($category),
            'Recipients' => esc(implode(', ', $to)),
            'Sent at' => esc(date('j M Y, H:i') . ' UTC'),
        ))));
        actionJson(
            $sent,
            $sent
                ? 'Test notification sent to ' . implode(', ', $to) . '.'
                : 'Sending failed: ' . (isset($GLOBALS['pieMailError']) ? $GLOBALS['pieMailError'] : 'check your SMTP settings under Settings → Email / SMTP.')
        );
        break;

    case 'test_ai':
        /* Test one AI provider slot through the shared provider layer (Task 14). */
        require_once BASE_PATH . '/core/AIProviders.php';
        $slot = isset($_POST['ai_provider1_slot']) ? (int) $_POST['ai_provider1_slot'] : (isset($_POST['ai_provider2_slot']) ? (int) $_POST['ai_provider2_slot'] : AIProviders::activeSlot());
        $cfg  = AIProviders::config($slot);
        if (!$cfg['enabled']) { actionJson(false, 'Provider ' . $slot . ' is disabled — enable it and save first.'); }
        if ($cfg['api_key'] === '') { actionJson(false, 'No API key saved for provider ' . $slot . ' — save your settings first.'); }
        $gen = AIProviders::generation();
        $testTurn = array(array('role' => 'user', 'content' => 'Reply with exactly: connection OK'));
        $result = $cfg['type'] === 'gemini'
            ? AIProviders::callGemini($cfg, $testTurn, '', $gen)
            : AIProviders::callOpenAI($cfg, $testTurn, '', $gen);
        if ($result && $result['ok']) {
            actionJson(true, 'Provider ' . $cfg['slot'] . ' responded.', array('reply' => mb_substr($result['reply'], 0, 200)));
        }
        $errorLabels = array('auth' => 'Invalid API key.', 'rate_limit' => 'Rate limited by the provider — try again shortly.', 'timeout' => 'Connection timed out.', 'model' => 'Model not found — check the model name.', 'config' => 'Provider is not fully configured.', 'provider' => 'The provider returned an error.');
        $errCode = $result ? $result['error'] : 'provider';
        actionJson(false, 'Connection failed: ' . (isset($errorLabels[$errCode]) ? $errorLabels[$errCode] : $errorLabels['provider']));
        break;

    case 'test_gemini':
        /* Legacy alias — tests the active provider through the shared layer. */
        require_once BASE_PATH . '/core/AIProviders.php';
        $result = AIProviders::chat(array(array('role' => 'user', 'content' => 'Reply with exactly: Alia connection OK')), '');
        if ($result['ok']) {
            actionJson(true, 'Provider responded.', array('reply' => mb_substr($result['reply'], 0, 200)));
        }
        actionJson(false, 'Connection failed: ' . ($result['detail'] !== '' ? 'check the API key, model and quota.' : 'unknown error.'));
        break;

    default:
        actionJson(false, 'Unknown action.');
}
