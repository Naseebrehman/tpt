<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Admin Management
 * ---------------------------------------------------------------------------
 *  Manage dashboard administrator accounts:
 *  - View list of admins
 *  - Add another admin
 *  - Edit admin details (username, email)
 *  - Reset/change admin password
 *  - Enable/disable admin accounts
 *  - Delete admin
 * ---------------------------------------------------------------------------
 */

require_once dirname(__DIR__) . '/includes/init.php';
require_once BASE_PATH . '/core/Schema.php';
Schema::ensure();
requireAdmin();

$adminPage  = 'admins';
$adminTitle = 'Admin Management';
$currentAdm = currentAdmin();
$currentId  = (int) $_SESSION['admin_id'];

/* ---------------------------------------------------------------------------
   POST Action Handling
   --------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired — please refresh and try again.');
        header('Location: admins.php');
        exit;
    }

    $action  = isset($_POST['action']) ? trim($_POST['action']) : '';
    $adminId = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0;

    /* 1. Add another admin */
    if ($action === 'add') {
        $username = sanitize(isset($_POST['username']) ? $_POST['username'] : '');
        $email    = strtolower(trim(sanitize(isset($_POST['email']) ? $_POST['email'] : '')));
        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
        $confirm  = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($username === '' || mb_strlen($username) < 2) {
            setFlash('err', 'Please provide a valid username or name (minimum 2 characters).');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('err', 'Please provide a valid email address.');
        } elseif (dbOne('SELECT id FROM admin_users WHERE email = ?', array($email))) {
            setFlash('err', 'An administrator with this email already exists.');
        } elseif (mb_strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            setFlash('err', 'Password must be at least 8 characters and contain both letters and numbers.');
        } elseif ($password !== $confirm) {
            setFlash('err', 'The password confirmation does not match.');
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $newId = dbInsert(
                'INSERT INTO admin_users (username, email, password_hash, is_active, created_at) VALUES (?, ?, ?, ?, NOW())',
                array($username, $email, $hash, $isActive)
            );
            if ($newId > 0) {
                try {
                    require_once BASE_PATH . '/core/Notifications.php';
                    Notifications::notifyAdmins('security', 'system', array(
                        'subject' => 'New admin account created: ' . $email,
                        'message' => 'A new admin user (' . $username . ' <' . $email . '>) was created by ' . ($currentAdm['email'] ?? 'an admin') . ' on ' . date('j M Y, H:i') . ' UTC.',
                    ), array('table' => ''));
                } catch (Throwable $e) {
                    error_log('[TPT] Admin creation notification failed: ' . $e->getMessage());
                }
                setFlash('ok', 'New administrator account for ' . esc($email) . ' created successfully.');
            } else {
                setFlash('err', 'Failed to create administrator account. Please check the database.');
            }
        }
        header('Location: admins.php');
        exit;
    }

    /* 2. Edit admin details */
    if ($action === 'edit') {
        $target = dbOne('SELECT * FROM admin_users WHERE id = ?', array($adminId));
        if (!$target) {
            setFlash('err', 'Administrator not found.');
            header('Location: admins.php');
            exit;
        }

        $username = sanitize(isset($_POST['username']) ? $_POST['username'] : '');
        $email    = strtolower(trim(sanitize(isset($_POST['email']) ? $_POST['email'] : '')));
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        // An admin cannot disable their own active account
        if ($adminId === $currentId) {
            $isActive = 1;
        }

        if ($username === '' || mb_strlen($username) < 2) {
            setFlash('err', 'Please provide a valid username or name (minimum 2 characters).');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('err', 'Please provide a valid email address.');
        } else {
            $existing = dbOne('SELECT id FROM admin_users WHERE email = ? AND id != ?', array($email, $adminId));
            if ($existing) {
                setFlash('err', 'Another administrator is already using this email address.');
            } else {
                // If disabling, ensure at least one active admin remains
                if ($isActive === 0) {
                    $activeCount = dbOne('SELECT COUNT(*) AS c FROM admin_users WHERE is_active = 1 AND id != ?', array($adminId));
                    if (!$activeCount || (int) $activeCount['c'] < 1) {
                        setFlash('err', 'Cannot disable this account: at least one active administrator must remain.');
                        header('Location: admins.php');
                        exit;
                    }
                }

                dbExec(
                    'UPDATE admin_users SET username = ?, email = ?, is_active = ? WHERE id = ?',
                    array($username, $email, $isActive, $adminId)
                );

                if ($adminId === $currentId) {
                    $_SESSION['admin_email'] = $email;
                }

                setFlash('ok', 'Administrator details updated successfully.');
            }
        }
        header('Location: admins.php');
        exit;
    }

    /* 3. Reset / change password */
    if ($action === 'reset_password') {
        $target = dbOne('SELECT * FROM admin_users WHERE id = ?', array($adminId));
        if (!$target) {
            setFlash('err', 'Administrator not found.');
            header('Location: admins.php');
            exit;
        }

        $newPassword = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
        $confirm     = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';

        if (mb_strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            setFlash('err', 'Password must be at least 8 characters and contain both letters and numbers.');
        } elseif ($newPassword !== $confirm) {
            setFlash('err', 'The password confirmation does not match.');
        } else {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            dbExec('UPDATE admin_users SET password_hash = ? WHERE id = ?', array($hash, $adminId));

            // Clear any lockouts for this admin
            dbExec('DELETE FROM admin_lockouts WHERE email = ?', array($target['email']));

            try {
                require_once BASE_PATH . '/core/Notifications.php';
                Notifications::notifyAdmins('security', 'system', array(
                    'subject' => 'Password reset for ' . $target['email'],
                    'message' => 'The password for ' . $target['email'] . ' was reset by ' . ($currentAdm['email'] ?? 'an admin') . ' on ' . date('j M Y, H:i') . ' UTC.',
                ), array('table' => ''));
            } catch (Throwable $e) {
                error_log('[TPT] Password reset notification failed: ' . $e->getMessage());
            }

            setFlash('ok', 'Password for ' . esc($target['email']) . ' was successfully updated.');
        }
        header('Location: admins.php');
        exit;
    }

    /* 4. Enable / Disable admin account */
    if ($action === 'toggle_status') {
        $target = dbOne('SELECT * FROM admin_users WHERE id = ?', array($adminId));
        if (!$target) {
            setFlash('err', 'Administrator not found.');
        } elseif ($adminId === $currentId) {
            setFlash('err', 'You cannot disable your own admin account.');
        } else {
            $currentStatus = isset($target['is_active']) ? (int) $target['is_active'] : 1;
            $newStatus     = $currentStatus ? 0 : 1;

            if ($newStatus === 0) {
                // Ensure at least one active admin remains
                $activeCount = dbOne('SELECT COUNT(*) AS c FROM admin_users WHERE is_active = 1 AND id != ?', array($adminId));
                if (!$activeCount || (int) $activeCount['c'] < 1) {
                    setFlash('err', 'Cannot disable this account: at least one active administrator must remain.');
                    header('Location: admins.php');
                    exit;
                }
            }

            dbExec('UPDATE admin_users SET is_active = ? WHERE id = ?', array($newStatus, $adminId));
            $msg = $newStatus ? 'Admin account enabled.' : 'Admin account disabled.';
            setFlash('ok', $msg);
        }
        header('Location: admins.php');
        exit;
    }

    /* 5. Delete admin */
    if ($action === 'delete') {
        $target = dbOne('SELECT * FROM admin_users WHERE id = ?', array($adminId));
        if (!$target) {
            setFlash('err', 'Administrator not found.');
        } elseif ($adminId === $currentId) {
            setFlash('err', 'You cannot delete your own admin account.');
        } else {
            $totalCount = dbOne('SELECT COUNT(*) AS c FROM admin_users');
            if ($totalCount && (int) $totalCount['c'] <= 1) {
                setFlash('err', 'Cannot delete the only administrator account.');
            } else {
                dbExec('DELETE FROM admin_users WHERE id = ?', array($adminId));
                dbExec('DELETE FROM admin_lockouts WHERE email = ?', array($target['email']));
                setFlash('ok', 'Administrator ' . esc($target['email']) . ' deleted successfully.');
            }
        }
        header('Location: admins.php');
        exit;
    }
}

