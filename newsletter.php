<?php
/**
 * ---------------------------------------------------------------------------
 *  Newsletter endpoint
 *    POST {name, email}  -> subscribe (AJAX JSON) + welcome email
 *    GET  ?unsubscribe=  -> one-click unsubscribe
 * ---------------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/email-templates.php';
require_once BASE_PATH . '/core/Captcha.php';

/* ------------------------------ unsubscribe ------------------------------ */
if (isset($_GET['unsubscribe'])) {
    $email = sanitize($_GET['unsubscribe']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        dbExec('UPDATE newsletter_subscribers SET is_active = 0 WHERE email = ?', array($email));
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Unsubscribed</title><style>body{background:#08080a;color:#f4f4f6;font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center}a{color:#a78bfa}</style></head>'
        . '<body><div><h1>You&rsquo;re unsubscribed.</h1><p style="color:#9a9aa7;margin:12px 0 28px">No hard feelings — the door is always open.</p>'
        . '<p><a href="' . esc(url('')) . '">Back to the website</a></p></div></body></html>';
    exit;
}

/* -------------------------------- subscribe ------------------------------ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('resources'));
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

if (!validateCSRF()) {
    echo json_encode(array('success' => false, 'message' => 'Your session expired — please refresh and try again.'));
    exit;
}

$name  = sanitize(isset($_POST['name']) ? $_POST['name'] : '');
$email = sanitize(isset($_POST['email']) ? $_POST['email'] : '');

if (!Ratelimit::allow('newsletter:' . pieClientIp(), 5, 600)) {
    echo json_encode(array('success' => false, 'message' => 'Too many attempts — please try again in a few minutes.'));
    exit;
}

if (!Captcha::verify(Captcha::tokenFromRequest(), pieClientIp())) {
    echo json_encode(array('success' => false, 'message' => 'Please complete the security check and try again.', 'errors' => array('captcha' => 'CAPTCHA verification failed.')));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(array('success' => false, 'message' => 'Please enter a valid email address.'));
    exit;
}

$existing = dbOne('SELECT id, is_active FROM newsletter_subscribers WHERE email = ?', array($email));
if ($existing) {
    if ((int) $existing['is_active'] === 0) {
        dbExec('UPDATE newsletter_subscribers SET is_active = 1, name = COALESCE(NULLIF(?, ""), name) WHERE id = ?', array($name, (int) $existing['id']));
        echo json_encode(array('success' => true, 'message' => 'Welcome back! You\'re on the list again.'));
    } else {
        echo json_encode(array('success' => true, 'message' => 'You\'re already subscribed — watch your inbox for the next growth letter.'));
    }
    exit;
}

$ok = dbInsert('INSERT INTO newsletter_subscribers (email, name, is_active) VALUES (?, ?, 1)', array($email, $name));
if ($ok < 0) {
    echo json_encode(array('success' => false, 'message' => 'Could not save your subscription. Please try again later.'));
    exit;
}

try {
    sendEmail($email, 'Welcome to the Growth Letter', emailNewsletterWelcome($name, $email));
} catch (Throwable $e) {
    error_log('[TPT] Newsletter mail error: ' . $e->getMessage());
}

echo json_encode(array('success' => true, 'message' => 'You\'re on the list! Check your inbox for a welcome email.'));
