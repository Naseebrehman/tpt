<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = getSetting('meta_title', 'Clicks Are Easy. Growth Is Engineered.');
$metaDesc  = getSetting('meta_description', 'The Pie Technologies is a growth agency across five disciplines — GROW, GET FOUND, BUILD, CREATE and MEASURE. Meta Ads, Google Ads, SEO, Local SEO, social, web, apps, design, AI and analytics — one system, one owner.');
$activeNav = 'home';
$bodyClass = 'page-home';
$pageLibs  = array('typed' => true, 'particles' => true, 'swiper' => true);

$services     = pieServices();
$disciplines  = pieDisciplines();
$industries   = pieIndustries();
$homeFaq      = pieHomeFaq();
$testimonials = getActiveTestimonials();
$posts        = getRecentPosts(3);

$avgRating = 0;
if ($testimonials) {
    $sum = 0;
    foreach ($testimonials as $t) { $sum += (int) $t['rating']; }
    $avgRating = round($sum / count($testimonials), 1);
}

$sameAs = array();
foreach (array('instagram_url', 'facebook_url', 'linkedin_url', 'tiktok_url', 'twitter_url', 'youtube_url') as $socialKey) {
    $socialVal = getSetting($socialKey);
    if ($socialVal !== '') { $sameAs[] = $socialVal; }
}
$jsonLd = json_encode(array(
    '@context'    => 'https://schema.org',
    '@type'       => 'MarketingAgency',
    'name'        => getSetting('site_name', SITE_NAME),
    'url'         => SITE_URL,
    'description' => 'Growth agency across five disciplines: GROW, GET FOUND, BUILD, CREATE and MEASURE — paid media, search, websites, apps, design, AI and analytics run as one system.',
    'telephone'   => getSetting('site_phone', '+1 (213) 257 8242'),
    'email'       => getSetting('site_email', 'info@thepietechnologies.com'),
    'address'     => array('@type' => 'PostalAddress', 'streetAddress' => getSetting('site_address', 'Collingswood, NJ, USA')),
    'sameAs'      => $sameAs,
), JSON_UNESCAPED_SLASHES);

/* Real clients, named in the testimonials and case studies we publish. */
$clients = array('Alpha Global', 'Pay Stream', 'Nicks Roofing');

/* This page offers the short Start-a-project popup. */
$contactModalEnabled = true;

require_once __DIR__ . '/includes/header.php';
?>

<!-- ================================ HERO ================================ -->
<section class="hero">
    <div id="particles-js" aria-hidden="true"></div>
    <div class="container hero-inner">
        <span class="hero-eyebrow"><?= icon('sparkle', 14) ?> Five Disciplines · One Growth System</span>
        <h1 class="display hero-title">
            <span class="line">Clicks are easy.</span>
            <span class="typed-line"><span id="typed-text" data-strings="Growth is engineered.|Systems beat tactics.|One owner. One scoreboard.|Engineered to compound."></span><span class="typed-caret" aria-hidden="true"></span></span>
        </h1>
        </h1>
        <p class="hero-sub">Most agencies sell tactics. We build the system under them — strategy, creative, media, websites and data working as one engine, with a single team accountable for the only number that matters: yours.</p>
        <div class="hero-ctas">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic" data-contact-modal>Start here <?= icon('arrow-r', 18) ?></a>
            <a href="<?= url('portfolio') ?>" class="btn btn-ghost btn-lg btn-magnetic">See the work</a>
        </div>

        <div class="hero-loop" aria-label="The TPT growth loop">
            <?php foreach (array('Discover', 'Build', 'Launch', 'Measure', 'Grow') as $li => $loopStep): ?>
            <span class="loop-step"><i class="mono"><?= str_pad((string) ($li + 1), 2, '0', STR_PAD_LEFT) ?></i><?= esc($loopStep) ?></span>
            <?php if ($li < 4): ?><span class="loop-arrow" aria-hidden="true"><?= icon('arrow-r', 14) ?></span><?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="hero-visual" data-aos="fade-up" data-aos-delay="150">
            <img src="<?= asset('assets/images/hero-studio.jpg') ?>" alt="The Pie Technologies studio at night, screens glowing with campaign dashboards" width="1376" height="768" fetchpriority="high">
            <div class="hero-float-stat">
                <strong>11 services</strong>
                <span>five disciplines,<br>one accountable team</span>
            </div>
        </div>
    </div>
    <div class="scroll-indicator" aria-hidden="true">
        <span>Scroll</span>
        <?= icon('arrow-d', 18) ?>
    </div>
