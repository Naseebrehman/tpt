<?php
/** Pay Online support — the existing Services list, the shared Terms URL and
 * the PayPal Client ID stored in the application settings.
 *
 * Payments are completed entirely in the browser by the PayPal JavaScript SDK
 * (pay-online.php). There is no server-side capture, confirmation, webhook or
 * payment record: this module only reads the settings the admin dashboard
 * writes.
 */
if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

/* ===========================================================================
   Existing Admin → Payment Settings services list
   =========================================================================== */

/** Active service names in the order configured by Admin → Payment Settings. */
function piePaymentServices()
{
    $table = dbOne(
        "SELECT COUNT(*) AS c FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_services'"
    );
    $names = array();
    if ($table && (int) $table['c'] > 0) {
        foreach (dbAll('SELECT name FROM payment_services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC') as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') { $names[] = $name; }
        }
        return $names;
    }
    return array('AI Optimization', 'Web Development', 'Digital Marketing', 'Business Consultation', 'G-W-M Services', 'Monthly Marketing Charges', 'Others');
}

/** Service rows for the existing Admin services manager. */
function piePaymentServiceRows()
{
    require_once BASE_PATH . '/core/Schema.php';
    return Schema::hasTable('payment_services')
        ? dbAll('SELECT * FROM payment_services ORDER BY sort_order ASC, id ASC')
        : array();
}

/* ===========================================================================
   PayPal Client ID (public application identifier used by the browser SDK)
   =========================================================================== */

/** The Client ID saved in Admin → Payment Settings; empty when not configured. */
function piePayPalClientId()
{
    return trim((string) getSetting('paypal_client_id', ''));
}

/** True when a usable PayPal Client ID is stored. */
function piePayPalClientIdConfigured()
{
    return pieIsValidPayPalClientId(piePayPalClientId());
}

/** PayPal Client IDs contain letters, numbers, hyphens and underscores
 *  (PayPal's own SDK demo value "test" is accepted too). */
function pieIsValidPayPalClientId($clientId)
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{4,255}$/D', (string) $clientId);
}

/** PayPal JavaScript SDK URL built from the currently saved Client ID. */
function piePayPalSdkUrl($clientId = '')
{
    $clientId = $clientId !== '' ? (string) $clientId : piePayPalClientId();
    return 'https://www.paypal.com/sdk/js?client-id=' . rawurlencode($clientId) . '&currency=USD&components=buttons';
}

/* ===========================================================================
   Terms & Conditions link shown on the payment page
   =========================================================================== */

/** Public Terms & Conditions URL linked from the payment page. */
function pieTermsUrl()
{
    return rtrim(SITE_URL, '/') . url('terms');
}
