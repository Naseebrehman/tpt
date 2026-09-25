<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Portfolio — Work That Drives Results';
$metaDesc  = 'Real campaigns, real numbers. Browse The Pie Technologies portfolio: Meta Ads, SEO, Social Media, Web Development and Branding case studies with verified results.';
$activeNav = 'portfolio';

$items      = getPortfolioItems('');
$categories = array();
foreach ($items as $item) {
    if (!in_array($item['service_category'], $categories, true)) {
        $categories[] = $item['service_category'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Portfolio</p>
        <h1>Work That Drives Results.</h1>
        <p class="lead">No stock photos of handshakes. Every case study below carries the number the client cared about — and how we moved it.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="filter-tabs" role="group" aria-label="Filter portfolio by service">
            <button class="filter-tab active" type="button" data-filter="all" aria-pressed="true">All</button>
            <?php foreach ($categories as $cat): ?>
            <button class="filter-tab" type="button" data-filter="<?= esc($cat) ?>" aria-pressed="false"><?= esc($cat) ?></button>
            <?php endforeach; ?>
        </div>

        <?php if ($items): ?>
        <div class="portfolio-grid" id="portfolioGrid">
            <?php foreach ($items as $i => $item):
                $stats = jsonCol($item['stats_json']);
                $headline = $stats && isset($stats[0]['value']) ? $stats[0]['value'] . (isset($stats[0]['label']) ? ' ' . $stats[0]['label'] : '') : 'Results on request';
            ?>
            <a class="pf-card" href="<?= url('portfolio/' . $item['slug']) ?>" data-category="<?= esc($item['service_category']) ?>" data-aos="fade-up" data-aos-delay="<?= ($i % 2) * 90 ?>">
                <div class="pf-media">
                    <img src="<?= asset($item['thumbnail'] !== '' ? $item['thumbnail'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($item['client_name']) ?> — <?= esc($item['service_category']) ?> case study" loading="lazy">
                    <span class="pf-badge"><?= esc($item['service_category']) ?></span>
                </div>
                <div class="pf-body">
                    <span class="pf-client"><?= esc($item['client_name']) ?></span>
                    <p class="pf-result"><?= esc($headline) ?></p>
                    <span class="pf-link-hint">Read the case study <?= icon('arrow-r', 15) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="chart-card text-center">
            <p class="text-muted">Case studies are being uploaded — check back shortly, or <a href="<?= url('contact') ?>" style="color:var(--violet-soft)">ask us directly</a> for relevant work in your industry.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="final-cta">
    <div class="container">
        <h2 data-aos="fade-up">Your brand could be the next case study.</h2>
        <p data-aos="fade-up" data-aos-delay="80">Every result above started with a 30-minute call and an honest conversation about goals.</p>
        <div class="hero-ctas" data-aos="fade-up" data-aos-delay="140">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic">Start a Project <?= icon('arrow-r', 18) ?></a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
