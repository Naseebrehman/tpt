<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = getSetting('meta_title', 'Digital Marketing Agency That Actually Moves Numbers');
$metaDesc  = getSetting('meta_description', 'The Pie Technologies is a full-service growth agency: Meta Ads, SEO, Social Media Management, Web Development, Email Marketing, Google Ads and Branding & Design for brands that mean business.');
$activeNav = 'home';
$bodyClass = 'page-home';
$pageLibs  = array('typed' => true, 'particles' => true, 'swiper' => true);

$services     = pieServices();
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
    'description' => 'Full-service digital marketing agency specializing in Meta Ads, SEO, Social Media Management, and Web Development.',
    'telephone'   => getSetting('site_phone', '+1-XXX-XXX-XXXX'),
    'email'       => getSetting('site_email', 'hello@thepietechnologies.com'),
    'address'     => array('@type' => 'PostalAddress', 'streetAddress' => getSetting('site_address', '')),
    'sameAs'      => $sameAs,
), JSON_UNESCAPED_SLASHES);

$clients = array('Aurelia Fashion', 'Brew Theory', 'IronCore Fitness', 'Nimbus SaaS', 'Zayn Estates', 'Lumen Skincare', 'Kardee Foods', 'Vela Studio');

require_once __DIR__ . '/includes/header.php';
?>

<!-- ================================ HERO ================================ -->
<section class="hero">
    <div id="particles-js" aria-hidden="true"></div>
    <div class="container hero-inner">
        <span class="hero-eyebrow"><?= icon('sparkle', 14) ?> Full-Service Growth Agency</span>
        <h1 class="display hero-title">
            <span class="line">The Growth Agency.</span>
            <span class="typed-line"><span id="typed-text" data-strings="For Brands That Mean Business.|Built to Scale You.|Results or Nothing.|Growth, Engineered."></span><span class="typed-caret" aria-hidden="true"></span></span>
        </h1>
        <p class="hero-sub">We run Meta Ads, SEO, Social Media and Web Development for ambitious brands. No fluff. Just growth.</p>
        <div class="hero-ctas">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic">Start a Project <?= icon('arrow-r', 18) ?></a>
            <a href="<?= url('portfolio') ?>" class="btn btn-ghost btn-lg btn-magnetic">See Our Work</a>
        </div>

        <div class="hero-visual" data-aos="fade-up" data-aos-delay="150">
            <img src="<?= asset('assets/images/hero-studio.jpg') ?>" alt="The Pie Technologies studio at night, screens glowing with campaign dashboards" width="1376" height="768" fetchpriority="high">
            <div class="hero-float-stat">
                <strong>4.2&times;</strong>
                <span>average ROAS across<br>managed ad accounts</span>
            </div>
        </div>
    </div>
    <div class="scroll-indicator" aria-hidden="true">
        <span>Scroll</span>
        <?= icon('arrow-d', 18) ?>
    </div>
</section>

<!-- ============================== MARQUEE =============================== -->
<section class="marquee-section" aria-label="Client logos">
    <p class="marquee-label">Trusted by brands that mean business.</p>
    <div class="marquee">
        <div class="marquee-track">
            <?php for ($loop = 0; $loop < 2; $loop++): foreach ($clients as $client): ?>
            <span class="marquee-item"><i class="dot"></i><?= esc($client) ?></span>
            <?php endforeach; endfor; ?>
        </div>
    </div>
    <div class="marquee rev">
        <div class="marquee-track">
            <?php for ($loop = 0; $loop < 2; $loop++): foreach (array_reverse($clients) as $client): ?>
            <span class="marquee-item"><i class="dot"></i><?= esc($client) ?></span>
            <?php endforeach; endfor; ?>
        </div>
    </div>
</section>

