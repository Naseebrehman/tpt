<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — global helper functions
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once __DIR__ . '/db.php';
}

/* ===========================================================================
   Settings
   =========================================================================== */

/** All settings rows, cached per request. */
function settingsCache()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = array();
    foreach (dbAll('SELECT setting_key, setting_value FROM settings') as $row) {
        $cache[$row['setting_key']] = $row['setting_value'];
    }
    return $cache;
}

/** Read one setting value from the database (with default fallback). */
function getSetting($key, $default = '')
{
    $all = settingsCache();
    if (isset($all[$key]) && $all[$key] !== null && $all[$key] !== '') {
        return $all[$key];
    }
    return $default;
}

/* ===========================================================================
   Escaping / sanitising
   =========================================================================== */

/** Escape for safe HTML output. */
function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Echo escaped output. */
function e($value)
{
    echo esc($value);
}

/** Clean user input on intake: trim, strip control characters & tags. */
function sanitize($input)
{
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    $value = (string) $input;
    $value = str_replace(array("\0", "\r\n", "\r"), array('', "\n", "\n"), $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
    return trim(strip_tags($value));
}

/** Keep line breaks for textarea content but strip everything else. */
function sanitizeMultiline($input)
{
    $value = str_replace(array("\0"), array(''), (string) $input);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
    return trim(strip_tags($value));
}

/* ===========================================================================
   URLs
   =========================================================================== */

/**
 * Build a site URL. Pass paths relative to the site root, without leading
 * slash and without .php (pretty URLs are added/removed automatically).
 *   url('about')                 -> /about            (or /about.php)
 *   url('services/meta-ads')     -> /services/meta-ads
 *   url('blog/' . $slug)         -> /blog/my-post
 */
function url($path = '', $pretty = null)
{
    $path = trim((string) $path, '/');
    if ($pretty === null) {
        $pretty = PRETTY_URLS;
    }
    /* paths that already carry a query string or extension stay untouched */
    if ($path !== '' && substr($path, -4) !== '.php' && $pretty && strpos($path, '?') === false) {
        $url = BASE_URL . '/' . $path;
    } elseif ($path !== '' && substr($path, -4) !== '.php' && !$pretty && strpos($path, '?') === false) {
        $url = BASE_URL . '/' . $path . '.php';
    } else {
        $url = BASE_URL . ($path === '' ? '/' : '/' . $path);
    }
    return $url;
}

/** Absolute URL for <link>/<img>/asset references. */
function asset($path)
{
    return BASE_URL . '/' . ltrim((string) $path, '/');
}

/** Absolute canonical URL (used for SEO tags). */
function canonicalUrl($path = '')
{
    $path = (string) $path;
    if ($path === '') {
        $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    }
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/* ===========================================================================
   CSRF
   =========================================================================== */

function generateCSRF()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (headers_sent()) {
            return '';
        }
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . esc(generateCSRF()) . '">';
}

function validateCSRF($token = null)
{
    if ($token === null) {
        $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $expected = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

/* ===========================================================================
   Misc utilities
   =========================================================================== */

function pieClientIp()
{
    $candidates = array();
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidates[] = trim($parts[0]);
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = $_SERVER['REMOTE_ADDR'];
    }
    foreach ($candidates as $ip) {
        $clean = filter_var($ip, FILTER_VALIDATE_IP);
        if ($clean) {
            return $clean;
        }
    }
    return '0.0.0.0';
}

function slugify($text)
{
    $text = strtolower(trim((string) $text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text === '' ? 'item-' . time() : $text;
}

function readingTime($html)
{
    $words = str_word_count(strip_tags((string) $html));
    $mins  = (int) ceil($words / 200);
    return max(1, $mins);
}

function formatDate($datetime, $format = 'j M Y')
{
    $ts = strtotime((string) $datetime);
    return $ts ? date($format, $ts) : '';
}

function isActive($key, $current = null)
{
    if ($current === null) {
        $current = isset($GLOBALS['activeNav']) ? $GLOBALS['activeNav'] : '';
    }
    return $current === $key ? ' active' : '';
}

/** Decode a JSON column safely into an array. */
function jsonCol($value, $default = array())
{
    if (is_array($value)) {
        return $value;
    }
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : $default;
}

/* ===========================================================================
   Content getters (all prepared statements, all cached-free & live)
   =========================================================================== */

function getRecentPosts($limit = 3, $excludeId = null)
{
    $sql    = "SELECT p.*, c.name AS category_name, c.slug AS category_slug
               FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id
               WHERE p.status = 'published'";
    $params = array();
    if ($excludeId !== null) {
        $sql .= ' AND p.id <> ?';
        $params[] = (int) $excludeId;
    }
    $sql .= ' ORDER BY p.published_at DESC, p.id DESC LIMIT ' . max(1, (int) $limit);
    return dbAll($sql, $params);
}

/**
 * Paginated / filtered post list.
 * $opts: category (slug), search, page, per_page
 * @return array{posts:array,total:int,pages:int}
 */
function getPosts($opts = array())
{
    $where    = array("p.status = 'published'");
    $params   = array();
    $category = isset($opts['category']) ? $opts['category'] : '';
    $search   = isset($opts['search']) ? trim((string) $opts['search']) : '';

    if ($category !== '') {
        $where[]  = 'c.slug = ?';
        $params[] = $category;
    }
    if ($search !== '') {
        $where[]  = '(p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)';
        $like     = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    $whereSql = implode(' AND ', $where);

    $totalRow = dbOne(
        "SELECT COUNT(*) AS c FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id WHERE $whereSql",
        $params
    );
    $total = $totalRow ? (int) $totalRow['c'] : 0;

    $perPage = max(1, isset($opts['per_page']) ? (int) $opts['per_page'] : 9);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = min(max(1, isset($opts['page']) ? (int) $opts['page'] : 1), $pages);
    $offset  = ($page - 1) * $perPage;

    $posts = dbAll(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id
         WHERE $whereSql
         ORDER BY p.published_at DESC, p.id DESC
         LIMIT $perPage OFFSET $offset",
        $params
    );

    return array('posts' => $posts, 'total' => $total, 'pages' => $pages, 'page' => $page);
}

function getPostBySlug($slug)
{
    return dbOne(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id
         WHERE p.slug = ? AND p.status = 'published'",
        array($slug)
    );
}

function getPostById($id)
{
    return dbOne(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id
         WHERE p.id = ?",
        array((int) $id)
    );
}

function getBlogCategories()
{
    return dbAll('SELECT * FROM blog_categories ORDER BY name ASC');
}

function getApprovedComments($postId)
{
    return dbAll(
        "SELECT * FROM blog_comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at ASC",
        array((int) $postId)
    );
}

function getActiveTestimonials()
{
    return dbAll('SELECT * FROM testimonials WHERE is_active = 1 ORDER BY id ASC');
}

/**
 * Portfolio items. $filter = service_category value or '' for all.
 */
function getPortfolioItems($filter = '')
{
    $sql = 'SELECT * FROM portfolio WHERE is_active = 1';
    $params = array();
    if ($filter !== '' && $filter !== null) {
        $sql .= ' AND service_category = ?';
        $params[] = $filter;
    }
    $sql .= ' ORDER BY display_order ASC, id DESC';
    return dbAll($sql, $params);
}

function getPortfolioBySlug($slug)
{
    return dbOne('SELECT * FROM portfolio WHERE slug = ? AND is_active = 1', array($slug));
}

function getPortfolioById($id)
{
    return dbOne('SELECT * FROM portfolio WHERE id = ?', array((int) $id));
}

function getResources($type = null, $activeOnly = true)
{
    $sql    = 'SELECT * FROM resources';
    $params = array();
    $where  = array();
    if ($activeOnly) {
        $where[] = 'is_active = 1';
    }
    if ($type !== null && $type !== '') {
        $where[]  = 'resource_type = ?';
        $params[] = $type;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY resource_type ASC, id ASC';
    return dbAll($sql, $params);
}

function getTeamMembers()
{
    return dbAll('SELECT * FROM team_members WHERE is_active = 1 ORDER BY display_order ASC, id ASC');
}

/* ===========================================================================
   Page views
   =========================================================================== */

function logPageView($page)
{
    if (!DB_OK || $page === '') {
        return;
    }
    $today = date('Y-m-d');
    dbExec(
        'INSERT INTO page_views (page, views, view_date) VALUES (?, 1, ?)
         ON DUPLICATE KEY UPDATE views = views + 1',
        array($page, $today)
    );
}

/* ===========================================================================
   Auth (admin)
   =========================================================================== */

function isAdminLoggedIn()
{
    if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
        session_start();
    }
    return !empty($_SESSION['admin_id']);
}

function requireAdmin()
{
    if (!isAdminLoggedIn()) {
        header('Location: ' . url('admin/login.php', false));
        exit;
    }
}

function currentAdmin()
{
    if (!isAdminLoggedIn()) {
        return null;
    }
    return dbOne('SELECT * FROM admin_users WHERE id = ?', array((int) $_SESSION['admin_id']));
}

/* ===========================================================================
   File uploads
   =========================================================================== */

/**
 * Handle a single file upload with strict validation.
 *
 * @param string $field      $_FILES key
 * @param string $subdir     folder inside /uploads (e.g. 'blog')
 * @param array  $allowedExt lower-case extensions, e.g. array('jpg','png')
 * @param int    $maxBytes   max size (default 5 MB)
 * @return array array('ok'=>bool,'path'=>string,'error'=>string)
 */
function uploadFile($field, $subdir, $allowedExt = array('jpg', 'jpeg', 'png', 'webp', 'svg'), $maxBytes = 5242880)
{
    $fail = array('ok' => false, 'path' => '', 'error' => '');
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return array('ok' => true, 'path' => '', 'error' => ''); /* nothing uploaded */
    }
    $file = $_FILES[$field];
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return array('ok' => true, 'path' => '', 'error' => '');
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        return array('ok' => false, 'path' => '', 'error' => 'Upload failed (server error code ' . (int) $file['error'] . ').');
    }
    if ((int) $file['size'] > $maxBytes) {
        return array('ok' => false, 'path' => '', 'error' => 'File is larger than the 5 MB limit.');
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return array('ok' => false, 'path' => '', 'error' => 'File type .' . $ext . ' is not allowed.');
    }

    /* Real MIME check via finfo */
    $mimeMap = array(
        'jpg'  => array('image/jpeg'),
        'jpeg' => array('image/jpeg'),
        'png'  => array('image/png'),
        'webp' => array('image/webp'),
        'gif'  => array('image/gif'),
        'svg'  => array('image/svg+xml', 'text/plain', 'application/xml', 'text/xml'),
        'pdf'  => array('application/pdf'),
    );
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (isset($mimeMap[$ext]) && !in_array($mime, $mimeMap[$ext], true)) {
            return array('ok' => false, 'path' => '', 'error' => 'File content does not match its extension.');
        }
    }

    $dir = UPLOAD_PATH . trim($subdir, '/');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return array('ok' => false, 'path' => '', 'error' => 'Upload folder is not writable.');
    }

    $newName = uniqid('up_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $newName)) {
        return array('ok' => false, 'path' => '', 'error' => 'Could not save the uploaded file.');
    }
    @chmod($dir . '/' . $newName, 0644);

    return array('ok' => true, 'path' => 'uploads/' . trim($subdir, '/') . '/' . $newName, 'error' => '');
}

/** Delete an uploaded file (only inside /uploads). */
function deleteUpload($relativePath)
{
    $relativePath = (string) $relativePath;
    if ($relativePath === '' || strpos($relativePath, 'uploads/') !== 0) {
        return false;
    }
    $full = BASE_PATH . '/' . $relativePath;
    if (is_file($full)) {
        return @unlink($full);
    }
    return false;
}

/* ===========================================================================
   Mail
   =========================================================================== */

/**
 * Send an HTML email.
 * Priority: 1) PHPMailer from /vendor (composer), 2) built-in SMTP client,
 * 3) PHP mail(). Returns true on success.
 */
function sendEmail($to, $subject, $bodyHtml, $attachments = array())
{
    require_once __DIR__ . '/Mailer.php';

    $smtp = array(
        'host'       => getSetting('smtp_host'),
        'port'       => (int) getSetting('smtp_port', 587),
        'encryption' => getSetting('smtp_encryption', 'tls'),
        'username'   => getSetting('smtp_user'),
        'password'   => getSetting('smtp_pass'),
        'from_name'  => getSetting('smtp_from_name', getSetting('site_name', SITE_NAME)),
        'from_email' => getSetting('smtp_from_email', getSetting('site_email', ADMIN_EMAIL)),
    );

    /* 1 — PHPMailer when installed through composer */
    if (is_file(VENDOR_PATH . 'autoload.php')) {
        require_once VENDOR_PATH . 'autoload.php';
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                if ($smtp['host'] !== '') {
                    $mail->isSMTP();
                    $mail->Host       = $smtp['host'];
                    $mail->Port       = $smtp['port'];
                    if ($smtp['username'] !== '') {
                        $mail->SMTPAuth = true;
                        $mail->Username = $smtp['username'];
                        $mail->Password = $smtp['password'];
                    }
                    if ($smtp['encryption'] === 'tls') {
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    } elseif ($smtp['encryption'] === 'ssl') {
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    } else {
                        $mail->SMTPAutoTLS = false;
                        $mail->SMTPSecure  = false;
                    }
                }
                $mail->setFrom($smtp['from_email'], $smtp['from_name']);
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $bodyHtml;
                $mail->AltBody = trim(strip_tags(preg_replace('/<(br|\/p|\/div|\/tr)>/i', "\n", $bodyHtml)));
                foreach ($attachments as $att) {
                    $mail->addAttachment($att);
                }
                $mail->send();
                return true;
            } catch (Throwable $e) {
                error_log('[TPT] PHPMailer error: ' . $e->getMessage());
            }
        }
    }

    /* 2 — built-in SMTP client */
    if ($smtp['host'] !== '') {
        $mailer = new PieMailer($smtp);
        $result = $mailer->send($to, $subject, $bodyHtml);
        if ($result['success']) {
            return true;
        }
        error_log('[TPT] SMTP error: ' . $result['error']);
    }

    /* 3 — last resort: PHP mail() */
    $headers = 'MIME-Version: 1.0' . "\r\n"
        . 'Content-type: text/html; charset=UTF-8' . "\r\n"
        . 'From: ' . $smtp['from_name'] . ' <' . $smtp['from_email'] . '>' . "\r\n";
    $plain = trim(strip_tags(preg_replace('/<(br|\/p|\/div|\/tr)>/i', "\n", $bodyHtml)));
    return @mail($to, $subject, $bodyHtml, $headers) || @mail($to, $subject, $plain);
}

/* ===========================================================================
   CSV export
   =========================================================================== */

function csvDownload($filename, $headers, $rows)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, $row, ',', '"', '');
    }
    fclose($out);
    exit;
}

/* ===========================================================================
   Flash messages (admin UI)
   =========================================================================== */

function setFlash($type, $message)
{
    $_SESSION['flash'] = array('type' => $type, 'message' => $message);
}

function getFlash()
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
