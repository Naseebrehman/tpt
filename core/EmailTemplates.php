<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — email template engine (Task 10/11)
 * ---------------------------------------------------------------------------
 *  DB-backed templates (email_templates table) with built-in branded defaults.
 *  Bodies are inner HTML fragments; the shared brand layout in
 *  includes/email-templates.php (emailShell) wraps every message so branding
 *  is never re-created per template. {{placeholders}} are replaced on send.
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

class EmailTemplates
{
    /** Supported placeholders shown to the admin. */
    public static $variables = array(
        'name', 'email', 'phone', 'service', 'amount', 'payment_method',
        'transaction_id', 'message', 'site_name', 'site_url', 'date',
        'company', 'reference', 'status', 'ip', 'budget', 'subject', 'admin_url',
    );

    /**
     * Built-in defaults. Keys are stable identifiers used by Notifications.
     * Body = the inner content area (the brand shell adds header/footer).
     */
    public static function defaults()
    {
        return array(
            'contact_admin' => array(
                'subject' => 'New Contact Form Submission — {{name}}',
                'body'    => '<h1 style="margin:0 0 8px;font-size:24px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">New Contact Form Submission</h1>'
                    . '<p style="margin:0;font-size:14px;color:#9ca3af;line-height:1.6;">A visitor just sent a project enquiry through the website.</p>'
                    . '{{table}}'
                    . '<p style="margin:18px 0 0;"><a href="{{admin_url}}" style="display:inline-block;padding:13px 26px;background:#7c3aed;border-radius:8px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">View in Dashboard</a></p>',
            ),
            'contact_confirm' => array(
                'subject' => 'We received your message, {{name}}',
                'body'    => '<h1 style="margin:0 0 10px;font-size:26px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">Thanks {{name}}, we&rsquo;ve received your message!</h1>'
                    . '<p style="margin:0 0 18px;font-size:15px;color:#c7c7d1;line-height:1.7;">Your enquiry is now with our strategy team. A senior strategist will reply within one business day.</p>'
                    . '{{table}}'
                    . '<p style="margin:22px 0 0;font-size:13px;color:#8b8b96;line-height:1.7;">Need us faster? Just reply to this email &mdash; a real person reads every message.</p>',
            ),
            'payment_admin' => array(
                'subject' => 'Payment received — ${{amount}} from {{name}}',
                'body'    => '<h1 style="margin:0 0 8px;font-size:24px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">Payment Received</h1>'
                    . '<p style="margin:0;font-size:14px;color:#9ca3af;line-height:1.6;">A customer completed a payment on the website.</p>'
                    . '{{table}}'
                    . '<p style="margin:18px 0 0;"><a href="{{admin_url}}" style="display:inline-block;padding:13px 26px;background:#7c3aed;border-radius:8px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">Manage in Admin &rarr; Payments</a></p>',
            ),
            'payment_confirm' => array(
                'subject' => 'Payment successful — ${{amount}} to {{site_name}}',
                'body'    => '<h1 style="margin:0 0 10px;font-size:26px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">Payment successful &mdash; thank you, {{name}}!</h1>'
                    . '<p style="margin:0 0 18px;font-size:15px;color:#c7c7d1;line-height:1.7;">We&rsquo;ve received your payment. This email is your confirmation &mdash; keep it for your records.</p>'
                    . '{{table}}'
                    . '<p style="margin:22px 0 0;font-size:13px;color:#8b8b96;line-height:1.7;">A question about this payment? Reply to this email and a human will sort it out.</p>',
            ),
            'payment_request_admin' => array(
                'subject' => 'Payment request — ${{amount}} from {{name}}',
                'body'    => '<h1 style="margin:0 0 8px;font-size:24px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">New Payment Request</h1>'
                    . '<p style="margin:0;font-size:14px;color:#9ca3af;line-height:1.6;">A customer asked for a secure invoice link.</p>'
                    . '{{table}}'
                    . '<p style="margin:18px 0 0;"><a href="{{admin_url}}" style="display:inline-block;padding:13px 26px;background:#7c3aed;border-radius:8px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">Open the secure payment link</a></p>',
            ),
            'payment_request_confirm' => array(
                'subject' => 'Your {{site_name}} secure payment link',
                'body'    => '<h1 style="margin:0 0 10px;font-size:26px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">We received your payment request</h1>'
                    . '<p style="margin:0 0 18px;font-size:15px;color:#c7c7d1;line-height:1.7;">Hi {{name}}, thanks &mdash; your request for <strong style="color:#ffffff;">${{amount}} USD</strong> is with our team.</p>'
                    . '{{table}}'
                    . '<p style="margin:22px 0 0;font-size:13px;color:#8b8b96;line-height:1.7;">Card details are handled by the payment provider&rsquo;s hosted checkout &mdash; never by this website.</p>',
            ),
            'lead_admin' => array(
                'subject' => 'New chatbot lead — {{name}}',
                'body'    => '<h1 style="margin:0 0 8px;font-size:24px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">New Chatbot Lead</h1>'
                    . '<p style="margin:0;font-size:14px;color:#9ca3af;line-height:1.6;">A visitor left their details with the chat assistant.</p>'
                    . '{{table}}'
                    . '<p style="margin:18px 0 0;"><a href="{{admin_url}}" style="display:inline-block;padding:13px 26px;background:#7c3aed;border-radius:8px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">Open Alia Leads</a></p>',
            ),
            'password_reset' => array(
                'subject' => 'Reset your {{site_name}} password',
                'body'    => '<h1 style="margin:0 0 10px;font-size:26px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">Password reset requested</h1>'
                    . '<p style="margin:0 0 18px;font-size:15px;color:#c7c7d1;line-height:1.7;">Hi {{name}}, someone requested a password reset for your {{site_name}} account on {{date}}.</p>'
                    . '<p style="margin:0 0 18px;font-size:15px;color:#c7c7d1;line-height:1.7;">{{message}}</p>'
                    . '<p style="margin:22px 0 0;font-size:13px;color:#8b8b96;line-height:1.7;">If you didn&rsquo;t request this, you can safely ignore this email.</p>',
            ),
            'welcome' => array(
                'subject' => 'Welcome to {{site_name}}, {{name}}',
                'body'    => '<h1 style="margin:0 0 10px;font-size:26px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">Welcome aboard, {{name}}!</h1>'
                    . '<p style="margin:0 0 18px;font-size:15px;color:#c7c7d1;line-height:1.7;">Your account at {{site_name}} is ready. We&rsquo;re glad to have you with us.</p>'
                    . '<p style="margin:22px 0 0;"><a href="{{site_url}}" style="display:inline-block;padding:13px 26px;background:#7c3aed;border-radius:8px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">Visit the Website</a></p>',
            ),
            'system' => array(
                'subject' => '{{site_name}} — {{subject}}',
                'body'    => '<h1 style="margin:0 0 10px;font-size:24px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">{{subject}}</h1>'
                    . '<p style="margin:0;font-size:15px;color:#c7c7d1;line-height:1.7;">{{message}}</p>'
                    . '{{table}}',
            ),
            'newsletter_welcome' => array(
                'subject' => 'You&rsquo;re on the list, {{name}}',
                'body'    => '<h1 style="margin:0 0 10px;font-size:26px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">You&rsquo;re on the list, {{name}}</h1>'
                    . '<p style="margin:0 0 16px;font-size:15px;color:#c7c7d1;line-height:1.7;">Welcome to the growth letter from {{site_name}}. Once or twice a month you&rsquo;ll get playbooks, new guides and teardowns &mdash; zero fluff, unsubscribe anytime.</p>'
                    . '<p style="margin:22px 0 0;"><a href="{{site_url}}" style="display:inline-block;padding:13px 26px;background:#7c3aed;border-radius:8px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">Grab a Free Guide</a></p>',
            ),
        );
    }

