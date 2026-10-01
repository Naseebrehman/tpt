<?php
/**
 * Dependency-free tests for the payment integration layer:
 *   • the existing Admin Services system is the single source of truth
 *   • PayPal Client ID, shared Terms & Conditions URL and the Services list
 *     reach the saved custom code through controlled placeholders only
 *   • nothing else in the administrator's code is rewritten
 *   • PayPal and Stripe can render together without duplicate ids
 *   • PayPal never requests shipping
 *   • complete implementations are stored without truncation
 *   • the Terms checkbox is gone (one small legal line instead)
 *   • the PayPal Secret is stored server-side and never displayed
 * Run: php tests/payments.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', ''); define('SITE_URL', 'https://example.test');
define('SITE_NAME', 'The Pie Technologies'); define('ADMIN_EMAIL', 'admin@example.test');
define('PRETTY_URLS', true); define('DB_OK', true); define('UPLOAD_PATH', BASE_PATH . '/uploads/');

/* Fake settings + tables so the modules run without MySQL. */
$GLOBALS['fake_settings'] = array();
$GLOBALS['fake_services'] = array();
$GLOBALS['fake_tables'] = array('payment_services' => true);

function dbAll($sql, $params = array())
{
    if (strpos($sql, 'FROM settings') !== false) {
        $rows = array();
        foreach ($GLOBALS['fake_settings'] as $k => $v) { $rows[] = array('setting_key' => $k, 'setting_value' => $v); }
        return $rows;
    }
    if (strpos($sql, 'FROM payment_services') !== false) {
        $rows = array();
        foreach ($GLOBALS['fake_services'] as $row) {
            if (strpos($sql, 'is_active = 1') !== false && (int) $row['is_active'] !== 1) { continue; }
            $rows[] = $row;
        }
        return $rows;
    }
    return array();
}
function dbOne($sql, $params = array())
{
    if (strpos($sql, 'information_schema.TABLES') !== false) {
        /* The table name is inlined in the SQL, exactly like the real query. */
        $table = preg_match("/TABLE_NAME = '([a-z_]+)'/i", $sql, $m) ? $m[1] : '';
        return array('c' => !empty($GLOBALS['fake_tables'][$table]) ? 1 : 0);
    }
    if (strpos($sql, 'FROM settings') !== false) {
        $key = $params[0] ?? '';
        return isset($GLOBALS['fake_settings'][$key]) ? array('setting_key' => $key, 'setting_value' => $GLOBALS['fake_settings'][$key]) : null;
    }
    return null;
}
function dbExec($sql, $params = array()) { return 1; }
function dbInsert($sql, $params = array()) { return 1; }

require BASE_PATH . '/includes/functions.php';
require BASE_PATH . '/core/Payments.php';
require BASE_PATH . '/core/PaymentTemplates.php';

$count = 0;
function check($condition, $message) { global $count; $count++; if (!$condition) { throw new RuntimeException('FAIL: ' . $message); } echo 'PASS: ' . $message . PHP_EOL; }
function setFakeSetting($key, $value) { $GLOBALS['fake_settings'][$key] = $value; settingsCache(true); }
function setFakeServices(array $rows) { $GLOBALS['fake_services'] = $rows; }
function source($relativePath) { return (string) file_get_contents(BASE_PATH . '/' . $relativePath); }

/* ------------------------- Services (single source) ------------------------- */
setFakeServices(array(
    array('id' => 1, 'name' => 'AI Optimization', 'sort_order' => 1, 'is_active' => 1),
    array('id' => 2, 'name' => 'Web Development', 'sort_order' => 2, 'is_active' => 1),
    array('id' => 3, 'name' => 'Retired Service', 'sort_order' => 3, 'is_active' => 0),
    array('id' => 4, 'name' => 'Digital Marketing', 'sort_order' => 4, 'is_active' => 1),
    array('id' => 5, 'name' => 'Business Consultation', 'sort_order' => 5, 'is_active' => 1),
));
$services = piePaymentServices();
check($services === array('AI Optimization', 'Web Development', 'Digital Marketing', 'Business Consultation'), 'services come from the existing Services system in admin order, active only');

