<?php
/**
 * Dependency-free tests for the notification / email template / CAPTCHA /
 * AI provider, notification, and contact/email modules (Tasks 6–16, 20).
 * Run: php tests/notifications.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', ''); define('SITE_URL', 'https://example.test');
define('SITE_NAME', 'The Pie Technologies'); define('ADMIN_EMAIL', 'admin@example.test');
define('PRETTY_URLS', true); define('DB_OK', true); define('UPLOAD_PATH', BASE_PATH . '/uploads/');

/* Fake settings + tables so the modules run without MySQL. */
$GLOBALS['fake_settings'] = array();
$GLOBALS['fake_recipients'] = array();
$GLOBALS['fake_templates'] = array();

function dbAll($sql, $params = array())
{
    if (strpos($sql, 'FROM settings') !== false) {
        $rows = array();
        foreach ($GLOBALS['fake_settings'] as $k => $v) { $rows[] = array('setting_key' => $k, 'setting_value' => $v); }
        return $rows;
    }
    if (strpos($sql, 'FROM notification_emails') !== false) {
        return array_values($GLOBALS['fake_recipients']);
    }
    return array();
}
function dbOne($sql, $params = array())
{
    if (strpos($sql, 'information_schema.TABLES') !== false) {
        /* Notifications::tableReady() probes this before listing recipients. */
        return array('c' => 1);
    }
    if (strpos($sql, 'FROM settings') !== false) {
        $key = $params[0] ?? '';
        return isset($GLOBALS['fake_settings'][$key]) ? array('setting_key' => $key, 'setting_value' => $GLOBALS['fake_settings'][$key]) : null;
    }
    if (strpos($sql, 'FROM email_templates') !== false) {
        $key = $params[0] ?? '';
        return isset($GLOBALS['fake_templates'][$key]) ? $GLOBALS['fake_templates'][$key] : null;
    }
    if (strpos($sql, 'FROM notification_emails') !== false) {
        foreach ($GLOBALS['fake_recipients'] as $row) {
            if (($row['email'] ?? '') === ($params[0] ?? '')) { return $row; }
        }
        return null;
    }
    return null;
}
function dbExec($sql, $params = array())
{
    if (strpos($sql, 'INSERT INTO settings') !== false && isset($params[0], $params[1])) {
        $GLOBALS['fake_settings'][$params[0]] = $params[1];
        return 1;
    }
    if (strpos($sql, 'DELETE FROM notification_emails') !== false) {
        foreach ($GLOBALS['fake_recipients'] as $i => $row) { if ((int) $row['id'] === (int) ($params[0] ?? -1)) { unset($GLOBALS['fake_recipients'][$i]); } }
        return 1;
    }
    return 1;
}
function dbInsert($sql, $params = array())
{
    return 1;
}

require BASE_PATH . '/includes/functions.php';
require BASE_PATH . '/includes/Mailer.php';
require BASE_PATH . '/includes/email-templates.php';
require BASE_PATH . '/core/Notifications.php';
require BASE_PATH . '/core/Captcha.php';
require BASE_PATH . '/core/AIProviders.php';
require BASE_PATH . '/core/Payments.php';

$count = 0;
function check($condition, $message) { global $count; $count++; if (!$condition) { throw new RuntimeException('FAIL: ' . $message); } echo 'PASS: ' . $message . PHP_EOL; }
function setFakeSetting($key, $value) { $GLOBALS['fake_settings'][$key] = $value; settingsCache(true); }

/* --------------------------- Email templates --------------------------- */
$vars = EmailTemplates::render('Hi {{name}} from {{site_name}}, {{unknown}}!', array('name' => 'Jo', 'site_name' => 'TPT'));
check($vars === 'Hi Jo from TPT, !', 'template variables replaced; unknown blanked');
$defaults = EmailTemplates::defaults();
foreach (array('contact_admin', 'contact_confirm', 'lead_admin', 'password_reset', 'welcome', 'system', 'newsletter_welcome') as $key) {
    check(isset($defaults[$key]), 'template exists: ' . $key);
}
foreach (array_keys(EmailTemplates::keys()) as $key) {
    check(isset($defaults[$key]), 'template key has a default body: ' . $key);
}
$t = EmailTemplates::get('contact_confirm');
check($t['source'] === 'default' && $t['is_active'], 'template falls back to built-in default without DB rows');
check(EmailTemplates::get('nope')['source'] === 'missing', 'unknown template reported as missing');
$composed = EmailTemplates::compose('contact_confirm', array('name' => 'Jo', 'email' => 'jo@example.test'), array('table' => EmailTemplates::detailTable(array('Email' => 'jo@example.test'))));
check($composed['sent'] && strpos($composed['subject'], 'Jo') !== false, 'compose renders subject variables');
check(strpos($composed['html'], 'received your message') !== false && strpos($composed['html'], 'The Pie Technologies') !== false, 'compose wraps content in the brand shell');
check(!isset(EmailTemplates::defaults()['payment_confirm']), 'no payment email templates remain');
check(strpos(EmailTemplates::detailTable(array('<b>X</b>' => 'ok')), '<b>X</b>') === false, 'detail table escapes labels');
$GLOBALS['fake_templates']['welcome'] = array('template_key' => 'welcome', 'subject' => 'Custom {{name}}', 'body' => 'Body', 'is_active' => 0);
check(EmailTemplates::get('welcome')['source'] === 'database', 'database override is used when present');
$disabled = EmailTemplates::compose('welcome', array('name' => 'x'));
check(!$disabled['sent'] && $disabled['reason'] === 'template_disabled', 'disabled template is not composed');
check(EmailTemplates::save('brand_new', 's', 'b', true) === false, 'unknown template keys cannot be saved');

