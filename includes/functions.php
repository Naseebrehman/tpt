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
    // Forwarded headers are untrusted unless a deployment explicitly validates its proxy.
    return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';
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

require_once dirname(__DIR__) . '/core/SampleContent.php';

function getRecentPosts($limit = 3, $excludeId = null)
{
    if (SampleContent::usesFallback('blog_posts')) { return array_slice(array_values(array_filter(SampleContent::all('blog_posts'), function ($row) use ($excludeId) { return $row['id'] !== $excludeId; })), 0, max(1,(int)$limit)); }
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
    if (SampleContent::usesFallback('blog_posts')) { return SampleContent::posts($opts); }
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
    if (SampleContent::usesFallback('blog_posts')) { return SampleContent::find('blog_posts', 'slug', $slug); }
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
    if (SampleContent::usesFallback('blog_posts')) { return array_map(function ($row) { return array('id'=>$row['id'],'name'=>$row['category_name'],'slug'=>$row['category_slug']); }, SampleContent::all('blog_posts')); }
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
    if (SampleContent::usesFallback('portfolio')) { return array_values(array_filter(SampleContent::all('portfolio'), function ($row) use ($filter) { return !$filter || $row['service_category'] === $filter; })); }
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
    if (SampleContent::usesFallback('portfolio')) { return SampleContent::find('portfolio', 'slug', $slug); }
    return dbOne('SELECT * FROM portfolio WHERE slug = ? AND is_active = 1', array($slug));
}

function getPortfolioById($id)
{
    return dbOne('SELECT * FROM portfolio WHERE id = ?', array((int) $id));
}

function getResources($type = null, $activeOnly = true)
{
    if ($activeOnly && SampleContent::usesFallback('resources')) { return array_values(array_filter(SampleContent::all('resources'), function ($row) use ($type) { return !$type || $row['resource_type'] === $type; })); }
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
    $sql .= ' ORDER BY id ASC';
    return dbAll($sql, $params);
}

function getResourceBySlug($slug)
{
    if (SampleContent::usesFallback('resources')) { return SampleContent::find('resources', 'slug', $slug); }
    return dbOne('SELECT * FROM resources WHERE slug = ? AND is_active = 1', array($slug));
}

function getTeamMembers()
{
    $members = dbAll('SELECT * FROM team_members WHERE is_active = 1 ORDER BY display_order ASC, id ASC');
    if ($members) { return $members; }
    $count = DB_OK ? dbOne('SELECT COUNT(*) AS total FROM team_members') : null;
    // Keep intentionally hidden profiles hidden. Only an empty/offline roster gets a preview.
    if (DB_OK && (!$count || (int) $count['total'] > 0)) { return array(); }
    return array(
        array('id'=>-401, 'name'=>getSetting('founder_name', 'Ali Raza'), 'role'=>'Founder & Growth Strategist',
            'photo'=>'assets/images/founder-avatar.jpg', 'bio'=>'Connects strategy, creative and delivery around a clear business goal.', 'linkedin'=>'', 'twitter'=>'', 'is_preview'=>true),
        array('id'=>-402, 'name'=>'Hina Shahid', 'role'=>'Head of Paid Media',
            'photo'=>'', 'bio'=>'Brings audience research, campaign planning and creative testing together.', 'linkedin'=>'', 'twitter'=>'', 'is_preview'=>true),
        array('id'=>-403, 'name'=>'Daniyal Khan', 'role'=>'Lead Developer',
            'photo'=>'', 'bio'=>'Builds accessible, responsive websites with reliable measurement.', 'linkedin'=>'', 'twitter'=>'', 'is_preview'=>true),
    );
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
    if (!isAdminLoggedIn() || !currentAdmin()) {
        unset($_SESSION['admin_id'], $_SESSION['admin_email']);
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
require_once dirname(__DIR__) . '/core/Upload.php';

/* ===========================================================================
   Mail
   =========================================================================== */

/**
 * Send an HTML email.
 * Priority: 1) PHPMailer from /vendor (composer), 2) built-in SMTP client,
 * Returns true only on accepted delivery; SMTP failures are not hidden.
 */
function sendEmail($to, $subject, $bodyHtml, $attachments = array())
{
    require_once __DIR__ . '/Mailer.php';
    $GLOBALS['pieMailError'] = '';

    $smtp = array(
        'reply_to'   => getSetting('smtp_reply_to'),
        'host'       => getSetting('smtp_host'),
        'port'       => (int) getSetting('smtp_port', 587),
        'encryption' => getSetting('smtp_encryption', 'tls'),
        'username'   => getSetting('smtp_user'),
        'password'   => getSetting('smtp_pass'),
        'from_name'  => getSetting('smtp_from_name', getSetting('site_name', SITE_NAME)),
        'from_email' => getSetting('smtp_from_email', getSetting('site_email', ADMIN_EMAIL)),
    );

    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $subject . $smtp['from_name']) || !filter_var($smtp['from_email'], FILTER_VALIDATE_EMAIL)) { $GLOBALS['pieMailError'] = 'Invalid mail address or header.'; return false; }

    if ($smtp['host'] === '') { $GLOBALS['pieMailError'] = 'SMTP host is not configured. Save SMTP settings first.'; return false; }

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
                if (filter_var($smtp['reply_to'], FILTER_VALIDATE_EMAIL)) { $mail->addReplyTo($smtp['reply_to']); }
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
        $GLOBALS['pieMailError'] = $result['error'];
        error_log('[TPT] SMTP delivery failed.');
        return false; // Never disguise broken SMTP as successful PHP mail delivery.
    }

    $GLOBALS['pieMailError'] = 'SMTP host is not configured. Save SMTP settings first.';
    return false;
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
        $row = array_map(function ($value) { return preg_match('/^[\s]*[=+@-]/', (string) $value) ? "'" . $value : $value; }, $row);
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
