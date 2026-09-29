<?php
/**
 * Migration 005 — Admin Management, Media Library, and Subscriber Notifications.
 * Every statement is rerunnable (MySQL DDL auto-commits).
 */
return function (PDO $pdo) {
    /* 1. Admin Management: account status toggle */
    Migrations::column($pdo, 'admin_users', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1");

    /* 2. Media Library */
    $pdo->exec("CREATE TABLE IF NOT EXISTS media_library (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_name VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type ENUM('image', 'video', 'document') NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        file_size BIGINT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_media_type (file_type),
        KEY idx_media_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /* 3. Subscriber Notification Email Templates */
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_key VARCHAR(60) NOT NULL,
        subject VARCHAR(255) NOT NULL DEFAULT '',
        body MEDIUMTEXT NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_template_key (template_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