/* ---------------------------- Notifications ---------------------------- */
check(count(Notifications::categories()) === 5, 'five notification categories (payment removed)');
check(Notifications::categoryEnabled('contact'), 'categories default to ON');
setFakeSetting('notify_cat_lead', '0');
check(!Notifications::categoryEnabled('lead'), 'category toggle turns a category OFF');
check(Notifications::recipientsFor('contact') === array('admin@example.test'), 'recipient list falls back to the site email');
list($ok, $msg) = Notifications::addRecipient('not-an-email', array('contact'), true);
check(!$ok && strpos($msg, 'valid') !== false, 'invalid recipient email rejected');
$GLOBALS['fake_recipients'] = array(
    array('id' => 1, 'email' => 'accounts@example.test', 'is_active' => 1, 'categories' => 'system,contact'),
    array('id' => 2, 'email' => 'marketing@example.test', 'is_active' => 0, 'categories' => 'system'),
    array('id' => 3, 'email' => 'support@example.test', 'is_active' => 1, 'categories' => 'lead'),
);
$systemRecipients = Notifications::recipientsFor('system');
check(in_array('accounts@example.test', $systemRecipients, true) && !in_array('marketing@example.test', $systemRecipients, true), 'disabled recipients are skipped');
check(!in_array('support@example.test', $systemRecipients, true), 'category filtering respects recipient category lists');
check(in_array('support@example.test', Notifications::recipientsFor('lead'), true), 'recipients receive their own categories');
list($ok, $msg) = Notifications::addRecipient('accounts@example.test', array('system'), true);
check(!$ok, 'duplicate recipient rejected');
list($ok, $msg) = Notifications::addRecipient('ops@example.test', array('contact', 'system'), true);
check($ok, 'valid recipient accepted');
check(is_bool(Notifications::removeRecipient(99)), 'removing a missing recipient does not crash');
check(Notifications::setRecipientActive(1, false) !== false, 'recipient enable/disable supported');
check(!Notifications::setRecipientCategories(1, array()), 'empty category list rejected');
check(Notifications::setRecipientCategories(1, array('system', 'nonsense')) !== false, 'recipient categories saved (unknown values dropped)');
check(Notifications::setRecipientCategories(1, array('payment')) === false, 'the removed payment category cannot be assigned');

/* Duplicate suppression via a delivery probe (no real mail). */
$GLOBALS['deliveries'] = array();
Notifications::$onDeliver = function ($to, $subject) { $GLOBALS['deliveries'][] = $to . '|' . $subject; };
Notifications::notifyAdmins('system', 'system', array('name' => 'Jo', 'subject' => 'Test', 'message' => 'Hello'));
Notifications::notifyAdmins('system', 'system', array('name' => 'Jo', 'subject' => 'Test', 'message' => 'Hello'));
check(count($GLOBALS['deliveries']) === 1, 'identical admin notification is not sent twice in one request');
$GLOBALS['deliveries'] = array();
check(!Notifications::notifyAdmins('lead', 'lead_admin', array('name' => 'Jo')), 'disabled category sends nothing');
check(count($GLOBALS['deliveries']) === 0, 'disabled category generates zero deliveries');

/* Throttled events (Task 7 — rare, meaningful notifications only). */
$GLOBALS['deliveries'] = array();
Notifications::eventThrottled('unit_test_event', 3600, 'system', 'system', array('subject' => 'S', 'message' => 'M'));
Notifications::eventThrottled('unit_test_event', 3600, 'system', 'system', array('subject' => 'S', 'message' => 'M'));
check(count($GLOBALS['deliveries']) === 1, 'throttled event notifies at most once per interval');
Notifications::$onDeliver = null;

/* -------------------------------- CAPTCHA ------------------------------- */
check(count(Captcha::providers()) === 3, 'three CAPTCHA providers');
check(!Captcha::enabled(), 'CAPTCHA disabled by default');
check(Captcha::verify('', '203.0.113.9') === true, 'verification passes while CAPTCHA is disabled');
check(Captcha::field() === '', 'no widget while CAPTCHA is disabled');
setFakeSetting('captcha_enabled', '1');
setFakeSetting('captcha_provider', 'turnstile');
setFakeSetting('captcha_site_key', 'site-key');
setFakeSetting('captcha_secret_key', 'secret-key');
check(Captcha::enabled(), 'CAPTCHA enabled with keys configured');
check(Captcha::verify('', '203.0.113.9') === false, 'missing CAPTCHA token rejected when enabled');
$widget = Captcha::field();
check(strpos($widget, 'site-key') !== false && strpos($widget, 'secret-key') === false, 'widget renders site key only — never the secret');
setFakeSetting('captcha_secret_key', '');
check(!Captcha::enabled(), 'CAPTCHA requires both keys before it can block forms');
$_POST['cf-turnstile-response'] = 'tok';
check(Captcha::tokenFromRequest() === 'tok', 'provider token collected from the request');
$_POST = array();

