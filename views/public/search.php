<?php $pageTitle = 'Search'; $noIndex = true; require BASE_PATH . '/includes/header.php'; ?>
<section class="page-hero"><div class="container"><h1>Search</h1>
<form method="get"><label for="q">Find services and resources</label><input id="q" name="q" type="search" value="<?= esc($query) ?>" maxlength="100"><button class="btn btn-primary">Search</button></form></div></section>
<section class="section"><div class="container">
<?php foreach ($results as $result): ?><article><h2><a href="<?= esc($result['url']) ?>"><?= esc($result['title']) ?></a></h2><p><?= esc($result['description']) ?></p></article><?php endforeach; ?>
<?php if ($query !== '' && !$results): ?><p>No results found.</p><?php endif; ?>
</div></section><?php require BASE_PATH . '/includes/footer.php'; ?>
