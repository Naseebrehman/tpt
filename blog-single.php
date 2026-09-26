<?php
require_once __DIR__ . '/includes/init.php';

$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';
$post = $slug !== '' ? getPostBySlug($slug) : null;

/* ------------------------- comment submission (PRG) ------------------------ */
if (!$post && isset($_GET['id'])) {
    $post = getPostById((int) $_GET['id']);
}

if ($post && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_submit'])) {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired — please try again.');
    } else {
        $cName    = sanitize(isset($_POST['name']) ? $_POST['name'] : '');
        $cEmail   = sanitize(isset($_POST['email']) ? $_POST['email'] : '');
        $cComment = sanitizeMultiline(isset($_POST['comment']) ? $_POST['comment'] : '');
        if ($cName === '' || !filter_var($cEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($cComment) < 4) {
            setFlash('err', 'Please add your name, a valid email and a comment.');
        } else {
            dbInsert(
                'INSERT INTO blog_comments (post_id, name, email, comment, status) VALUES (?, ?, ?, ?, "pending")',
                array((int) $post['id'], $cName, $cEmail, $cComment)
            );
            setFlash('ok', 'Thanks! Your comment is waiting for moderation.');
        }
    }
    header('Location: ' . url('blog/' . $post['slug']) . '#comments');
    exit;
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
$activeNav = 'blog';
$ogImage   = $post['featured_image'] !== '' ? $post['featured_image'] : 'assets/images/og-image.jpg';

$related  = array_slice(getRecentPosts(4, (int) $post['id']), 0, 3);
$comments = getApprovedComments((int) $post['id']);
$flash    = getFlash();
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

<section class="comments" id="comments">
    <h2 style="font-size:clamp(1.4rem,2.6vw,2rem);margin-bottom:8px">Comments (<?= count($comments) ?>)</h2>
    <?php if ($flash): ?>
    <div class="form-status <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?> show" style="margin:16px 0"><?= esc($flash['message']) ?></div>
    <?php endif; ?>

    <?php foreach ($comments as $comment): ?>
    <div class="comment">
        <div class="comment-head">
            <span class="comment-avatar"><?= esc(strtoupper(substr($comment['name'], 0, 1))) ?></span>
            <span>
                <strong><?= esc($comment['name']) ?></strong>
                <span><?= esc(formatDate($comment['created_at'], 'j M Y, H:i')) ?></span>
            </span>
        </div>
        <p><?= nl2br(esc($comment['comment'])) ?></p>
    </div>
    <?php endforeach; ?>

    <form method="post" action="<?= url('blog/' . $post['slug']) ?>#comments" class="contact-panel" style="margin-top:34px">
        <?= csrfField() ?>
        <h2 style="font-size:1.3rem">Leave a comment</h2>
        <p class="sub">Your email stays private. Comments are moderated before publishing.</p>
        <div class="form-grid">
            <div class="field">
                <label for="cName">Name <span class="req">*</span></label>
                <input id="cName" name="name" type="text" required maxlength="150" autocomplete="name">
            </div>
            <div class="field">
                <label for="cEmail">Email <span class="req">*</span></label>
                <input id="cEmail" name="email" type="email" required maxlength="150" autocomplete="email">
            </div>
            <div class="field full">
                <label for="cComment">Comment <span class="req">*</span></label>
                <textarea id="cComment" name="comment" required maxlength="2000"></textarea>
            </div>
            <div class="full">
                <button class="btn btn-primary" type="submit" name="comment_submit" value="1">Post Comment</button>
            </div>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
