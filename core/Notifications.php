<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — centralized notification system (Tasks 6–9)
 * ---------------------------------------------------------------------------
 *  One service routes every website notification:
 *   - Category toggles (contact, payment, lead, chatbot, system, security).
 *   - Configurable recipient list (notification_emails), never hard-coded.
 *   - Branded email templates (core/EmailTemplates.php) with {{variables}}.
 *   - Per-request duplicate suppression and event throttling.
 *  Customer transactional mail (receipts, confirmations) goes through
 *  sendTemplate() and is not gated by admin category toggles.
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}
require_once __DIR__ . '/EmailTemplates.php';

class Notifications
{
    /** Notification categories (Task 9). */
    public static function categories()
    {
        return array(
            'contact'  => 'Contact Form',
            'payment'  => 'Payment',
            'lead'     => 'Lead',
            'chatbot'  => 'Chatbot',
            'system'   => 'System',
            'security' => 'Security',
        );
    }

    /** Category enabled? Each category has one admin toggle. */
    public static function categoryEnabled($category)
    {
        return getSetting('notify_cat_' . $category, '1') === '1';
    }

    /* ------------------------------------------------------------------
       Recipient list
       ------------------------------------------------------------------ */

    /**
     * All configured recipients.
     * @return array<int, array{id:int,email:string,is_active:bool,categories:string}>
     */
    public static function recipients()
    {
        if (!DB_OK || !self::tableReady()) {
            return array();
        }
        $rows = dbAll('SELECT * FROM notification_emails ORDER BY id');
        $out  = array();
        foreach ($rows as $row) {
            $out[] = array(
                'id'         => (int) $row['id'],
                'email'      => (string) $row['email'],
                'is_active'  => (int) $row['is_active'] === 1,
                'categories' => (string) $row['categories'],
            );
        }
        return $out;
    }

    /** Active recipients for one category (falls back to the site email). */
    public static function recipientsFor($category)
    {
        $emails = array();
        foreach (self::recipients() as $recipient) {
            if (!$recipient['is_active']) {
                continue;
            }
            $cats = array_map('trim', explode(',', $recipient['categories']));
            if ($recipient['categories'] === '' || in_array('*', $cats, true) || in_array($category, $cats, true)) {
                $emails[] = $recipient['email'];
            }
        }
        if (!$emails) {
            /* No list configured yet — the site email receives everything. */
            $emails[] = getSetting('site_email', ADMIN_EMAIL);
        }
        return array_values(array_unique($emails));
    }

    /** Add a recipient. Returns array(ok, message). */
    public static function addRecipient($email, array $categories = array(), $active = true)
    {
        $email = strtolower(trim((string) $email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array(false, 'Enter a valid email address.');
        }
        if (!DB_OK) {
            return array(false, 'The database is not connected. Check your configuration.');
        }
        if (!self::tableReady()) {
            return array(false, 'The notification email table is missing and could not be created. Run: php bin/cli.php migrate');
        }
        $known = array_keys(self::categories());
        $cats  = array_values(array_intersect($known, array_map('trim', $categories)));
        if (!$cats) {
            $cats = $known;
        }
        $existing = dbOne('SELECT id FROM notification_emails WHERE email = ?', array($email));
        if ($existing) {
            return array(false, 'That email is already on the notification list.');
        }
        $ok = dbExec(
            'INSERT INTO notification_emails (email, is_active, categories) VALUES (?, ?, ?)',
            array($email, $active ? 1 : 0, implode(',', $cats))
        ) >= 0;
        return array($ok, $ok ? 'Recipient added.' : 'Could not save the recipient. Please try again.');
    }

    /**
     * Edit a recipient: change the address and (optionally) its categories.
     * Returns array(ok, message).
     */
    public static function updateRecipient($id, $email, ?array $categories = null)
    {
        $id    = (int) $id;
        $email = strtolower(trim((string) $email));
        if ($id <= 0) {
            return array(false, 'Unknown recipient.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array(false, 'Enter a valid email address.');
        }
        if (!DB_OK) {
            return array(false, 'The database is not connected. Check your configuration.');
        }
        if (!self::tableReady()) {
            return array(false, 'The notification email table is missing and could not be created. Run: php bin/cli.php migrate');
        }
        $clash = dbOne('SELECT id FROM notification_emails WHERE email = ? AND id <> ?', array($email, $id));
        if ($clash) {
            return array(false, 'Another recipient already uses that email address.');
        }
        if ($categories === null) {
            $ok = dbExec('UPDATE notification_emails SET email = ? WHERE id = ?', array($email, $id)) >= 0;
            return array($ok, $ok ? 'Recipient updated.' : 'Could not update the recipient.');
        }
        $known = array_keys(self::categories());
        $cats  = array_values(array_intersect($known, array_map('trim', $categories)));
        if (!$cats) {
            return array(false, 'Pick at least one category.');
        }
        $ok = dbExec(
            'UPDATE notification_emails SET email = ?, categories = ? WHERE id = ?',
            array($email, implode(',', $cats), $id)
        ) >= 0;
        return array($ok, $ok ? 'Recipient updated.' : 'Could not update the recipient.');
    }

    /**
     * Make sure the recipient table exists before reading or writing it.
     * Installations created from database.sql (instead of the CLI installer)
     * never ran migration 002, which is why adding a recipient used to fail.
     */
    private static function tableReady()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $ready = true;
        $row = dbOne(
            "SELECT COUNT(*) AS c FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_emails'"
        );
        if (!$row || (int) $row['c'] === 0) {
            dbExec("CREATE TABLE IF NOT EXISTS notification_emails (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(150) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                categories VARCHAR(255) NOT NULL DEFAULT 'contact,payment,lead,chatbot,system,security',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_notification_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $row = dbOne(
                "SELECT COUNT(*) AS c FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_emails'"
            );
            $ready = (bool) ($row && (int) $row['c'] > 0);
        }
        return $ready;
    }

    /** Remove a recipient by id. */
    public static function removeRecipient($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }
        return dbExec('DELETE FROM notification_emails WHERE id = ?', array($id)) >= 0;
    }