/* ------------------------------ AI providers ---------------------------- */
check(AIProviders::activeSlot() === 1, 'provider 1 active by default');
$cfg1 = AIProviders::config(1);
check($cfg1['type'] === 'gemini' && $cfg1['model'] === 'gemini-2.5-flash', 'provider 1 defaults to Gemini with legacy model');
setFakeSetting('gemini_api_key', 'legacy-key');
check(AIProviders::config(1)['api_key'] === 'legacy-key', 'provider 1 falls back to the legacy Gemini key');
check(AIProviders::config(2)['type'] === 'openai', 'provider 2 defaults to OpenAI-compatible');
setFakeSetting('ai_active_provider', '2');
check(AIProviders::activeSlot() === 2, 'admin can switch the active provider');
setFakeSetting('ai_provider2_enabled', '1');
$result = AIProviders::chat(array(array('role' => 'user', 'content' => 'hi')), '');
check(!$result['ok'] && $result['error'] === 'config', 'missing API key degrades to a config error, never a crash');
setFakeSetting('ai_provider2_api_key', 'k');
$result = AIProviders::chat(array(array('role' => 'user', 'content' => 'hi')), '');
check(!$result['ok'], 'provider call fails gracefully without network');
check(in_array($result['error'], array('timeout', 'provider', 'auth', 'rate_limit', 'model'), true), 'failure carries a classified error code');
setFakeSetting('ai_provider2_model', 'gpt-4o-mini');
$result = AIProviders::chat(array(), '');
check(!$result['ok'] && $result['error'] === 'empty', 'empty conversation rejected');
setFakeSetting('chatbot_max_tokens', '99999');
$gen = AIProviders::generation();
check($gen['max_tokens'] <= 8192, 'max response length is clamped');
setFakeSetting('ai_provider2_model', 'bad model!!');
$result = AIProviders::chat(array(array('role' => 'user', 'content' => 'hi')), '');
check(!$result['ok'] && $result['error'] === 'model', 'invalid model name rejected before any network call');

/* ------------------------- PayPal SDK payment page ---------------------- */
check(function_exists('piePaymentServices'), 'the existing Admin Services list remains available');
check(function_exists('piePayPalClientId') && function_exists('piePayPalClientIdConfigured') && function_exists('piePayPalSdkUrl'), 'the stored PayPal Client ID helpers are available');
check(!function_exists('piePaymentCreateAttempt') && !function_exists('piePaymentCompleteAttempt') && !function_exists('piePaymentValidateSubmission'), 'no pending payment attempts, server validation or confirmed completion remain');
check(!function_exists('piePayPalCreateOrder') && !function_exists('piePayPalCaptureOrder') && !function_exists('pieStripeCreateCheckoutSession') && !function_exists('pieStripeConfirmCheckoutSession'), 'no server-side PayPal/Stripe checkout or confirmation helpers remain');
check(!function_exists('piePaymentCredentialEncrypt') && !function_exists('piePayPalSecret') && !function_exists('pieStripeSecret'), 'no credential encryption or secret accessors remain');
setFakeSetting('paypal_client_id', 'Axxxxxxxxxxxxxxxxxxxxxxxx');
check(piePayPalClientId() === 'Axxxxxxxxxxxxxxxxxxxxxxxx' && piePayPalClientIdConfigured(), 'the Client ID is read from the settings system');
check(piePayPalSdkUrl() === 'https://www.paypal.com/sdk/js?client-id=Axxxxxxxxxxxxxxxxxxxxxxxx&currency=USD&components=buttons', 'the PayPal SDK URL is built from the stored Client ID');
setFakeSetting('paypal_client_id', 'not a valid id');
check(!piePayPalClientIdConfigured() && piePayPalSdkUrl('') === 'https://www.paypal.com/sdk/js?client-id=not%20a%20valid%20id&currency=USD&components=buttons', 'an invalid Client ID is not treated as configured');
setFakeSetting('paypal_client_id', '');
check(!piePayPalClientIdConfigured(), 'an empty Client ID disables the PayPal button');
$adminPayments = file_get_contents(BASE_PATH . '/admin/payments.php');
check(stripos($adminPayments, 'stripe') === false && stripos($adminPayments, 'paypal_secret') === false, 'Admin Payment Settings contains no Stripe or secret fields');
check(stripos((string) file_get_contents(BASE_PATH . '/core/Payments.php'), 'stripe') === false, 'core/Payments.php contains no Stripe code');

/* -------------------------------- done ---------------------------------- */
echo "\n$count checks passed.\n";
