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

    case 'test_gemini':
        $apiKey = getSetting('gemini_api_key');
        if ($apiKey === '') { actionJson(false, 'No API key saved yet — save your settings first.'); }
        $payload = array(
            'contents'         => array(array('role' => 'user', 'parts' => array(array('text' => 'Reply with exactly: Alia connection OK')))),
            'generationConfig' => array('maxOutputTokens' => 40, 'temperature' => 0.2),
        );
        $decoded = chatbotCallGemini($apiKey, $payload);
        $reply = null;
        if (is_array($decoded) && isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            $reply = trim($decoded['candidates'][0]['content']['parts'][0]['text']);
        }
        if ($reply !== null && $reply !== '') {
            actionJson(true, 'Gemini responded.', array('reply' => $reply));
        }
        $errMsg = is_array($decoded) && isset($decoded['error']['message']) ? $decoded['error']['message'] : 'No response from the API.';
        actionJson(false, 'Connection failed: ' . $errMsg);
        break;

    default:
        actionJson(false, 'Unknown action.');
}
