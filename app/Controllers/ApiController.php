<?php
class ApiController
{
    public static function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP);
    }
    public static function search()
    {
        self::json(array('success' => true, 'results' => Repository::search(mb_substr((string) ($_GET['q'] ?? ''), 0, 100))));
    }
    public static function legacy($file, $submit = null)
    {
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === 0) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) { self::json(array('success' => false, 'message' => 'Invalid JSON.'), 400); return; }
            foreach ($input as $value) {
                if (!is_scalar($value) && $value !== null) { self::json(array('success' => false, 'message' => 'Invalid field.'), 422); return; }
            }
            $_POST = $input;
        }
        if ($file === 'pay-online.php' && getSetting('pay_online_enabled', '1') !== '1') { self::json(array('success' => false, 'message' => 'Payments are unavailable.'), 503); return; }
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        if ($submit) { $_POST[$submit] = '1'; }
        require BASE_PATH . '/' . $file;
    }
}
