-- ===========================================================================
--  The Pie Technologies — canonical schema for FRESH installations.
--  Applied by: php bin/cli.php install  (statement-by-statement)
--
--  This file is the complete current schema: it already contains every column
--  and table the /admin dashboard expects (including chatbot lead fields,
--  contact_submissions.notification_status and payment_events — formerly only
--  added by database/migrations/001_application.php on older installs).
--
--  Existing installations must NOT re-import this file; use:
--      php bin/cli.php migrate
--
--  Statements are split on ";" outside quotes/comments before execution, so
--  this file must stay delimiter-simple (no stored procedures / DELIMITER).
-- ===========================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_lockouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150),
  ip_address VARCHAR(45),
  attempts INT DEFAULT 0,
  locked_until DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lock_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(30),
  company VARCHAR(150),
  service VARCHAR(100),
  budget VARCHAR(50),
  message TEXT,
  source VARCHAR(100),
  status ENUM('new','in_progress','replied','closed') DEFAULT 'new',
  notes TEXT,
  ip_address VARCHAR(45),
  user_agent TEXT,
  notification_status VARCHAR(30) NOT NULL DEFAULT 'unknown',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sub_status (status),
  KEY idx_sub_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) UNIQUE NOT NULL,
  category_id INT,
  featured_image VARCHAR(255),
  excerpt TEXT,
  content LONGTEXT,
  tags VARCHAR(255) DEFAULT '',
  author VARCHAR(150) DEFAULT 'The Pie Technologies',
  meta_title VARCHAR(255),
  meta_description TEXT,
  reading_time INT DEFAULT 5,
  views INT DEFAULT 0,
  status ENUM('draft','published') DEFAULT 'draft',
  published_at DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_post_status (status),
  KEY idx_post_cat (category_id),
  CONSTRAINT fk_post_category FOREIGN KEY (category_id)
    REFERENCES blog_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT,
  name VARCHAR(150),
  email VARCHAR(150),
  comment TEXT,
  status ENUM('pending','approved','spam') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_comment_post (post_id),
  CONSTRAINT fk_comment_post FOREIGN KEY (post_id)
    REFERENCES blog_posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_name VARCHAR(150),
  service_category VARCHAR(100),
  industry VARCHAR(100) DEFAULT '',
  slug VARCHAR(255) UNIQUE NOT NULL,
  thumbnail VARCHAR(255),
  challenge TEXT,
  strategy TEXT,
  results TEXT,
  stats_json TEXT,
  chart_data_json TEXT,
  testimonial TEXT,
  testimonial_author VARCHAR(150),
  display_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150),
  role VARCHAR(150),
  photo VARCHAR(255),
  bio TEXT,
  linkedin VARCHAR(255),
  twitter VARCHAR(255),
  display_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150),
  company VARCHAR(150),
  role VARCHAR(150),
  content TEXT,
  rating TINYINT DEFAULT 5,
  photo VARCHAR(255),
  service VARCHAR(100),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255),
  slug VARCHAR(200) UNIQUE,
  description TEXT,
  content LONGTEXT,
  cover_image VARCHAR(255),
  file_path VARCHAR(255),
  resource_type ENUM('guide','template','video','blueprint','playbook','checklist','framework','tutorial','case-study') DEFAULT 'guide',
  video_url VARCHAR(500),
  category VARCHAR(100),
  reading_time INT DEFAULT 5,
  download_count INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) UNIQUE NOT NULL,
  name VARCHAR(150),
  is_active TINYINT(1) DEFAULT 1,
  subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chatbot_leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(100),
  name VARCHAR(150),
  email VARCHAR(150),
  phone VARCHAR(30),
  company VARCHAR(150),
  service VARCHAR(100),
  status VARCHAR(30) NOT NULL DEFAULT 'new',
  notes TEXT,
  conversation TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(64) UNIQUE NOT NULL,
  name VARCHAR(150),
  email VARCHAR(150),
  phone VARCHAR(30) NOT NULL DEFAULT '',
  service VARCHAR(100) NOT NULL DEFAULT '',
  reference VARCHAR(150),
  amount_usd DECIMAL(10,2) DEFAULT 0,
  notes TEXT,
  method ENUM('invoice','stripe','paypal') DEFAULT 'invoice',
  status ENUM('requested','pending','paid','failed','cancelled') DEFAULT 'requested',
  provider_ref VARCHAR(255) DEFAULT '',
  ip_address VARCHAR(45) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_events (
  event_id VARCHAR(255) PRIMARY KEY,
  payment_id INT NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payment_event (payment_id),
  CONSTRAINT fk_event_payment FOREIGN KEY (payment_id)
    REFERENCES payments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_records (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value MEDIUMTEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_emails (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  categories VARCHAR(255) NOT NULL DEFAULT 'contact,payment,lead,chatbot,system,security',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_notification_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(60) NOT NULL,
  subject VARCHAR(255) NOT NULL DEFAULT '',
  body MEDIUMTEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_template_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_views (
  id INT AUTO_INCREMENT PRIMARY KEY,
  page VARCHAR(255),
  views INT DEFAULT 0,
  view_date DATE,
  UNIQUE KEY uniq_page_date (page, view_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(150) PRIMARY KEY,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
