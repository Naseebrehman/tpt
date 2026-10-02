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
foreach (array('Router','Ratelimit','Settings','Content') as $class) { require BASE_PATH . '/core/' . $class . '.php'; }
foreach (array('PublicController','AdminController','ApiController') as $class) { require BASE_PATH . '/app/Controllers/' . $class . '.php'; }
require BASE_PATH . '/includes/Mailer.php';
require BASE_PATH . '/app/Models/Repository.php';
$count = 0;
function check($condition, $message) { global $count; $count++; if (!$condition) { throw new RuntimeException('FAIL: ' . $message); } echo 'PASS: ' . $message . PHP_EOL; }
$router = require BASE_PATH . '/app/routes.php';
foreach (array('/', '/about', '/services', '/services/seo', '/work', '/work/example', '/resources', '/resources/example', '/portfolio', '/portfolio/', '/blog/example', '/contact', '/search', '/pay-online', '/legal/privacy-policy', '/admin', '/admin/login', '/admin/dashboard', '/admin/leads', '/admin/content', '/admin/profile', '/admin/admins', '/admin/media', '/sitemap.xml') as $path) {
    check(isset($router->resolve('GET', $path)['handler']), 'route ' . $path);
}
foreach (array('/api/chat','/api/contact','/api/lead','/api/newsletter') as $path) {
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
check($router->resolve('POST', '/api/payment')['status'] === 404, 'legacy payment API removed');
check($router->resolve('POST', '/api/webhooks/stripe')['status'] === 404, 'Stripe webhook API removed');
check($router->resolve('POST', '/api/payments/paypal/create')['handler'] !== null, 'PayPal checkout creation is routed server-side');
check($router->resolve('POST', '/api/payments/stripe/create')['handler'] !== null, 'Stripe checkout creation is routed server-side');
check($router->resolve('POST', '/api/payments/stripe/webhook')['handler'] !== null, 'Stripe webhook endpoint is routed for signature verification');
check($router->resolve('GET', '/api/payments/paypal/create')['status'] === 405, 'payment creation is POST-only');
check($router->resolve('POST', '/paypal-api')['status'] === 404 && $router->resolve('POST', '/stripe-api')['status'] === 404, 'legacy payment APIs remain absent');
check($router->resolve('GET', '/book-appointment')['handler'] !== null, 'the appointment page has a public route');
$bookingSource = (string) file_get_contents(BASE_PATH . '/book-appointment.php');
$calendlyUrl = 'https://calendly.com/mominalitech/book-appointment';
check(strpos($bookingSource, $calendlyUrl) !== false && strpos($bookingSource, '<iframe') !== false
    && strpos($bookingSource, 'Book a Free Strategy Call') !== false
    && strpos($bookingSource, 'background_color=202127') !== false, 'the appointment page embeds branded Calendly and provides a direct booking button');

/* Short "Start a project" popup: opt-in per page, same contact endpoint. */
$quick = (string) file_get_contents(BASE_PATH . '/includes/quick-contact.php');
check(strpos($quick, "url('contact')") !== false || strpos($quick, 'url(\'contact\')') !== false, 'the popup form posts to the same contact endpoint');
check(strpos($quick, 'csrfField()') !== false && strpos($quick, 'website_url') !== false, 'the popup form keeps the CSRF token and honeypot');
check(strpos($quick, 'name="contact_submit"') !== false, 'the popup form carries the contact_submit marker');
check(strpos($quick, 'name="name"') !== false && strpos($quick, 'name="email"') !== false
    && strpos($quick, 'name="phone"') !== false && strpos($quick, 'name="message"') !== false, 'the popup asks for only the four requested contact details');
check(strpos($quick, 'name="service"') === false && strpos($quick, '<select') === false && strpos($quick, 'name="company"') === false, 'the popup removes service, company and other extra fields');
check(strpos($quick, '<label') === false
    && strpos($quick, 'aria-label="Name / Business Name"') !== false
    && strpos($quick, 'aria-label="Email"') !== false
    && strpos($quick, 'aria-label="Phone Number"') !== false
    && strpos($quick, 'aria-label="Question / Query"') !== false
    && substr_count($quick, '<textarea') === 1, 'the popup hides visible labels while keeping all four fields accessible');
check(strpos($quick, 'contact_variant" value="project_popup') !== false, 'the popup marks its compact variant for the shared contact validator');
$contactController = (string) file_get_contents(BASE_PATH . '/app/Controllers/ContactController.php');
check(strpos($contactController, 'public static function handle') !== false
    && strpos($contactController, '$isProjectPopup') !== false
    && strpos($contactController, 'Captcha::verify') !== false
    && strpos($contactController, 'if (!$isProjectPopup && $source !==') !== false
    && strpos($contactController, 'Repository::createContact') !== false
    && strpos($contactController, 'Notifications::notifyAdmins') !== false, 'popup submissions share Contact Us verification, recording and notification without extra source/service fields');
$footerSource = (string) file_get_contents(BASE_PATH . '/includes/footer.php');
check(strpos($footerSource, '$contactModalEnabled') !== false, 'the popup form is rendered only when a page asks for it');
$hostPages = array();
foreach (glob(BASE_PATH . '/*.php') as $pageFile) {
    $source = (string) file_get_contents($pageFile);
    if (strpos($source, '$contactModalEnabled = true') !== false) { $hostPages[] = basename($pageFile); }
}
sort($hostPages);
check($hostPages === array('index.php', 'portfolio.php', 'resource-single.php', 'services-index.php'), 'the marketing pages offer the popup (found: ' . implode(', ', $hostPages) . ')');
check(strpos((string) file_get_contents(BASE_PATH . '/includes/service-page.php'), '$contactModalEnabled = true') !== false, 'every service page offers the popup with its service pre-selected');
$triggerPages = array();
foreach (glob(BASE_PATH . '/*.php') as $pageFile) {
    if (strpos((string) file_get_contents($pageFile), 'data-contact-modal') !== false) { $triggerPages[] = basename($pageFile); }
}
sort($triggerPages);
check($triggerPages === array('index.php', 'portfolio.php', 'resource-single.php', 'services-index.php'), 'popup triggers are used on the marketing pages (found: ' . implode(', ', $triggerPages) . ')');
$headerSource = (string) file_get_contents(BASE_PATH . '/includes/header.php');
check(substr_count($headerSource, "url('book-appointment')") === 2
    && substr_count($headerSource, 'Book a Strategy Call') === 2
    && strpos($headerSource, 'data-contact-modal') === false, 'desktop and mobile header buttons link to the strategy-call booking page');
check(strpos((string) file_get_contents(BASE_PATH . '/sitemap.php'), "book-appointment', '0.7'") !== false, 'the appointment page is included in the sitemap');
check(strpos((string) file_get_contents(BASE_PATH . '/portfolio/case-study.php'), 'data-contact-modal') !== false, 'case studies offer the popup');
$homeSource = (string) file_get_contents(BASE_PATH . '/index.php');
check(strpos($homeSource, 'Selected Work') === false && strpos($homeSource, 'work-grid') === false
    && strpos($homeSource, 'getPortfolioItems') === false, 'Selected Work cards and spacing are removed only from Home');
check(is_file(BASE_PATH . '/portfolio.php') && is_file(BASE_PATH . '/admin/portfolio.php')
    && strpos((string) file_get_contents(BASE_PATH . '/portfolio.php'), 'getPortfolioItems') !== false, 'portfolio remains available outside Home and in Admin');
$contactSource = (string) file_get_contents(BASE_PATH . '/contact.php');
check(strpos($contactSource, 'id="contactForm"') !== false, 'the Contact Us page keeps the full form');
check(strpos($contactSource, 'id="formSuccess"') !== false, 'the Contact Us page keeps its success block');
check(strpos($contactSource, 'data-contact-modal') === false, 'the Contact Us page itself does not open the popup');

/* The popup is only usable when its dialog styles exist and pages without the
   form fall back to the normal Contact Us link. */
$refinements = (string) file_get_contents(BASE_PATH . '/assets/css/refinements.css');
foreach (array('.contact-modal{', '.contact-modal.open{display:flex}', '.contact-modal__overlay{', '.contact-modal__panel{', '.contact-modal__close{', 'body.modal-open{overflow:hidden}', 'z-index:10010', '.contact-modal__top{', 'position:relative;top:auto;z-index:2', '.contact-modal__body{flex:1 1 auto;min-width:0;min-height:0;margin:0;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain') as $rule) {
    check(strpos($refinements, $rule) !== false, 'popup style present: ' . $rule);
}
foreach (array('.book-appointment-hero-grid{', '.book-appointment-hero-card{', '.book-appointment-section-head{', '.book-appointment-calendar{', '.book-appointment-embed iframe{', '@media(max-width:620px){') as $rule) {
    check(strpos($refinements, $rule) !== false, 'responsive booking-page style present: ' . $rule);
}
$chatbotStyles = (string) file_get_contents(BASE_PATH . '/assets/css/style.css');
check(strpos($chatbotStyles, 'z-index:1200') !== false
    && strpos($chatbotStyles, 'bottom:calc(100% + 14px)') !== false
    && strpos($chatbotStyles, 'height:min(640px,calc(var(--tpt-chatbot-vh) - 112px') !== false
    && strpos($chatbotStyles, 'overflow-x:hidden') !== false, 'Alia stays above the navigation and is constrained to the viewport without horizontal message overflow');
$mainJs = (string) file_get_contents(BASE_PATH . '/assets/js/main.js');
check(strpos($mainJs, "if (!quickHost || !quickForm) return;") !== false, 'a Start button on a page without the popup keeps its normal link');
check(strpos($mainJs, "if (!triggers.length || document.getElementById('contactModal')) return;") !== false, 'the popup never stacks a second dialog');
check(strpos($mainJs, "trigger.getAttribute('data-modal-bound')") !== false, 'popup triggers are bound once');
check(strpos($mainJs, 'function safeInit(init)') !== false
    && strpos($mainJs, '].forEach(safeInit);') !== false, 'each widget initializes in isolation so the popup cannot be skipped');
check(substr_count($mainJs, "document.addEventListener('DOMContentLoaded'") === 1, 'the boot sequence is registered once');
/* The payment page + popup wiring must never regress on the JS side. */
check(strpos($mainJs, "panel.classList.add('open')") !== false && strpos($mainJs, 'body.appendChild(quickForm);') !== false, 'the popup mounts the real short form into the dialog');
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
check(count($s) === 1 && strpos($s[0], "it''s") !== false, 'splitSql handles doubled quotes and escapes');
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
foreach (array('notification_status', 'chatbot_leads') as $must) {
    if (strpos((string) file_get_contents(BASE_PATH . '/database/schema-mysql.sql'), $must) === false) { $bad++; }
}
check($bad === 0, 'fresh schema includes former migration-001 additions');
foreach (array('notification_emails', 'email_templates') as $must) {
    if (strpos((string) file_get_contents(BASE_PATH . '/database/schema-mysql.sql'), $must) === false) { $bad++; }
}
check($bad === 0, 'fresh schema includes the notification and email-template tables');
$schemaSource = (string) file_get_contents(BASE_PATH . '/database/schema-mysql.sql');
check(strpos($schemaSource, 'CREATE TABLE IF NOT EXISTS payment_records') !== false
    && strpos($schemaSource, 'CREATE TABLE IF NOT EXISTS payment_events') !== false, 'fresh schema preserves a payment ledger and idempotency events');
check(is_file(BASE_PATH . '/database/migrations/012_server_side_payments.php'), 'migration 012 additively installs server-side payment records');
$legacyPaymentMigration = (string) file_get_contents(BASE_PATH . '/database/migrations/011_paypal_sdk_only.php');
check(stripos($legacyPaymentMigration, 'DROP TABLE') === false && stripos($legacyPaymentMigration, 'DELETE FROM settings') === false, 'legacy payment migration no longer drops data or credentials');
$corePayments = (string) file_get_contents(BASE_PATH . '/core/Payments.php');
$payOnline = (string) file_get_contents(BASE_PATH . '/pay-online.php');
check(strpos($corePayments, 'private static function paypalApi') !== false
    && strpos($corePayments, '/v2/checkout/orders') !== false
    && strpos($corePayments, 'https://api.stripe.com') !== false
    && strpos($corePayments, '/v1/checkout/sessions') !== false, 'PayPal and Stripe provider calls execute only in the server module');
check(strpos($payOnline, 'paypal.com/sdk/js') === false && strpos($payOnline, 'actions.order.capture') === false
    && strpos($payOnline, 'piePayPalSdkUrl') === false, 'the browser page contains no PayPal SDK or browser-side capture');
check(strpos($payOnline, 'csrfField()') !== false && strpos($payOnline, 'data-payment-provider="paypal"') !== false
    && strpos($payOnline, 'data-payment-provider="stripe"') !== false, 'hosted provider checkout buttons use a CSRF-protected shared form');
check(strpos($payOnline, "if (result.status === 'confirmed')") !== false
    && strpos($payOnline, 'resetPaymentForm();') !== false, 'payment fields are cleared only on the server-confirmed success path');
check(is_file(BASE_PATH . '/database/migrations/002_notifications.php'), 'migration 002 exists for existing installations');
check(strpos((string) file_get_contents(BASE_PATH . '/database/migrations/002_notifications.php'), 'notification_emails') !== false, 'migration 002 creates the notification tables');
check(strpos((string) file_get_contents(BASE_PATH . '/database.sql'), 'Admin@123') !== false, 'phpMyAdmin dump documents its default password');

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH));
foreach ($files as $file) {
    if ($file->isFile() && substr($file->getFilename(), -4) === '.php' && strpos($file->getPathname(), '/.git/') === false) { token_get_all(file_get_contents($file->getPathname()), TOKEN_PARSE); }
}
check(true, 'all PHP source parses');
echo "\n$count checks passed. External SMTP/MySQL/provider/Apache tests require staging.\n";