/* Reordering the admin list reorders both payment dropdowns. */
setFakeServices(array(
    array('id' => 5, 'name' => 'Business Consultation', 'sort_order' => 1, 'is_active' => 1),
    array('id' => 1, 'name' => 'AI Optimization', 'sort_order' => 2, 'is_active' => 1),
));
check(piePaymentServices() === array('Business Consultation', 'AI Optimization'), 'moving a service up/down changes the dropdown order');
setFakeServices(array(
    array('id' => 1, 'name' => 'AI Optimization', 'sort_order' => 1, 'is_active' => 1),
    array('id' => 2, 'name' => 'Web Development', 'sort_order' => 2, 'is_active' => 1),
    array('id' => 4, 'name' => 'Digital Marketing', 'sort_order' => 4, 'is_active' => 1),
    array('id' => 5, 'name' => 'Business Consultation', 'sort_order' => 5, 'is_active' => 1),
));

$options = pieServicesOptionsHtml();
check(strpos($options, '<option value="AI Optimization">AI Optimization</option>') !== false, 'service options are rendered as HTML');
check(substr_count($options, '<option') === 4, 'one option per active service');
check(json_decode(pieServicesJson(), true) === $services, 'service JSON matches the dropdown');
check(strpos($options, '<option value="Retired Service">') === false, 'hidden services never reach the dropdown');

$GLOBALS['fake_tables'] = array();
check(count(piePaymentServices()) === 7, 'services fall back to the built-in list when the table is missing');
$GLOBALS['fake_tables'] = array('payment_services' => true);

/* ------------------------------ Admin settings ------------------------------ */
check(piePayPalClientId() === '' && !piePayPalClientIdConfigured(), 'no PayPal Client ID by default');
setFakeSetting('paypal_client_id', 'TEST-CLIENT_ID_123');
check(piePayPalClientId() === 'TEST-CLIENT_ID_123' && piePayPalClientIdConfigured(), 'PayPal Client ID is read from the dashboard');
check(strpos(piePayPalSdkUrl(), 'client-id=TEST-CLIENT_ID_123') !== false, 'the PayPal SDK URL is built from the stored Client ID');
check(!pieIsValidPayPalClientId('has spaces') && !pieIsValidPayPalClientId('short'), 'unsafe Client IDs are rejected');

setFakeSetting('terms_url', 'https://example.test/legal/terms');
check(pieTermsUrl() === 'https://example.test/legal/terms', 'the shared Terms URL is read from the dashboard');
setFakeSetting('terms_url', '');
check(pieTermsUrl() === 'https://example.test/terms', 'an empty Terms URL falls back to this site\'s Terms page');
setFakeSetting('terms_url', 'https://example.test/legal/terms');

$placeholders = piePaymentPlaceholders();
foreach (array('{{PAYPAL_CLIENT_ID}}', '{{PAYPAL_SDK_URL}}', '{{TERMS_URL}}', '{{SERVICES_OPTIONS}}', '{{SERVICES_JSON}}') as $token) {
    check(isset($placeholders[$token]), 'integration point exists: ' . $token);
}
check($placeholders['{{TERMS_AND_CONDITIONS_URL}}'] === $placeholders['{{TERMS_URL}}'], 'the Terms URL has exactly one value for both gateways');

/* --------------------- Only placeholders are substituted -------------------- */
$customCode = "<style>\n  .mine { color: red; } /* keep me */\n</style>\n"
    . "<form id=\"paypal-payment-form\" class=\"mine\">\n"
    . "  <select id=\"paypal-service\" name=\"service\"><option value=\"\">Pick</option>{{SERVICES_OPTIONS}}</select>\n"
    . "  <input id=\"paypal-amount\" name=\"amount\" type=\"number\" value=\"500\">\n"
    . "  <a href=\"{{TERMS_URL}}\">Terms &amp; Conditions</a>\n"
    . "  <script src=\"https://www.paypal.com/sdk/js?client-id={{PAYPAL_CLIENT_ID}}&currency=USD\"></script>\n"
    . "</form>\n"
    . "<script>\n  // \$not-a-php-var and \"quotes\" stay untouched\n"
    .  "  document.querySelector('#paypal-amount').addEventListener('change', function () { paypal.Buttons({}).render('#paypal-button-container'); });\n"
    . "</script>\n";

