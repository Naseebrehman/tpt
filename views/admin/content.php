<?php
require BASE_PATH . '/includes/admin-header.php';
?>
<div class="a-card"><h3>Preserve the original, override only what you need</h3><p>SEO overrides work for public pages, services, blog and resources. Service headlines, introductions and FAQs update the existing service layout. Other page body content remains in its existing templates/editors.</p>
<form method="get" class="a-filters"><label for="path">Public path</label><input id="path" name="path" value="<?= esc($path) ?>" placeholder="/about"><button class="a-btn">Load</button></form>
<p><?php foreach (pieServices() as $service): ?><a href="?path=<?= rawurlencode('/services/' . $service['key']) ?>"><?= esc($service['name']) ?></a> · <?php endforeach; ?></p></div>
<form method="post" class="a-card"><?= csrfField() ?><input type="hidden" name="path" value="<?= esc($path) ?>">
<?php foreach (array('title'=>'Meta title','description'=>'Meta description','canonical'=>'Canonical HTTPS URL','og_image'=>'Open Graph image HTTPS URL','headline'=>'Service headline','lead'=>'Service introduction') as $key=>$label): ?>
<div class="a-field"><label for="<?= $key ?>"><?= esc($label) ?></label><textarea id="<?= $key ?>" name="<?= $key ?>"><?= esc($data[$key] ?? '') ?></textarea></div>
<?php endforeach; ?>
<label class="a-check"><input type="checkbox" name="noindex" value="1"<?= !empty($data['noindex']) ? ' checked' : '' ?>> Exclude from indexing and sitemap</label>
<div class="a-field"><label for="faqs">Service FAQs (JSON; blank retains original FAQs)</label><textarea id="faqs" name="faqs" placeholder='[{"q":"Question", "a":"Answer"}]'><?= isset($data['faqs']) ? esc(json_encode($data['faqs'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : '' ?></textarea></div>
<button class="a-btn primary">Save</button> <button class="a-btn" name="reset" value="1">Restore original content and SEO</button></form>
<?php require BASE_PATH . '/includes/admin-footer.php'; ?>
