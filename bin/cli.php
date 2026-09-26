<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — maintenance CLI (Hostinger SSH / terminal).
 * ---------------------------------------------------------------------------
 *  php bin/cli.php install   Fresh database only; prompts for the admin login.
 *  php bin/cli.php migrate   Additive schema upgrades; never imports seeds.
 *  php bin/cli.php status    Read-only connectivity / schema / admin check.
 *
 *  Errors are reported with their real cause (SQL error, missing table,
 *  unknown column, access denied, syntax problem, …). The database password
 *  is NEVER printed — every message is scrubbed through Installer::sanitizeMessage().
 * ---------------------------------------------------------------------------
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/core/Migrations.php';
require_once BASE_PATH . '/core/Installer.php';

/* ---------------------------------------------------------------------------
   Helpers — all output goes through these so no secret can leak.
   --------------------------------------------------------------------------- */
function pieScrub($text)
{
    $text = (string) $text;
    if (defined('DB_PASS') && DB_PASS !== '') { $text = str_replace(DB_PASS, '***', $text); }
    return $text;
}

function pieOut($text) { fwrite(STDOUT, pieScrub($text) . PHP_EOL); }
function pieFail($text) { fwrite(STDERR, pieScrub($text) . PHP_EOL); exit(1); }

/** Which local config file actually supplied the credentials (path only). */
function pieConfigFile()
{
    $candidates = array(
        dirname(__DIR__) . '/config/config.local.php',
        __DIR__ . '/../includes/config.local.php',
    );
    foreach ($candidates as $file) {
        if (is_file($file)) { return $file; }
    }
    return '';
}

/* ---------------------------------------------------------------------------
   Pre-flight: configuration sanity (placeholder values, missing file).
   --------------------------------------------------------------------------- */
$command = isset($argv[1]) ? strtolower(trim($argv[1])) : 'help';

if (in_array($command, array('install', 'migrate', 'status'), true)) {
    $configFile = pieConfigFile();
    if ($configFile === '') {
        pieFail("No local configuration found.\n"
            . "  1. Copy config/config.local.php.example to config/config.local.php\n"
            . "  2. Enter your Hostinger database credentials there (DB_HOST, DB_NAME, DB_USER, DB_PASS).\n"
            . "  3. Run this command again.\n"
            . "The file is git-ignored, so deploying never overwrites your credentials.");
    }
    $placeholders = array();
    if (defined('DB_NAME') && preg_match('/YOUR_HOSTINGER|your_database|YOUR_DATABASE/i', DB_NAME)) { $placeholders[] = 'DB_NAME'; }
    if (defined('DB_USER') && preg_match('/YOUR_HOSTINGER|your_username|YOUR_DATABASE/i', DB_USER)) { $placeholders[] = 'DB_USER'; }
    if (defined('DB_PASS') && preg_match('/YOUR_HOSTINGER|your_password|YOUR_DATABASE/i', DB_PASS)) { $placeholders[] = 'DB_PASS'; }
    if (defined('DB_HOST') && preg_match('/YOUR_HOSTINGER/i', DB_HOST)) { $placeholders[] = 'DB_HOST'; }
    if ($placeholders) {
        pieFail("Configuration still contains placeholder values: " . implode(', ', $placeholders) . "\n"
            . "Edit " . $configFile . " and enter the real Hostinger values from hPanel → Databases → MySQL Databases.");
    }
}

/* ---------------------------------------------------------------------------
   Shared PDO connection (classified, secret-free error messages).
   --------------------------------------------------------------------------- */
function pieCliConnect()
{
    if (!defined('DB_HOST') || !defined('DB_NAME')) {
        pieFail('Configuration constants missing — config/config.php did not load correctly.');
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 15,
            )
        );
        $pdo->query('SELECT 1');
        return $pdo;
    } catch (PDOException $e) {
        $info  = Installer::classifyError($e);
        $lines = array();
        $lines[] = 'Database connection failed.';
        $lines[] = '  Cause: ' . ($info['hint'] !== '' ? $info['hint'] : $info['message']);
        $lines[] = '  Detail: ' . $info['message'];
        $lines[] = '  Checked values — DB_HOST=' . DB_HOST . ', DB_NAME=' . DB_NAME . ', DB_USER=' . DB_USER . ' (password never shown).';
        pieFail(implode(PHP_EOL, $lines));
    }
}

/* ---------------------------------------------------------------------------
   Commands
   --------------------------------------------------------------------------- */