</section>

<!-- ============================== MARQUEE =============================== -->
<section class="marquee-section" aria-label="Clients">
    <p class="marquee-label">Working with businesses that measure.</p>
    <div class="marquee">
        <div class="marquee-track">
            <?php for ($loop = 0; $loop < 4; $loop++): foreach ($clients as $client): ?>
            <span class="marquee-item"><i class="dot"></i><?= esc($client) ?></span>
            <?php endforeach; endfor; ?>
        </div>
    </div>
    <div class="marquee rev">
        <div class="marquee-track">
            <?php for ($loop = 0; $loop < 4; $loop++): foreach (array_reverse($clients) as $client): ?>
            <span class="marquee-item"><i class="dot"></i><?= esc($client) ?></span>
            <?php endforeach; endfor; ?>
        </div>
    </div>
</section>

<!-- ========================= PROBLEM / THE WAY ========================== -->
<section class="section" aria-label="The problem and the way">
    <div class="container">
        <div class="split">
            <div class="copy" data-aos="fade-up">
                <p class="eyebrow">The problem</p>
                <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.9rem)">Five vendors.<br>Zero accountability.</h2>
                <p>The ad freelancer blames the website. The web guy blames the leads. The social team blames the algorithm. Everyone reports “success” — and nobody owns the number that actually decides whether the business grows.</p>
            </div>
            <div class="copy" data-aos="fade-up" data-aos-delay="120">
                <p class="eyebrow">The way</p>
                <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.9rem)">One system.<br>One owner.</h2>
                <p>TPT runs every discipline under one roof and one scoreboard. Strategy, creative, media, websites and data connect by design — so when something underperforms, there’s one team accountable for fixing it, not five teams accountable for explaining it.</p>
            </div>
        </div>
    </div>
</section>

<!-- ========================= HOW WE WORK ================================ -->
<section class="section how-section" aria-labelledby="howHeading">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">How we work</p>
            <h2 class="section-title" id="howHeading">One system, run in six moves.</h2>
            <p class="section-lead">Every engagement — a single service or the full machine — runs on the same operating rhythm, with one accountable team and a written plan you can hold us to.</p>
        </div>

        <div class="stepper stepper-6" role="list">
            <div class="step" role="listitem" data-aos="fade-up">
                <span class="dot mono">01</span>
                <div>
                    <h4>Discover</h4>
                    <p>We learn the offer, the market and the numbers — accounts, tracking, CRM and the leads that actually closed.</p>
                </div>
            </div>
            <div class="step" role="listitem" data-aos="fade-up" data-aos-delay="70">
                <span class="dot mono">02</span>
                <div>
                    <h4>Strategize</h4>
                    <p>Which disciplines apply, what each will be measured on, what we would fix first — written down before anything is built.</p>
                </div>
            </div>
            <div class="step" role="listitem" data-aos="fade-up" data-aos-delay="140">
                <span class="dot mono">03</span>
                <div>
                    <h4>Build</h4>
                    <p>Campaign structure, creative, landing pages, tracking and automation, built to be tested rather than admired.</p>
                </div>
            </div>
            <div class="step" role="listitem" data-aos="fade-up" data-aos-delay="210">
                <span class="dot mono">04</span>
                <div>
                    <h4>Launch</h4>
                    <p>Controlled starts, verified conversion tracking and a speed-to-lead path wired to a real human on your side.</p>
                </div>
            </div>
            <div class="step" role="listitem" data-aos="fade-up" data-aos-delay="280">
                <span class="dot mono">05</span>
                <div>
                    <h4>Optimize</h4>
                    <p>Weekly testing with written kill rules — winners scale, losers retire early, and every change has a hypothesis.</p>
                </div>
            </div>
            <div class="step" role="listitem" data-aos="fade-up" data-aos-delay="350">
                <span class="dot mono">06</span>
                <div>
                    <h4>Scale</h4>
                    <p>Budget follows evidence: vertical, then horizontal — reported against cost per qualified lead and revenue, monthly.</p>
                </div>
            </div>
        </div>

        <div class="how-foot" data-aos="fade-up">
            <p><strong>What you get every month:</strong> one point of contact, a shared project board, weekly updates and a performance review with real numbers — across all five disciplines.</p>
            <a class="btn btn-ghost btn-magnetic" href="<?= url('contact') ?>" data-contact-modal>Plan the first 90 days <?= icon('arrow-r', 18) ?></a>
        </div>
    </div>
