<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'settings';
$adminTitle = 'Change Password';
$adminUser  = currentAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired.');
    } else {
        $current = isset($_POST['current_password']) ? (string) $_POST['current_password'] : '';
        $new     = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
        $confirm = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';

        if (!$adminUser || !password_verify($current, $adminUser['password_hash'])) {
            setFlash('err', 'Your current password is incorrect.');
        } elseif (mb_strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
            setFlash('err', 'New password must be 8+ characters with letters and numbers.');
        } elseif ($new !== $confirm) {
            setFlash('err', 'The confirmation does not match.');
        } else {
            dbExec('UPDATE admin_users SET password_hash = ? WHERE id = ?', array(password_hash($new, PASSWORD_BCRYPT), (int) $adminUser['id']));
            setFlash('ok', 'Password changed. Use it from your next login.');
            header('Location: password.php');
            exit;
        }
    }
}

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-card" style="max-width:520px">
    <h3>Change your password</h3>
    <form method="post">
        <?= csrfField() ?>
        <div class="a-field">
            <label for="cur">Current password</label>
            <input id="cur" name="current_password" type="password" required autocomplete="current-password">
        </div>
        <div class="a-field">
            <label for="new">New password</label>
            <input id="new" name="new_password" type="password" required minlength="8" autocomplete="new-password">
            <div class="hint">Minimum 8 characters, with letters and numbers.</div>
        </div>
        <div class="a-field">
            <label for="conf">Confirm new password</label>
            <input id="conf" name="confirm_password" type="password" required autocomplete="new-password">
        </div>
        <div class="a-toolbar">
            <button class="a-btn primary" type="submit"><?= icon('shield', 16) ?> Update Password</button>
            <a class="a-btn" href="settings.php">Back to Settings</a>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
