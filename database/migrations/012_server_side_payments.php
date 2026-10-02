<?php
/**
 * Additive server-side PayPal + Stripe checkout ledger.
 * No existing payment or portfolio data is deleted or rewritten.
 */
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_payment_service (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("INSERT IGNORE INTO payment_services (name, sort_order, is_active) VALUES
        ('AI Optimization', 1, 1),
        ('Web Development', 2, 1),
        ('Digital Marketing', 3, 1),
        ('Business Consultation', 4, 1),
        ('G-W-M Services', 5, 1),
        ('Monthly Marketing Charges', 6, 1),
        ('Others', 7, 1)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_records (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        reference VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        provider VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        provider_order_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
        provider_transaction_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
        name VARCHAR(150) NOT NULL,
        service VARCHAR(150) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'USD',
        status ENUM('pending','completed','cancelled','failed') NOT NULL DEFAULT 'pending',
        state_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        confirmed_at DATETIME NULL,
        UNIQUE KEY uniq_payment_reference (reference),
        UNIQUE KEY uniq_payment_provider_order (provider, provider_order_id),
        KEY idx_payment_status_created (status, created_at),
        KEY idx_payment_provider (provider, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payment_id BIGINT UNSIGNED NOT NULL,
        provider VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        provider_event_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        payload_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uniq_payment_event (provider, provider_event_id),
        KEY idx_payment_event_record (payment_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