</section>

<!-- ======================= SERVICES ACCORDION =========================== -->
<section class="section">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Services</p>
            <h2 class="section-title">Eleven services.<br>One connected system.</h2>
        </div>

        <div class="services-acc" data-accordion="single">
            <?php foreach ($services as $i => $svc): ?>
            <div class="acc-item">
                <button class="acc-head" type="button" aria-expanded="false">
                    <span class="acc-num mono"><?= esc($svc['num']) ?></span>
                    <span class="acc-title"><?= esc($svc['name']) ?><?= !empty($svc['core']) ? '<span class="dd-core">Core</span>' : '' ?></span>
                    <span class="acc-tag"><?= esc($svc['tagline']) ?></span>
                    <span class="acc-icon"><?= icon('plus', 16) ?></span>
                </button>
                <div class="acc-body" inert>
                    <div class="acc-inner">
                        <div class="acc-copy">
                            <p><?= esc($svc['desc']) ?></p>
                            <div class="acc-tags">
                                <?php foreach ($svc['tags'] as $tag): ?><span class="chip"><?= esc($tag) ?></span><?php endforeach; ?>
                            </div>
                            <a class="link-arrow" href="<?= url('services/' . $svc['key']) ?>">Learn More <?= icon('arrow-r', 16) ?></a>
                        </div>
                        <div class="acc-media">
                            <img src="<?= asset($svc['image']) ?>" alt="<?= esc($svc['name']) ?> at The Pie Technologies" loading="lazy">
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <p class="acc-foot" data-aos="fade-up">
            <span class="plus">+</span> Not sure where to start?
            <a class="link-arrow" href="<?= url('contact') ?>">Tell us the goal — we&rsquo;ll tell you what you need. <?= icon('arrow-r', 16) ?></a>
        </p>
    </div>
</section>

<!-- ====================== THE SYSTEM (pinned scroll) ==================== -->
<section class="system" aria-label="The five disciplines">
    <div class="system-sticky">
        <aside class="system-side">
            <div>
                <p class="eyebrow">The System</p>
                <h2>Explore the disciplines.</h2>
            </div>
            <ul class="system-progress">
                <?php foreach ($disciplines as $di => $disc): ?>
                <li<?= $di === 0 ? ' class="current"' : '' ?>><span class="mono"><?= esc($disc['num']) ?></span><span class="bar"><i></i></span></li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <div class="system-stage">
            <?php
            $panelDirs = array('left', 'bottom', 'right', 'top', 'scale');
            foreach ($disciplines as $di => $disc):
                $discServices = pieServicesByDiscipline($disc['key']);
            ?>
            <article class="system-panel<?= $di === 0 ? ' is-active' : '' ?>" data-dir="<?= esc($panelDirs[$di % 5]) ?>">
                <span class="system-num"><?= esc($disc['num']) ?></span>
                <h3><?= esc($disc['name']) ?></h3>
                <p class="desc"><?= esc($disc['desc']) ?></p>
                <div class="system-tags">
                    <?php foreach ($discServices as $discSvc): ?>
                    <a class="chip" href="<?= url('services/' . $discSvc['key']) ?>" style="text-decoration:none"><?= icon($discSvc['icon'], 13) ?> <?= esc($discSvc['name']) ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="system-metrics">
                    <div><strong><?= count($discServices) ?></strong><span>Services in this discipline</span></div>
                    <div><strong><?= esc($disc['num']) ?></strong><span><?= esc($disc['label']) ?></span></div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="system-spacer" aria-hidden="true">
        <?php foreach ($disciplines as $di => $disc): ?>
        <div class="system-trigger" data-index="<?= $di ?>"></div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============================== INDUSTRIES ============================ -->