    /** All template keys with their default subject. */
    public static function keys()
    {
        $labels = array(
            'contact_admin'          => 'Contact form notification',
            'contact_confirm'        => 'Contact form confirmation',
            'payment_admin'          => 'Payment admin notification',
            'payment_confirm'        => 'Payment confirmation',
            'payment_request_admin'  => 'Invoice request notification',
            'payment_request_confirm' => 'Invoice request confirmation',
            'lead_admin'             => 'Lead notification',
            'password_reset'         => 'Password reset',
            'welcome'                => 'Welcome email',
            'system'                 => 'System notification',
            'newsletter_welcome'     => 'Newsletter welcome',
        );
        return $labels;
    }

    /**
     * Template for a key: DB row when saved, built-in default otherwise.
     * @return array{subject:string,body:string,is_active:bool,source:string}
     */
    public static function get($key)
    {
        $defaults = self::defaults();
        if (!isset($defaults[$key])) {
            return array('subject' => '', 'body' => '', 'is_active' => true, 'source' => 'missing');
        }
        $row = DB_OK ? dbOne('SELECT * FROM email_templates WHERE template_key = ?', array($key)) : null;
        if ($row && isset($row['subject'])) {
            return array(
                'subject'   => (string) $row['subject'],
                'body'      => (string) $row['body'],
                'is_active' => (int) $row['is_active'] === 1,
                'source'    => 'database',
            );
        }
        return array(
            'subject'   => $defaults[$key]['subject'],
            'body'      => $defaults[$key]['body'],
            'is_active' => true,
            'source'    => 'default',
        );
    }

