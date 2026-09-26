<?php
return function (PDO $pdo) {
    Migrations::column($pdo, 'chatbot_leads', 'status', "VARCHAR(30) NOT NULL DEFAULT 'new'");
    Migrations::column($pdo, 'chatbot_leads', 'notes', 'TEXT NULL');
    Migrations::column($pdo, 'chatbot_leads', 'conversation', 'TEXT NULL');
    Migrations::column($pdo, 'chatbot_leads', 'phone', 'VARCHAR(30) NULL');
    Migrations::column($pdo, 'chatbot_leads', 'company', 'VARCHAR(150) NULL');
    Migrations::column($pdo, 'chatbot_leads', 'service', 'VARCHAR(100) NULL');
    Migrations::column($pdo, 'contact_submissions', 'notification_status', "VARCHAR(30) NOT NULL DEFAULT 'unknown'");
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_events (
        event_id VARCHAR(255) PRIMARY KEY, payment_id INT NOT NULL,
        event_type VARCHAR(100) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_payment_event (payment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
