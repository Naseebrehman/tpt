<?php
/**
 * The Pie Technologies — Services index (/services)
 * All eleven services grouped by the five disciplines.
 */
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Services — Five Disciplines, Eleven Services';
$metaDesc  = 'Explore the full TPT system: GROW (Meta Ads, Social, Google Ads, Digital Marketing), GET FOUND (SEO, Local SEO, AI Optimization), BUILD (Web, Apps), CREATE (Design) and MEASURE (Analytics).';
$activeNav = 'services';
$bodyClass = 'page-services-index';

$jsonLd = json_encode(array(
    '@context' => 'https://schema.org',
    '@type'    => 'ItemList',
    'name'     => 'The Pie Technologies services',
    'itemListElement' => array_values(array_map(function ($i, $svc) {
        return array(
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'item'     => array('@type' => 'Service', 'name' => $svc['name'], 'url' => url('services/' . $svc['key'])),
        );
    }, array_keys(pieServices()), pieServices())),
), JSON_UNESCAPED_SLASHES);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ================================ HERO ================================ -->
<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Services</p>
        <h1>Five disciplines.<br>Eleven services. One system.</h1>
        <p class="lead">Every service below plugs into the same growth machine — strategy, creative, media, build and measurement, run by one accountable team. Start where your bottleneck is; the rest connects.</p>
        <div class="page-hero-actions">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-magnetic">Start here <?= icon('arrow-r', 18) ?></a>
            <a href="<?= url('resources') ?>" class="btn btn-ghost btn-magnetic">Browse the Growth Library</a>
        </div>
    </div>
</section>

<!-- ========================== DISCIPLINE BLOCKS ========================= -->
<?php foreach (pieDisciplines() as $di => $disc): $discSvcs = pieServicesByDiscipline($disc['key']); if (!$discSvcs) { continue; } ?>
<section class="section<?= $di === 0 ? '' : '' ?>"<?= $di === 0 ? '' : ' style="padding-top:0"' ?>>
    <div class="container">
        <div class="disc-head" data-aos="fade-up">
            <span class="disc-num mono"><?= esc($disc['num']) ?></span>
            <div>
                <p class="eyebrow"><?= esc($disc['label']) ?></p>
                <h2 class="section-title" style="font-size:clamp(1.6rem,3.2vw,2.4rem)"><?= esc($disc['name']) ?></h2>
                <p class="text-muted" style="max-width:70ch;margin-top:10px;line-height:1.75"><?= esc($disc['desc']) ?></p>
            </div>
        </div>
        <div class="feature-grid" style="margin-top:28px">
            <?php foreach ($discSvcs as $si => $svc): ?>
            <a class="feature-card" href="<?= url('services/' . $svc['key']) ?>" data-aos="fade-up" data-aos-delay="<?= $si * 80 ?>" style="text-decoration:none;color:inherit">
                <span style="display:flex;align-items:center;gap:10px"><?= icon($svc['icon'], 24) ?><?= !empty($svc['core']) ? '<span class="dd-core">Core</span>' : '' ?></span>
                <h3><?= esc($svc['name']) ?></h3>
                <p><?= esc($svc['tagline']) ?></p>
                <span class="link-arrow" style="margin-top:14px;display:inline-flex">Explore <?= icon('arrow-r', 15) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endforeach; ?>

<!-- ============================= FINAL CTA ============================== -->
<section class="final-cta">
    <div class="container">
        <h2 data-aos="fade-up">Not sure which discipline is your bottleneck?</h2>
        <p data-aos="fade-up" data-aos-delay="80">Tell us the goal. We&rsquo;ll map the system — and say honestly which parts you need now and which can wait.</p>
        <div class="hero-ctas" data-aos="fade-up" data-aos-delay="140">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic">Start a Project <?= icon('arrow-r', 18) ?></a>
            <a href="<?= url('portfolio') ?>" class="btn btn-ghost btn-lg btn-magnetic">See the work</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
