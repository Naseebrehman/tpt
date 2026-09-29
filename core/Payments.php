<?php
/** Payment-page service list. Payment processing is intentionally delegated to
 * customer-owned browser SDK code; this module contains no gateway API calls. */
if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

/** Active services for the Pay Online dropdown, in admin-defined order. */
function piePaymentServices()
{
    $tableExists = dbOne("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_services'");
    $names = array();
    if ($tableExists && (int) $tableExists['c'] > 0) {
        foreach (dbAll('SELECT name FROM payment_services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC') as $row) {
            $names[] = (string) $row['name'];
        }
    }
    return $names ?: array('AI Optimization', 'Web Development', 'Digital Marketing', 'Business Consultation', 'G-W-M Services', 'Monthly Marketing Charges', 'Others');
}
