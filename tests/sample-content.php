<?php
/** Run: php tests/sample-content.php. No production DB or credentials are used. */
define('BASE_PATH', dirname(__DIR__));
define('DB_OK', false);
function getSetting($key, $default = '') { return $default; }
require BASE_PATH . '/core/SampleContent.php';
$count = 0;
function verifySample($condition, $name) { global $count; if (!$condition) { throw new RuntimeException('FAIL: ' . $name); } $count++; echo 'PASS: ' . $name . PHP_EOL; }
foreach (array('blog_posts','resources','portfolio') as $table) {
    $rows = SampleContent::all($table);
    verifySample(count($rows) === 3, $table . ' has three examples');
    verifySample(SampleContent::usesFallback($table), $table . ' offline fallback');
    foreach ($rows as $row) {
        verifySample(strpos($row['slug'], 'sample-') === 0 && $row['id'] < 0, 'sample identity: ' . $row['slug']);
        verifySample(SampleContent::find($table, 'slug', $row['slug']) === $row, 'detail resolution: ' . $row['slug']);
        if (isset($row['file_path'])) { verifySample(is_file(BASE_PATH . '/' . $row['file_path']), 'download exists: ' . $row['slug']); }
    }
}
verifySample(SampleContent::posts(array('search'=>'not-a-real-result'))['total'] === 0, 'empty search stays empty');
verifySample(SampleContent::posts(array('category'=>'websites'))['total'] === 1, 'category filter');
verifySample(SampleContent::posts(array('per_page'=>2,'page'=>2))['page'] === 2, 'sample pagination');
verifySample(SampleContent::find('portfolio','slug','unknown') === null, 'unknown detail returns null');
verifySample(!SampleContent::usesFallback('admin_users'), 'unsupported table rejected');
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:', null, null, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
    foreach (array('blog_posts','resources','portfolio') as $table) {
        $row = SampleContent::all($table)[0]; unset($row['id'], $row['category_name'], $row['category_slug']);
        $definitions = array_map(function ($column) { return '"'.$column.'" TEXT' . ($column === 'slug' ? ' UNIQUE' : ''); }, array_keys($row));
        $pdo->exec('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY AUTOINCREMENT, '.implode(',', $definitions).')');
    }
    verifySample(SampleContent::seed($pdo) === 9, 'first import adds nine records');
    $pdo->exec("UPDATE blog_posts SET title = 'Edited by administrator' WHERE slug = 'sample-a-better-campaign-brief'");
    verifySample(SampleContent::seed($pdo) === 0, 'second import adds no duplicates');
    verifySample($pdo->query("SELECT title FROM blog_posts WHERE slug = 'sample-a-better-campaign-brief'")->fetchColumn() === 'Edited by administrator', 'repeat import preserves admin edits');
} else { echo "SKIP: seed persistence checks require the optional PDO SQLite test driver.\n"; }
echo $count . " sample-content checks passed.\n";
