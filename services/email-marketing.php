<?php
/**
 * The Pie Technologies — Email Marketing (retired slug)
 * Email marketing now lives inside our Digital Marketing system.
 * This page 301-redirects so old URLs and links keep working.
 */
require_once dirname(__DIR__) . '/includes/init.php';

$redirects = pieServiceRedirects();
$target    = isset($redirects['email-marketing']) ? $redirects['email-marketing'] : 'digital-marketing';

header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . url('services/' . $target));
exit;
