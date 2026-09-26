<?php
class Settings
{
    public static function validate($input)
    {
        foreach ($input as $key => $value) { if (!is_scalar($value)) { return 'Invalid settings field.'; } }
        foreach (array('brand_primary', 'brand_secondary', 'brand_accent') as $key) {
            if (!empty($input[$key]) && !preg_match('/^#[0-9a-f]{6}$/iD', $input[$key])) { return 'Colors must be six-digit hex values.'; }
        }
        foreach (array('smtp_from_email', 'smtp_reply_to', 'site_email') as $key) {
            if (!empty($input[$key]) && !filter_var($input[$key], FILTER_VALIDATE_EMAIL)) { return 'Invalid email: ' . $key; }
        }
        foreach (array('smtp_encryption' => array('tls','ssl','none'), 'paypal_mode' => array('sandbox','live'), 'stripe_mode' => array('test','live'), 'brand_font' => array('default','system')) as $key => $allowed) {
            if (isset($input[$key]) && !in_array($input[$key], $allowed, true)) { return 'Invalid option: ' . $key; }
        }
        foreach (array('smtp_port' => array(1,65535), 'gemini_temperature' => array(0,2), 'gemini_max_tokens' => array(64,8192)) as $key => $range) {
            if (isset($input[$key]) && (!is_numeric($input[$key]) || $input[$key] < $range[0] || $input[$key] > $range[1])) { return 'Invalid range: ' . $key; }
        }
        if (!empty($input['gemini_model']) && !preg_match('/^[a-zA-Z0-9._-]{1,100}$/D', $input['gemini_model'])) { return 'Invalid Gemini model name.'; }
        foreach (array('instagram_url','facebook_url','linkedin_url','tiktok_url','twitter_url','youtube_url') as $key) {
            if (!empty($input[$key]) && (!filter_var($input[$key], FILTER_VALIDATE_URL) || !in_array(parse_url($input[$key], PHP_URL_SCHEME), array('https','http'), true))) { return 'Invalid social URL.'; }
        }
        foreach (array('google_analytics_id' => '/^G-[A-Z0-9]+$/D', 'facebook_pixel_id' => '/^[0-9]+$/D') as $key => $pattern) { if (!empty($input[$key]) && !preg_match($pattern, $input[$key])) { return 'Invalid tracking ID.'; } }
        return '';
    }
}
