<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'blog';
$adminTitle = 'Write / Edit Post';
$adminLibs  = array('tinymce' => true);

$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$post = $id ? getPostById($id) : null;
if ($id && !$post) {
    setFlash('err', 'Post not found.');
    header('Location: blog.php');
    exit;
}

/** Strip dangerous constructs from admin-authored rich text. */
function cleanAdminHtml($html)
{
    $html = (string) $html;
    $html = preg_replace('#<\s*(script|style)\b[^>]*>.*?<\s*/\s*(script|style)\s*>#is', '', $html);
    $html = preg_replace('#<\s*(script|style)\b[^>]*>?#is', '', $html);
    $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1="#"', $html);
    return $html;
}

$cats = getBlogCategories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired — please try again.');
    } else {
        $title    = sanitize(isset($_POST['title']) ? $_POST['title'] : '');
        $catId    = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $tags     = sanitize(isset($_POST['tags']) ? $_POST['tags'] : '');
        $content  = cleanAdminHtml(isset($_POST['content']) ? $_POST['content'] : '');
        $excerpt  = sanitizeMultiline(isset($_POST['excerpt']) ? $_POST['excerpt'] : '');
        $metaT    = sanitize(isset($_POST['meta_title']) ? $_POST['meta_title'] : '');
        $metaD    = sanitizeMultiline(isset($_POST['meta_description']) ? $_POST['meta_description'] : '');
        $status   = isset($_POST['status']) && $_POST['status'] === 'published' ? 'published' : 'draft';
        $pubDate  = sanitize(isset($_POST['published_at']) ? $_POST['published_at'] : '');

        if ($title === '' || mb_strlen($title) < 3) {
            setFlash('err', 'Please give the post a title (3+ characters).');
        } else {
            if ($excerpt === '') {
                $excerpt = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($content))), 0, 220);
            }
            $slugBase = slugify($title);
            $slug     = $slugBase;
            $n        = 2;
            while (dbOne('SELECT id FROM blog_posts WHERE slug = ? AND id <> ?', array($slug, $id))) {
                $slug = $slugBase . '-' . $n;
                $n++;
            }
            $readTime = readingTime($content);
            $pubAt    = $pubDate !== '' ? date('Y-m-d H:i:s', strtotime($pubDate)) : date('Y-m-d H:i:s');

            $image = '';
            if ($post && !empty($post['featured_image'])) {
                $image = $post['featured_image'];
            }
            $upload = uploadFile('featured_image', 'blog', array('jpg', 'jpeg', 'png', 'webp'));
            if (!$upload['ok']) {
                setFlash('err', $upload['error']);
            } else {
                if ($upload['path'] !== '') {
                    if ($image !== '') { deleteUpload($image); }
                    $image = $upload['path'];
                }
                if ($post) {
                    dbExec(
                        'UPDATE blog_posts SET title=?, slug=?, category_id=?, featured_image=?, excerpt=?, content=?, meta_title=?, meta_description=?, reading_time=?, status=?, published_at=?, tags=? WHERE id=?',
                        array($title, $slug, $catId ?: null, $image, $excerpt, $content, $metaT, $metaD, $readTime, $status, $pubAt, $tags, $id)
                    );
                    setFlash('ok', 'Post updated.');
                } else {
                    dbInsert(
                        'INSERT INTO blog_posts (title, slug, category_id, featured_image, excerpt, content, meta_title, meta_description, reading_time, status, published_at, tags) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                        array($title, $slug, $catId ?: null, $image, $excerpt, $content, $metaT, $metaD, $readTime, $status, $pubAt, $tags)
                    );
                    setFlash('ok', 'Post created.');
                }
                header('Location: blog.php');
                exit;
            }
        }
    }
}

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<form method="post" enctype="multipart/form-data" class="a-grid cols-2" style="align-items:start">
    <?= csrfField() ?>
    <div class="a-card">
        <h3>Content</h3>
        <div class="a-field">
            <label for="postTitle">Title</label>
            <input id="postTitle" name="title" type="text" required maxlength="255" value="<?= esc($post ? $post['title'] : '') ?>" placeholder="The Meta Ads checklist we run before every launch">
            <div class="hint">The slug is generated automatically from the title.</div>
        </div>
        <div class="a-field">
            <label for="postContent">Article body</label>
            <textarea id="postContent" name="content"><?= esc($post ? $post['content'] : '') ?></textarea>
        </div>
        <div class="a-field">
            <label for="postExcerpt">Excerpt / summary</label>
            <textarea id="postExcerpt" name="excerpt" style="min-height:80px"><?= esc($post ? $post['excerpt'] : '') ?></textarea>
            <div class="hint">Leave empty to auto-generate from the first paragraph.</div>
        </div>
    </div>

    <div class="a-grid" style="gap:20px">
        <div class="a-card">
            <h3>Publishing</h3>
            <div class="a-field-row">
                <div class="a-field">
                    <label for="postCat">Category</label>
                    <select id="postCat" name="category_id">
                        <option value="0">— none —</option>
                        <?php foreach ($cats as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"<?= $post && (int) $post['category_id'] === (int) $cat['id'] ? ' selected' : '' ?>><?= esc($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="a-field">
                    <label for="postStatus">Status</label>
                    <select id="postStatus" name="status">
                        <option value="draft"<?= $post && $post['status'] === 'draft' ? ' selected' : '' ?>>Draft</option>
                        <option value="published"<?= $post && $post['status'] === 'published' ? ' selected' : '' ?>>Published</option>
                    </select>
                </div>
            </div>
            <div class="a-field-row">
                <div class="a-field">
                    <label for="postDate">Publish date</label>
                    <input id="postDate" name="published_at" type="datetime-local" value="<?= esc($post && $post['published_at'] ? date('Y-m-d\TH:i', strtotime($post['published_at'])) : date('Y-m-d\TH:i')) ?>">
                </div>
                <div class="a-field">
                    <label for="postTags">Tags (comma separated)</label>
                    <input id="postTags" name="tags" type="text" maxlength="255" value="<?= esc($post && isset($post['tags']) ? $post['tags'] : '') ?>" placeholder="meta ads, ppc, testing">
                </div>
            </div>
            <div class="a-field">
                <label for="postImage">Featured image</label>
                <input id="postImage" name="featured_image" type="file" accept="image/jpeg,image/png,image/webp">
                <div class="hint">JPG / PNG / WebP, max 5 MB. <?= $post && $post['featured_image'] !== '' ? 'Current: ' . esc($post['featured_image']) : 'None yet.' ?></div>
            </div>
        </div>

        <div class="a-card">
            <h3>SEO</h3>
            <div class="a-field">
                <label for="postMetaTitle">Meta title</label>
                <input id="postMetaTitle" name="meta_title" type="text" maxlength="255" value="<?= esc($post ? $post['meta_title'] : '') ?>" placeholder="Defaults to the post title">
            </div>
            <div class="a-field">
                <label for="postMetaDesc">Meta description</label>
                <textarea id="postMetaDesc" name="meta_description" style="min-height:80px"><?= esc($post ? $post['meta_description'] : '') ?></textarea>
                <div class="hint">Around 150 characters shows best in Google.</div>
            </div>
            <div class="a-toolbar">
                <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save Post</button>
                <a class="a-btn" href="blog.php">Cancel</a>
                <?php if ($post): ?><span class="text-muted td-sub">Reading time auto: <?= (int) $post['reading_time'] ?> min · <?= number_format((int) $post['views']) ?> views</span><?php endif; ?>
            </div>
        </div>
    </div>
</form>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