<!-- =============================== STATS ================================ -->
<section class="section" aria-label="Agency results in numbers">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card" data-aos="fade-up">
                <span class="stat-index mono">01</span>
                <div class="stat-value"><span data-countup="120" data-suffix="">0</span><span class="suffix">+</span></div>
                <p class="stat-label">Campaigns launched across Meta, Google &amp; email.</p>
            </div>
            <div class="stat-card" data-aos="fade-up" data-aos-delay="80">
                <span class="stat-index mono">02</span>
                <div class="stat-value"><span data-countup="4.2" data-decimals="1">0</span><span class="suffix">&times;</span></div>
                <p class="stat-label">Average ROAS across managed ad spend.</p>
            </div>
            <div class="stat-card" data-aos="fade-up" data-aos-delay="160">
                <span class="stat-index mono">03</span>
                <div class="stat-value"><span data-countup="98">0</span><span class="suffix">%</span></div>
                <p class="stat-label">Client retention — partners stay because it works.</p>
            </div>
            <div class="stat-card" data-aos="fade-up" data-aos-delay="240">
                <span class="stat-index mono">04</span>
                <div class="stat-value"><span data-countup="50">0</span><span class="suffix">+</span></div>
                <p class="stat-label">Brands scaled from first campaign to market leader.</p>
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
            <h2 class="section-title">Work that drives results.</h2>
            <p class="section-lead">Not vanity metrics. Revenue, leads and rankings our clients can take to the bank.</p>
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
            <h2 class="section-title">Everything you need.<br>Nothing you don&rsquo;t.</h2>
        </div>

        <div class="services-acc" data-accordion="single">
            <?php foreach ($services as $i => $svc): ?>
            <div class="acc-item<?= $i === 0 ? ' open' : '' ?>">
                <button class="acc-head" type="button">
                    <span class="acc-num mono"><?= esc($svc['num']) ?></span>
                    <span class="acc-title"><?= esc($svc['name']) ?></span>
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
<section class="system" aria-label="The Pie Technologies system">
    <div class="system-sticky">
        <aside class="system-side">
            <div>
                <p class="eyebrow">The System</p>
                <h2>Explore the disciplines.</h2>
            </div>
            <ul class="system-progress">
                <?php $systemItems = array(array('01','Meta Ads'),array('02','Social Media'),array('03','SEO'),array('04','Web Development'),array('05','Growth & AI Automation')); foreach ($systemItems as $si => $sItem): ?>
                <li<?= $si === 0 ? ' class="current"' : '' ?>><span class="mono"><?= esc($sItem[0]) ?></span><span class="bar"><i></i></span></li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <div class="system-stage">
            <?php
            $systemPanels = array(
                array('dir' => 'left',   'num' => '01', 'title' => 'Meta Ads',              'svc' => $services[0]),
                array('dir' => 'bottom', 'num' => '02', 'title' => 'Social Media',          'svc' => $services[1]),
                array('dir' => 'right',  'num' => '03', 'title' => 'SEO',                   'svc' => $services[2]),
                array('dir' => 'top',    'num' => '04', 'title' => 'Web Development',       'svc' => $services[3]),
                array('dir' => 'scale',  'num' => '05', 'title' => 'Growth & AI Automation','svc' => null),
            );
            foreach ($systemPanels as $pi => $panel): ?>
            <article class="system-panel<?= $pi === 0 ? ' is-active' : '' ?>" data-dir="<?= esc($panel['dir']) ?>">
                <span class="system-num"><?= esc($panel['num']) ?></span>
                <h3><?= esc($panel['title']) ?></h3>
                <?php if ($panel['svc']): ?>
                <p class="desc"><?= esc($panel['svc']['desc']) ?></p>
                <div class="system-tags"><?php foreach ($panel['svc']['tags'] as $tag): ?><span class="chip"><?= esc($tag) ?></span><?php endforeach; ?></div>
                <div class="system-metrics">
                    <?php foreach ($panel['svc']['metrics'] as $metric): ?>
                    <div><strong><?= esc($metric[0]) ?></strong><span><?= esc($metric[1]) ?></span></div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="desc">The layer that ties it all together: dashboards, attribution, AI-assisted reporting and automation that removes busywork — so every decision across every channel is made on live data, not gut feel.</p>
                <div class="system-tags">
                    <span class="chip">Attribution</span><span class="chip">Live Dashboards</span><span class="chip">AI Reporting</span><span class="chip">Workflow Automation</span>
                </div>
                <div class="system-metrics">
                    <div><strong>24h</strong><span>Reporting refresh cycle</span></div>
                    <div><strong>100%</strong><span>Spend attributed</span></div>
                    <div><strong>-12h</strong><span>Manual work saved / week</span></div>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="system-spacer" aria-hidden="true">
        <div class="system-trigger" data-index="0"></div>
        <div class="system-trigger" data-index="1"></div>
        <div class="system-trigger" data-index="2"></div>
        <div class="system-trigger" data-index="3"></div>
        <div class="system-trigger" data-index="4"></div>
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
                <p>I started this agency after watching too many good brands burn budget on agencies that reported impressions instead of revenue. We built the opposite: a team obsessed with one number — yours. Let&rsquo;s talk about your growth.</p>
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
            <span class="google-badge"><span class="g-stars">★★★★★</span> <?= esc(number_format($avgRating, 1)) ?> average · <?= count($testimonials) ?> verified client reviews</span>
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
                    <p>Every decision backed by numbers. 50M+ in ad spend managed, every riyal attributed.</p>
                </div>
            </article>
            <article class="why-card" data-aos="fade-up" data-aos-delay="100">
                <img src="<?= asset('assets/images/why-team.jpg') ?>" alt="The Pie Technologies team collaborating" loading="lazy">
                <div class="why-copy">
                    <?= icon('layers', 26) ?>
                    <h3>Full-Service</h3>
                    <p>Meta Ads to Web Dev, under one team. No outsourcing, no telephone game, no excuses.</p>
                </div>
            </article>
            <article class="why-card" data-aos="fade-up" data-aos-delay="200">
                <img src="<?= asset('assets/images/hero-studio.jpg') ?>" alt="The Pie Technologies studio" loading="lazy">
                <div class="why-copy">
                    <?= icon('target', 26) ?>
                    <h3>Results-First</h3>
                    <p>We don&rsquo;t charge for effort. We deliver outcomes — and we report on them brutally honestly.</p>
                </div>
            </article>
        </div>
    </div>
</section>

<!-- ============================ BLOG PREVIEW ============================ -->
<?php if ($posts): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Insights</p>
            <h2 class="section-title">Latest from the blog.</h2>
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

<!-- ============================= FINAL CTA ============================== -->
<section class="final-cta">
    <div class="container">
        <h2 data-aos="fade-up">Ready to grow? Let&rsquo;s build something that actually works.</h2>
        <p data-aos="fade-up" data-aos-delay="80">Free strategy call. No pressure, no jargon — just a clear plan for your next 90 days of growth.</p>
        <div class="hero-ctas" data-aos="fade-up" data-aos-delay="140">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic">Start a Project <?= icon('arrow-r', 18) ?></a>
            <a href="<?= url('services/meta-ads') ?>" class="btn btn-ghost btn-lg btn-magnetic">Browse Services</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
