<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Resources — Free Guides, Templates & Videos';
$metaDesc  = 'Free marketing guides, plug-and-play templates and video trainings from The Pie Technologies. Download the Meta Ads Blueprint, SEO checklist, content playbooks and more.';
$activeNav = 'resources';

$guides    = getResources('guide');
$templates = getResources('template');
$videos    = getResources('video');
$posts     = getRecentPosts(6);

/** Convert any YouTube URL into a privacy-friendly embed URL. */
function youtubeEmbedUrl($url)
{
    $url = (string) $url;
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    return $url;
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Resources</p>
        <h1>The Growth Library.</h1>
        <p class="lead">Every guide, template and teardown we wish someone had handed us when we started spending other people&rsquo;s money on marketing. Free, forever.</p>
    </div>
</section>

<!-- ============================== GUIDES ================================ -->
<section class="section" id="guides">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Guides &amp; Blueprints</p>
            <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.8rem)">Download a free guide.</h2>
            <p class="section-lead">Written from live client work, not theory. No email gate on the PDFs — just click and read.</p>
        </div>
        <?php if ($guides): ?>
        <div class="resource-grid">
            <?php foreach ($guides as $i => $guide): ?>
            <article class="resource-card" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 70 ?>">
                <div class="resource-cover">
                    <span class="resource-type">Guide</span>
                    <img src="<?= asset($guide['cover_image'] !== '' ? $guide['cover_image'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($guide['title']) ?> cover" loading="lazy">
                </div>
                <div class="resource-body">
                    <h3><?= esc($guide['title']) ?></h3>
                    <p><?= esc($guide['description']) ?></p>
                    <div class="resource-foot">
                        <a class="btn btn-primary btn-sm" href="<?= url('download.php', false) ?>?id=<?= (int) $guide['id'] ?>"><?= icon('download', 15) ?> Download Free Guide</a>
                        <span class="resource-count"><?= number_format((int) $guide['download_count']) ?> downloads</span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="chart-card"><p class="text-muted">New guides are being uploaded this week — subscribe below and we&rsquo;ll email them to you first.</p></div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================ LATEST ARTICLES ========================= -->
<?php if ($posts): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up" style="display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;max-width:none">
            <div>
                <p class="eyebrow">From the blog</p>
                <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.8rem)">Latest articles.</h2>
            </div>
            <a class="link-arrow" href="<?= url('blog') ?>">View All Articles <?= icon('arrow-r', 16) ?></a>
        </div>
        <div class="blog-grid">
            <?php foreach (array_slice($posts, 0, 3) as $i => $post): ?>
            <article class="blog-card" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
                <div class="blog-card-media">
                    <?php if (!empty($post['category_name'])): ?><span class="blog-cat"><?= esc($post['category_name']) ?></span><?php endif; ?>
                    <img src="<?= asset($post['featured_image'] !== '' ? $post['featured_image'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($post['title']) ?>" loading="lazy">
                </div>
                <div class="blog-card-body">
                    <div class="blog-meta"><span><?= esc(formatDate($post['published_at'])) ?></span><span><?= (int) $post['reading_time'] ?> min read</span></div>
                    <h3><a href="<?= url('blog/' . $post['slug']) ?>"><?= esc($post['title']) ?></a></h3>
                    <p class="blog-excerpt"><?= esc($post['excerpt']) ?></p>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =============================== VIDEOS =============================== -->
<?php if ($videos): ?>
<section class="section" style="padding-top:0" id="videos">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Video Library</p>
            <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.8rem)">Watch &amp; learn.</h2>
        </div>
        <div class="blog-grid">
            <?php foreach ($videos as $i => $video): ?>
            <article class="blog-card video-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
                <div class="blog-card-media" style="aspect-ratio:16/9">
                    <button class="video-load" type="button" data-video="<?= esc(youtubeEmbedUrl($video['video_url'])) ?>" aria-label="Play video: <?= esc($video['title']) ?>">
                        <img src="<?= asset($video['cover_image'] !== '' ? $video['cover_image'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($video['title']) ?> thumbnail" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
                        <span class="play-btn"><?= icon('play', 24) ?></span>
                    </button>
                </div>
                <div class="blog-card-body">
                    <h3><?= esc($video['title']) ?></h3>
                    <p class="blog-excerpt"><?= esc($video['description']) ?></p>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================= TEMPLATES ============================== -->
<?php if ($templates): ?>
<section class="section" style="padding-top:0" id="templates">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Free Templates</p>
            <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.8rem)">Steal our internal templates.</h2>
            <p class="section-lead">The exact sheets and briefs our team uses on client accounts every week.</p>
        </div>
        <div class="resource-grid">
            <?php foreach ($templates as $i => $template): ?>
            <article class="resource-card" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 70 ?>">
                <div class="resource-cover">
                    <span class="resource-type" style="color:var(--violet-soft)">Template</span>
                    <img src="<?= asset($template['cover_image'] !== '' ? $template['cover_image'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($template['title']) ?> cover" loading="lazy">
                </div>
                <div class="resource-body">
                    <h3><?= esc($template['title']) ?></h3>
                    <p><?= esc($template['description']) ?></p>
                    <div class="resource-foot">
                        <a class="btn btn-ghost btn-sm" href="<?= url('download.php', false) ?>?id=<?= (int) $template['id'] ?>"><?= icon('download', 15) ?> Download</a>
                        <span class="resource-count"><?= number_format((int) $template['download_count']) ?> downloads</span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================ NEWSLETTER ============================== -->
<section class="section" style="padding-top:0" id="newsletter">
    <div class="container">
        <div class="newsletter-box" data-aos="fade-up">
            <div>
                <p class="eyebrow">The Growth Letter</p>
                <h2>Get the playbooks before they hit the blog.</h2>
                <p>One or two emails a month: live campaign teardowns, new guides and templates, zero spam. Unsubscribe in one click, always.</p>
            </div>
            <form data-ajax="newsletter" action="<?= url('newsletter.php', false) ?>" method="post" novalidate>
                <?= csrfField() ?>
                <div class="newsletter-form">
                    <div class="field">
                        <label for="nlName">Name</label>
                        <input id="nlName" type="text" name="name" placeholder="Your name" autocomplete="name" maxlength="150">
                    </div>
                    <div class="field">
                        <label for="nlEmail">Email <span class="req">*</span></label>
                        <input id="nlEmail" type="email" name="email" placeholder="you@company.com" required autocomplete="email" maxlength="150">
                    </div>
                    <div style="flex-basis:100%">
                        <button class="btn btn-primary btn-block" type="submit">Subscribe Free <?= icon('send', 16) ?></button>
                    </div>
                </div>
                <div class="form-status" role="status" aria-live="polite"></div>
            </form>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
