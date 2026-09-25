<?php
require_once __DIR__ . '/includes/init.php';

if (!headers_sent()) {
    http_response_code(404);
}
$pageTitle = 'Page Not Found';
$metaDesc  = 'The page you were looking for has moved or never existed. Let\'s get you back to growing.';

require_once __DIR__ . '/includes/header.php';
?>

<section class="state-page">
    <div class="inner">
        <div class="state-code">404</div>
        <h1>This page went off to find itself.</h1>
        <p>The link may be old, mistyped, or the page moved during a redesign. Either way, the growth is still happening on the other pages.</p>
        <div class="state-actions">
            <a href="<?= url('') ?>" class="btn btn-primary btn-magnetic">Back to Home <?= icon('arrow-r', 18) ?></a>
            <a href="<?= url('portfolio') ?>" class="btn btn-ghost btn-magnetic">See Our Work</a>
            <a href="<?= url('contact') ?>" class="btn btn-ghost btn-magnetic">Report a Broken Link</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
