<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — branded HTML email layout + template helpers
 * ---------------------------------------------------------------------------
 *  emailShell() is the reusable global brand layout (Task 11) — logo, brand
 *  colours, typography, content area and footer — used by every outgoing
 *  message. Template content and subjects live in core/EmailTemplates.php
 *  (admin-editable, {{variable}} placeholders); this file only renders the
 *  shared chrome and a few backwards-compatible helpers.
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/../core/EmailTemplates.php';

/** Shared dark email shell — the global branded layout. */
function emailShell($innerHtml, $preheader = '', $unsubscribeUrl = null)
{
    $siteName = esc(getSetting('site_name', SITE_NAME));
    $siteUrl  = esc(rtrim(SITE_URL, '/'));
    $year     = date('Y');
    $unsub    = $unsubscribeUrl !== null ? $unsubscribeUrl : rtrim(SITE_URL, '/') . '/resources';
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
        . esc(getSetting('site_address', 'Collingswood, NJ, USA · Punjab, Pakistan')) . '</p>'
        . '<p style="margin:0;font-size:12px;color:#6b7280;">'
        . '<a href="' . $siteUrl . '" style="color:#a78bfa;text-decoration:none;">Website</a> &nbsp;&middot;&nbsp; '
        . '<a href="' . $siteUrl . '/contact" style="color:#a78bfa;text-decoration:none;">Contact</a> &nbsp;&middot;&nbsp; '
        . '<a href="{{UNSUBSCRIBE}}" style="color:#6b7280;text-decoration:underline;">Unsubscribe</a></p>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
    return str_replace('{{UNSUBSCRIBE}}', $unsub, $html);
}

/** Detail rows shared by contact notifications. */
function emailContactRows($submission)
{
    $rows = array(
        'Name'     => esc($submission['name']),
        'Email'    => esc($submission['email']),
        'Phone'    => esc($submission['phone'] !== '' ? $submission['phone'] : '—'),
        'Company'  => esc($submission['company'] !== '' ? $submission['company'] : '—'),
        'Service'  => esc($submission['service'] !== '' ? $submission['service'] : '—'),
        'Received' => esc($submission['created_at']),
    );
    if (!empty($submission['budget'])) { $rows['Budget'] = esc($submission['budget']); }
    if (!empty($submission['source'])) { $rows['Source'] = esc($submission['source']); }
    $rows['Message'] = nl2br(esc($submission['message']));
    return $rows;
}

/** Admin notification: new contact form submission (template: contact_admin). */
function emailAdminNotification($submission)
{
    $composed = EmailTemplates::compose('contact_admin', array(
        'name'       => $submission['name'],
        'email'      => $submission['email'],
        'phone'      => $submission['phone'],
        'service'    => $submission['service'],
        'message'    => nl2br(esc($submission['message'])),
    ), array(
        'table'     => EmailTemplates::detailTable(emailContactRows($submission)),
        'admin_url' => rtrim(SITE_URL, '/') . '/admin/submissions.php',
    ));
    return $composed['html'];
}

/** Client auto-reply after submitting the contact form (template: contact_confirm). */
function emailClientAutoReply($submission)
{
    $composed = EmailTemplates::compose('contact_confirm', array(
        'name'    => $submission['name'],
        'email'   => $submission['email'],
        'phone'   => $submission['phone'],
        'service' => $submission['service'],
        'message' => nl2br(esc($submission['message'])),
    ), array(
        'table' => EmailTemplates::detailTable(emailContactRows($submission)),
    ));
    return $composed['html'];
}

/** Newsletter welcome email (template: newsletter_welcome). */
function emailNewsletterWelcome($name, $email)
{
    $composed = EmailTemplates::compose('newsletter_welcome', array('name' => $name, 'email' => $email), array(),
        rtrim(SITE_URL, '/') . '/newsletter.php?unsubscribe=' . urlencode($email));
    return $composed['html'];
}