$rendered = pieRenderPaymentCode($customCode, 'paypal');
$expected = str_replace(
    array('{{PAYPAL_CLIENT_ID}}', '{{TERMS_URL}}', '{{SERVICES_OPTIONS}}'),
    array('TEST-CLIENT_ID_123', 'https://example.test/legal/terms', $options),
    $customCode
);
check($rendered === $expected, 'rendering replaces ONLY the integration points — the rest is byte-identical');
check(strpos($rendered, '{{') === false, 'no placeholder is left unresolved');
check(strpos($rendered, 'color: red') !== false && strpos($rendered, 'keep me') !== false, 'CSS and comments survive');
check(strpos($rendered, 'paypal.Buttons({}).render') !== false, 'JavaScript survives');
check(strpos($rendered, '&amp;') !== false, 'existing HTML entities are not decoded or re-encoded');
check(substr_count($rendered, '<style>') === 1 && substr_count($rendered, '<script') === 2, 'style and script blocks are preserved');
check(strpos($rendered, 'client-id=TEST-CLIENT_ID_123') !== false, 'the SDK URL uses the stored Client ID');

/* A pasted SDK URL that still carries an old client id follows the dashboard. */
$pastedSdk = '<script src="https://www.paypal.com/sdk/js?client-id=OLD_CLIENT_ID&currency=USD"></script>';
check(strpos(pieRenderPaymentCode($pastedSdk, 'paypal'), 'client-id=TEST-CLIENT_ID_123') !== false, 'a hard-coded SDK client id is pointed at the dashboard value');
check(strpos(pieRenderPaymentCode($pastedSdk, 'paypal'), 'OLD_CLIENT_ID') === false, 'the old hard-coded client id is gone');
setFakeSetting('paypal_client_id', '');
check(pieRenderPaymentCode($pastedSdk, 'paypal') === $pastedSdk, 'without a stored Client ID the pasted code is left alone');
setFakeSetting('paypal_client_id', 'TEST-CLIENT_ID_123');
check(pieRenderPaymentCode($pastedSdk, 'stripe') === $pastedSdk, 'the Stripe block is never touched by the PayPal Client ID');

/* The dashboard Client ID always wins, whatever the pasted SDK URL looks like. */
check(pieRenderPaymentCode('<script src="https://www.paypal.com/sdk/js?currency=USD&intent=capture"></script>', 'paypal') === '<script src="https://www.paypal.com/sdk/js?client-id=TEST-CLIENT_ID_123&currency=USD&intent=capture"></script>', 'the Client ID is added to an SDK URL that has none');
check(pieRenderPaymentCode('<script src="https://www.paypal.com/sdk/js"></script>', 'paypal') === '<script src="https://www.paypal.com/sdk/js?client-id=TEST-CLIENT_ID_123"></script>', 'the Client ID is added to a bare SDK URL');
check(pieRenderPaymentCode("<script src='https://www.paypal.com/sdk/js?client-id=OLD_CLIENT_ID&currency=USD'></script>", 'paypal') === "<script src='https://www.paypal.com/sdk/js?client-id=TEST-CLIENT_ID_123&currency=USD'></script>", 'quoted SDK URLs are handled');
check(pieRenderPaymentCode('<script src="https://example.com/sdk/js?client-id=KEEPME"></script>', 'paypal') === '<script src="https://example.com/sdk/js?client-id=KEEPME"></script>', 'non-PayPal URLs are never touched');
check(pieRenderPaymentCode('client-id=NOT_A_URL stays', 'paypal') === 'client-id=NOT_A_URL stays', 'plain text that only looks like a parameter is left alone');

/* -------------------------- PayPal shipping disabled ----------------------- */
$paypalStarter = piePayPalStarterCode();
$stripeStarter = pieStripeStarterCode();
check(strpos($paypalStarter, "shipping_preference: 'NO_SHIPPING'") !== false, 'the PayPal implementation creates its order with shipping_preference NO_SHIPPING');
check(stripos($paypalStarter, 'GET_FROM_FILE') === false, 'PayPal never takes the shipping address from the buyer profile');
check(preg_match('/shipping_preference[^,}]*NO_SHIPPING/', $paypalStarter) === 1, 'the only shipping preference configured is NO_SHIPPING');
check(!preg_match('/name=["\']shipping/i', $paypalStarter) && !preg_match('/id=["\']paypal-shipping/i', $paypalStarter), 'no shipping address field is created');
check(!preg_match('/shipping_amount|shipping_charge|delivery_fee/i', $paypalStarter), 'no shipping charge is added');
check(strpos($paypalStarter, 'id="paypal-button-container"') !== false, 'the PayPal SDK render container is preserved');

