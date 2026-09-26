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
$portfolio    = array_slice(getPortfolioItems(''), 0, 4);
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
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic">Start here <?= icon('arrow-r', 18) ?></a>
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

<!-- =============================== STATS ================================ -->
<section class="section" style="padding-top:0" aria-label="TPT by the numbers">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card" data-aos="fade-up">
                <span class="stat-index mono">01</span>
                <div class="stat-value"><span data-countup="5">0</span></div>
                <p class="stat-label">Disciplines — GROW, GET FOUND, BUILD, CREATE and MEASURE.</p>
            </div>
            <div class="stat-card" data-aos="fade-up" data-aos-delay="80">
                <span class="stat-index mono">02</span>
                <div class="stat-value"><span data-countup="11">0</span></div>
                <p class="stat-label">Services that plug into one system instead of eleven silos.</p>
            </div>
            <div class="stat-card" data-aos="fade-up" data-aos-delay="160">
                <span class="stat-index mono">03</span>
                <div class="stat-value"><span data-countup="12">0</span></div>
                <p class="stat-label">Free playbooks in the Growth Library — the frameworks we run.</p>
            </div>
            <div class="stat-card" data-aos="fade-up" data-aos-delay="240">
                <span class="stat-index mono">04</span>
                <div class="stat-value"><span data-countup="2">0</span></div>
                <p class="stat-label">Locations — Collingswood, NJ and Punjab, Pakistan. One team.</p>
            </div>
        </div>
    </div>
</section>

<!-- ========================== WORK PREVIEW ============================== -->
<?php if ($portfolio): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Selected Work</p>
            <h2 class="section-title">Systems in the field.</h2>
            <p class="section-lead">Case studies told the honest way: the challenge, the strategy, the execution — without invented numbers.</p>
        </div>
        <div class="work-grid">
            <?php foreach ($portfolio as $i => $item): $stats = jsonCol($item['stats_json']); $firstStat = $stats ? array_slice($stats, 0, 1) : array(); ?>
            <a class="work-card" href="<?= url('portfolio/' . $item['slug']) ?>" data-aos="fade-up" data-aos-delay="<?= $i * 90 ?>">
                <div class="work-media">
                    <img src="<?= asset($item['thumbnail'] !== '' ? $item['thumbnail'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($item['client_name']) ?> case study" loading="lazy">
                </div>
                <?php if ($firstStat): $fs = reset($firstStat); ?>
                <span class="work-stat"><?= esc(is_array($fs) ? implode(' ', $fs) : $fs) ?></span>
                <?php endif; ?>
                <div class="work-body">
                    <span class="work-cat"><?= esc($item['service_category']) ?></span>
                    <h3 class="work-title"><?= esc($item['client_name']) ?></h3>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="work-more">
            <a class="link-arrow" href="<?= url('portfolio') ?>">View All Work <?= icon('arrow-r', 16) ?></a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ======================= SERVICES ACCORDION =========================== -->
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Services</p>
            <h2 class="section-title">Eleven services.<br>One connected system.</h2>
        </div>

        <div class="services-acc" data-accordion="single">
            <?php foreach ($services as $i => $svc): ?>
            <div class="acc-item<?= $i === 0 ? ' open' : '' ?>">
                <button class="acc-head" type="button">
                    <span class="acc-num mono"><?= esc($svc['num']) ?></span>
                    <span class="acc-title"><?= esc($svc['name']) ?><?= !empty($svc['core']) ? '<span class="dd-core">Core</span>' : '' ?></span>
                    <span class="acc-tag"><?= esc($svc['tagline']) ?></span>
                    <span class="acc-icon"><?= icon('plus', 16) ?></span>
                </button>
                <div class="acc-body">
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
<section class="section" aria-label="Industries we know">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Industries</p>
            <h2 class="section-title">We learn your trade before we touch your funnel.</h2>
            <p class="section-lead">Generic marketing wastes budget on the wrong message. These are the industries where we know the sales cycle, the season and the objections.</p>
        </div>
        <div class="industry-grid">
            <?php foreach ($industries as $ii => $ind): ?>
            <article class="industry-card" data-aos="fade-up" data-aos-delay="<?= ($ii % 4) * 80 ?>">
                <span class="industry-abbr mono"><?= esc($ind['abbr']) ?></span>
                <h3><?= esc($ind['name']) ?></h3>
                <p><?= esc($ind['copy']) ?></p>
                <div class="industry-services">
                    <?php foreach ($ind['services'] as $indSvcKey): $indSvc = pieServiceByKey($indSvcKey); if ($indSvc): ?>
                    <a href="<?= url('services/' . $indSvc['key']) ?>"><?= esc($indSvc['name']) ?></a>
                    <?php endif; endforeach; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================ FOUNDER CTA ============================= -->
<section class="section">
    <div class="container">
        <div class="founder-card" data-aos="fade-up">
            <div class="founder-media">
                <img src="<?= asset('assets/images/founder.jpg') ?>" alt="<?= esc(getSetting('founder_name', 'The founder')) ?>, founder of The Pie Technologies" loading="lazy">
            </div>
            <div class="founder-copy">
                <span class="wave" aria-hidden="true">👋</span>
                <h2>Hey, I&rsquo;m <?= esc(getSetting('founder_name', 'the founder')) ?> — Founder of The Pie Technologies.</h2>
                <p>I started this agency after watching too many good businesses burn budget on vendors who reported impressions instead of outcomes. We built the opposite: five disciplines under one roof, connected by design, accountable to one number — yours. Let&rsquo;s talk about your growth.</p>
                <p class="founder-sig">— <?= esc(getSetting('founder_name', 'The Founder')) ?></p>
                <div>
                    <a href="<?= url('contact') ?>" class="btn btn-primary btn-magnetic">Book a Free Call <?= icon('arrow-r', 18) ?></a>
                </div>
            </div>
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
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head center" data-aos="fade-up">
            <p class="eyebrow">FAQ</p>
            <h2 class="section-title">Before you ask — answered.</h2>
        </div>
        <div class="faq" data-accordion="single">
            <?php foreach ($homeFaq as $fi => $faqItem): ?>
            <div class="acc-item<?= $fi === 0 ? ' open' : '' ?>">
                <button class="acc-head" type="button" aria-expanded="<?= $fi === 0 ? 'true' : 'false' ?>">
                    <span class="acc-title"><?= esc($faqItem['q']) ?></span>
                    <span class="acc-icon"><?= icon('plus', 16) ?></span>
                </button>
                <div class="acc-body">
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
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic">Start a Project <?= icon('arrow-r', 18) ?></a>
            <a href="<?= url('services') ?>" class="btn btn-ghost btn-lg btn-magnetic">Browse Services</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
