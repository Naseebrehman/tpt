<?php
/**
 * Migration 003 — Alia chat transcripts, chat status tracking and the
 * admin-managed payment service list.
 * Every statement is rerunnable (MySQL DDL auto-commits).
 */
return function (PDO $pdo) {
    /* ------------------------------------------------------------------
       Alia chats (Task: Admin → Alia → Chats)
       One row per chat session in chatbot_leads; every turn is stored
       chronologically in chatbot_messages so the dashboard can replay the
       full conversation.
       ------------------------------------------------------------------ */
    $pdo->exec("CREATE TABLE IF NOT EXISTS chatbot_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        content TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_msg_session (session_id),
        KEY idx_msg_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    Migrations::column($pdo, 'chatbot_leads', 'last_message', "TEXT");
    Migrations::column($pdo, 'chatbot_leads', 'message_count', "INT NOT NULL DEFAULT 0");
    Migrations::column($pdo, 'chatbot_leads', 'updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    Migrations::column($pdo, 'chatbot_leads', 'ip_address', "VARCHAR(45) NOT NULL DEFAULT ''");

    /* ------------------------------------------------------------------
       Payment services (Task: manage the Pay Online dropdown from admin)
       ------------------------------------------------------------------ */
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_payment_service (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /* Seed the list the payment form has always offered, without duplicating. */
    $pdo->exec("INSERT IGNORE INTO payment_services (name, sort_order, is_active) VALUES
        ('AI Optimization', 1, 1),
        ('Web Development', 2, 1),
        ('Digital Marketing', 3, 1),
        ('Business Consultation', 4, 1),
        ('G-W-M Services', 5, 1),
        ('Monthly Marketing Charges', 6, 1),
        ('Others', 7, 1)");
};