    /** Save a template override (empty subject/body restores the built-in default). */
    public static function save($key, $subject, $body, $isActive)
    {
        if (!isset(self::defaults()[$key])) {
            return false;
        }
        $subject  = trim((string) $subject);
        $body     = trim((string) $body);
        $isActive = $isActive ? 1 : 0;
        if ($subject === '' && $body === '') {
            /* Restore built-in default by removing the override row. */
            dbExec('DELETE FROM email_templates WHERE template_key = ?', array($key));
            return true;
        }
        return dbExec(
            'INSERT INTO email_templates (template_key, subject, body, is_active) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body), is_active = VALUES(is_active)',
            array($key, $subject, $body, $isActive)
        ) >= 0;
    }

    /** Replace {{placeholders}} in a string. Unknown placeholders resolve to ''. */
    public static function render($text, array $vars)
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($match) use ($vars) {
            $name = strtolower($match[1]);
            return isset($vars[$name]) ? (string) $vars[$name] : '';
        }, (string) $text);
    }

    /**
     * Build a label/value table for structured notification details.
     * Values are caller-escaped HTML (so links/formatting are possible);
     * labels are escaped here.
     * @param array $rows label => escaped HTML value
     */
    public static function detailTable(array $rows)
    {
        $table = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:22px 0;">';
        foreach ($rows as $label => $value) {
            $table .= '<tr>'
                . '<td style="padding:9px 14px;border:1px solid #23232b;color:#8b8b96;font-size:12px;text-transform:uppercase;letter-spacing:1px;width:130px;">' . esc($label) . '</td>'
                . '<td style="padding:9px 14px;border:1px solid #23232b;color:#f4f4f6;font-size:14px;line-height:1.6;">' . $value . '</td>'
                . '</tr>';
        }
        return $table . '</table>';
    }

    /**
     * Render a complete branded email for a template key.
     * @return array{subject:string,html:string,sent:bool,reason:string}
     */
    public static function compose($key, array $vars, array $extraVars = array(), $unsubscribeUrl = null)
    {
        $template = self::get($key);
        if (!$template['is_active']) {
            return array('subject' => '', 'html' => '', 'sent' => false, 'reason' => 'template_disabled');
        }
        $vars = array_merge(array(
            'site_name' => getSetting('site_name', SITE_NAME),
            'site_url'  => rtrim(SITE_URL, '/'),
            'date'      => date('j M Y, H:i') . ' UTC',
        ), $vars, $extraVars);
        $subject = self::render($template['subject'], $vars);
        $inner   = self::render($template['body'], $vars);
        require_once dirname(__DIR__) . '/includes/email-templates.php';
        return array('subject' => $subject, 'html' => emailShell($inner, trim(strip_tags($subject)), $unsubscribeUrl), 'sent' => true, 'reason' => '');
    }
}
