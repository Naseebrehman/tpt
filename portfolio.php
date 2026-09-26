<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Work — Case Studies | The Pie Technologies';
$metaDesc  = 'Real engagements, described honestly — what was broken, what we built, and how it was measured. No invented numbers; client-confidential where required.';
$activeNav = 'portfolio';

$items      = getPortfolioItems('');
$industries = array();
foreach ($items as $item) {
    $ind = isset($item['industry']) && $item['industry'] !== '' ? $item['industry'] : $item['service_category'];
    if (!in_array($ind, $industries, true)) {
        $industries[] = $ind;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Work</p>
        <h1>Work that moves businesses forward.</h1>
        <p class="lead">Real engagements, described honestly — what was broken, what we built, and how it was measured. No invented numbers; client-confidential where required.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="filter-tabs" role="group" aria-label="Filter case studies by industry">
            <button class="filter-tab active" type="button" data-filter="all" aria-pressed="true">All</button>
            <?php foreach ($industries as $ind): ?>
            <button class="filter-tab" type="button" data-filter="<?= esc($ind) ?>" aria-pressed="false"><?= esc($ind) ?></button>
            <?php endforeach; ?>
        </div>

        <?php if ($items): ?>
        <div class="portfolio-grid" id="portfolioGrid">
            <?php foreach ($items as $i => $item):
                $stats    = jsonCol($item['stats_json']);
                $headline = $stats && isset($stats[0]['value']) ? $stats[0]['value'] . (isset($stats[0]['label']) ? ' ' . $stats[0]['label'] : '') : 'Described honestly — no invented numbers';
                $industry = isset($item['industry']) && $item['industry'] !== '' ? $item['industry'] : $item['service_category'];
                $svcList  = array_map('trim', explode(',', $item['service_category']));
            ?>
            <a class="pf-card" href="<?= url('portfolio/' . $item['slug']) ?>" data-category="<?= esc($industry) ?>" data-aos="fade-up" data-aos-delay="<?= ($i % 2) * 90 ?>">
                <div class="pf-media">
                    <img src="<?= asset($item['thumbnail'] !== '' ? $item['thumbnail'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($item['client_name']) ?> — <?= esc($industry) ?> case study" loading="lazy">
                    <span class="pf-badge"><?= esc($industry) ?></span>
                </div>
                <div class="pf-body">
                    <span class="pf-client"><?= esc($item['client_name']) ?></span>
                    <p class="pf-result"><?= esc($headline) ?></p>
                    <p class="text-muted" style="font-size:.8rem;margin:6px 0 0"><?= esc(implode(' · ', array_slice($svcList, 0, 3))) ?></p>
                    <span class="pf-link-hint">Read the case study <?= icon('arrow-r', 15) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="chart-card text-center hidden" id="pfEmpty" style="margin-top:22px">
            <p class="text-muted">No case studies in this industry yet — <a href="<?= url('contact') ?>" style="color:var(--violet-soft)">ask us about related engagements</a>.</p>
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
        <h2 data-aos="fade-up">Your business could be the next case study.</h2>
        <p data-aos="fade-up" data-aos-delay="80">Every engagement above started with one call and an honest conversation about goals — and a promise to describe the results truthfully, whatever they are.</p>
        <div class="hero-ctas" data-aos="fade-up" data-aos-delay="140">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic">Start a Project <?= icon('arrow-r', 18) ?></a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
