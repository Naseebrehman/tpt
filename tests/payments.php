<?php
/** Dependency-free shared payment-form, validation, encryption and security checks.
 * Run: php tests/payments.php
 */
require __DIR__ . '/payment-test-bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

paymentSetServices(array(
    array('id' => 1, 'name' => 'AI Optimization', 'sort_order' => 1, 'is_active' => 1),
    array('id' => 2, 'name' => 'Web Development', 'sort_order' => 2, 'is_active' => 1),
    array('id' => 3, 'name' => 'Retired Service', 'sort_order' => 3, 'is_active' => 0),
    array('id' => 4, 'name' => 'Digital Marketing', 'sort_order' => 4, 'is_active' => 1),
));
$services = piePaymentServices();
paymentCheck($services === array('AI Optimization', 'Web Development', 'Digital Marketing'), 'active services use the existing ordered Admin Services list');
$options = pieServicesOptionsHtml();
paymentCheck(substr_count($options, '<option') === 3 && strpos($options, 'Retired Service') === false, 'hidden Services stay out of the public dropdown');
paymentSetServices(array(
    array('id' => 4, 'name' => 'Digital Marketing', 'sort_order' => 1, 'is_active' => 1),
    array('id' => 1, 'name' => 'AI Optimization', 'sort_order' => 2, 'is_active' => 1),
));
paymentCheck(piePaymentServices() === array('Digital Marketing', 'AI Optimization'), 'Admin service reordering is reflected immediately');
$GLOBALS['fake_tables']['payment_services'] = false;
paymentCheck(count(piePaymentServices()) === 7, 'legacy fallback services remain available when the table is missing');
$GLOBALS['fake_tables']['payment_services'] = true;
paymentSetServices(array());
paymentCheck(piePaymentServices() === array(), 'an intentionally empty active Services list stays empty instead of exposing defaults');
paymentSetServices(array(
    array('id' => 1, 'name' => 'AI Optimization', 'sort_order' => 1, 'is_active' => 1),
    array('id' => 2, 'name' => 'Web Development', 'sort_order' => 2, 'is_active' => 1),
));

paymentCheck(pieIsValidTermsUrl('https://example.test/terms') && pieIsValidTermsUrl('/terms'), 'shared Terms accepts absolute HTTPS URLs and site-local paths');
paymentCheck(!pieIsValidTermsUrl('javascript:alert(1)') && !pieIsValidTermsUrl('//evil.example/terms') && !pieIsValidTermsUrl('/\\\\evil.example'), 'Terms links reject script schemes, protocol-relative hosts and backslashes');
paymentCheck(piePaymentNormalizeAmount('500') === '500.00', 'whole-dollar amount is normalized to two decimals');
paymentCheck(piePaymentNormalizeAmount('0.01') === '0.01' && piePaymentNormalizeAmount('1000000.00') === '1000000.00', 'the minimum and maximum USD amounts are accepted');
foreach (array('', '0', '0.00', '-5', '+5', '1e3', '1.001', '1,000', '1000000.01', '10000000', array('amount' => 5)) as $invalidAmount) {
    paymentCheck(piePaymentNormalizeAmount($invalidAmount) === '', 'invalid/ambiguous amount is rejected');
}

$validated = piePaymentValidateSubmission(array(
    'name' => '  Ada Lovelace  ',
    'email' => 'ADA@example.test',
    'phone' => '+1 (555) 123-4567',
    'service' => 'AI Optimization',
    'amount' => '250.25',
    'notes' => "First line\n<script>alert(1)</script> second line",
));
paymentCheck($validated['ok'] === true, 'valid customer, active service and amount pass server validation');
paymentCheck($validated['data']['email'] === 'ada@example.test' && $validated['data']['amount'] === '250.25', 'email and amount are normalized server-side');
paymentCheck(strpos($validated['data']['notes'], '<script') === false && strpos($validated['data']['notes'], "\n") !== false, 'notes are sanitized while retaining line breaks');
foreach (array(
    array('email' => 'nope'),
    array('service' => 'Retired Service'),
    array('service' => 'Made up service'),
    array('amount' => '1e3'),
    array('name' => '<b></b>'),
    array('email' => array('bad')),
) as $invalidPatch) {
    $base = array('name' => 'Customer', 'email' => 'buyer@example.test', 'phone' => '', 'service' => 'AI Optimization', 'amount' => '25.00', 'notes' => '');
    $result = piePaymentValidateSubmission(array_merge($base, $invalidPatch));
    paymentCheck($result['ok'] === false, 'server rejects invalid submitted payment data');
}

