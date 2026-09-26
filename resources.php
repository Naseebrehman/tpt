<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Growth Library — Free Blueprints, Playbooks & Checklists';
$metaDesc  = 'Steal our playbooks. The frameworks TPT runs on client accounts — published in full. Blueprints, playbooks, checklists and templates. Free, because educated clients build better systems.';
$activeNav = 'resources';

$resources = getResources();
$posts     = getRecentPosts(3);

/* Type + category filter dimensions, derived from live rows. */
$typeLabels = array(
    'blueprint' => 'Blueprints', 'playbook' => 'Playbooks', 'checklist' => 'Checklists',
    'template' => 'Templates', 'guide' => 'Guides', 'framework' => 'Frameworks',
    'tutorial' => 'Tutorials', 'case-study' => 'Case Studies', 'video' => 'Videos',
);
$types = array();
$cats  = array();
foreach ($resources as $res) {
    if (!empty($res['resource_type']) && !in_array($res['resource_type'], $types, true)) { $types[] = $res['resource_type']; }
    if (!empty($res['category']) && !in_array($res['category'], $cats, true)) { $cats[] = $res['category']; }
}
sort($cats);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Growth Library</p>
        <h1>Steal our playbooks.<br>Grow with them.</h1>
        <p class="lead">The frameworks we run on client accounts — published in full. Blueprints, playbooks, checklists and templates. Free, because educated clients build better systems.</p>
    </div>
</section>

<!-- ============================== LIBRARY =============================== -->
<section class="section">
    <div class="container">
        <?php if ($resources): ?>
        <div class="lib-filters" data-aos="fade-up">
            <div class="filter-tabs" role="group" aria-label="Filter by type">
                <button class="filter-tab active" type="button" data-libfilter="type" data-value="all" aria-pressed="true">All</button>
                <?php foreach ($types as $type): ?>
                <button class="filter-tab" type="button" data-libfilter="type" data-value="<?= esc($type) ?>" aria-pressed="false"><?= esc(isset($typeLabels[$type]) ? $typeLabels[$type] : ucfirst($type)) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="filter-tabs lib-cats" role="group" aria-label="Filter by category">
                <button class="filter-tab active" type="button" data-libfilter="cat" data-value="all" aria-pressed="true">Every topic</button>
                <?php foreach ($cats as $cat): ?>
                <button class="filter-tab" type="button" data-libfilter="cat" data-value="<?= esc($cat) ?>" aria-pressed="false"><?= esc($cat) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="resource-grid">
            <?php foreach ($resources as $i => $res):
                $hasFile = !empty($res['file_path']);
                /* Legacy rows may predate slugs — link them straight to the download. */
                $resUrl  = !empty($res['slug']) ? url('resources/' . $res['slug']) : url('download.php', false) . '?id=' . (int) $res['id'];
            ?>
            <article class="resource-card" data-type="<?= esc($res['resource_type']) ?>" data-category="<?= esc($res['category']) ?>" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 70 ?>">
                <div class="resource-cover">
                    <span class="resource-type"><?= esc(isset($typeLabels[$res['resource_type']]) ? rtrim($typeLabels[$res['resource_type']], 's') : ucfirst($res['resource_type'])) ?></span>
                    <a href="<?= esc($resUrl) ?>" aria-label="<?= esc($res['title']) ?>">
                        <img src="<?= asset($res['cover_image'] !== '' ? $res['cover_image'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($res['title']) ?> cover" loading="lazy">
                    </a>
                </div>
                <div class="resource-body">
                    <div class="resource-meta">
                        <span><?= esc($res['category']) ?></span>
                        <span><?= (int) $res['reading_time'] ?> min read</span>
                    </div>
                    <h3><a href="<?= esc($resUrl) ?>" style="color:inherit;text-decoration:none"><?= esc($res['title']) ?></a></h3>
                    <p><?= esc($res['description']) ?></p>
                    <div class="resource-foot">
                        <a class="btn btn-primary btn-sm" href="<?= esc($resUrl) ?>"><?= icon('book', 15) ?> <?= !empty($res['slug']) ? 'Read ' . esc(isset($typeLabels[$res['resource_type']]) ? rtrim($typeLabels[$res['resource_type']], 's') : 'it') : 'Download' ?></a>
                        <?php if ($hasFile && !empty($res['slug'])): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= url('download.php', false) ?>?id=<?= (int) $res['id'] ?>"><?= icon('download', 15) ?> PDF</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="chart-card hidden" id="libEmpty" style="text-align:center"><p class="text-muted">No resources match that combination yet — try another filter.</p></div>
        <?php else: ?>
        <div class="chart-card"><p class="text-muted">The library is being restocked — subscribe below and new playbooks land in your inbox first.</p></div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================ LATEST ARTICLES ========================= -->
<?php if ($posts): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up" style="display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;max-width:none">
            <div>
                <p class="eyebrow">From the Journal</p>
                <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.8rem)">Sharp thinking on growth.</h2>
            </div>
            <a class="link-arrow" href="<?= url('blog') ?>">View All Articles <?= icon('arrow-r', 16) ?></a>
        </div>
        <div class="blog-grid">
            <?php foreach ($posts as $i => $post): ?>
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

<!-- ============================ NEWSLETTER ============================== -->
<section class="section" style="padding-top:0" id="newsletter">
    <div class="container">
        <div class="newsletter-box" data-aos="fade-up">
            <div>
                <p class="eyebrow">The Growth Letter</p>
                <h2>Get the playbooks before they hit the Journal.</h2>
                <p>One or two emails a month: live campaign teardowns, new blueprints and templates, zero spam. Unsubscribe in one click, always.</p>
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