<section class="section industries-section" aria-labelledby="industriesHeading">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Who we grow</p>
            <h2 class="section-title" id="industriesHeading">Built for businesses<br>that live on leads.</h2>
            <p class="section-lead">We learn your trade before we touch your funnel. Explore how we approach your industry.</p>
        </div>
        <div class="industry-explorer" data-industry-explorer>
            <div class="industry-tabs" role="tablist" aria-label="Choose your industry" hidden>
                <?php foreach ($industries as $ii => $ind): ?>
                <button class="industry-tab" type="button" id="industry-tab-<?= $ii ?>" role="tab" aria-selected="<?= $ii === 0 ? 'true' : 'false' ?>" aria-controls="industry-panel-<?= $ii ?>" tabindex="<?= $ii === 0 ? '0' : '-1' ?>">
                    <span class="industry-tab-code" aria-hidden="true"><?= esc($ind['abbr']) ?></span><span><?= esc($ind['name']) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="industry-panels">
                <?php foreach ($industries as $ii => $ind): ?>
                <article class="industry-detail" id="industry-panel-<?= $ii ?>" aria-labelledby="industry-title-<?= $ii ?>">
                    <span class="industry-detail-code" aria-hidden="true"><?= esc($ind['abbr']) ?></span>
                    <div class="industry-detail-copy">
                        <p class="eyebrow">Your industry. A connected approach.</p>
                        <h3 id="industry-title-<?= $ii ?>"><?= esc($ind['name']) ?></h3>
                        <p><?= esc($ind['copy']) ?></p>
                        <div class="industry-services">
                            <?php foreach ($ind['services'] as $indSvcKey): $indSvc = pieServiceByKey($indSvcKey); if ($indSvc): ?>
                            <a href="<?= url('services/' . $indSvc['key']) ?>"><?= esc($indSvc['name']) ?></a>
                            <?php endif; endforeach; ?>
                        </div>
                        <a class="link-arrow industry-discuss" href="<?= url('contact') ?>?industry=<?= rawurlencode($ind['name']) ?>">Discuss your industry <?= icon('arrow-r', 18) ?></a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================ FOUNDER CTA ============================= -->
<section class="section founder-section" aria-label="A note from our founder">
    <div class="container">
        <div class="founder-card founder-compact" data-aos="fade-up">
            <img class="founder-avatar" src="<?= asset('assets/images/founder-avatar.jpg') ?>" width="80" height="80" alt="<?= esc(getSetting('founder_name', 'Ali Raza')) ?>, founder" loading="lazy">
            <div class="founder-note">
                <p class="eyebrow">A note from our founder</p>
                <h2>Hey, I’m <?= esc(getSetting('founder_name', 'Ali Raza')) ?>.</h2>
                <p>We built TPT to connect strategy, creative and technology under one accountable team. Let’s talk about your growth.</p>
                <span class="founder-sig">Founder · The Pie Technologies</span>
            </div>
            <a href="<?= url('contact') ?>" class="btn btn-primary founder-book">Book a Free Call <?= icon('arrow-r', 18) ?></a>
        </div>
    </div>
</section>