/* The key is private configuration, not a database setting. */
$secret = 'sk_test_' . str_repeat('AbC123', 5);
$ciphertext = piePaymentCredentialEncrypt($secret);
paymentCheck(is_string($ciphertext) && strpos($ciphertext, 'enc:v1:') === 0, 'credentials use the versioned AES-GCM ciphertext format');
paymentCheck($ciphertext !== $secret && strpos($ciphertext, $secret) === false, 'stored credential text does not contain the plaintext key');
paymentCheck(piePaymentCredentialDecrypt($ciphertext) === $secret, 'an encrypted credential decrypts only with the configured server key');
$altered = substr($ciphertext, 0, -1) . (substr($ciphertext, -1) === 'A' ? 'B' : 'A');
paymentCheck(piePaymentCredentialDecrypt($altered) === '', 'tampered ciphertext fails closed');
paymentCheck(piePaymentCredentialIsEncrypted($ciphertext) && !piePaymentCredentialIsEncrypted($secret), 'legacy plaintext is distinguishable for migration');

$paypalClientId = 'AbCdEfGh1234567890_XYZ';
paymentSetSetting('paypal_enabled', '1');
paymentSetSetting('paypal_client_id', $paypalClientId);
paymentSetSetting('paypal_env', 'sandbox');
paymentSetSetting('paypal_secret', piePaymentCredentialEncrypt('PaypalSecret-' . str_repeat('x', 30)));
paymentCheck(pieIsPayPalEnabled() && piePayPalEnv() === 'sandbox', 'PayPal enabled and Sandbox settings are read by PHP');
paymentCheck(piePayPalServerReady() && piePayPalApiBase() === 'https://api-m.sandbox.paypal.com', 'PayPal server readiness requires its Client ID and decryptable Secret');
paymentCheck(piePayPalSecretConfigured() && piePayPalSecret() === 'PaypalSecret-' . str_repeat('x', 30), 'PayPal Secret decrypts on the server only');
$goodPayPalSecret = $GLOBALS['fake_settings']['paypal_secret'];
paymentSetSetting('paypal_secret', piePaymentCredentialEncrypt('too-short'));
paymentCheck(piePayPalServerReady() === false, 'PayPal will not be exposed to customers with an invalid stored Secret');
paymentSetSetting('paypal_secret', $goodPayPalSecret);
paymentCheck(pieIsValidPayPalClientId('bad id') === false && pieIsValidPayPalClientId('x') === false, 'malformed PayPal Client IDs are rejected');
paymentSetSetting('stripe_enabled', '1');
paymentSetSetting('stripe_secret_key', piePaymentCredentialEncrypt($secret));
paymentCheck(pieIsStripeEnabled() && pieStripeServerReady() && pieStripeKeyMode() === 'test', 'Stripe test key is decrypted server-side and its mode is detected');
paymentCheck(pieStripeSecret() === $secret, 'Stripe Secret Key remains available only through the PHP helper');
$goodStripeSecret = $GLOBALS['fake_settings']['stripe_secret_key'];
paymentSetSetting('stripe_secret_key', piePaymentCredentialEncrypt('pk_test_' . str_repeat('x', 24)));
paymentCheck(pieStripeServerReady() === false, 'Stripe will not be exposed to customers with a publishable key in the Secret field');
paymentSetSetting('stripe_secret_key', $goodStripeSecret);

/* Pending attempt first, then record only after matching provider confirmation. */
$GLOBALS['fake_tables']['payments'] = true;
$GLOBALS['fake_tables']['payment_records'] = true;
$created = piePaymentCreateAttempt('stripe', $validated['data']);
paymentCheck($created['ok'] === true && $created['attempt']['status'] === 'pending', 'checkout first creates a pending row in the existing payments table');
paymentCheck((bool) preg_match('/^[a-f0-9]{64}$/D', $created['attempt']['token']), 'each pending attempt has an unpredictable server-generated token');
$attempt = $created['attempt'];
$recordCountBefore = count($GLOBALS['fake_record_rows']);
$notConfirmed = piePaymentCompleteAttempt($attempt, 'stripe', array('confirmed' => false), 'cs_test_fake');
paymentCheck($notConfirmed['ok'] === false && count($GLOBALS['fake_record_rows']) === $recordCountBefore, 'unconfirmed browser/provider data cannot create a success record');
$amountMismatch = piePaymentCompleteAttempt($attempt, 'stripe', array('confirmed' => true, 'reference' => 'pi_12345678', 'amount' => '249.99', 'currency' => 'USD'), 'cs_test_fake');
paymentCheck($amountMismatch['ok'] === false && count($GLOBALS['fake_record_rows']) === $recordCountBefore, 'a mismatched gateway amount cannot be recorded as successful');
$confirmation = array('confirmed' => true, 'reference' => 'pi_12345678', 'amount' => '250.25', 'currency' => 'USD');
$completed = piePaymentCompleteAttempt($attempt, 'stripe', $confirmation, 'cs_test_session');
paymentCheck($completed['ok'] === true && $completed['transaction_id'] === 'pi_12345678', 'matching server confirmation creates the successful record');
$record = $GLOBALS['fake_record_rows']['stripe|pi_12345678'];
paymentCheck($record['status'] === 'succeeded' && $record['currency'] === 'USD' && $record['amount'] === '250.25', 'record stores confirmed amount, currency and success status');
paymentCheck($record['payer_name'] === 'Ada Lovelace' && $record['payer_email'] === 'ada@example.test' && $record['payer_phone'] === '+1 (555) 123-4567', 'record stores the server-validated customer details');
paymentCheck($record['service'] === 'AI Optimization' && $record['notes'] !== '', 'record stores the selected service and customer notes');
$again = piePaymentCompleteAttempt($attempt, 'stripe', $confirmation, 'cs_test_session');
paymentCheck($again['ok'] === true && count($GLOBALS['fake_record_rows']) === $recordCountBefore + 1, 'a duplicate provider confirmation does not create another record');