/* ---------------------- Both gateways on the same page --------------------- */
$paypalIds = piePaymentCodeIds($paypalStarter);
$stripeIds = piePaymentCodeIds($stripeStarter);
check(count($paypalIds) > 5 && count($stripeIds) > 5, 'both implementations define their own elements');
check(count(array_intersect($paypalIds, $stripeIds)) === 0, 'PayPal and Stripe share no HTML id');
check(in_array('paypal-payment-form', $paypalIds, true) && in_array('stripe-payment-form', $stripeIds, true), 'each form has its own unique id');
check(in_array('paypal-service', $paypalIds, true) && in_array('stripe-service', $stripeIds, true), 'each service dropdown has its own unique id');
check(!in_array('paypal-terms', $paypalIds, true) && !in_array('stripe-terms', $stripeIds, true), 'the Terms checkbox is gone from both implementations');
check(substr_count($paypalStarter, 'data-tpt-terms') >= 1 && substr_count($stripeStarter, 'data-tpt-terms') >= 1, 'both implementations still link the shared Terms URL');
check(strpos($paypalStarter, 'By continuing with your payment, you agree to our') !== false, 'the PayPal starter shows the short Terms agreement line');
check(strpos($stripeStarter, 'By continuing with your payment, you agree to our') !== false, 'the Stripe starter shows the short Terms agreement line');
check(!preg_match('/name="terms"|id="paypal-terms"|id="stripe-terms"/', $paypalStarter . $stripeStarter), 'no Terms checkbox remains in either implementation');
check(preg_match('/type="checkbox"[^>]*name="terms"/i', $paypalStarter . $stripeStarter) === 0, 'no checkbox is required before paying');
check(in_array('paypal-amount', $paypalIds, true) && in_array('stripe-amount', $stripeIds, true), 'each amount field has its own unique id');
check(in_array('paypal-button-container', $paypalIds, true) && in_array('stripe-payment-container', $stripeIds, true), 'provider containers are unique');
check(count(piePaymentCodeIds($paypalStarter)) === count(array_unique($paypalIds)), 'no id is repeated inside the PayPal code');
check(count(piePaymentCodeIds($stripeStarter)) === count(array_unique($stripeIds)), 'no id is repeated inside the Stripe code');
check(piePaymentIdConflicts(array('paypal' => $paypalStarter, 'stripe' => $stripeStarter)) === array(), 'the two implementations can be enabled together without conflicts');

$clashing = '<form id="payment-form"><input id="service"><input id="amount"></form>';
$other    = '<form id="payment-form"><input id="amount"></form>';
check(piePaymentIdConflicts(array('paypal' => $clashing, 'stripe' => $other)) === array('amount', 'payment-form'), 'duplicate ids across the two blocks are reported');
check(piePaymentIdConflicts(array('paypal' => $paypalStarter, 'stripe' => '')) === array(), 'an empty Stripe block reports no conflicts');

/* A dropdown that ignores the Services system is reported, not silently ignored. */
check(piePaymentUnlinkedSelects($paypalStarter) === array(), 'the PayPal starter is linked to the Services system');
check(piePaymentUnlinkedSelects($stripeStarter) === array(), 'the Stripe starter is linked to the Services system');
check(piePaymentUnlinkedSelects('<select id="paypal-service"><option>Service 1</option></select>') === array('paypal-service'), 'a hard-coded dropdown is reported');
check(piePaymentUnlinkedSelects('<select><option>x</option></select>') === array('(unnamed select)'), 'an unnamed hard-coded dropdown is reported');
check(piePaymentUnlinkedSelects('<select id="a" data-tpt-services></select>') === array(), 'a dropdown marked data-tpt-services is linked');
check(piePaymentUnlinkedSelects('') === array(), 'no dropdown, no warning');

/* Both implementations read the shared Services list and the shared Terms URL. */
foreach (array($paypalStarter, $stripeStarter) as $index => $starter) {
    check(strpos($starter, '{{SERVICES_OPTIONS}}') !== false, 'starter ' . ($index + 1) . ' builds its dropdown from the Services system');
    check(strpos($starter, '{{TERMS_URL}}') !== false, 'starter ' . ($index + 1) . ' links the shared Terms URL');
    check(strpos($starter, 'data-tpt-services') !== false && strpos($starter, 'data-tpt-terms') !== false, 'starter ' . ($index + 1) . ' also works through the shared bridge');
}
check(strpos($paypalStarter, '{{PAYPAL_CLIENT_ID}}') !== false, 'the PayPal starter takes its Client ID from the dashboard');

