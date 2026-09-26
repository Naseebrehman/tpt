<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/core/Migrations.php';
require_once BASE_PATH . '/core/Installer.php';
$command = $argv[1] ?? 'help';
if (!in_array($command, array('install', 'migrate'), true)) {
    echo "TPT maintenance\n  php bin/cli.php install   Empty database only; prompts for initial admin.\n  php bin/cli.php migrate   Additive upgrades, never imports seeds.\nConfigure config/config.local.php first. Back up DB/uploads before upgrading.\n"; exit;
}
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false));
    if ($command === 'install') {
        fwrite(STDOUT, 'Initial admin email: '); $email = trim(fgets(STDIN));
        fwrite(STDOUT, 'Password (12+ characters; hidden on supported terminals): ');
        $hidden = function_exists('shell_exec') && function_exists('stream_isatty') && stream_isatty(STDIN);
        if ($hidden) { shell_exec('stty -echo'); }
        try { $password = rtrim(fgets(STDIN), "\r\n"); } finally { if ($hidden) { shell_exec('stty echo'); } }
        echo PHP_EOL;
        Installer::install($pdo, $email, $password);
        echo "Installed. No default-password account was created.\n";
    } else { Migrations::run($pdo); echo "Migration check complete.\n"; }
} catch (Throwable $e) {
    // Do not print driver exceptions containing connection details.
    fwrite(STDERR, $e instanceof PDOException ? "Database operation failed. Check configuration/schema and private PHP logs.\n" : $e->getMessage() . PHP_EOL);
    exit(1);
}