/* ---------------------------------------------------------------------------
   Query Admins List
   --------------------------------------------------------------------------- */
$admins = dbAll('SELECT * FROM admin_users ORDER BY id ASC');
$totalAdmins = count($admins);
$activeAdmins = 0;
foreach ($admins as $adm) {
    if (!isset($adm['is_active']) || (int) $adm['is_active'] === 1) {
        $activeAdmins++;
    }
}

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-toolbar">
    <span class="text-muted"><?= $totalAdmins ?> administrator<?= $totalAdmins === 1 ? '' : 's' ?> · <?= $activeAdmins ?> active</span>
    <span class="spacer"></span>
    <button class="a-btn primary" type="button" data-modal="modal-add-admin"><?= icon('plus', 16) ?> Add Administrator</button>
</div>

<div class="a-table-wrap">
    <table class="a-table">
        <thead>
            <tr>
                <th>Administrator</th>
                <th>Email</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Created</th>
                <th style="text-align:right">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$admins): ?>
            <tr><td colspan="6" class="text-muted">No administrator accounts found.</td></tr>
        <?php endif; ?>
        <?php foreach ($admins as $adm):
            $isSelf = ((int) $adm['id'] === $currentId);
            $isActive = (!isset($adm['is_active']) || (int) $adm['is_active'] === 1);
        ?>
            <tr>
                <td>
                    <div class="td-main" style="display:flex;align-items:center;gap:8px;">
                        <?= icon('shield', 16) ?>
                        <span><?= esc($adm['username'] !== '' ? $adm['username'] : 'Admin') ?></span>
                        <?php if ($isSelf): ?>
                            <span class="badge in_progress" style="font-size:0.65rem;padding:2px 7px;">You</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="td-sub"><?= esc($adm['email']) ?></td>
                <td>
                    <?php if ($isActive): ?>
                        <span class="badge active">Active</span>
                    <?php else: ?>
                        <span class="badge inactive">Disabled</span>
                    <?php endif; ?>
                </td>
                <td class="td-sub">
                    <?= !empty($adm['last_login']) ? esc(formatDate($adm['last_login'], 'j M Y, H:i')) : '<span class="text-muted">Never</span>' ?>
                </td>
                <td class="td-sub">
                    <?= !empty($adm['created_at']) ? esc(formatDate($adm['created_at'], 'j M Y')) : '—' ?>
                </td>
                <td>
                    <div class="row-actions">
                        <!-- Edit Button -->
                        <button class="a-btn small" type="button" data-modal="modal-edit-<?= (int) $adm['id'] ?>" title="Edit admin details">
                            <?= icon('edit', 14) ?> Edit
                        </button>

                        <!-- Change/Reset Password Button -->
                        <button class="a-btn small" type="button" data-modal="modal-pwd-<?= (int) $adm['id'] ?>" title="Change or reset password">
                            <?= icon('lock', 14) ?> Password
                        </button>

                        <!-- Toggle Enable/Disable Button (disabled for self) -->
                        <?php if (!$isSelf): ?>
                        <form method="post" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="admin_id" value="<?= (int) $adm['id'] ?>">
                            <button class="a-btn small <?= $isActive ? 'danger' : '' ?>" type="submit" data-confirm="Are you sure you want to <?= $isActive ? 'disable' : 'enable' ?> this admin account?">
                                <?= $isActive ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>

                        <!-- Delete Button (disabled for self) -->
                        <form method="post" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="admin_id" value="<?= (int) $adm['id'] ?>">
                            <button class="a-btn small danger" type="submit" data-confirm="Are you sure you want to permanently delete this administrator account?">
                                <?= icon('trash', 14) ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Hidden Modal Templates for Admins -->
