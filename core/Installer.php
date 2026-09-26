<?php
class Installer
{
    public static function install(PDO $pdo, $email, $password)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) { throw new RuntimeException('Use a valid email and password of at least 12 characters.'); }
        if ($pdo->query('SHOW TABLES')->fetchColumn()) { throw new RuntimeException('Installation is only allowed in an empty database. Use migrate for an existing site.'); }
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/schema-mysql.sql'));
        $seed = require dirname(__DIR__) . '/database/seed.php';
        $pdo->beginTransaction();
        try {
            $seed($pdo);
            $st = $pdo->prepare('INSERT INTO admin_users (username, email, password_hash) VALUES (?, ?, ?)');
            $st->execute(array('admin', $email, password_hash($password, PASSWORD_DEFAULT)));
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
        Migrations::run($pdo);
        // Existing tables also disable installation, even if this marker is removed.
        file_put_contents(dirname(__DIR__) . '/storage/runtime/installed.lock', date(DATE_ATOM), LOCK_EX);
    }
}