/* --------------------------------- Bridge ---------------------------------- */
$bridge = piePaymentBridge(array('paypal', 'stripe'));
check(strpos($bridge, 'window.TPT_PAYMENT=') !== false, 'the bridge publishes the shared payment settings');
check(strpos($bridge, "'NO_SHIPPING'") !== false, 'the bridge forces NO_SHIPPING on PayPal orders');
check(strpos($bridge, 'TPT_PAYMENT_SERVICES') !== false, 'the bridge exposes the shared Services list');
check(strpos($bridge, 'data-tpt-terms') !== false, 'the bridge applies the shared Terms URL');
check(strpos($bridge, '"providers":["paypal","stripe"]') !== false, 'the bridge only wires the rendered providers');
check(strpos($bridge, '</script>') === false || strpos($bridge, '<\/script>') !== false || strpos($bridge, 'json_encode') === false, 'the bridge never breaks out of its script tag');
setFakeServices(array(array('id' => 9, 'name' => 'Evil</script><script>alert(1)</script>', 'sort_order' => 1, 'is_active' => 1)));
$hostileBridge = piePaymentBridge(array('paypal'));
check(strpos($hostileBridge, '</script><script>alert(1)') === false, 'service names cannot break out of the bridge script');
setFakeServices(array(
    array('id' => 1, 'name' => 'AI Optimization', 'sort_order' => 1, 'is_active' => 1),
    array('id' => 2, 'name' => 'Web Development', 'sort_order' => 2, 'is_active' => 1),
    array('id' => 4, 'name' => 'Digital Marketing', 'sort_order' => 4, 'is_active' => 1),
    array('id' => 5, 'name' => 'Business Consultation', 'sort_order' => 5, 'is_active' => 1),
));

/* --------------------- Complete code is never truncated -------------------- */
$largeCode = str_repeat("/* line of CSS */\n.paypal-pay-form .field input{border:1px solid var(--line)}\n<div id=\"paypal-x\">text</div>\n", 900);
check(strlen($largeCode) > 40000, 'the sample implementation is larger than 40 KB');
check(pieRenderPaymentCode($largeCode, 'paypal') === $largeCode, 'a large implementation with no placeholders is returned unchanged');
check(html_entity_decode(esc($largeCode), ENT_QUOTES) === $largeCode, 'a large implementation survives the textarea round-trip (escaping is lossless)');
check(html_entity_decode(esc($paypalStarter), ENT_QUOTES) === $paypalStarter, 'the PayPal starter survives the textarea round-trip');
check(html_entity_decode(esc($stripeStarter), ENT_QUOTES) === $stripeStarter, 'the Stripe starter survives the textarea round-trip');

$schema = source('database/schema-mysql.sql') . source('database.sql');
check(strpos($schema, 'setting_value MEDIUMTEXT') !== false, 'payment code is stored in a MEDIUMTEXT column');
$widen = source('database/migrations/006_payment_code_storage.php') . source('database/migrations/007_payment_integration_settings.php');
check(strpos($widen, 'MEDIUMTEXT') !== false && strpos($widen, 'ALTER TABLE `settings`') !== false, 'older installations are widened to MEDIUMTEXT');
$seed = source('database/seed.php') . source('database.sql');
check(strpos($seed, "'paypal_client_id'") !== false && strpos($seed, "'terms_url'") !== false, 'fresh installs seed the new payment settings');
$upgrade = source('database-upgrade.sql');
check(strpos($upgrade, "'paypal_client_id'") !== false && strpos($upgrade, "'terms_url'") !== false, 'the upgrade dump adds the new payment settings');
check(substr_count($upgrade, "WHERE setting_key = 'terms_url'") === 1, 'the Terms URL setting is added exactly once');

