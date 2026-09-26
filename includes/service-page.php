<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — service page renderer
 * ---------------------------------------------------------------------------
 *  Each /services/*.php file defines a $service array (its unique copy and
 *  section mix) and then includes this template. Sections render only when
 *  their key exists, so every service page keeps its own shape.
 * ---------------------------------------------------------------------------
 */

if (!isset($service) || !is_array($service)) {
    http_response_code(500);
    echo 'Service configuration missing.';
    return;
}

$svcMeta    = pieServiceByKey($service['key']);
$pageTitle  = isset($service['seoTitle']) ? $service['seoTitle'] : $service['title'] . ' — ' . ($svcMeta ? $svcMeta['name'] : 'Services');
$metaDesc   = isset($service['seoDesc']) ? $service['seoDesc'] : (isset($service['lead']) ? $service['lead'] : '');
$activeNav  = 'services';
$bodyClass  = 'page-service page-service-' . $service['key'];
$pageLibs   = array('chart' => !empty($service['chart']), 'swiper' => false);

require_once dirname(__DIR__) . '/includes/header.php';

$svcName = $svcMeta ? $svcMeta['name'] : $service['key'];
?>

<!-- ================================ HERO ================================ -->
<?php
$svcDiscipline = '';
if ($svcMeta) {
    foreach (pieDisciplines() as $disc) {
        if ($disc['key'] === $svcMeta['discipline']) { $svcDiscipline = $disc['name']; break; }
    }
}
?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; <a href="<?= url('services/meta-ads') ?>">Services</a> &nbsp;/&nbsp; <?php if ($svcDiscipline !== ''): ?><?= esc(ucwords(strtolower($svcDiscipline))) ?> · <?php endif; ?><?= esc($svcName) ?></p>
        <h1><?= esc($service['title']) ?></h1>
        <?php if (!empty($service['lead'])): ?><p class="lead"><?= esc($service['lead']) ?></p><?php endif; ?>
        <div class="page-hero-actions">
            <a href="<?= url('contact') ?>?service=<?= rawurlencode($svcName) ?>" class="btn btn-primary btn-magnetic"><?= esc(isset($service['cta']['button']) ? $service['cta']['button'] : 'Start a Project') ?> <?= icon('arrow-r', 18) ?></a>
            <a href="<?= url('portfolio') ?>" class="btn btn-ghost btn-magnetic">See related work</a>
        </div>
        <?php if (!empty($service['heroDesc'])): ?>
        <p class="hero-desc" style="margin-top:26px;max-width:62ch;color:var(--muted);line-height:1.75"><?= esc($service['heroDesc']) ?></p>
        <?php endif; ?>
        <?php if (!empty($service['bullets'])): ?>
        <ul class="hero-bullets" style="margin-top:22px;display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px 26px;list-style:none;padding:0">
            <?php foreach ($service['bullets'] as $hb): ?>
            <li style="display:flex;gap:10px;align-items:flex-start;color:var(--muted);font-size:.95rem"><span style="color:var(--violet);flex:none;margin-top:2px"><?= icon('check', 16) ?></span><span><?= esc($hb) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== PROBLEM =============================== -->
<?php if (!empty($service['problem'])): ?>
<section class="section">
    <div class="container">
        <div class="split">
            <div class="copy" data-aos="fade-up">
                <p class="eyebrow"><?= esc(isset($service['problem']['eyebrow']) ? $service['problem']['eyebrow'] : 'The problem') ?></p>
                <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.9rem)"><?= esc($service['problem']['title']) ?></h2>
                <?php foreach ((array) $service['problem']['paragraphs'] as $pp): ?>
                <p><?= esc($pp) ?></p>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($service['problem']['familiar'])): ?>
            <div class="copy" data-aos="fade-up" data-aos-delay="100">
                <div class="chart-card">
                    <p class="eyebrow" style="margin-bottom:14px"><?= esc(isset($service['problem']['familiarTitle']) ? $service['problem']['familiarTitle'] : 'Sound familiar?') ?></p>
                    <ul style="display:grid;gap:12px;list-style:none;padding:0">
                        <?php foreach ($service['problem']['familiar'] as $fam): ?>
                        <li style="display:flex;gap:10px;color:var(--muted);font-size:.92rem;align-items:flex-start"><span style="color:#f87171;flex:none"><?= icon('close', 15) ?></span><span><?= esc($fam) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================ WHAT WE DO ============================== -->
