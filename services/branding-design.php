<?php
/**
 * The Pie Technologies — Branding & Design (retired slug)
 * Brand work now lives inside our Graphic Design service.
 * This page 301-redirects so old URLs and links keep working.
 */
require_once dirname(__DIR__) . '/includes/init.php';

$redirects = pieServiceRedirects();
$target    = isset($redirects['branding-design']) ? $redirects['branding-design'] : 'graphic-design';

header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . url('services/' . $target));
exit;
