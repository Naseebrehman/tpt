<?php
/**
 * ---------------------------------------------------------------------------
 *  DEVELOPMENT ONLY — build the local preview database.
 * ---------------------------------------------------------------------------
 *  Runs the SAME installer the deployment uses (core/Installer.php) against
 *  the sandbox MySQL server, through the mysqli-backed PDO shim in
 *  tests/harness/dev-db.php.
 *
 *    node tests/harness/cli.mjs tests/harness/db-setup.php
 *
 *  The database name/user come from config/config.local.php (git-ignored).
 */

define('PIE_DEV_CLI', true);
define('PIE_DEV_NO_AUTOPDO', true);
require __DIR__ . '/dev-db.php';
require dirname(__DIR__, 2) . '/config/config.php';
require BASE_PATH . '/core/Migrations.php';
require BASE_PATH . '/core/Installer.php';

$port = (int) (getenv('TPT_DB_PORT') ?: 3307);
$root = new mysqli(DB_HOST, DB_USER === 'root' ? 'root' : DB_USER, DB_PASS === '' ? '' : DB_PASS, '', $port);
if ($root->connect_errno) {
    fwrite(STDERR, 'Cannot reach the local MySQL server on ' . DB_HOST . ':' . $port . ' — ' . $root->connect_error . PHP_EOL);
    exit(1);
}
$root->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$root->close();

$pdo = new PieDevPdo();

$email    = getenv('TPT_ADMIN_EMAIL') ?: 'admin@thepietechnologies.com';
$password = getenv('TPT_ADMIN_PASSWORD') ?: 'LocalPreview!2026';
try {
    Installer::install($pdo, $email, $password);
    echo PHP_EOL . 'Preview database ready (' . DB_NAME . ' on ' . DB_HOST . ':' . $port . ').' . PHP_EOL;
} catch (Throwable $error) {
    echo PHP_EOL . 'Installer said: ' . $error->getMessage() . PHP_EOL;
    echo 'Schema may already exist — ensuring migrations instead.' . PHP_EOL;
    try {
        Migrations::run($pdo);
        echo 'Migrations applied.' . PHP_EOL;
    } catch (Throwable $migrationError) {
        echo 'Migration error: ' . $migrationError->getMessage() . PHP_EOL;
    }
}
