<?php
/** Historical migration 007 — shared payment page settings.
 *
 * The Pay Online form uses the existing Services list and one shared Terms URL.
 * The PayPal Client ID is retained as the public application identifier used
 * by the server's OAuth flow; gateway Secrets are introduced by later server-
 * side migrations. Migration 010 removes obsolete browser-code settings.
 *
 * Keep this migration re-runnable for installations that have not applied it.
 */
return function (PDO $pdo) {
    /* Keep the shared settings value column at its established capacity. */
    $check = $pdo->prepare(
        "SELECT DATA_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'setting_value'"
    );
    $check->execute();
    if ((string) $check->fetchColumn() === 'text') {
        $pdo->exec('ALTER TABLE `settings` MODIFY `setting_value` MEDIUMTEXT');
    }

    /* The existing Services system — created by migration 003, re-created here
       so a fresh import that skipped it still has the single source of truth. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_payment_service (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /* One PayPal Client ID and ONE shared Terms & Conditions URL. */
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('paypal_client_id', ''),
        ('terms_url', '')");
};