<?php if (!empty($service['intro'])): ?>
<section class="section">
    <div class="container">
        <div class="split">
            <div class="copy" data-aos="fade-up">
                <p class="eyebrow"><?= esc(isset($service['intro']['heading']) ? $service['intro']['heading'] : 'What we do') ?></p>
                <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.9rem)"><?= esc(isset($service['intro']['title']) ? $service['intro']['title'] : $svcName) ?></h2>
                <?php foreach ((array) $service['intro']['paragraphs'] as $para): ?>
                <p><?= esc($para) ?></p>
                <?php endforeach; ?>
            </div>
            <div class="copy" data-aos="fade-up" data-aos-delay="100">
                <?php if (!empty($service['intro']['aside'])): ?>
                <div class="chart-card">
                    <p class="eyebrow" style="margin-bottom:14px">In practice</p>
                    <p class="text-muted" style="line-height:1.8"><?= esc($service['intro']['aside']) ?></p>
                </div>
                <?php else: ?>
                <div class="chart-card">
                    <p class="eyebrow" style="margin-bottom:14px">What you get</p>
                    <ul style="display:grid;gap:12px">
                        <?php foreach (array_slice($service['intro']['features'], 0, 4) as $feat): ?>
                        <li style="display:flex;gap:10px;color:var(--muted);font-size:.92rem;align-items:flex-start"><?= icon('check', 16) ?><span><?= esc($feat['title']) ?> — <?= esc($feat['text']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($service['intro']['features'])): ?>
        <div class="feature-grid" style="margin-top:clamp(36px,5vw,64px)">
            <?php foreach ($service['intro']['features'] as $fi => $feat): ?>
            <article class="feature-card" data-aos="fade-up" data-aos-delay="<?= ($fi % 3) * 80 ?>">
                <?= icon($feat['icon'], 24) ?>
                <h3><?= esc($feat['title']) ?></h3>
                <p><?= esc($feat['text']) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================ PLATFORMS =============================== -->
