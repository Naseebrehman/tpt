<?php
/**
 * The Pie Technologies — single Growth Library resource (/resources/{slug})
 */
require_once __DIR__ . '/includes/init.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$res  = $slug !== '' ? getResourceBySlug($slug) : null;

if (!$res) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$typeLabels = array(
    'blueprint' => 'Blueprint', 'playbook' => 'Playbook', 'checklist' => 'Checklist',
    'template' => 'Template', 'guide' => 'Guide', 'framework' => 'Framework',
    'tutorial' => 'Tutorial', 'case-study' => 'Case Study', 'video' => 'Video',
);
$typeLabel = isset($typeLabels[$res['resource_type']]) ? $typeLabels[$res['resource_type']] : ucfirst($res['resource_type']);

$pageTitle = $res['title'] . ' — Free ' . $typeLabel;
$metaDesc  = $res['description'] !== '' ? $res['description'] : 'A free resource from the TPT Growth Library.';
$noIndex = strpos($res['slug'], 'sample-') === 0;
$activeNav = 'resources';

/* This page offers the short Start-a-project popup. */
$contactModalEnabled = true;

$jsonLd = json_encode(array(
    '@context'      => 'https://schema.org',
    '@type'         => 'Article',
    'headline'      => $res['title'],
    'description'   => $res['description'],
    'image'         => canonicalUrl($res['cover_image'] !== '' ? $res['cover_image'] : 'assets/images/og-image.jpg'),
    'author'        => array('@type' => 'Organization', 'name' => getSetting('site_name', SITE_NAME)),
    'publisher'     => array('@type' => 'Organization', 'name' => getSetting('site_name', SITE_NAME)),
    'mainEntityOfPage' => canonicalUrl('resources/' . $res['slug']),
), JSON_UNESCAPED_SLASHES);

$related = array();
foreach (getResources() as $other) {
    if ((int) $other['id'] !== (int) $res['id'] && $other['category'] === $res['category']) { $related[] = $other; }
}
foreach (getResources() as $other) {
    if (count($related) >= 3) { break; }
    if ((int) $other['id'] !== (int) $res['id'] && !in_array($other, $related, true)) { $related[] = $other; }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; <a href="<?= url('resources') ?>">Growth Library</a> &nbsp;/&nbsp; <?= esc($typeLabel) ?></p>
        <h1 style="font-size:clamp(2rem,4.6vw,3.4rem)"><?= esc($res['title']) ?></h1>
        <p class="lead"><?= esc($res['description']) ?></p>
        <div class="resource-single-head" style="margin-top:20px">
            <span class="chip"><?= esc($typeLabel) ?></span>
            <span><?= icon('clock', 15) ?> <?= (int) $res['reading_time'] ?> min read</span>
            <span><?= icon('layers', 15) ?> <?= esc($res['category']) ?></span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="resource-body-grid">
            <div>
                <?php if (!empty($res['content'])): ?>
                <article class="post-body" style="max-width:none;padding-block:0">
                    <?= $res['content'] /* stored as trusted admin-authored HTML */ ?>
                </article>
                <?php else: ?>
                <article class="post-body" style="max-width:none;padding-block:0">
                    <p><?= esc($res['description']) ?></p>
                    <p>This resource is available as a download — grab the full document from the panel beside this text.</p>
                </article>
                <?php endif; ?>
            </div>
            <aside class="resource-side">
                <div class="chart-card">
                    <img src="<?= asset($res['cover_image'] !== '' ? $res['cover_image'] : 'assets/images/placeholder.svg') ?>" alt="<?= esc($res['title']) ?> cover" style="width:100%;border-radius:12px;margin-bottom:16px">
                    <?php if (!empty($res['file_path'])): ?>
                    <a class="btn btn-primary btn-block" href="<?= url('download.php', false) ?>?id=<?= (int) $res['id'] ?>"><?= icon('download', 16) ?> Download the PDF</a>
                    <p class="text-muted" style="font-size:.8rem;margin-top:10px;text-align:center">Free. No email gate.</p>
                    <?php else: ?>
                    <a class="btn btn-primary btn-block" href="<?= url('contact') ?>"><?= icon('arrow-r', 16) ?> Apply this with TPT</a>
                    <p class="text-muted" style="font-size:.8rem;margin-top:10px;text-align:center">Want this installed, not just read? Start a project.</p>
                    <?php endif; ?>
                </div>
                <div class="chart-card">
                    <p class="eyebrow" style="margin-bottom:12px">Ask Alia</p>
                    <p class="text-muted" style="font-size:.88rem;line-height:1.7">Questions about this <?= esc(strtolower($typeLabel)) ?>? Ask <strong style="color:var(--text)">Alia</strong>, the TPT growth assistant — bottom-right corner. She answers from real TPT content and hands you to a human when she doesn&rsquo;t know.</p>
                </div>
                <?php if ($related): ?>
                <div class="chart-card">
                    <p class="eyebrow" style="margin-bottom:12px">More from the library</p>
                    <ul style="display:grid;gap:12px;list-style:none;padding:0;margin:0">
                        <?php foreach ($related as $rel): ?>
                        <li><a href="<?= url('resources/' . $rel['slug']) ?>" style="color:var(--text);text-decoration:none;font-size:.9rem;font-weight:500;display:block"><?= esc($rel['title']) ?><span class="text-muted" style="display:block;font-weight:400;font-size:.78rem;margin-top:3px"><?= esc($rel['category']) ?> · <?= (int) $rel['reading_time'] ?> min</span></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<!-- ============================= FINAL CTA ============================== -->
<section class="final-cta">
    <div class="container">
        <h2 data-aos="fade-up">Read it, or have us run it.</h2>
        <p data-aos="fade-up" data-aos-delay="80">The Growth Library is how we think. If you&rsquo;d rather have the system installed — tracking, campaigns, pages and the weekly rhythm — that&rsquo;s the whole job.</p>
        <div class="hero-ctas" data-aos="fade-up" data-aos-delay="140">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic" data-contact-modal>Start a Project <?= icon('arrow-r', 18) ?></a>
            <a href="<?= url('resources') ?>" class="btn btn-ghost btn-lg btn-magnetic">Back to the Library</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