/* PRG notices keep success in server session state, never in a query string. */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
piePaymentSetNotice('success', '', array('name' => 'Ada Lovelace', 'amount' => '250.25', 'reference' => 'pi_12345678'));
$notice = piePaymentConsumeNotice();
paymentCheck($notice['type'] === 'success' && $notice['name'] === 'Ada Lovelace' && $notice['reference'] === 'pi_12345678', 'a server-created PRG notice carries the confirmed name and transaction reference');
paymentCheck(piePaymentConsumeNotice() === null, 'PRG notices are consumed once');

/* Static safety checks for the customer page and Admin. */
$page = paymentSource('pay-online.php');
foreach (array('actions.order.create', 'actions.order.capture', 'paypal.com/sdk', 'paypal.Buttons', 'stripe-js', 'Stripe(', 'client_secret', 'TPT_STRIPE', 'TPT_PAYPAL') as $forbidden) {
    paymentCheck(stripos($page, $forbidden) === false, 'public payment page has no browser payment integration: ' . $forbidden);
}
paymentCheck(strpos($page, 'name="phone"') !== false && strpos($page, 'name="service"') !== false && strpos($page, 'name="amount"') !== false, 'existing customer, service and amount form fields remain');
paymentCheck(strpos($page, 'id="payment-form"') !== false && strpos($page, 'id="payment-name"') !== false && strpos($page, 'id="tpt-payment-confirmation"') !== false, 'stable payment form and confirmation identifiers remain in place');
paymentCheck(strpos($page, 'Payment Reference:') !== false && strpos($page, 'successfully completed.') !== false, 'the exact server-confirmed thank-you and reference messages are rendered');
paymentCheck(strpos($page, 'csrfField()') !== false && strpos($page, 'validateCSRF') === false, 'the public form includes the session CSRF field');
$admin = paymentSource('admin/payments.php');
paymentCheck(strpos($admin, 'requireAdmin()') !== false && strpos($admin, 'validateCSRF()') !== false, 'Admin Payments retains authentication and CSRF checks');
paymentCheck(strpos($admin, 'requireAdmin()') < strpos($admin, 'Schema::ensure()'), 'Admin authentication runs before payment migrations or settings writes');
paymentCheck(strpos($admin, 'name="paypal_secret" type="password" value=""') !== false && strpos($admin, 'name="stripe_secret_key" type="password" value=""') !== false, 'admin secret inputs are blank and never echo stored credentials');
paymentCheck(strpos($admin, 'pieSavePaymentCredential') !== false && strpos($admin, 'savePaymentSetting(\'paypal_secret\'') === false, 'admin stores provider secrets encrypted rather than as plaintext settings');
paymentCheck(strpos($admin, 'data-edit-code') === false && strpos($admin, 'paypal_sdk_code') === false && strpos($admin, 'stripe_sdk_code') === false, 'custom payment-code editors/settings have been removed');
paymentCheck(!file_exists(BASE_PATH . '/core/PaymentTemplates.php'), 'legacy custom payment-code renderer has been removed');
$routes = paymentSource('app/routes.php');
paymentCheck(strpos($routes, '/paypal-api') !== false && strpos($routes, '/stripe-api') !== false && strpos($routes, '/stripe-webhook') !== false, 'separate server-side provider routes and signed Stripe webhook are routed');
$css = paymentSource('assets/css/refinements.css');
paymentCheck(strpos($css, '.pay-layout{display:grid') !== false && strpos($css, '@media(max-width:640px)') !== false, 'the payment form has responsive desktop/mobile layout rules');

paymentTestSummary();
