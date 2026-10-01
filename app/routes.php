<?php
$router = new Router();
$pages = array('' => 'index.php', 'services' => 'services-index.php', 'work' => 'portfolio.php',
    'legal/privacy-policy' => 'privacy-policy.php', 'legal/terms' => 'terms.php', 'sitemap.xml' => 'sitemap.php');
foreach (array('about', 'blog', 'contact', 'resources', 'portfolio', 'privacy-policy', 'terms', 'pay-online', 'download', 'newsletter', 'blog-single', 'resource-single', 'sitemap') as $page) { $pages[$page] = $page . '.php'; }
foreach ($pages as $path => $file) {
    $router->add(array('GET', 'POST', 'HEAD'), '~^/' . preg_quote($path, '~') . '/?$~D', function () use ($file) { PublicController::page($file); });
}
foreach (glob(BASE_PATH . '/services/*.php') as $file) {
    $relative = 'services/' . basename($file);
    $router->add(array('GET', 'HEAD'), '~^/services/' . preg_quote(basename($file, '.php'), '~') . '/?$~D', function () use ($relative) { PublicController::page($relative); });
}
foreach (array('resources' => 'resource-single.php', 'blog' => 'blog-single.php', 'work' => 'portfolio/case-study.php', 'portfolio' => 'portfolio/case-study.php') as $prefix => $file) {
    $router->add(array('GET', 'POST', 'HEAD'), '~^/' . $prefix . '/([a-z0-9-]+)/?$~D', function ($slug) use ($file) { PublicController::page($file, array('slug' => $slug)); });
}
$admins = array('services' => 'content.php', 'pages' => 'content.php', '' => 'index.php', 'dashboard' => 'index.php', 'profile' => 'password.php', 'work' => 'portfolio.php', 'chatbot' => 'leads.php');
foreach (glob(BASE_PATH . '/admin/*.php') as $file) { $admins[basename($file, '.php')] = basename($file); }
foreach ($admins as $path => $file) {
    $router->add(array('GET', 'POST', 'HEAD'), '~^/admin' . ($path ? '/' . preg_quote($path, '~') : '') . '/?$~D', function () use ($file) { AdminController::page($file); });
}
$router->add(array('GET', 'HEAD'), '~^/search/?$~D', array('PublicController', 'search'));
$router->add(array('GET'), '~^/api/search/?$~D', array('ApiController', 'search'));
$router->add(array('POST'), '~^/api/chat/?$~D', function () { require_once BASE_PATH . '/includes/chatbot-api.php'; handleChatbotRequest(); });
/* Server-side PayPal create/capture (the browser never receives the Secret). */
foreach (array('/paypal-api', '/api/paypal/create-order', '/api/paypal/capture-order', '/api/paypal/status') as $paypalPath) {
    $router->add(array('POST'), '~^' . preg_quote($paypalPath, '~') . '/?$~D', function () { require_once BASE_PATH . '/paypal-api.php'; });
}
/* Server-side Stripe create/confirm (the browser never receives the key). */
foreach (array('/stripe-api', '/api/stripe/create-intent', '/api/stripe/confirm', '/api/stripe/status') as $stripePath) {
    $router->add(array('POST'), '~^' . preg_quote($stripePath, '~') . '/?$~D', function () { require_once BASE_PATH . '/stripe-api.php'; });
}
/* Stripe webhook — authenticated by the Stripe-Signature header, not a session. */
foreach (array('/stripe-webhook', '/api/stripe/webhook') as $stripeWebhookPath) {
    $router->add(array('POST'), '~^' . preg_quote($stripeWebhookPath, '~') . '/?$~D', function () { require_once BASE_PATH . '/stripe-webhook.php'; });
}
foreach (array('contact' => array('contact.php', 'contact_submit'), 'lead' => array('contact.php', 'contact_submit'), 'newsletter' => array('newsletter.php', null)) as $path => $handler) {
    $router->add(array('POST'), '~^/api/' . $path . '/?$~D', function () use ($handler) { ApiController::legacy($handler[0], $handler[1]); });
}
return $router;