<?php foreach ($admins as $adm):
    $isSelf = ((int) $adm['id'] === $currentId);
    $isActive = (!isset($adm['is_active']) || (int) $adm['is_active'] === 1);
?>
    <!-- Edit Modal Template -->
    <div id="modal-edit-<?= (int) $adm['id'] ?>" style="display:none">
        <h3>Edit Administrator: <?= esc($adm['username']) ?></h3>
        <form method="post" data-modal-form style="margin-top:16px;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="admin_id" value="<?= (int) $adm['id'] ?>">

            <div class="a-field">
                <label for="edit_user_<?= (int) $adm['id'] ?>">Username / Name</label>
                <input id="edit_user_<?= (int) $adm['id'] ?>" name="username" type="text" required maxlength="100" value="<?= esc($adm['username']) ?>">
            </div>

            <div class="a-field">
                <label for="edit_email_<?= (int) $adm['id'] ?>">Email Address</label>
                <input id="edit_email_<?= (int) $adm['id'] ?>" name="email" type="email" required maxlength="150" value="<?= esc($adm['email']) ?>">
            </div>

            <?php if (!$isSelf): ?>
            <div class="a-field">
                <label class="a-check">
                    <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                    <span>Account is active (can sign in)</span>
                </label>
            </div>
            <?php else: ?>
            <input type="hidden" name="is_active" value="1">
            <p class="hint">You cannot disable your own active administrator account.</p>
            <?php endif; ?>

            <div class="a-toolbar" style="margin-top:20px;">
                <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Reset Password Modal Template -->
    <div id="modal-pwd-<?= (int) $adm['id'] ?>" style="display:none">
        <h3>Reset Password: <?= esc($adm['email']) ?></h3>
        <form method="post" data-modal-form style="margin-top:16px;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="admin_id" value="<?= (int) $adm['id'] ?>">

            <div class="a-field">
                <label for="new_pwd_<?= (int) $adm['id'] ?>">New Password</label>
                <div class="pw-wrap">
                    <input id="new_pwd_<?= (int) $adm['id'] ?>" name="new_password" type="password" required minlength="8" autocomplete="new-password">
                    <button type="button" class="pw-toggle" data-target="new_pwd_<?= (int) $adm['id'] ?>">Show</button>
                </div>
                <div class="hint">Minimum 8 characters with both letters and numbers.</div>
            </div>

            <div class="a-field">
                <label for="conf_pwd_<?= (int) $adm['id'] ?>">Confirm New Password</label>
                <input id="conf_pwd_<?= (int) $adm['id'] ?>" name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
            </div>

            <div class="a-toolbar" style="margin-top:20px;">
                <button class="a-btn primary" type="submit"><?= icon('lock', 16) ?> Update Password</button>
            </div>
        </form>
    </div>
