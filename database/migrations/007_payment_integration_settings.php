<?php
/** Migration 007 — payment integration settings.
 *
 * The Pay Online page now substitutes three admin-controlled values into the
 * administrator's saved PayPal / Stripe code:
 *
 *   paypal_client_id  the PayPal Client ID used by the PayPal SDK
 *   terms_url         the ONE shared Terms & Conditions URL (PayPal + Stripe)
 *   payment_services  the existing Services system (single source of truth)
 *
 * Only the two new settings rows are added here; the Services table already
 * exists from migration 003 and is never duplicated. The payment-code column
 * is re-checked so a complete implementation (HTML + CSS + JavaScript) is never
 * truncated — MEDIUMTEXT holds 16 MB.
 *
 * Every statement is re-runnable (MySQL DDL auto-commits). */
return function (PDO $pdo) {
    /* Payment code storage must stay wide enough for a complete paste. */
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
