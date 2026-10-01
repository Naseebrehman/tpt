<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Privacy Policy';
$metaDesc  = 'How The Pie Technologies collects, uses and protects your data. Short version: we collect what you send us, we never sell it, and you can delete it anytime.';

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Privacy Policy</p>
        <h1>Privacy Policy</h1>
        <p class="lead">Last updated: <?= date('j F Y') ?></p>
    </div>
</section>

<section class="legal-body">
    <div class="container" style="max-width:860px">
        <h2>1. What we collect</h2>
        <p>Only what you give us or what your browser sends automatically: contact form submissions (name, email, phone, company, service, budget, message), newsletter signups (name, email), blog comments (name, email, comment), chatbot conversations you choose to start (optional name and email), and basic technical logs (IP address, user agent, pages viewed) used for security and analytics.</p>

        <h2>2. How we use it</h2>
        <ul>
            <li>To respond to your enquiry and provide the services you request.</li>
            <li>To send the newsletter you subscribed to — every issue includes a one-click unsubscribe.</li>
            <li>To understand which content helps people, via aggregated analytics.</li>
            <li>To protect the site from spam and abuse.</li>
        </ul>

        <h2>3. What we never do</h2>
        <p>We never sell your personal data, never rent it out, and never share it with third parties except the processors required to run the site (hosting, email delivery and, if you chat with PIE Bot, the AI provider that generates the reply). Ad platforms only receive anonymised, aggregated conversion signals.</p>

        <h2>4. Cookies &amp; tracking</h2>
        <p>The site uses strictly necessary session cookies (login and security). If the owner enables Google Analytics or Meta Pixel, those set their own cookies under their respective privacy policies; you can block them with any standard browser extension without breaking the site.</p>

        <h2>5. Retention</h2>
        <p>Contact enquiries are kept for 24 months so we can reference past conversations. Newsletter records live until you unsubscribe. You can request deletion of anything we hold about you at any time.</p>

        <h2>6. Your rights</h2>
        <p>You may request access to, correction of, or deletion of your personal data, and you may object to processing, by emailing <?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>. We respond within 30 days.</p>

        <h2>7. Security</h2>
        <p>Data is stored in access-controlled databases over encrypted connections, forms are protected against CSRF and injection, and uploads are validated and scanned by type. No system is perfect; if we ever suffer a breach affecting your data we will tell you promptly.</p>

        <h2>8. Contact</h2>
        <p>Questions about this policy: <?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?> · <?= esc(getSetting('site_address', 'Collingswood, New Jersey, USA')) ?></p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
