<?php
/** Migration 010 — secure the existing payment system for server-side hosted
 * checkout, add payment notes to confirmed records, and retire browser code.
 * Re-runnable: all columns/tables are checked and credential ciphertext is
 * recognized before encrypting legacy plaintext settings.
 */
require_once dirname(__DIR__, 2) . '/core/PaymentCredentials.php';

return function (PDO $pdo) {
    /* Existing Payments rows track pending attempts and later receive their
       gateway status; payment_records remains the confirmed Admin audit list. */
    $payments = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'"
    );
    $payments->execute();
    if ((int) $payments->fetchColumn() > 0) {
        Migrations::column($pdo, 'payments', 'phone', "VARCHAR(30) NOT NULL DEFAULT ''");
        Migrations::column($pdo, 'payments', 'service', "VARCHAR(191) NOT NULL DEFAULT ''");
        $phoneColumn = $pdo->prepare(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'phone'"
        );
        $phoneColumn->execute();
        if ((int) $phoneColumn->fetchColumn() < 30) {
            $pdo->exec("ALTER TABLE `payments` MODIFY `phone` VARCHAR(30) NOT NULL DEFAULT ''");
        }
        $serviceColumn = $pdo->prepare(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'service'"
        );
        $serviceColumn->execute();
        if ((int) $serviceColumn->fetchColumn() < 191) {
            $pdo->exec("ALTER TABLE `payments` MODIFY `service` VARCHAR(191) NOT NULL DEFAULT ''");
        }
    }

    /* Preserve the existing successful-payment dashboard while also storing
       optional notes with each confirmed gateway record. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(20) NOT NULL DEFAULT '',
        provider_transaction_id VARCHAR(150) DEFAULT NULL,
        payer_name VARCHAR(191) NOT NULL DEFAULT '',
        payer_email VARCHAR(191) NOT NULL DEFAULT '',
        payer_phone VARCHAR(30) NOT NULL DEFAULT '',
        service VARCHAR(191) NOT NULL DEFAULT '',
        notes TEXT,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        currency VARCHAR(10) NOT NULL DEFAULT 'USD',
        status VARCHAR(30) NOT NULL DEFAULT 'succeeded',
        verification_mode VARCHAR(20) NOT NULL DEFAULT 'server',
        raw_reference VARCHAR(255) NOT NULL DEFAULT '',
        ip_address VARCHAR(45) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_provider_transaction (provider, provider_transaction_id),
        KEY idx_payment_records_provider (provider),
        KEY idx_payment_records_status (status),
        KEY idx_payment_records_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    Migrations::column($pdo, 'payment_records', 'payer_phone', "VARCHAR(30) NOT NULL DEFAULT ''");
    Migrations::column($pdo, 'payment_records', 'notes', 'TEXT NULL');

    /* Missing toggles are safely off. Existing admin choices are preserved. */
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('paypal_enabled', '0'),
        ('stripe_enabled', '0')");

    /* Remove settings that injected browser-side gateway code. The PayPal
       Client ID, PayPal Secret, PayPal environment, Stripe Secret, optional
       Stripe Webhook Secret, enable toggles and shared Terms URL remain. */
    $pdo->exec("DELETE FROM settings WHERE setting_key IN (
        'paypal_sdk_code', 'stripe_sdk_code',
        'paypal_custom_code', 'stripe_custom_code',
        'paypal_sdk_url', 'stripe_sdk_url',
        'paypal_code', 'stripe_code', 'payment_code',
        'stripe_publishable_key'
    )");

    /* Encrypt previously saved credentials in place. Keys are derived from
       server-only deployment configuration or a dedicated local config key. */
    $credentialRows = $pdo->query("SELECT setting_key, setting_value FROM settings
        WHERE setting_key IN ('paypal_secret', 'stripe_secret_key', 'stripe_webhook_secret')")->fetchAll(PDO::FETCH_ASSOC);
    $update = $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
    foreach ($credentialRows as $row) {
        $stored = (string) ($row['setting_value'] ?? '');
        if ($stored === '' || piePaymentCredentialIsEncrypted($stored)) { continue; }
        $encrypted = piePaymentCredentialEncrypt($stored);
        if (!is_string($encrypted) || $encrypted === '') {
            throw new RuntimeException('Could not encrypt existing payment credentials; enable PHP OpenSSL and rerun the migration.');
        }
        $update->execute(array($encrypted, (string) $row['setting_key']));
    }
};
