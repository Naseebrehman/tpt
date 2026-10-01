<?php
/** Migration 011 — PayPal SDK-only payment page.
 *
 * The payment page now uses the PayPal JavaScript SDK with the Client ID saved
 * in Admin → Payment Settings. This migration removes everything the previous
 * server-side PayPal/Stripe integration and payment-record system left behind:
 *
 *  1. Keeps `paypal_client_id` (the only payment setting the site needs) and
 *     widens the existing settings value column when an old install still uses
 *     TEXT.
 *  2. Deletes every Stripe setting and every stored PayPal/server credential
 *     and credential toggle.
 *  3. Drops the legacy `payments`, `payment_events` and `payment_records`
 *     tables: payments are completed by PayPal in the browser and are not
 *     stored in this database.
 *  4. Removes the payment email templates and the Payment notification
 *     category, and refreshes the stored Alia prompt when it still mentions
 *     Stripe.
 *
 * Every statement is re-runnable (MySQL DDL auto-commits; deletes/drops use
 * IF EXISTS/WHERE guards).
 */
return function (PDO $pdo) {
    /* 1 — Client ID setting kept; settings column stays large enough. */
    $check = $pdo->prepare(
        "SELECT DATA_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'setting_value'"
    );
    $check->execute();
    if ((string) $check->fetchColumn() === 'text') {
        $pdo->exec('ALTER TABLE `settings` MODIFY `setting_value` MEDIUMTEXT');
    }
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('paypal_client_id', '')");

    /* 2 — Stripe and server-side credential settings. */
    $pdo->exec("DELETE FROM settings WHERE setting_key IN (
        'paypal_secret', 'paypal_env', 'paypal_enabled', 'paypal_mode',
        'paypal_sdk_code', 'paypal_custom_code', 'paypal_sdk_url', 'paypal_code',
        'stripe_enabled', 'stripe_secret_key', 'stripe_webhook_secret',
        'stripe_publishable_key', 'stripe_mode', 'stripe_sdk_code',
        'stripe_custom_code', 'stripe_sdk_url', 'stripe_code',
        'payment_code', 'terms_url'
    )");

    /* 3 — Legacy payment attempt/record tables (contacts are not stored). */
    $pdo->exec('DROP TABLE IF EXISTS payment_events');
    $pdo->exec('DROP TABLE IF EXISTS payment_records');
    $pdo->exec('DROP TABLE IF EXISTS payments');

    /* 4 — Payment emails/category and the stored Alia payment wording. */
    $tableExists = function ($table) use ($pdo) {
        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $check->execute(array($table));
        return (int) $check->fetchColumn() > 0;
    };
    if ($tableExists('email_templates')) {
        $pdo->exec("DELETE FROM email_templates WHERE template_key IN (
            'payment_admin', 'payment_confirm', 'payment_request_admin', 'payment_request_confirm'
        )");
    }
    if ($tableExists('notification_emails')) {
        $pdo->exec("UPDATE notification_emails
            SET categories = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', categories, ','), ',payment,', ','))
            WHERE categories LIKE '%payment%'");
    }

    $update = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'chatbot_system_prompt' AND setting_value LIKE '%Stripe%'");
    $select = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'chatbot_system_prompt' AND setting_value LIKE '%Stripe%'");
    foreach ($select->fetchAll(PDO::FETCH_COLUMN) as $prompt) {
        $update->execute(array(str_replace(
            array('through PayPal and Stripe on the Pay Online page', 'PayPal and Stripe'),
            array('through PayPal on the Pay Online page', 'PayPal'),
            (string) $prompt
        )));
    }
};
