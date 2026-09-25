<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — shared site header
 * ---------------------------------------------------------------------------
 *  Page variables (set before including this file):
 *    $pageTitle  string   page title (suffixed with the agency name)
 *    $metaDesc   string   meta description / OG description
 *    $activeNav  string   nav key to highlight
 *    $bodyClass  string   extra <body> classes
 *    $pageLibs   array    optional JS libs: 'swiper','chart','typed','particles','sortable'
 *    $jsonLd     string   optional raw JSON-LD block
 *    $ogImage    string   optional OG image path (relative to site root)
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once __DIR__ . '/init.php';
}

$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle : getSetting('meta_title', 'Digital Marketing That Actually Moves Numbers');
$metaDesc  = isset($metaDesc) && $metaDesc !== '' ? $metaDesc : getSetting('meta_description', 'The Pie Technologies is a full-service growth agency: Meta Ads, SEO, Social Media Management, Web Development, Email Marketing, Google Ads and Branding.');
$pageLibs  = isset($pageLibs) && is_array($pageLibs) ? $pageLibs : array();
$bodyClass = isset($bodyClass) ? $bodyClass : '';
$ogImage   = isset($ogImage) && $ogImage !== '' ? $ogImage : getSetting('og_image', 'assets/images/og-image.jpg');
$siteName  = getSetting('site_name', SITE_NAME);
$services  = pieServices();
$navItems  = pieNav();
$gaId      = getSetting('google_analytics_id');
$fbPixel   = getSetting('facebook_pixel_id');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($pageTitle . ' | ' . $siteName) ?></title>
<meta name="description" content="<?= esc($metaDesc) ?>">
<meta property="og:title" content="<?= esc($pageTitle) ?>">
<meta property="og:description" content="<?= esc($metaDesc) ?>">
<meta property="og:type" content="website">
<meta property="og:image" content="<?= esc(canonicalUrl($ogImage)) ?>">
<meta property="og:url" content="<?= esc(canonicalUrl()) ?>">
<meta property="og:site_name" content="<?= esc($siteName) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= esc($pageTitle) ?>">
<meta name="twitter:description" content="<?= esc($metaDesc) ?>">
<meta name="twitter:image" content="<?= esc(canonicalUrl($ogImage)) ?>">
<link rel="canonical" href="<?= esc(canonicalUrl()) ?>">
<meta name="theme-color" content="#08080a">
<link rel="icon" type="image/svg+xml" href="<?= asset('assets/images/favicon.svg') ?>">
<link rel="apple-touch-icon" href="<?= asset($ogImage) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
<?php if (!empty($pageLibs['swiper'])): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<?php endif; ?>
<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/sections.css') ?>">
<?php if ($gaId): ?>
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= esc($gaId) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', <?= json_encode($gaId) ?>);
</script>
<?php endif; ?>
<?php if ($fbPixel): ?>
<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?= json_encode($fbPixel) ?>);
fbq('track', 'PageView');
</script>
<?php endif; ?>
<?php if (!empty($jsonLd)): ?>
<script type="application/ld+json">
<?= is_array($jsonLd) ? json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $jsonLd ?>
</script>
<?php endif; ?>
</head>
<body class="theme-dark<?= $bodyClass !== '' ? ' ' . esc($bodyClass) : '' ?>">

<a class="skip-link" href="#main">Skip to content</a>

<div id="preloader" aria-hidden="true">
    <div class="preloader-logo" data-text="THE PIE TECHNOLOGIES">THE PIE TECHNOLOGIES</div>
    <div class="preloader-bar"><span></span></div>
</div>

<div class="cursor-dot" id="cursorDot" aria-hidden="true"></div>
<div class="cursor-ring" id="cursorRing" aria-hidden="true"></div>

<header class="site-nav" id="siteNav">
    <div class="container nav-inner">
        <a class="brand" href="<?= url('') ?>" aria-label="<?= esc($siteName) ?> — home">
            The&nbsp;Pie<span class="brand-dot">.</span>&nbsp;Technologies
        </a>

        <nav class="nav-links" aria-label="Primary">
            <ul>
                <?php foreach ($navItems as $navItem): ?>
                <li class="nav-item<?= !empty($navItem['dropdown']) ? ' has-dropdown' : '' ?>">
                    <a href="<?= esc($navItem['url']) ?>" class="nav-link<?= isActive($navItem['key']) ?>"<?php if (!empty($navItem['dropdown'])): ?> aria-haspopup="true" aria-expanded="false"<?php endif; ?>>
                        <?= esc($navItem['label']) ?>
                        <?php if (!empty($navItem['dropdown'])): ?><svg class="caret" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg><?php endif; ?>
                    </a>
                    <?php if (!empty($navItem['dropdown'])): ?>
                    <div class="dropdown" role="menu">
                        <?php foreach ($services as $svc): ?>
                        <a class="dropdown-item" role="menuitem" href="<?= url('services/' . $svc['key']) ?>">
                            <span class="dd-icon"><?= icon($svc['icon'], 18) ?></span>
                            <span class="dd-copy">
                                <strong><?= esc($svc['name']) ?></strong>
                                <small><?= esc($svc['tagline']) ?></small>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="nav-cta">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-magnetic btn-sm">Start a Project</a>
        </div>

        <button class="nav-burger" id="navBurger" aria-label="Open menu" aria-expanded="false" aria-controls="mobileMenu">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<div class="mobile-menu" id="mobileMenu" aria-hidden="true">
    <nav aria-label="Mobile">
        <ul class="mobile-links">
            <?php foreach ($navItems as $i => $navItem): ?>
            <li style="--i:<?= $i ?>"><a href="<?= esc($navItem['url']) ?>"><?= esc($navItem['label']) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="mobile-services">
            <p class="eyebrow">Services</p>
            <div class="mobile-service-links">
                <?php foreach ($services as $svc): ?>
                <a href="<?= url('services/' . $svc['key']) ?>"><?= esc($svc['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="<?= url('contact') ?>" class="btn btn-primary btn-block">Start a Project</a>
    </nav>
</div>

<main id="main">