<?php if (!empty($service['platforms'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Coverage</p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)"><?= esc(isset($service['platformsTitle']) ? $service['platformsTitle'] : 'Every surface your audience touches.') ?></h2>
        </div>
        <div class="platform-grid">
            <?php foreach ($service['platforms'] as $pi => $platform): ?>
            <div class="platform-tile" data-aos="zoom-in" data-aos-delay="<?= $pi * 60 ?>">
                <?= icon($platform['icon'], 24) ?>
                <span><?= esc($platform['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ====================== ACCORDION (types / scope) ===================== -->
<?php if (!empty($service['accordion'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Scope</p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)"><?= esc($service['accordion']['heading']) ?></h2>
        </div>
        <div class="services-acc faq-style" data-accordion="single">
            <?php foreach ($service['accordion']['items'] as $ai => $accItem): ?>
            <div class="acc-item<?= $ai === 0 ? ' open' : '' ?>">
                <button class="acc-head" type="button">
                    <span class="acc-num mono"><?= str_pad((string) ($ai + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <span class="acc-title" style="font-size:clamp(1.05rem,2.2vw,1.45rem)"><?= esc($accItem['title']) ?></span>
                    <span class="acc-icon"><?= icon('plus', 16) ?></span>
                </button>
                <div class="acc-body">
                    <div class="acc-inner" style="grid-template-columns:1fr">
                        <div class="acc-copy"><p><?= esc($accItem['body']) ?></p></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================= PILLARS ================================ -->
<?php if (!empty($service['pillars'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow"><?= esc(isset($service['pillarsEyebrow']) ? $service['pillarsEyebrow'] : 'Strategy') ?></p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)"><?= esc(isset($service['pillarsTitle']) ? $service['pillarsTitle'] : 'The strategy, in three pillars.') ?></h2>
            <?php if (!empty($service['pillarsLead'])): ?><p class="section-lead"><?= esc($service['pillarsLead']) ?></p><?php endif; ?>
        </div>
        <div class="pillar-grid">
            <?php foreach ($service['pillars'] as $pi => $pillar): ?>
            <article class="pillar" data-aos="fade-up" data-aos-delay="<?= $pi * 90 ?>">
                <span class="num mono"><?= str_pad((string) ($pi + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <h3><?= esc($pillar['title']) ?></h3>
                <p><?= esc($pillar['text']) ?></p>
                <?php if (!empty($pillar['points'])): ?>
                <ul>
                    <?php foreach ($pillar['points'] as $point): ?>
                    <li><?= icon('check', 14) ?><span><?= esc($point) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== MACHINE =============================== -->
<?php if (!empty($service['machine'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow"><?= esc(isset($service['machine']['eyebrow']) ? $service['machine']['eyebrow'] : 'The machine') ?></p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)"><?= esc($service['machine']['title']) ?></h2>
            <?php if (!empty($service['machine']['lead'])): ?><p class="section-lead"><?= esc($service['machine']['lead']) ?></p><?php endif; ?>
        </div>
        <div class="stepper stepper-<?= count($service['machine']['steps']) ?>">
            <?php foreach ($service['machine']['steps'] as $mi => $mstep): ?>
            <div class="step" data-aos="fade-up" data-aos-delay="<?= $mi * 70 ?>">
                <span class="dot mono"><?= str_pad((string) ($mi + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <div>
                    <h4><?= esc($mstep['title']) ?></h4>
                    <p><?= esc($mstep['text']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($service['machine']['note'])): ?>
        <p class="text-muted" data-aos="fade-up" style="margin-top:26px;font-style:italic"><?= esc($service['machine']['note']) ?></p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ======================== SAMPLE CONTENT MOCK ========================= -->
<?php if (!empty($service['mock'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">A week in your feed</p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)">What a managed calendar looks like.</h2>
            <p class="section-lead">Every post planned, designed, written and scheduled before your week even starts.</p>
        </div>
        <div class="content-mock" data-aos="fade-up">
            <?php
            $mockTypes = array('Reel', 'Carousel', 'Static', 'Story', 'Reel', 'Static', 'Carousel', 'Story');
            foreach ($mockTypes as $mi => $mtype): ?>
            <div class="mock-post">
                <span class="mock-type"><?= esc($mtype) ?></span>
                <span class="mock-lines"><i></i><i></i><i></i></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== PROCESS =============================== -->
<?php if (!empty($service['steps'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow"><?= esc(isset($service['stepsEyebrow']) ? $service['stepsEyebrow'] : 'Our Process') ?></p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)"><?= esc(isset($service['stepsTitle']) ? $service['stepsTitle'] : 'How we work.') ?></h2>
            <?php if (!empty($service['stepsLead'])): ?><p class="section-lead"><?= esc($service['stepsLead']) ?></p><?php endif; ?>
        </div>
        <div class="stepper<?= count($service['steps']) === 5 ? ' stepper-5' : (count($service['steps']) === 7 ? ' stepper-7' : '') ?>">
            <?php foreach ($service['steps'] as $si => $step): ?>
            <div class="step" data-aos="fade-up" data-aos-delay="<?= $si * 70 ?>">
                <span class="dot mono"><?= str_pad((string) ($si + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <div>
                    <h4><?= esc($step['title']) ?></h4>
                    <p><?= esc($step['text']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================ RESULT STATS ============================ -->
<?php if (!empty($service['stats'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="result-stats">
            <?php foreach ($service['stats'] as $si => $stat): ?>
            <div class="result-stat" data-aos="fade-up" data-aos-delay="<?= $si * 90 ?>">
                <strong><span data-countup="<?= esc($stat['value']) ?>" data-decimals="<?= isset($stat['decimals']) ? (int) $stat['decimals'] : 0 ?>">0</span><span class="suffix"><?= esc($stat['suffix']) ?></span></strong>
                <span><?= esc($stat['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================ WHO IT'S FOR ============================ -->
<?php if (!empty($service['who'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Fit</p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)">Who it&rsquo;s for.</h2>
        </div>
        <div class="who-grid">
            <?php foreach ($service['who'] as $wi => $who): ?>
            <article class="who-card" data-aos="fade-up" data-aos-delay="<?= $wi * 90 ?>">
                <?= icon($who['icon'], 24) ?>
                <h3><?= esc($who['title']) ?></h3>
                <p><?= esc($who['text']) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =============================== TOOLS ================================ -->
<?php if (!empty($service['tools'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Tooling</p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)">The stack behind the strategy.</h2>
        </div>
        <div class="tools-grid">
            <?php foreach ($service['tools'] as $ti => $tool): ?>
            <div class="tool-tile" data-aos="zoom-in" data-aos-delay="<?= $ti * 60 ?>"><?= esc($tool) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================= TIMELINE =============================== -->
<?php if (!empty($service['timeline'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow"><?= esc(isset($service['timelineEyebrow']) ? $service['timelineEyebrow'] : 'Expectations') ?></p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)"><?= esc(isset($service['timelineTitle']) ? $service['timelineTitle'] : 'What happens, and when.') ?></h2>
            <?php if (!empty($service['timelineLead'])): ?><p class="section-lead"><?= esc($service['timelineLead']) ?></p><?php endif; ?>
        </div>
        <div class="timeline">
            <?php foreach ($service['timeline'] as $ti => $milestone): ?>
            <div class="timeline-item" data-aos="fade-up" data-aos-delay="<?= $ti * 90 ?>">
                <span class="when"><?= esc($milestone['when']) ?></span>
                <h4><?= esc($milestone['title']) ?></h4>
                <p><?= esc($milestone['text']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =============================== CHART ================================ -->
<?php if (!empty($service['chart'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="chart-card" data-aos="fade-up">
            <p class="eyebrow"><?= esc(isset($service['chartTitle']) ? $service['chartTitle'] : 'Typical growth curve') ?></p>
            <div class="chart-wrap">
                <canvas data-chart="<?= esc(json_encode($service['chart'])) ?>" aria-label="Growth chart" role="img"></canvas>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =========================== METRIC CARDS ============================= -->
<?php if (!empty($service['metrics'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="metric-cards">
            <?php foreach ($service['metrics'] as $mi => $metric): ?>
            <div class="metric-card" data-aos="fade-up" data-aos-delay="<?= $mi * 90 ?>">
                <strong><?= esc($metric['value']) ?></strong>
                <span><?= esc($metric['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== DEEPER ================================ -->
<?php if (!empty($service['deeper'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow"><?= esc(isset($service['deeperEyebrow']) ? $service['deeperEyebrow'] : 'Go deeper') ?></p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)"><?= esc(isset($service['deeperTitle']) ? $service['deeperTitle'] : 'From the TPT Growth Library.') ?></h2>
        </div>
        <div class="deeper-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px">
            <?php foreach ($service['deeper'] as $di => $deep): ?>
            <a class="chart-card" href="<?= esc($deep['url']) ?>" data-aos="fade-up" data-aos-delay="<?= $di * 80 ?>" style="display:block;text-decoration:none;transition:transform .25s ease,border-color .25s ease" onfocus="this.style.transform='translateY(-4px)'" onblur="this.style.transform=''">
                <p class="eyebrow" style="margin-bottom:10px"><?= esc($deep['label']) ?></p>
                <strong style="display:block;font-size:1.05rem;color:var(--text);margin-bottom:8px"><?= esc($deep['title']) ?></strong>
                <span class="text-muted" style="font-size:.88rem"><?= esc($deep['note']) ?> →</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================ DELIVERABLES ============================ -->
<?php if (!empty($service['deliverables'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="split">
            <div class="copy" data-aos="fade-up">
                <p class="eyebrow"><?= esc(isset($service['deliverablesEyebrow']) ? $service['deliverablesEyebrow'] : 'Scope of engagement') ?></p>
                <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)"><?= esc(isset($service['deliverablesTitle']) ? $service['deliverablesTitle'] : 'Every deliverable, named upfront.') ?></h2>
            </div>
            <div class="copy" data-aos="fade-up" data-aos-delay="100">
                <ul style="display:grid;gap:12px;list-style:none;padding:0">
                    <?php foreach ($service['deliverables'] as $dl): ?>
                    <li style="display:flex;gap:10px;align-items:flex-start;color:var(--muted);font-size:.95rem"><span style="color:#34d399;flex:none;margin-top:2px"><?= icon('check', 16) ?></span><span><?= esc($dl) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ========================== PORTFOLIO MINI ============================ -->
<?php if (!empty($service['gallery'])):
    $galleryItems = array_slice(getPortfolioItems($svcName), 0, 3);
    if (!$galleryItems) { $galleryItems = array_slice(getPortfolioItems(''), 0, 3); }
    if ($galleryItems): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow"><?= esc(isset($service['galleryEyebrow']) ? $service['galleryEyebrow'] : 'Proof') ?></p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)"><?= esc(isset($service['galleryTitle']) ? $service['galleryTitle'] : 'Recent ' . $svcName . ' work.') ?></h2>
        </div>
        <div class="work-grid" style="grid-template-columns:repeat(3,1fr)">
            <?php foreach ($galleryItems as $gi => $gItem): ?>
            <a class="work-card" href="<?= url('portfolio/' . $gItem['slug']) ?>" data-aos="fade-up" data-aos-delay="<?= $gi * 90 ?>">
                <div class="work-media"><img src="<?= asset($gItem['thumbnail'] !== '' ? $gItem['thumbnail'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($gItem['client_name']) ?>" loading="lazy"></div>
                <div class="work-body">
                    <span class="work-cat"><?= esc($gItem['service_category']) ?></span>
                    <h3 class="work-title"><?= esc($gItem['client_name']) ?></h3>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; endif; ?>

<!-- ========================= SERVICE TESTIMONIAL ======================== -->
<?php
$serviceTestimonial = null;
if (!empty($service['testimonial'])) {
    foreach (getActiveTestimonials() as $cand) {
        if (stripos((string) $cand['service'], $svcName) !== false) { $serviceTestimonial = $cand; break; }
    }
    if (!$serviceTestimonial) {
        $all = getActiveTestimonials();
        $serviceTestimonial = $all ? $all[0] : null;
    }
}
if ($serviceTestimonial): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <figure class="testimonial-band" data-aos="fade-up">
            <div class="testimonial-stars" aria-label="<?= (int) $serviceTestimonial['rating'] ?> out of 5 stars"><?= str_repeat('★', max(1, min(5, (int) $serviceTestimonial['rating']))) ?></div>
            <blockquote class="testimonial-quote" style="margin-top:18px">&ldquo;<?= esc($serviceTestimonial['content']) ?>&rdquo;</blockquote>
            <figcaption class="testimonial-who" style="margin-top:22px">
                <span class="avatar-fallback"><?= esc(strtoupper(substr($serviceTestimonial['name'], 0, 1))) ?></span>
                <span>
                    <strong><?= esc($serviceTestimonial['name']) ?></strong>
                    <span><?= esc($serviceTestimonial['role']) ?> · <?= esc($serviceTestimonial['company']) ?></span>
                </span>
            </figcaption>
        </figure>
    </div>
</section>
<?php endif; ?>

<!-- ================================ FAQ ================================= -->
<?php if (!empty($service['faq'])): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head center" data-aos="fade-up">
            <p class="eyebrow"><?= esc(isset($service['faqEyebrow']) ? $service['faqEyebrow'] : 'FAQ') ?></p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)"><?= esc(isset($service['faqTitle']) ? $service['faqTitle'] : 'Questions, answered honestly.') ?></h2>
        </div>
        <div class="faq" data-accordion="single">
            <?php foreach ($service['faq'] as $fi => $faqItem): ?>
            <div class="acc-item">
                <button class="acc-head" type="button">
                    <span class="acc-title"><?= esc($faqItem['q']) ?></span>
                    <span class="acc-icon"><?= icon('plus', 14) ?></span>
                </button>
                <div class="acc-body">
                    <p class="acc-copy"><?= esc($faqItem['a']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================ PAIRS WELL WITH ========================= -->
<?php
$relatedServices = array();
if (!empty($service['related'])) {
    foreach ($service['related'] as $relKey) {
        $relSvc = pieServiceByKey($relKey);
        if ($relSvc) { $relatedServices[] = $relSvc; }
    }
}
if ($relatedServices): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Connected by design</p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)">Pairs well with.</h2>
        </div>
        <div class="feature-grid">
            <?php foreach ($relatedServices as $ri => $relSvc): ?>
            <a class="feature-card" href="<?= url('services/' . $relSvc['key']) ?>" data-aos="fade-up" data-aos-delay="<?= $ri * 90 ?>" style="text-decoration:none;color:inherit">
                <?= icon($relSvc['icon'], 24) ?>
                <h3><?= esc($relSvc['name']) ?></h3>
                <p><?= esc($relSvc['tagline']) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== FINAL CTA ============================= -->
<section class="final-cta">
    <div class="container">
        <h2 data-aos="fade-up"><?= esc(isset($service['cta']['title']) ? $service['cta']['title'] : 'Ready when you are.') ?></h2>
        <?php if (!empty($service['cta']['text'])): ?><p data-aos="fade-up" data-aos-delay="80"><?= esc($service['cta']['text']) ?></p><?php endif; ?>
        <div class="hero-ctas" data-aos="fade-up" data-aos-delay="140">
            <a href="<?= url('contact') ?>?service=<?= rawurlencode($svcName) ?>" class="btn btn-primary btn-lg btn-magnetic"><?= esc(isset($service['cta']['button']) ? $service['cta']['button'] : 'Start a Project') ?> <?= icon('arrow-r', 18) ?></a>
            <?php $ctaPhone = preg_replace('/[^0-9+]/', '', (string) getSetting('site_phone', '+1 (213) 257 8242')); ?>
            <a href="tel:<?= esc($ctaPhone) ?>" class="btn btn-ghost btn-lg btn-magnetic"><?= icon('phone', 17) ?> <?= esc(getSetting('site_phone', '+1 (213) 257 8242')) ?></a>
        </div>
        <p class="text-muted" data-aos="fade-up" data-aos-delay="200" style="margin-top:20px;font-size:.88rem">Still comparing options? Ask <strong style="color:var(--text)">Alia</strong>, our growth assistant — bottom-right corner. She&rsquo;ll point you to the right service or blueprint, and hand you to a human when she doesn&rsquo;t know.</p>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
