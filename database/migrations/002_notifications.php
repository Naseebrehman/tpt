<?php
/**
 * Migration 002 — notification system, email templates, payment details.
 * Every statement is rerunnable (MySQL DDL auto-commits).
 */
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_emails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        categories VARCHAR(255) NOT NULL DEFAULT 'contact,payment,lead,chatbot,system,security',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_notification_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_key VARCHAR(60) NOT NULL,
        subject VARCHAR(255) NOT NULL DEFAULT '',
        body MEDIUMTEXT NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_template_key (template_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /* Payment form details (Task 3): phone + service captured with each payment. */
    Migrations::column($pdo, 'payments', 'phone', "VARCHAR(30) NOT NULL DEFAULT ''");
    Migrations::column($pdo, 'payments', 'service', "VARCHAR(100) NOT NULL DEFAULT ''");
};
