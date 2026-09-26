<?php
/** Optional overrides; absence never replaces the existing editorial content. */
class Content
{
    public static function key($path) { return 'content_' . hash('sha256', self::path($path)); }
    public static function path($path)
    {
        $path = trim(parse_url($path, PHP_URL_PATH) ?: '/', '/');
        $path = preg_replace('/\.php$/', '', $path);
        if ($path === 'legal/privacy-policy') { $path = 'privacy-policy'; }
        if ($path === 'legal/terms') { $path = 'terms'; }
        if ($path === 'work') { $path = 'portfolio'; }
        if (strpos($path, 'work/') === 0) { $path = 'portfolio/' . substr($path, 5); }
        if ($path === 'index') { $path = ''; }
        if ($path === 'services-index') { $path = 'services'; }
        return '/' . $path;
    }
    public static function currentPath()
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (BASE_URL && strpos($path, BASE_URL . '/') === 0) { $path = substr($path, strlen(BASE_URL)); }
        // Legacy detail URLs map to the same editorial record as clean paths.
        foreach (array('/blog-single.php' => '/blog/', '/resource-single.php' => '/resources/', '/portfolio/case-study.php' => '/portfolio/') as $old => $prefix) {
            if ($path === $old && preg_match('/^[a-z0-9-]+$/D', $_GET['slug'] ?? '')) { $path = $prefix . $_GET['slug']; }
        }
        if (strpos($path, '/work/') === 0) { $path = '/portfolio/' . substr($path, 6); }
        if ($path === '/work') { $path = '/portfolio'; }
        return self::path($path);
    }
    public static function get($path)
    {
        $value = json_decode(getSetting(self::key($path), '{}'), true);
        return is_array($value) ? $value : array();
    }
}
