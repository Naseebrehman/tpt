<?php
/** Dependency-free, no database credentials or network needed. */
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', ''); define('SITE_URL', 'https://example.test');
define('SITE_NAME', 'The Pie Technologies'); define('ADMIN_EMAIL', 'admin@example.test');
define('PRETTY_URLS', true); define('DB_OK', false); define('UPLOAD_PATH', BASE_PATH . '/uploads/');
function dbAll($sql, $params = array()) { return array(); }
function dbOne($sql, $params = array()) { return null; }
require BASE_PATH . '/includes/functions.php';
require BASE_PATH . '/includes/data.php';
foreach (array('Router','Ratelimit','Settings','Content','StripeWebhook') as $class) { require BASE_PATH . '/core/' . $class . '.php'; }
foreach (array('PublicController','AdminController','ApiController') as $class) { require BASE_PATH . '/app/Controllers/' . $class . '.php'; }
require BASE_PATH . '/includes/Mailer.php';
require BASE_PATH . '/app/Models/Repository.php';
$count = 0;
function check($condition, $message) { global $count; $count++; if (!$condition) { throw new RuntimeException('FAIL: ' . $message); } echo 'PASS: ' . $message . PHP_EOL; }
$router = require BASE_PATH . '/app/routes.php';
foreach (array('/', '/about', '/services', '/services/seo', '/work', '/work/example', '/resources', '/resources/example', '/portfolio', '/portfolio/', '/blog/example', '/contact', '/search', '/pay-online', '/pay-status', '/pay/secure/' . str_repeat('a', 32), '/legal/privacy-policy', '/admin', '/admin/login', '/admin/dashboard', '/admin/leads', '/admin/content', '/admin/profile', '/sitemap.xml') as $path) {
    check(isset($router->resolve('GET', $path)['handler']), 'route ' . $path);
}
foreach (array('/api/chat','/api/contact','/api/lead','/api/newsletter','/api/payment','/api/webhooks/stripe') as $path) {
    check(isset($router->resolve('POST', $path)['handler']), 'POST route ' . $path);
    check($router->resolve('GET', $path)['status'] === 405, 'method protection ' . $path);
}
foreach (array('/services/../../config/config.local', '/unknown', '/api/arbitrary', '/admin/../../includes/config') as $path) { check($router->resolve('GET', $path)['status'] === 404, 'unknown/traversal rejected ' . $path); }
check(url('about') === '/about', 'URLs have no public prefix');
check(validateCSRF(generateCSRF()), 'valid CSRF');
check(!validateCSRF('forged'), 'forged CSRF');
check(esc('<script>') === '&lt;script&gt;', 'output escaping');
$_SERVER['REMOTE_ADDR'] = '192.0.2.1'; $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1';
check(pieClientIp() === '192.0.2.1', 'forwarded IP cannot evade limits');
check(!deleteUpload('uploads/../README.md'), 'delete traversal rejected');
check(!uploadFile('missing', '../config')['ok'], 'upload destination validated');
check(Settings::validate(array('gemini_temperature'=>'0','brand_primary'=>'#123abc','smtp_reply_to'=>'reply@example.test')) === '', 'valid settings');
check(Settings::validate(array('gemini_temperature'=>'5')) !== '', 'temperature validation');
check(Settings::validate(array('brand_primary'=>'red;}</style>')) !== '', 'CSS injection rejected');
check(Settings::validate(array('facebook_url'=>'javascript:alert(1)')) !== '', 'unsafe URL rejected');
check(Settings::validate(array('smtp_port'=>array('25'))) !== '', 'array field rejected');
check(Content::path('/about.php?utm_source=test') === '/about', 'canonical removes tracking and extension');
check(Content::path('/index.php') === '/', 'canonical home');
$body = '{"id":"evt_test"}'; $secret = 'test-secret'; $timestamp = 1700000000;
$sig = 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
check(StripeWebhook::validSignature($body, $sig, $secret, $timestamp), 'valid Stripe signature');
check(!StripeWebhook::validSignature($body . ' ', $sig, $secret, $timestamp), 'tampered Stripe payload');
check(!StripeWebhook::validSignature($body, $sig, $secret, $timestamp + 301), 'stale Stripe signature');
check(!StripeWebhook::validSignature($body, $sig, '', $timestamp), 'unconfigured webhook rejected');
$mailer = new PieMailer(array('from_email'=>'sender@example.test','host'=>'smtp.example.test'));
check(!$mailer->send("victim@example.test\r\nBcc:attacker@example.test", 'Test', 'Test')['success'], 'SMTP recipient injection rejected');
check(!$mailer->send('victim@example.test', "Test\r\nBcc:attacker", 'Test')['success'], 'SMTP header injection rejected');
$dir = sys_get_temp_dir() . '/tpt-test-' . bin2hex(random_bytes(5));
check(Ratelimit::allow('one', 2, 60, $dir), 'rate limit first request');
check(Ratelimit::allow('one', 2, 60, $dir), 'rate limit second request');
check(!Ratelimit::allow('one', 2, 60, $dir), 'rate limit blocks excess');
check(Ratelimit::allow('two', 2, 60, $dir), 'rate limit isolates identities');
unlink($dir . '/ratelimits.json'); rmdir($dir);
check(count(Repository::search('SEO')) > 0, 'service search works without DB');

