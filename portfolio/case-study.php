<?php
require_once dirname(__DIR__) . '/includes/init.php';

$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';
$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$item = null;
if ($slug !== '') {
    $item = getPortfolioBySlug($slug);
}
if (!$item && $id) {
    $item = getPortfolioById($id);
}
if (!$item) {
    http_response_code(404);
    require dirname(__DIR__) . '/404.php';
    exit;
}

$pageTitle = $item['client_name'] . ' — ' . $item['service_category'] . ' Case Study';
$metaDesc  = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $item['challenge']))), 0, 155);
$noIndex = strpos($item['slug'], 'sample-') === 0;
$activeNav = 'portfolio';
$pageLibs  = array('chart' => !empty($item['chart_data_json']));

$stats = jsonCol($item['stats_json']);
$chart = jsonCol($item['chart_data_json']);

/* next active case study for the footer link */
$all   = getPortfolioItems('');
$next  = null;
foreach ($all as $candidate) {
    if ((int) $candidate['id'] !== (int) $item['id']) {
        $next = $candidate;
        break;
    }
}

/* This page offers the short Start-a-project popup. */
$contactModalEnabled = true;

require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs">
            <a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; <a href="<?= url('portfolio') ?>">Work</a> &nbsp;/&nbsp; <?= esc($item['client_name']) ?>
        </p>
        <h1><?= esc($item['client_name']) ?></h1>
        <?php $caseServices = array_map('trim', explode(',', $item['service_category'])); ?>
        <p class="lead"><?= esc($item['service_category']) ?> · Case study</p>
        <div class="case-hero-meta">
            <?php if (!empty($item['industry'])): ?><span class="chip chip-violet"><?= esc($item['industry']) ?></span><?php endif; ?>
            <?php foreach ($caseServices as $caseSvcName): ?><span class="chip"><?= esc($caseSvcName) ?></span><?php endforeach; ?>
            <?php foreach ($stats as $stat): ?>
            <span class="chip"><?= esc(is_array($stat) ? ($stat['value'] . (isset($stat['label']) ? ' ' . $stat['label'] : '')) : $stat) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($item['thumbnail'])): ?>
<section class="section-tight" style="padding-top:clamp(28px,4vw,48px)">
    <div class="container">
        <div class="post-featured" data-aos="fade-up">
            <img src="<?= asset($item['thumbnail']) ?>" alt="<?= esc($item['client_name']) ?> campaign visual">
        </div>
    </div>
</section>
<?php endif; ?>

<section class="case-section">
    <div class="container">
        <h2 data-aos="fade-up">The Challenge</h2>
        <p data-aos="fade-up"><?= nl2br(esc($item['challenge'])) ?></p>
    </div>
</section>

<section class="case-section">
    <div class="container">
        <h2 data-aos="fade-up">The Strategy</h2>
        <p data-aos="fade-up"><?= nl2br(esc($item['strategy'])) ?></p>
    </div>
</section>

<section class="case-section">
    <div class="container">
        <h2 data-aos="fade-up">Execution</h2>
        <p data-aos="fade-up"><?= nl2br(esc($item['results'])) ?></p>
        <?php if ($stats): ?>
        <div class="case-stats" data-aos="fade-up" style="margin-top:34px">
            <?php foreach ($stats as $stat): ?>
            <div>
                <strong><?= esc(is_array($stat) ? $stat['value'] : $stat) ?></strong>
                <span><?= esc(is_array($stat) && isset($stat['label']) ? $stat['label'] : '') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($chart): ?>
<section class="case-section">
    <div class="container">
        <h2 data-aos="fade-up">Results</h2>
        <div class="chart-card" data-aos="fade-up">
            <div class="chart-wrap">
                <canvas data-chart="<?= esc(json_encode(array('type' => 'line', 'data' => $chart))) ?>" role="img" aria-label="Results chart for <?= esc($item['client_name']) ?>"></canvas>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
/* "Behind this result" — link the services named on this case study. */
$behindLinks = array();
foreach ($caseServices as $caseSvcName) {
    foreach (pieServices() as $mapSvc) {
        $a = strtolower(str_replace(array('—', 'the ', '&'), array('', '', 'and'), $caseSvcName));
        $b = strtolower(str_replace(array('—', 'the ', '&'), array('', '', 'and'), $mapSvc['name']));
        if ($a === $b || strpos($b, $a) === 0 || strpos($a, $b) === 0) {
            $behindLinks[] = $mapSvc;
            break;
        }
    }
}
if ($behindLinks): ?>
<section class="case-section">
    <div class="container">
        <h2 data-aos="fade-up">Behind this result</h2>
        <div class="feature-grid" style="margin-top:22px">
            <?php foreach ($behindLinks as $bi => $bSvc): ?>
            <a class="feature-card" href="<?= url('services/' . $bSvc['key']) ?>" data-aos="fade-up" data-aos-delay="<?= $bi * 80 ?>" style="text-decoration:none;color:inherit">
                <?= icon($bSvc['icon'], 24) ?>
                <h3><?= esc($bSvc['name']) ?></h3>
                <p><?= esc($bSvc['tagline']) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($item['testimonial'])): ?>
<section class="case-section" style="border-bottom:0">
    <div class="container">
        <figure class="case-quote" data-aos="fade-up">
            <p>&ldquo;<?= esc($item['testimonial']) ?>&rdquo;</p>
            <footer>— <?= esc($item['testimonial_author'] !== '' ? $item['testimonial_author'] : $item['client_name']) ?></footer>
        </figure>
    </div>
</section>
<?php endif; ?>

<section style="border-top:1px solid var(--line)">
    <div class="container case-next">
        <div>
            <p class="eyebrow" style="margin-bottom:8px">Want results like these?</p>
            <h2 style="font-size:clamp(1.4rem,3vw,2.2rem)">Let&rsquo;s talk about your numbers.</h2>
        </div>
        <div style="display:flex;gap:14px;flex-wrap:wrap">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-magnetic" data-contact-modal>Start a Project <?= icon('arrow-r', 18) ?></a>
            <?php if ($next): ?>
            <a href="<?= url('portfolio/' . $next['slug']) ?>" class="btn btn-ghost btn-magnetic">Next Case Study: <?= esc($next['client_name']) ?> <?= icon('arrow-r', 16) ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
