<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Journal — Sharp Thinking on Growth, Ads & AI';
$metaDesc  = 'No recycled listicles. What the TPT Strategy Team is learning running real growth systems — with the receipts.';
$activeNav = 'blog';

$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$search   = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$page     = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$result   = getPosts(array('category' => $category, 'search' => $search, 'page' => $page, 'per_page' => 9));
$posts    = $result['posts'];
$pages    = $result['pages'];
$cats     = getBlogCategories();

function blogQueryUrl($params = array())
{
    $query = array();
    if (!empty($params['category'])) { $query['category'] = $params['category']; }
    if (!empty($params['q']))        { $query['q'] = $params['q']; }
    if (!empty($params['page']))     { $query['page'] = (int) $params['page']; }
    return url('blog') . ($query ? '?' . http_build_query($query) : '');
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Journal</p>
        <h1>Sharp thinking on growth, ads &amp; AI.</h1>
        <p class="lead">No recycled listicles. What we&rsquo;re learning running real growth systems — with the receipts. Written by the TPT Strategy Team.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (SampleContent::usesFallback('blog_posts')): ?><p class="sample-notice"><strong>Sample content.</strong> These examples are for previewing the website, not claims about real clients or results. Add your own content in the dashboard to replace them.</p><?php endif; ?>
        <div class="blog-toolbar">
            <div class="filter-tabs" style="margin-bottom:0" role="group" aria-label="Filter articles by category">
                <a class="filter-tab<?= $category === '' ? ' active' : '' ?>" href="<?= blogQueryUrl(array('q' => $search)) ?>">All</a>
                <?php foreach ($cats as $cat): ?>
                <a class="filter-tab<?= $category === $cat['slug'] ? ' active' : '' ?>" href="<?= blogQueryUrl(array('category' => $cat['slug'], 'q' => $search)) ?>"><?= esc($cat['name']) ?></a>
                <?php endforeach; ?>
            </div>
            <form class="search-box" role="search" method="get" action="<?= url('blog') ?>">
                <?= icon('search', 16) ?>
                <input type="search" name="q" value="<?= esc($search) ?>" placeholder="Search articles…" aria-label="Search articles">
                <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= esc($category) ?>"><?php endif; ?>
            </form>
        </div>

        <?php if ($posts): ?>
        <div class="blog-grid">
            <?php foreach ($posts as $i => $post): ?>
            <article class="blog-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
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

        <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Blog pagination">
            <?php if ($page > 1): ?><a href="<?= blogQueryUrl(array('category' => $category, 'q' => $search, 'page' => $page - 1)) ?>" rel="prev"><?= icon('arrow-l', 14) ?></a><?php else: ?><span class="disabled"><?= icon('arrow-l', 14) ?></span><?php endif; ?>
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <?php if ($p === $page): ?><span class="current"><?= $p ?></span>
                <?php else: ?><a href="<?= blogQueryUrl(array('category' => $category, 'q' => $search, 'page' => $p)) ?>"><?= $p ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a href="<?= blogQueryUrl(array('category' => $category, 'q' => $search, 'page' => $page + 1)) ?>" rel="next"><?= icon('arrow-r', 14) ?></a><?php else: ?><span class="disabled"><?= icon('arrow-r', 14) ?></span><?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php else: ?>
        <div class="chart-card text-center">
            <p class="text-muted">No articles match<?= $search !== '' ? ' &ldquo;' . esc($search) . '&rdquo;' : '' ?> yet. Try another category or <a href="<?= url('blog') ?>" style="color:var(--violet-soft)">clear the filters</a>.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
