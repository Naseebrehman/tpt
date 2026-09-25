<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Terms of Service';
$metaDesc  = 'The plain-English terms that govern use of this website and engagement of The Pie Technologies services.';

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Terms</p>
        <h1>Terms of Service</h1>
        <p class="lead">Last updated: <?= date('j F Y') ?></p>
    </div>
</section>

<section class="legal-body">
    <div class="container" style="max-width:860px">
        <h2>1. Agreement</h2>
        <p>By using this website you accept these terms. If you do not agree with them, please do not use the site. Service engagements are governed by the separate signed agreement between you and The Pie Technologies; these terms cover website use only.</p>

        <h2>2. Use of the site</h2>
        <ul>
            <li>You may browse, download free resources and submit forms for legitimate business purposes.</li>
            <li>You may not attempt to disrupt, scan, scrape aggressively or gain unauthorised access to any part of the site.</li>
            <li>Content submitted by you (comments, enquiries) must be lawful and non-infringing; we may remove anything at our discretion.</li>
        </ul>

        <h2>3. Intellectual property</h2>
        <p>All site content — copy, design, code, logos and case study material — belongs to The Pie Technologies or its clients and is protected by copyright. Free guides and templates are licensed for your internal business use; resale or redistribution is not permitted.</p>

        <h2>4. No guarantees of results</h2>
        <p>Marketing outcomes depend on markets, budgets, creative and factors outside any agency&rsquo;s control. Case study figures describe past client results and are not a promise of future performance. Specific commitments appear only in signed service agreements.</p>

        <h2>5. Third-party links &amp; embeds</h2>
        <p>The site links to and embeds third-party services (YouTube, social networks, analytics). Those services operate under their own terms and privacy policies; we are not responsible for their content or availability.</p>

        <h2>6. Limitation of liability</h2>
        <p>To the maximum extent permitted by law, The Pie Technologies is not liable for indirect or consequential losses arising from use of this website or reliance on its free educational content.</p>

        <h2>7. Changes</h2>
        <p>We may update these terms; the &ldquo;last updated&rdquo; date above always reflects the current version. Continued use after changes constitutes acceptance.</p>

        <h2>8. Contact</h2>
        <p>Questions: <?= esc(getSetting('site_email', 'hello@thepietechnologies.com')) ?></p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