<!-- ============================ TESTIMONIALS ============================ -->
<?php if ($testimonials): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <span class="google-badge"><span class="g-stars">★★★★★</span> <?= esc(number_format($avgRating, 1)) ?> average · <?= count($testimonials) ?> client reviews</span>
            <h2 class="section-title">Don&rsquo;t take our word for it.</h2>
        </div>

        <div class="testimonial-shell" data-aos="fade-up">
            <div class="swiper testimonial-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($testimonials as $t): ?>
                    <div class="swiper-slide">
                        <figure class="testimonial-card">
                            <div class="testimonial-stars" aria-label="<?= (int) $t['rating'] ?> out of 5 stars"><?= str_repeat('★', max(1, min(5, (int) $t['rating']))) ?></div>
                            <blockquote class="testimonial-quote">&ldquo;<?= esc($t['content']) ?>&rdquo;</blockquote>
                            <figcaption class="testimonial-who">
                                <?php if (!empty($t['photo'])): ?>
                                <img src="<?= asset($t['photo']) ?>" alt="<?= esc($t['name']) ?>" loading="lazy">
                                <?php else: ?>
                                <span class="avatar-fallback"><?= esc(strtoupper(substr($t['name'], 0, 1))) ?></span>
                                <?php endif; ?>
                                <span>
                                    <strong><?= esc($t['name']) ?></strong>
                                    <span><?= esc($t['role']) ?> · <?= esc($t['company']) ?><?php if (!empty($t['service'])): ?> · <?= esc($t['service']) ?><?php endif; ?></span>
                                </span>
                            </figcaption>
                        </figure>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if (count($testimonials) > 1): ?>
            <div class="swiper-pagination-custom" role="tablist" aria-label="Testimonials">
                <?php foreach ($testimonials as $ti => $t): ?>
                <button type="button" class="<?= $ti === 0 ? 'active' : '' ?>" aria-label="Show testimonial <?= $ti + 1 ?>"></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =============================== WHY US =============================== -->
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Why Us</p>
            <h2 class="section-title">The difference is in how we&rsquo;re built.</h2>
        </div>
        <div class="why-grid">
            <article class="why-card" data-aos="fade-up">
                <img src="<?= asset('assets/images/why-data.jpg') ?>" alt="Analytics dashboards glowing in the dark" loading="lazy">
                <div class="why-copy">
                    <?= icon('chart', 26) ?>
                    <h3>Data-Driven</h3>
                    <p>Every decision backed by numbers. Tracking rebuilt around real outcomes, and every dollar of spend attributed — no platform dashboards taken on faith.</p>
                </div>
            </article>
            <article class="why-card" data-aos="fade-up" data-aos-delay="100">
                <img src="<?= asset('assets/images/why-team.jpg') ?>" alt="The Pie Technologies team collaborating" loading="lazy">
                <div class="why-copy">
                    <?= icon('layers', 26) ?>
                    <h3>Full-System</h3>
                    <p>Meta Ads to web development to analytics, under one team. No outsourcing, no telephone game, no vendor pointing at another vendor.</p>
                </div>
            </article>
            <article class="why-card" data-aos="fade-up" data-aos-delay="200">
                <img src="<?= asset('assets/images/hero-studio.jpg') ?>" alt="The Pie Technologies studio" loading="lazy">
                <div class="why-copy">
                    <?= icon('target', 26) ?>
                    <h3>Honest by Default</h3>
                    <p>No invented statistics, no guaranteed rankings, no fake urgency. If a channel isn&rsquo;t right for you yet, we&rsquo;ll say so — and tell you what is.</p>
                </div>
            </article>
        </div>
    </div>
</section>

