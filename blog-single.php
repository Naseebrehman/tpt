<?php
/**
 * The Pie Technologies — single blog post
 * Mobile-first article layout; the comments section has been replaced by the
 * newsletter subscription (comments were removed from the public site).
 */
require_once __DIR__ . '/includes/init.php';
require_once BASE_PATH . '/core/Captcha.php';

$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';
$post = $slug !== '' ? getPostBySlug($slug) : null;

if (!$post && isset($_GET['id'])) {
    $post = getPostById((int) $_GET['id']);
}

if (!$post) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

/* count the view */
dbExec('UPDATE blog_posts SET views = views + 1 WHERE id = ?', array((int) $post['id']));

$pageTitle = $post['meta_title'] !== '' ? $post['meta_title'] : $post['title'];
$metaDesc  = $post['meta_description'] !== '' ? $post['meta_description'] : $post['excerpt'];
$noIndex = strpos($post['slug'], 'sample-') === 0;
$activeNav = 'blog';
$ogImage   = $post['featured_image'] !== '' ? $post['featured_image'] : 'assets/images/og-image.jpg';

$related  = array_slice(getRecentPosts(4, (int) $post['id']), 0, 3);
$shareUrl = canonicalUrl('blog/' . $post['slug']);

$jsonLd = json_encode(array(
    '@context'         => 'https://schema.org',
    '@type'            => 'BlogPosting',
    'headline'         => $post['title'],
    'description'      => $post['excerpt'],
    'datePublished'    => $post['published_at'],
    'author'           => array('@type' => 'Organization', 'name' => getSetting('site_name', SITE_NAME)),
    'publisher'        => array('@type' => 'Organization', 'name' => getSetting('site_name', SITE_NAME)),
    'mainEntityOfPage' => $shareUrl,
), JSON_UNESCAPED_SLASHES);

require_once __DIR__ . '/includes/header.php';
?>

<div class="reading-progress" aria-hidden="true"><i></i></div>

<article>
    <header class="post-header">
        <div class="container" style="max-width:900px">
            <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; <a href="<?= url('blog') ?>">Blog</a><?php if (!empty($post['category_name'])): ?> &nbsp;/&nbsp; <a href="<?= url('blog') ?>?category=<?= esc($post['category_slug']) ?>"><?= esc($post['category_name']) ?></a><?php endif; ?></p>
            <h1 style="font-size:clamp(2rem,4.6vw,3.4rem);letter-spacing:-.035em"><?= esc($post['title']) ?></h1>
            <div class="post-meta-row">
                <span><?= icon('users', 14) ?> <?= esc($post['author'] !== '' ? $post['author'] : getSetting('site_name', SITE_NAME)) ?></span>
                <span><?= icon('calendar', 14) ?> <?= esc(formatDate($post['published_at'])) ?></span>
                <span><?= icon('clock', 14) ?> <?= (int) $post['reading_time'] ?> min read</span>
                <span><?= icon('eye', 14) ?> <?= number_format((int) $post['views']) ?> views</span>
            </div>
            <?php if (!empty($post['featured_image'])): ?>
            <div class="post-featured">
                <img src="<?= asset($post['featured_image']) ?>" alt="<?= esc($post['title']) ?>">
            </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <div class="post-body">
            <?= $post['content'] /* stored as trusted admin-authored HTML */ ?>

            <div class="share-row">
                <span class="label">Share</span>
                <a class="share-btn" target="_blank" rel="noopener noreferrer" href="https://twitter.com/intent/tweet?url=<?= rawurlencode($shareUrl) ?>&text=<?= rawurlencode($post['title']) ?>" aria-label="Share on X"><?= icon('twitter', 16) ?></a>
                <a class="share-btn" target="_blank" rel="noopener noreferrer" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shareUrl) ?>" aria-label="Share on Facebook"><?= icon('facebook', 16) ?></a>
                <a class="share-btn" target="_blank" rel="noopener noreferrer" href="https://www.linkedin.com/shareArticle?mini=true&url=<?= rawurlencode($shareUrl) ?>&title=<?= rawurlencode($post['title']) ?>" aria-label="Share on LinkedIn"><?= icon('linkedin', 16) ?></a>
                <a class="share-btn" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text=<?= rawurlencode($post['title'] . ' ' . $shareUrl) ?>" aria-label="Share on WhatsApp"><?= icon('whatsapp', 16) ?></a>
            </div>
        </div>
    </div>
</article>

<?php if ($related): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Keep reading</p>
            <h2 class="section-title" style="font-size:clamp(1.6rem,3vw,2.4rem)">Related articles.</h2>
        </div>
        <div class="blog-grid">
            <?php foreach ($related as $i => $rel): ?>
            <article class="blog-card" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
                <div class="blog-card-media">
                    <?php if (!empty($rel['category_name'])): ?><span class="blog-cat"><?= esc($rel['category_name']) ?></span><?php endif; ?>
                    <img src="<?= asset($rel['featured_image'] !== '' ? $rel['featured_image'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($rel['title']) ?>" loading="lazy">
                </div>
                <div class="blog-card-body">
                    <div class="blog-meta"><span><?= esc(formatDate($rel['published_at'])) ?></span><span><?= (int) $rel['reading_time'] ?> min read</span></div>
                    <h3><a href="<?= url('blog/' . $rel['slug']) ?>"><?= esc($rel['title']) ?></a></h3>
                    <a class="link-arrow" href="<?= url('blog/' . $rel['slug']) ?>">Read Article <?= icon('arrow-r', 16) ?></a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================ NEWSLETTER ============================== -->
<section class="section post-newsletter" id="newsletter">
    <div class="container">
        <div class="newsletter-box" data-aos="fade-up">
            <div class="newsletter-copy">
                <p class="eyebrow">The Growth Letter</p>
                <h2>Get the playbooks before they hit the Journal.</h2>
                <p>One or two emails a month: live campaign teardowns, new blueprints and templates, zero spam. Unsubscribe in one click, always.</p>
                <ul class="newsletter-points">
                    <li><?= icon('check', 15) ?> Teardowns of real campaigns we run</li>
                    <li><?= icon('check', 15) ?> New blueprints and templates first</li>
                    <li><?= icon('check', 15) ?> No spam, one-click unsubscribe</li>
                </ul>
            </div>
            <form class="newsletter-panel" data-ajax="newsletter" action="<?= url('newsletter.php', false) ?>" method="post" novalidate>
                <?= csrfField() ?>
                <h3>Subscribe free</h3>
                <div class="field">
                    <label for="postNlName">Name <span class="text-muted">(optional)</span></label>
                    <input id="postNlName" type="text" name="name" placeholder="Your name" autocomplete="name" maxlength="150">
                </div>
                <div class="field">
                    <label for="postNlEmail">Email <span class="req">*</span></label>
                    <input id="postNlEmail" type="email" name="email" placeholder="you@company.com" required autocomplete="email" maxlength="150">
                </div>
                <?php if (Captcha::enabled()): ?>
                <div class="field"><?= Captcha::field() ?></div>
                <?php endif; ?>
                <button class="btn btn-primary btn-block" type="submit">Subscribe Free <?= icon('send', 16) ?></button>
                <p class="newsletter-fine">We never share your address. Unsubscribe anytime in one click.</p>
                <div class="form-status" role="status" aria-live="polite"></div>
            </form>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
