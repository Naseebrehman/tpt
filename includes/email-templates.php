<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — branded HTML email templates
 * ---------------------------------------------------------------------------
 */

/** Shared dark email shell. */
function emailShell($innerHtml, $preheader = '')
{
    $siteName = esc(getSetting('site_name', SITE_NAME));
    $siteUrl  = esc(rtrim(SITE_URL, '/'));
    $year     = date('Y');
    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $siteName . '</title></head>'
        . '<body style="margin:0;padding:0;background:#08080a;font-family:Helvetica,Arial,sans-serif;">'
        . ($preheader !== '' ? '<div style="display:none;max-height:0;overflow:hidden;color:#08080a;">' . esc($preheader) . '</div>' : '')
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#08080a;padding:28px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#101014;border:1px solid #23232b;border-radius:14px;overflow:hidden;">'
        . '<tr><td style="background:#0d0d11;padding:26px 32px;border-bottom:1px solid #23232b;">'
        . '<span style="font-size:20px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;">' . $siteName . '</span>'
        . '<span style="float:right;font-size:11px;color:#6b7280;letter-spacing:2px;text-transform:uppercase;padding-top:6px;">Growth Agency</span>'
        . '</td></tr>'
        . '<tr><td style="padding:34px 32px;">' . $innerHtml . '</td></tr>'
        . '<tr><td style="padding:22px 32px;border-top:1px solid #23232b;background:#0d0d11;">'
        . '<p style="margin:0 0 6px;font-size:12px;color:#8b8b96;">&copy; ' . $year . ' ' . $siteName . ' &middot; '
        . esc(getSetting('site_address', 'Lahore, Pakistan')) . '</p>'
        . '<p style="margin:0;font-size:12px;color:#6b7280;">'
        . '<a href="' . $siteUrl . '" style="color:#a78bfa;text-decoration:none;">Website</a> &nbsp;&middot;&nbsp; '
        . '<a href="' . $siteUrl . '/contact" style="color:#a78bfa;text-decoration:none;">Contact</a> &nbsp;&middot;&nbsp; '
        . '<a href="{{UNSUBSCRIBE}}" style="color:#6b7280;text-decoration:underline;">Unsubscribe</a></p>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
    /* Resolve the unsubscribe placeholder for every template by default. */
    return str_replace('{{UNSUBSCRIBE}}', rtrim(SITE_URL, '/') . '/resources', $html);
}

/** Admin notification: new contact form submission. */
function emailAdminNotification($submission)
{
    $rows = array(
        'Name'     => $submission['name'],
        'Email'    => $submission['email'],
        'Phone'    => $submission['phone'],
        'Company'  => $submission['company'],
        'Service'  => $submission['service'],
        'Budget'   => $submission['budget'],
        'Source'   => $submission['source'],
        'IP'       => $submission['ip_address'],
        'Received' => $submission['created_at'],
    );
    $table = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:22px 0;">';
    foreach ($rows as $label => $value) {
        $table .= '<tr>'
            . '<td style="padding:9px 14px;border:1px solid #23232b;color:#8b8b96;font-size:12px;text-transform:uppercase;letter-spacing:1px;width:110px;">' . esc($label) . '</td>'
            . '<td style="padding:9px 14px;border:1px solid #23232b;color:#f4f4f6;font-size:14px;">' . esc($value === '' ? '—' : $value) . '</td>'
            . '</tr>';
    }
    $table .= '<tr>'
        . '<td style="padding:9px 14px;border:1px solid #23232b;color:#8b8b96;font-size:12px;text-transform:uppercase;letter-spacing:1px;vertical-align:top;">Message</td>'
        . '<td style="padding:9px 14px;border:1px solid #23232b;color:#f4f4f6;font-size:14px;line-height:1.6;">' . nl2br(esc($submission['message'])) . '</td>'
        . '</tr></table>';

    $dashboardUrl = esc(rtrim(SITE_URL, '/') . '/admin/submissions.php');

    $inner = '<h1 style="margin:0 0 8px;font-size:24px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">New Contact Form Submission</h1>'
        . '<p style="margin:0;font-size:14px;color:#9ca3af;line-height:1.6;">A visitor just sent a project enquiry through the website.</p>'
        . $table
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 4px;"><tr><td style="background:#7c3aed;border-radius:8px;">'
        . '<a href="' . $dashboardUrl . '" style="display:inline-block;padding:13px 26px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">View in Dashboard</a>'
        . '</td></tr></table>';

    return emailShell($inner, 'New enquiry from ' . $submission['name']);
}

