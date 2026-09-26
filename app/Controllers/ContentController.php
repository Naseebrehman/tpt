<?php
class ContentController
{
    public static function show()
    {
        require_once BASE_PATH . '/core/Content.php';
        requireAdmin();
        $adminPage = 'content'; $adminTitle = 'Content & SEO';
        $path = Content::path((string) ($_POST['path'] ?? $_GET['path'] ?? '/'));
        if (!preg_match('~^/(?:[a-z0-9-]+(?:/[a-z0-9-]+)*)?$~D', $path) || strlen($path) > 255 || preg_match('~^/(admin|api|pay/secure)(/|$)~', $path)) { http_response_code(422); exit('Use a public page path, such as /about or /services/seo.'); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!validateCSRF()) { http_response_code(403); exit('Security token expired.'); }
            $data = array();
            foreach (array('title','description','canonical','og_image','headline','lead') as $field) {
                if (!is_string($_POST[$field] ?? '')) { http_response_code(422); exit('Invalid content field.'); }
                $data[$field] = mb_substr(sanitizeMultiline($_POST[$field] ?? ''), 0, $field === 'lead' ? 4000 : 1000);
            }
            $data['noindex'] = isset($_POST['noindex']);
            foreach (array('canonical','og_image') as $field) {
                if ($data[$field] !== '' && (!filter_var($data[$field], FILTER_VALIDATE_URL) || parse_url($data[$field], PHP_URL_SCHEME) !== 'https')) { http_response_code(422); exit('Canonical and OG image must be complete HTTPS URLs.'); }
            }
            $faq = trim((string) ($_POST['faqs'] ?? ''));
            $data['faqs'] = $faq === '' ? null : json_decode($faq, true);
            if ($faq !== '') {
                if (!is_array($data['faqs']) || count($data['faqs']) > 30) { http_response_code(422); exit('FAQs must be a JSON array (maximum 30).'); }
                foreach ($data['faqs'] as $row) { if (!is_array($row) || !is_string($row['q'] ?? null) || !is_string($row['a'] ?? null) || strlen($row['q'] . $row['a']) > 10000) { http_response_code(422); exit('Each FAQ needs q and a text fields.'); } }
            }
            $value = isset($_POST['reset']) ? '{}' : json_encode($data);
            $ok = dbExec('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', array(Content::key($path), $value));
            setFlash($ok >= 0 ? 'ok' : 'err', $ok >= 0 ? 'Overrides saved. Blank fields retain existing content.' : 'Save failed.');
            header('Location: ' . url('admin/content') . '?path=' . rawurlencode($path)); exit;
        }
        $data = Content::get($path);
        require BASE_PATH . '/views/admin/content.php';
    }
}
