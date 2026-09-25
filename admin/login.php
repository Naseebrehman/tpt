<?php
require_once dirname(__DIR__) . '/includes/init.php';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error   = '';
$lockMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $error = 'Security token expired — please try again.';
    } else {
        $email    = strtolower(trim(sanitize(isset($_POST['email']) ? $_POST['email'] : '')));
        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
        $ip       = pieClientIp();

        /* lockout check */
        $lock = dbOne('SELECT * FROM admin_lockouts WHERE email = ? AND ip_address = ? ORDER BY id DESC LIMIT 1', array($email, $ip));
        if ($lock && !empty($lock['locked_until']) && strtotime($lock['locked_until']) > time()) {
            $mins    = (int) ceil((strtotime($lock['locked_until']) - time()) / 60);
            $lockMsg = 'Too many failed attempts. Try again in ' . $mins . ' minute' . ($mins === 1 ? '' : 's') . '.';
        } else {
            $user = dbOne('SELECT * FROM admin_users WHERE email = ?', array($email));
            if ($user && password_verify($password, $user['password_hash'])) {
                dbExec('DELETE FROM admin_lockouts WHERE email = ?', array($email));
                dbExec('UPDATE admin_users SET last_login = NOW() WHERE id = ?', array((int) $user['id']));
                session_regenerate_id(true);
                $_SESSION['admin_id']   = (int) $user['id'];
                $_SESSION['admin_email'] = $user['email'];
                header('Location: index.php');
                exit;
            }

            /* failed attempt bookkeeping */
            if ($lock) {
                $attempts = (int) $lock['attempts'] + 1;
                $lockedUntil = null;
                if ($attempts >= 5) {
                    $lockedUntil = date('Y-m-d H:i:s', time() + 900);
                    $attempts = 0;
                    $lockMsg = 'Too many failed attempts. This login is locked for 15 minutes.';
                }
                dbExec('UPDATE admin_lockouts SET attempts = ?, locked_until = ? WHERE id = ?', array($attempts, $lockedUntil, (int) $lock['id']));
            } else {
                dbInsert('INSERT INTO admin_lockouts (email, ip_address, attempts, locked_until) VALUES (?, ?, 1, NULL)', array($email, $ip));
            }
            if ($lockMsg === '') {
                $error = 'Incorrect email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login | The Pie Technologies</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="<?= asset('assets/images/favicon.svg') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-login-body">
<div class="login-wrap">
    <div class="login-card">
        <div class="login-brand">The Pie<span>.</span> Technologies</div>
        <p class="login-sub">Admin Dashboard</p>

        <?php if ($lockMsg !== ''): ?><div class="admin-alert err"><?= esc($lockMsg) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="admin-alert err"><?= esc($error) ?></div><?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <?= csrfField() ?>
            <div class="a-field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" required autocomplete="username" autofocus>
            </div>
            <div class="a-field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <button class="a-btn primary block" type="submit">Sign In</button>
        </form>
        <p class="login-foot"><a href="<?= url('') ?>">&larr; Back to website</a></p>
    </div>
</div>
</body>
</html>
