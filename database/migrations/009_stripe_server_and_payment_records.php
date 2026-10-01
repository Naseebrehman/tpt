<?php
/** Migration 009 — Stripe server-side settings + the payment_records table.
 *
 *  1. Adds the two Stripe credentials the server-side flow needs:
 *        stripe_secret_key      the Stripe Secret Key — used ONLY on the server
 *                               (core/Stripe.php, stripe-api.php,
 *                               stripe-webhook.php), never rendered. Its
 *                               presence switches Stripe to server-verified
 *                               mode; without it the browser-only SDK flow
 *                               keeps working exactly as before.
 *        stripe_webhook_secret  the Stripe Webhook Secret — used ONLY to verify
 *                               the Stripe-Signature of the webhook endpoint.
 *
 *  2. Creates `payment_records`: one row per gateway-confirmed payment
 *     (PayPal capture or Stripe PaymentIntent / Checkout Session), written by
 *     the confirmation endpoints and by the signature-verified Stripe webhook.
 *     `provider` + `provider_transaction_id` are UNIQUE together, so the same
 *     gateway payment is never recorded (or exported / counted) twice.
 *
 *  Every statement is re-runnable (MySQL DDL auto-commits; the settings rows
 *  are INSERT IGNORE and the table is CREATE TABLE IF NOT EXISTS). */
return function (PDO $pdo) {
    /* 1 — Stripe credentials (server-side only; empty means "not stored"). */
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('stripe_secret_key', ''),
        ('stripe_webhook_secret', '')");

    /* 2 — confirmed payments, exactly once per gateway transaction.
       provider_transaction_id is NULL when a record has no gateway id (NULLs
       are not compared by a MySQL UNIQUE key, so such rows never collide). */
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(20) NOT NULL DEFAULT '',
        provider_transaction_id VARCHAR(150) DEFAULT NULL,
        payer_name VARCHAR(191) NOT NULL DEFAULT '',
        payer_email VARCHAR(191) NOT NULL DEFAULT '',
        service VARCHAR(191) NOT NULL DEFAULT '',
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
};
