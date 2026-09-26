<?php
/**
 * ---------------------------------------------------------------------------
 *  Auto-generated XML sitemap: static pages + published posts + live work.
 * ---------------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';

require_once BASE_PATH . '/core/Content.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = array();
$today = date('Y-m-d');

function sitemapAdd(&$urls, $path, $priority, $changefreq, $lastmod = null)
{
    if (strpos($path, '/sample-') !== false) { return; }
    $override = Content::get('/' . ltrim($path, '/'));
    if (!empty($override['noindex'])) { return; }
    $urls[] = array(
        'loc'        => !empty($override['canonical']) ? $override['canonical'] : canonicalUrl($path === '' ? '/' : $path),
        'lastmod'    => $lastmod ? date('Y-m-d', strtotime($lastmod)) : null,
        'changefreq' => $changefreq,
        'priority'   => $priority,
    );
}

sitemapAdd($urls, '', '1.0', 'weekly');
sitemapAdd($urls, 'about', '0.8', 'monthly');
sitemapAdd($urls, 'portfolio', '0.9', 'weekly');
sitemapAdd($urls, 'resources', '0.8', 'weekly');
sitemapAdd($urls, 'blog', '0.9', 'daily');
sitemapAdd($urls, 'contact', '0.8', 'monthly');
sitemapAdd($urls, 'pay-online', '0.6', 'monthly');
sitemapAdd($urls, 'services', '0.9', 'weekly');
foreach (pieServices() as $svc) {
    sitemapAdd($urls, 'services/' . $svc['key'], '0.9', 'monthly');
}
foreach (dbAll("SELECT slug FROM resources WHERE is_active = 1 AND slug IS NOT NULL AND slug != ''") as $libItem) {
    sitemapAdd($urls, 'resources/' . $libItem['slug'], '0.7', 'monthly');
}
foreach (dbAll("SELECT slug, published_at FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC") as $post) {
    sitemapAdd($urls, 'blog/' . $post['slug'], '0.7', 'monthly', $post['published_at']);
}
foreach (dbAll("SELECT slug, created_at FROM portfolio WHERE is_active = 1 ORDER BY display_order ASC") as $work) {
    sitemapAdd($urls, 'portfolio/' . $work['slug'], '0.7', 'monthly', $work['created_at']);
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    if ($u['lastmod']) {
        echo '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
    }
    echo '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $u['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
