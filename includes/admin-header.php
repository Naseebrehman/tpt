<?php
/**
 * ---------------------------------------------------------------------------
 *  Admin layout header. Pages set: $adminTitle, $adminPage (nav key).
 * ---------------------------------------------------------------------------
 */
requireAdmin();

/* Admin screens reachable as /admin/page.php bypass the front controller, so
   the schema self-check runs here as well: tables added by later migrations
   (notification emails, chat transcripts, payment services) are created
   quietly instead of producing SQL errors in the dashboard. */
require_once BASE_PATH . '/core/Schema.php';
Schema::ensure();

$adminPage  = isset($adminPage) ? $adminPage : 'dashboard';
$adminTitle = isset($adminTitle) ? $adminTitle : 'Dashboard';
$adminUser  = currentAdmin();
$flash      = getFlash();

$adminNav = array(
    array('key' => 'leads', 'label' => 'Alia Leads', 'href' => 'leads.php', 'icon' => 'users'),
    array('key' => 'chats', 'label' => 'Alia Chats', 'href' => 'chats.php', 'icon' => 'chat'),
    array('key' => 'content', 'label' => 'Content & SEO', 'href' => 'content.php', 'icon' => 'edit'),
    array('key' => 'dashboard',    'label' => 'Dashboard',     'href' => 'index.php',        'icon' => 'grid'),
    array('key' => 'submissions',  'label' => 'Submissions',   'href' => 'submissions.php',  'icon' => 'mail'),
    array('key' => 'payments',     'label' => 'Payment Settings', 'href' => 'payments.php',  'icon' => 'card'),
    array('key' => 'blog',         'label' => 'Blog',          'href' => 'blog.php',         'icon' => 'edit'),
    array('key' => 'resources',    'label' => 'Resources',     'href' => 'resources.php',    'icon' => 'download'),
    array('key' => 'portfolio',    'label' => 'Portfolio',     'href' => 'portfolio.php',    'icon' => 'layers'),
    array('key' => 'team',         'label' => 'Team',          'href' => 'team.php',         'icon' => 'users'),
    array('key' => 'testimonials', 'label' => 'Testimonials',  'href' => 'testimonials.php', 'icon' => 'star'),
    array('key' => 'subscribers',  'label' => 'Subscribers',   'href' => 'subscribers.php',  'icon' => 'send'),
    array('key' => 'media',        'label' => 'Media Library', 'href' => 'media.php',        'icon' => 'image'),
    array('key' => 'admins',       'label' => 'Admin Management', 'href' => 'admins.php',    'icon' => 'shield'),
    array('key' => 'settings',     'label' => 'Settings',      'href' => 'settings.php',     'icon' => 'cpu'),
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($adminTitle) ?> | Admin — The Pie Technologies</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="<?= asset('assets/images/favicon.svg') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
<?php if (!empty($adminLibs['chart'])): ?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script><?php endif; ?>
<?php if (!empty($adminLibs['sortable'])): ?><script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" defer></script><?php endif; ?>
<?php if (!empty($adminLibs['tinymce'])): ?>
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin" defer></script>
<?php endif; ?>
</head>
<body class="admin-body">

<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-brand">The Pie<span>.</span><small>Admin</small></div>
    <nav>
        <?php foreach ($adminNav as $navItem): ?>
        <a href="<?= esc($navItem['href']) ?>" class="<?= $adminPage === $navItem['key'] ? 'active' : '' ?>"><?= icon($navItem['icon'], 17) ?><span><?= esc($navItem['label']) ?></span></a>
        <?php endforeach; ?>
    </nav>
    <div class="admin-side-foot">
        <a href="<?= url('') ?>" target="_blank" rel="noopener"><?= icon('globe', 16) ?><span>View Site</span></a>
        <a href="logout.php"><?= icon('close', 16) ?><span>Logout</span></a>
    </div>
</aside>

<div class="admin-main">
    <header class="admin-topbar">
        <button class="a-icon-btn sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation"><?= icon('menu', 20) ?></button>
        <h1><?= esc($adminTitle) ?></h1>
        <div class="admin-top-actions">
            <span class="admin-user"><?= icon('users', 15) ?> <?= esc($adminUser ? $adminUser['email'] : 'admin') ?></span>
        </div>
    </header>

    <?php if ($flash): ?>
    <div class="admin-alert <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= esc($flash['message']) ?></div>
    <?php endif; ?>

    <main class="admin-content">