<?php endforeach; ?>

<!-- Hidden Template: Add Admin Modal -->
<div id="modal-add-admin" style="display:none">
    <h3>Add New Administrator</h3>
    <p class="hint">Create an administrator account. Passwords are securely hashed with BCRYPT.</p>
    <form method="post" data-modal-form style="margin-top:16px;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">

        <div class="a-field">
            <label for="new_username">Username / Full Name</label>
            <input id="new_username" name="username" type="text" required maxlength="100" placeholder="e.g. Sarah Connor">
        </div>

        <div class="a-field">
            <label for="new_email">Email Address</label>
            <input id="new_email" name="email" type="email" required maxlength="150" placeholder="sarah@thepietechnologies.com">
        </div>

        <div class="a-field-row">
            <div class="a-field">
                <label for="new_password">Password</label>
                <div class="pw-wrap">
                    <input id="new_password" name="password" type="password" required minlength="8" autocomplete="new-password">
                    <button type="button" class="pw-toggle" data-target="new_password">Show</button>
                </div>
                <div class="hint">8+ chars, letters &amp; numbers</div>
            </div>
            <div class="a-field">
                <label for="new_confirm">Confirm Password</label>
                <input id="new_confirm" name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
            </div>
        </div>

        <div class="a-field">
            <label class="a-check">
                <input type="checkbox" name="is_active" value="1" checked>
                <span>Account is active (can log in immediately)</span>
            </label>
        </div>

        <div class="a-toolbar" style="margin-top:20px;">
            <button class="a-btn primary" type="submit"><?= icon('plus', 16) ?> Create Administrator</button>
        </div>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