/* ------------------------- installer (no DB needed) ---------------------- */
require BASE_PATH . '/core/Installer.php';
$s = Installer::splitSql("SET NAMES utf8mb4; CREATE TABLE a (id INT);");
check(count($s) === 2 && $s[0] === 'SET NAMES utf8mb4' && $s[1] === 'CREATE TABLE a (id INT)', 'splitSql splits statements');
$s = Installer::splitSql("INSERT INTO t VALUES ('a;b', \"c;d\", `e;f`); SELECT 1;");
check(count($s) === 2 && strpos($s[0], 'a;b') !== false, 'splitSql keeps semicolons inside quotes/identifiers');
$s = Installer::splitSql("INSERT INTO t VALUES ('it''s', 'back\\\\slash');");
check(count($s) === 1 && strpos($s[0], 'it''s') !== false, 'splitSql handles doubled quotes and escapes');
$s = Installer::splitSql("-- comment ; with semicolon\nSELECT 1; # hash comment\nSELECT 2;");
check(count($s) === 2, 'splitSql strips -- and # comments');
$s = Installer::splitSql("/* ordinary ; comment */ SELECT 1; /*!40101 SET @old=1 */;");
check(count($s) === 2 && strpos($s[1], '/*!40101') === 0, 'splitSql keeps /*! */ executable comments, drops plain ones');
$s = Installer::splitSql(";;");
check(count($s) === 0, 'splitSql ignores empty statements');
$schemaStmts = Installer::splitSql((string) file_get_contents(BASE_PATH . '/database/schema-mysql.sql'));
check(count($schemaStmts) >= 17, 'schema-mysql.sql splits into its full statement set');
$bad = 0;
foreach ($schemaStmts as $stmt) { if (!preg_match('/^(SET|CREATE)\b/i', $stmt)) { $bad++; } }
check($bad === 0, 'every schema statement starts with SET or CREATE');
$seedSql = require BASE_PATH . '/database/seed.php';
check(is_string($seedSql) && strpos($seedSql, 'INSERT') !== false, 'database/seed.php returns idempotent seed SQL');
foreach (Installer::splitSql($seedSql) as $stmt) { if (!preg_match('/^INSERT\b/i', $stmt)) { $bad++; } }
check($bad === 0, 'every seed statement is an INSERT');
$createTables = array();
foreach ($schemaStmts as $stmt) { if (preg_match('/^CREATE TABLE IF NOT EXISTS `?(\w+)`?/i', $stmt, $m)) { $createTables[] = strtolower($m[1]); } }
check(count($createTables) >= 16, 'fresh schema creates all application tables');
$notOwned = array_diff($createTables, Installer::$appTables);
check(count($notOwned) === 0, 'installer recognizes every table it creates');
$notCreated = array_diff(Installer::$appTables, $createTables);
check(count($notCreated) === 0, 'every application table is created by the fresh schema');
foreach (array('notification_status', 'chatbot_leads', 'payment_events') as $must) {
    if (strpos((string) file_get_contents(BASE_PATH . '/database/schema-mysql.sql'), $must) === false) { $bad++; }
}
check($bad === 0, 'fresh schema includes former migration-001 additions');
check(strpos((string) file_get_contents(BASE_PATH . '/database.sql'), 'Admin@123') !== false, 'phpMyAdmin dump documents its default password');

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH));
foreach ($files as $file) {
    if ($file->isFile() && substr($file->getFilename(), -4) === '.php' && strpos($file->getPathname(), '/.git/') === false) { token_get_all(file_get_contents($file->getPathname()), TOKEN_PARSE); }
}
check(true, 'all PHP source parses');
echo "\n$count checks passed. External SMTP/MySQL/provider/Apache tests require staging.\n";