<!-- ========================== GROWTH LIBRARY ============================ -->
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="library-band" data-aos="fade-up">
            <div class="library-copy">
                <p class="eyebrow">Growth Library</p>
                <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)">The frameworks we run — free.</h2>
                <p>Twelve blueprints, playbooks and checklists: the actual systems behind our campaigns, sites and SEO. No gatekeeping, no email drip — just the thinking, written down.</p>
                <div class="library-links">
                    <a href="<?= url('resources/lead-generation-blueprint') ?>"><?= icon('download', 15) ?> Lead Generation Blueprint</a>
                    <a href="<?= url('resources/seo-blueprint') ?>"><?= icon('search', 15) ?> The SEO Blueprint</a>
                    <a href="<?= url('resources/local-seo-blueprint') ?>"><?= icon('pin', 15) ?> Local SEO Blueprint</a>
                </div>
                <a class="link-arrow" href="<?= url('resources') ?>" style="margin-top:18px;display:inline-flex">Browse all 12 resources <?= icon('arrow-r', 16) ?></a>
            </div>
            <div class="library-visual" aria-hidden="true">
                <div class="chart-card">
                    <p class="eyebrow">Inside the library</p>
                    <ul style="display:grid;gap:10px;list-style:none;padding:0;margin:0">
                        <li><?= icon('check', 15) ?> Meta Ads Lead Generation Blueprint</li>
                        <li><?= icon('check', 15) ?> Google Ads Blueprint</li>
                        <li><?= icon('check', 15) ?> Website Conversion Blueprint</li>
                        <li><?= icon('check', 15) ?> AI Search Visibility Blueprint</li>
                        <li><?= icon('check', 15) ?> Website Launch Checklist</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================ BLOG PREVIEW ============================ -->
<?php if ($posts): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Journal</p>
            <h2 class="section-title">Latest from the TPT Strategy Team.</h2>
        </div>
        <div class="blog-grid">
            <?php foreach ($posts as $i => $post): ?>
            <article class="blog-card" data-aos="fade-up" data-aos-delay="<?= $i * 90 ?>">
                <div class="blog-card-media">
                    <?php if (!empty($post['category_name'])): ?><span class="blog-cat"><?= esc($post['category_name']) ?></span><?php endif; ?>
                    <img src="<?= asset($post['featured_image'] !== '' ? $post['featured_image'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($post['title']) ?>" loading="lazy">
                </div>
                <div class="blog-card-body">
                    <div class="blog-meta">
                        <span><?= esc(formatDate($post['published_at'])) ?></span>
                        <span><?= (int) $post['reading_time'] ?> min read</span>
                    </div>
                    <h3><a href="<?= url('blog/' . $post['slug']) ?>"><?= esc($post['title']) ?></a></h3>
                    <p class="blog-excerpt"><?= esc($post['excerpt']) ?></p>
                    <a class="link-arrow" href="<?= url('blog/' . $post['slug']) ?>">Read Article <?= icon('arrow-r', 16) ?></a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================ HOME FAQ ================================ -->
<section class="section">
    <div class="container">
        <div class="section-head center" data-aos="fade-up">
            <p class="eyebrow">FAQ</p>
            <h2 class="section-title">Before you ask — answered.</h2>
        </div>
        <div class="faq" data-accordion="single">
            <?php foreach ($homeFaq as $fi => $faqItem): ?>
            <div class="acc-item">
                <button class="acc-head" type="button" aria-expanded="false">
                    <span class="acc-title"><?= esc($faqItem['q']) ?></span>
                    <span class="acc-icon"><?= icon('plus', 16) ?></span>
                </button>
                <div class="acc-body" inert>
                    <p class="acc-copy"><?= esc($faqItem['a']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-muted" data-aos="fade-up" style="text-align:center;margin-top:22px;font-size:.92rem">Still deciding? Ask <strong style="color:var(--text)">Alia</strong>, our growth assistant — bottom-right corner. She answers from real TPT content and hands you to a human when she doesn&rsquo;t know.</p>
    </div>
</section>

<!-- ============================= FINAL CTA ============================== -->
<section class="final-cta">
    <div class="container">
        <h2 data-aos="fade-up">Start here. Tell us the goal — we&rsquo;ll build the system.</h2>
        <p data-aos="fade-up" data-aos-delay="80">A free strategy call with a clear plan for your next 90 days: which disciplines apply, what they&rsquo;d cost, and what we&rsquo;d measure. No pressure, no jargon — and an honest &ldquo;not yet&rdquo; when that&rsquo;s the answer.</p>
        <div class="hero-ctas" data-aos="fade-up" data-aos-delay="140">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic" data-contact-modal>Start a Project <?= icon('arrow-r', 18) ?></a>
            <a href="<?= url('services') ?>" class="btn btn-ghost btn-lg btn-magnetic">Browse Services</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
