<?php
return function (PDO $pdo) {
    Migrations::column($pdo, 'chatbot_leads', 'status', "VARCHAR(30) NOT NULL DEFAULT 'new'");
    Migrations::column($pdo, 'chatbot_leads', 'notes', 'TEXT NULL');
    Migrations::column($pdo, 'chatbot_leads', 'conversation', 'TEXT NULL');
    Migrations::column($pdo, 'chatbot_leads', 'phone', 'VARCHAR(30) NULL');
    Migrations::column($pdo, 'chatbot_leads', 'company', 'VARCHAR(150) NULL');
    Migrations::column($pdo, 'chatbot_leads', 'service', 'VARCHAR(100) NULL');
    Migrations::column($pdo, 'contact_submissions', 'notification_status', "VARCHAR(30) NOT NULL DEFAULT 'unknown'");
};