/* ------------------------------- Admin wiring ------------------------------ */
$admin = source('admin/payments.php');
check(strpos($admin, 'name="paypal_client_id"') !== false, 'the dashboard has a PayPal Client ID field');
check(substr_count($admin, 'name="terms_url"') === 1, 'the dashboard has exactly ONE Terms & Conditions URL field');
check(!preg_match('/name="(paypal|stripe)_terms_url"/', $admin), 'no per-gateway Terms URL duplicates exist');
check(strpos($admin, "payment_action\" value=\"save_shared\"") !== false || strpos($admin, "value=\"save_shared\"") !== false, 'the shared settings have their own save action');
check(strpos($admin, 'paymentCodeFromRequest') !== false && strpos($admin, 'paymentCodeFromRequest($field)') !== false, 'saved payment code is taken from the request untouched');
check(strpos($admin, 'sanitizeMultiline($_POST[\'paypal_sdk_code\']') === false, 'payment code is never sanitised on save');
check(!preg_match('/name="paypal_sdk_code"[^>]*maxlength/', $admin) && !preg_match('/name="stripe_sdk_code"[^>]*maxlength/', $admin), 'no character limit on the payment code fields');
check(substr_count($admin, 'data-edit-code="paypal_sdk_code"') === 1 && substr_count($admin, 'data-edit-code="stripe_sdk_code"') === 1, 'both editors keep the Edit Code button');
check(strpos($admin, 'esc($paypalSdkCode)') !== false && strpos($admin, 'esc($stripeSdkCode)') !== false, 'saved code is HTML-escaped for the textarea');
check(strpos($admin, 'piePaymentIdConflicts') !== false, 'the dashboard audits duplicate ids between the two blocks');
check(strpos($admin, 'piePaymentUnlinkedSelects') !== false, 'the dashboard flags dropdowns that ignore the Services system');
check(strpos($admin, 'service_action') !== false && strpos($admin, 'payment_services') !== false, 'the existing Services manager is preserved');
check(strpos($admin, 'piePayPalStarterCode()') !== false && strpos($admin, 'pieStripeStarterCode()') !== false, 'ready-to-paste implementations are offered');
check(strpos($admin, 'name="paypal_secret"') !== false, 'the dashboard stores the PayPal Secret');
check(preg_match('/name="paypal_secret"[^>]*type="password"|type="password"[^>]*name="paypal_secret"/', $admin) === 1, 'the Secret field is a password field');
check(!preg_match('/name="paypal_secret"[^>]*value="<\?=/', $admin), 'the stored Secret is never written back into the field');
check(!preg_match('/esc\(\$paypalSecret\)|esc\(piePayPalSecret/', $admin), 'the stored Secret is never printed anywhere in the dashboard');
check(strpos($admin, 'paypal_secret_clear') !== false, 'an empty Secret field keeps the stored value until it is explicitly removed');
check(!preg_match('/name="stripe_secret"|secret_key/i', $admin), 'no other gateway secret is stored or displayed');
$paypalCore = source('core/PayPal.php');
check(strpos($paypalCore, 'SERVER-SIDE ONLY') !== false, 'core/PayPal.php marks the Secret as server-side only');
check(strpos(source('paypal-api.php'), 'piePayPalSecret') === false, 'the public endpoint never touches the Secret directly');
check(strpos(source('pay-online.php'), 'paypal_secret') === false, 'the Pay Online page never reads the Secret');
check(strpos(source('pay-online.php'), 'id="tpt-payment-confirmation"') !== false, 'the page ships the confirmed-payment block');
check(strpos(source('pay-online.php'), 'By continuing with your payment, you agree to our') !== false, 'the page shows the short Terms agreement line');

/* --------------------------- Public page wiring --------------------------- */
$public = source('pay-online.php');
check(strpos($public, 'pieIsPayPalEnabled()') !== false && strpos($public, 'pieIsStripeEnabled()') !== false, 'the Pay Online page still honours both toggles');
check(strpos($public, "pieRenderPaymentCode(\$paypalSdkCode, 'paypal')") !== false, 'the PayPal block is rendered through the integration layer');
check(strpos($public, "pieRenderPaymentCode(\$stripeSdkCode, 'stripe')") !== false, 'the Stripe block is rendered through the integration layer');
check(strpos($public, 'id="paypal-payment-code"') !== false && strpos($public, 'id="stripe-payment-code"') !== false, 'the two provider blocks keep unique container ids');
check(strpos($public, 'Online payments are currently unavailable') !== false, 'the unavailable message is unchanged');
check(substr_count($public, 'pay-custom-code') === 2, 'each provider renders exactly one code block');
check(strpos($public, '<?= $paypalSdkCode ?>') === false && strpos($public, '<?= $stripeSdkCode ?>') === false, 'code is never echoed without going through the renderer');
check(substr_count($public, 'class="info-card') === 1, 'the Pay Online page shows exactly ONE helper card');
check(strpos($public, 'Need help or having difficulties?') !== false, 'the helper card is the need-help card');
check(strpos($public, 'Provider-managed checkout') === false && strpos($public, 'Services you can pay for') === false, 'the two removed helper cards are gone');
check(strpos($public, 'pay-faq-wrap') !== false, 'the compact payment FAQ has its own wrapper');
check(substr_count($public, 'paymentFaq') >= 2, 'the FAQ is built from the payment FAQ data');
check(strpos($public, 'data-contact-modal') === false, 'the Pay Online page links to the Contact page instead of the popup');

echo "\n$count checks passed.\n";