if ($command === 'help' || $command === '') {
    pieOut("TPT maintenance CLI\n"
        . "  php bin/cli.php install   Create the full schema in an EMPTY database, then prompt for the initial admin login.\n"
        . "  php bin/cli.php migrate   Additive schema upgrades for an existing install; never imports or rewrites data.\n"
        . "  php bin/cli.php status    Read-only check: connection, tables, admin account, pending migrations.\n\n"
        . "Credentials live in config/config.local.php (copy config/config.local.php.example if it does not exist).\n"
        . "Back up the database and /uploads before upgrading an existing site.");
    exit(0);
}

if ($command === 'status') {
    $pdo = pieCliConnect();
    try {
        $state = Installer::analyze($pdo);
        pieOut('Server: ' . $pdo->query('SELECT VERSION()')->fetchColumn());
        pieOut('Database state: ' . $state['status']);
        pieOut('Application tables present: ' . count($state['app']) . ' of ' . count(Installer::$appTables));
        $missing = array_diff(Installer::$appTables, $state['app']);
        pieOut($missing ? 'Missing tables: ' . implode(', ', $missing) : 'All application tables exist.');
        if (in_array('admin_users', $state['app'], true)) {
            pieOut('Admin accounts: ' . (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn());
        }
        $applied = array();
        if (in_array('schema_migrations', $state['app'], true)) {
            foreach ($pdo->query('SELECT version FROM schema_migrations')->fetchAll() as $row) { $applied[] = $row['version']; }
        }
        $pending = array();
        foreach (glob(BASE_PATH . '/database/migrations/*.php') as $file) {
            if (!in_array(basename($file), $applied, true)) { $pending[] = basename($file); }
        }
        pieOut($pending ? 'Pending migrations: ' . implode(', ', $pending) . ' — run: php bin/cli.php migrate' : 'No pending migrations.');
    } catch (Throwable $e) {
        if ($e instanceof PDOException) {
            $info = Installer::classifyError($e);
            pieFail('Status check failed: ' . ($info['hint'] !== '' ? $info['hint'] . ' [' . $info['message'] . ']' : $info['message']));
        }
        pieFail('Status check failed: ' . pieScrub($e->getMessage()));
    }
    exit(0);
}

if ($command === 'install') {
    pieOut('TPT installer');
    pieOut('Config file: ' . pieConfigFile());
    pieOut('Target: DB_NAME=' . DB_NAME . ' on ' . DB_HOST . ' (as ' . DB_USER . ')');

    fwrite(STDOUT, 'Initial admin email: ');
    $email = trim((string) fgets(STDIN));
    fwrite(STDOUT, 'Admin password (12+ characters; hidden on supported terminals): ');
    $hidden = function_exists('shell_exec') && function_exists('stream_isatty') && stream_isatty(STDIN);
    if ($hidden) { shell_exec('stty -echo'); }
    try { $password = rtrim((string) fgets(STDIN), "\r\n"); } finally { if ($hidden) { shell_exec('stty echo'); } }
    echo PHP_EOL;
    if (trim($password) === '') {
        pieFail('No password entered — installation stopped. Nothing was changed.');
    }

    $pdo = pieCliConnect();
    try {
        Installer::install($pdo, $email, $password);
    } catch (Throwable $e) {
        $lines = array();
        $lines[] = '';
        $lines[] = 'INSTALLATION FAILED — nothing was destroyed. Fix the cause and re-run; the installer can resume.';
        if ($e instanceof PDOException) {
            $info = Installer::classifyError($e);
            $lines[] = '  Database error: ' . ($info['hint'] !== '' ? $info['hint'] : $info['message']);
            $lines[] = '  Detail: ' . $info['message'];
        } else {
            $lines[] = '  Error: ' . pieScrub($e->getMessage());
        }
        pieFail(implode(PHP_EOL, $lines));
    }

    pieOut('');
    pieOut('Installed successfully.');
    pieOut('No default-password account exists — only the admin login you just entered.');
    pieOut('Admin panel: ' . (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/admin/');
    pieOut('Front site:  ' . (defined('SITE_URL') ? rtrim(SITE_URL, '/') : ''));
    exit(0);
}

if ($command === 'migrate') {
    $pdo = pieCliConnect();
    try {
        Migrations::run($pdo);
    } catch (Throwable $e) {
        if ($e instanceof PDOException) {
            $info = Installer::classifyError($e);
            pieFail("Migration failed: " . ($info['hint'] !== '' ? $info['hint'] . ' [' . $info['message'] . ']' : $info['message'])
                . "\nMigration operations are re-runnable — fix the cause and run php bin/cli.php migrate again.");
        }
        pieFail('Migration failed: ' . pieScrub($e->getMessage()));
    }
    pieOut('Migration check complete.');
    exit(0);
}

pieFail("Unknown command: " . $command . "\nRun php bin/cli.php to see available commands.");