    /** Enable/disable one recipient. */
    public static function setRecipientActive($id, $active)
    {
        return dbExec('UPDATE notification_emails SET is_active = ? WHERE id = ?', array($active ? 1 : 0, (int) $id)) >= 0;
    }

    /** Set a recipient's category list. */
    public static function setRecipientCategories($id, array $categories)
    {
        $known = array_keys(self::categories());
        $cats  = array_values(array_intersect($known, array_map('trim', $categories)));
        if (!$cats) {
            return false;
        }
        return dbExec('UPDATE notification_emails SET categories = ? WHERE id = ?', array(implode(',', $cats), (int) $id)) >= 0;
    }

    /* ------------------------------------------------------------------
       Sending
       ------------------------------------------------------------------ */

    /** @var array<string,bool> per-request duplicate suppression */
    private static $sentThisRequest = array();

    /** @var array<string,int> per-request event timestamps (in addition to the DB) */
    private static $eventTimes = array();

    /** Optional probe for tests: called for every delivery attempt. */
    public static $onDeliver = null;

    /**
     * Notify admin recipients about an event in a category.
     * Sends one email per recipient via the given template. Never throws.
     *
     * @param string $category    one of categories()
     * @param string $templateKey EmailTemplates key
     * @param array  $vars        template variables (name, email, …)
     * @return bool true when at least one notification was accepted for delivery
     */
    public static function notifyAdmins($category, $templateKey, array $vars = array(), array $extraVars = array())
    {
        if (!self::categoryEnabled($category)) {
            return false;
        }
        $compose = EmailTemplates::compose($templateKey, $vars, $extraVars);
        if (!$compose['sent']) {
            return false;
        }
        $any = false;
        foreach (self::recipientsFor($category) as $to) {
            $dedupeKey = $category . '|' . $templateKey . '|' . $to . '|' . substr(md5($compose['subject'] . serialize($vars)), 0, 12);
            if (isset(self::$sentThisRequest[$dedupeKey])) {
                continue; /* never send the same notification twice in one request */
            }
            self::$sentThisRequest[$dedupeKey] = true;
            $ok = self::deliver($to, $compose['subject'], $compose['html']);
            $any = $any || $ok;
        }
        return $any;
    }

    /**
     * Send one templated email to an explicit recipient (customer receipts…).
     * Ignores admin category toggles; respects the template's enable flag.
     */
    public static function sendTemplate($templateKey, $to, array $vars = array(), array $extraVars = array())
    {
        $compose = EmailTemplates::compose($templateKey, $vars, $extraVars);
        if (!$compose['sent']) {
            return false;
        }
        $dedupeKey = 'tx|' . $templateKey . '|' . $to . '|' . substr(md5($compose['subject'] . serialize($vars)), 0, 12);
        if (isset(self::$sentThisRequest[$dedupeKey])) {
            return true;
        }
        self::$sentThisRequest[$dedupeKey] = true;
        return self::deliver($to, $compose['subject'], $compose['html']);
    }

    /**
     * Throttled system/chatbot events: at most one notification per $key per
     * $intervalSeconds. Used for meaningful-but-rare events (Task 7/9).
     */
    public static function eventThrottled($key, $intervalSeconds, $category, $templateKey, array $vars = array(), array $extraVars = array())
    {
        $settingKey = 'notify_last_' . preg_replace('/[^a-z0-9_]/i', '', $key);
        $last = isset(self::$eventTimes[$settingKey]) ? self::$eventTimes[$settingKey] : (int) getSetting($settingKey, '0');
        if ($last > 0 && (time() - $last) < $intervalSeconds) {
            return false;
        }
        self::$eventTimes[$settingKey] = time();
        self::rememberEvent($settingKey);
        return self::notifyAdmins($category, $templateKey, $vars, $extraVars);
    }

    /** Store event timestamp in the settings table (best effort). */
    private static function rememberEvent($settingKey)
    {
        if (!DB_OK) {
            return;
        }
        dbExec(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            array($settingKey, (string) time())
        );
    }

    /** Low-level send; logs failures, never exposes internals to visitors. */
    private static function deliver($to, $subject, $html)
    {
        if (self::$onDeliver !== null) {
            call_user_func(self::$onDeliver, $to, $subject);
        }
        try {
            $ok = sendEmail($to, $subject, $html);
            if (!$ok) {
                error_log('[TPT] Notification delivery failed for category email to ' . $to);
            }
            return $ok;
        } catch (Throwable $mailError) {
            error_log('[TPT] Notification error: ' . $mailError->getMessage());
            return false;
        }
    }
}