/** Client auto-reply after submitting the contact form. */
function emailClientAutoReply($submission)
{
    $name = trim($submission['name']);
    $firstName = $name !== '' ? explode(' ', $name)[0] : 'there';

    $inner = '<h1 style="margin:0 0 10px;font-size:26px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">Thanks ' . esc($firstName) . ', we&rsquo;ve received your message!</h1>'
        . '<p style="margin:0 0 18px;font-size:15px;color:#c7c7d1;line-height:1.7;">Your enquiry is now sitting with our strategy team. Here is a quick summary of what you sent us:</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 22px;">'
        . '<tr><td style="padding:10px 14px;border:1px solid #23232b;color:#8b8b96;font-size:12px;text-transform:uppercase;letter-spacing:1px;width:110px;">Service</td>'
        . '<td style="padding:10px 14px;border:1px solid #23232b;color:#f4f4f6;font-size:14px;">' . esc($submission['service'] !== '' ? $submission['service'] : 'Not sure yet') . '</td></tr>'
        . '<tr><td style="padding:10px 14px;border:1px solid #23232b;color:#8b8b96;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Budget</td>'
        . '<td style="padding:10px 14px;border:1px solid #23232b;color:#f4f4f6;font-size:14px;">' . esc($submission['budget'] !== '' ? $submission['budget'] : 'To discuss') . '</td></tr>'
        . '<tr><td style="padding:10px 14px;border:1px solid #23232b;color:#8b8b96;font-size:12px;text-transform:uppercase;letter-spacing:1px;vertical-align:top;">Details</td>'
        . '<td style="padding:10px 14px;border:1px solid #23232b;color:#f4f4f6;font-size:14px;line-height:1.6;">' . nl2br(esc($submission['message'])) . '</td></tr>'
        . '</table>'
        . '<p style="margin:0 0 22px;font-size:15px;color:#c7c7d1;line-height:1.7;"><strong style="color:#ffffff;">We&rsquo;ll be in touch within 24 hours</strong> &mdash; usually much sooner. '
        . 'In the meantime, feel free to browse our work or grab one of our free guides.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
        . '<td style="background:#7c3aed;border-radius:8px;"><a href="' . esc(rtrim(SITE_URL, '/') . '/portfolio') . '" style="display:inline-block;padding:13px 24px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">See Our Work</a></td>'
        . '<td width="10"></td>'
        . '<td style="border:1px solid #34343e;border-radius:8px;"><a href="' . esc(rtrim(SITE_URL, '/') . '/resources') . '" style="display:inline-block;padding:12px 24px;color:#e5e5ea;font-size:14px;font-weight:700;text-decoration:none;">Free Guides</a></td>'
        . '</tr></table>'
        . '<p style="margin:26px 0 0;font-size:13px;color:#8b8b96;line-height:1.7;">'
        . 'Need us faster? Email <a href="mailto:' . esc(getSetting('site_email', 'hello@thepietechnologies.com')) . '" style="color:#a78bfa;text-decoration:none;">' . esc(getSetting('site_email', 'hello@thepietechnologies.com')) . '</a>'
        . ' or WhatsApp <a href="https://wa.me/' . esc(preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', ''))) . '" style="color:#a78bfa;text-decoration:none;">' . esc(getSetting('whatsapp_number', '—')) . '</a>.</p>';

    return emailShell($inner, 'We received your message, ' . $firstName);
}

/** Newsletter welcome email. */
function emailNewsletterWelcome($name, $email)
{
    $firstName = trim($name) !== '' ? explode(' ', trim($name))[0] : 'there';
    $inner = '<h1 style="margin:0 0 10px;font-size:26px;color:#ffffff;font-weight:800;letter-spacing:-0.5px;">You&rsquo;re on the list, ' . esc($firstName) . ' 🎉</h1>'
        . '<p style="margin:0 0 16px;font-size:15px;color:#c7c7d1;line-height:1.7;">Welcome to the growth letter from The Pie Technologies. Once or twice a month you&rsquo;ll get:</p>'
        . '<p style="margin:0 0 16px;font-size:15px;color:#c7c7d1;line-height:1.9;">&bull;&nbsp; Playbooks we&rsquo;re running right now on live client accounts<br>'
        . '&bull;&nbsp; New guides, templates and teardowns<br>'
        . '&bull;&nbsp; Zero fluff, zero spam &mdash; unsubscribe anytime</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:6px 0 4px;"><tr><td style="background:#7c3aed;border-radius:8px;">'
        . '<a href="' . esc(rtrim(SITE_URL, '/') . '/resources') . '" style="display:inline-block;padding:13px 26px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">Grab a Free Guide</a>'
        . '</td></tr></table>';
    $html = emailShell($inner, 'Welcome aboard');
    return str_replace('{{UNSUBSCRIBE}}', rtrim(SITE_URL, '/') . '/newsletter.php?unsubscribe=' . urlencode($email), $html);
}
