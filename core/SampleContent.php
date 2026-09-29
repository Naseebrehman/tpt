<?php
class SampleContent
{
    public static function all($table)
    {
        static $data;
        if ($data === null) { $data = require BASE_PATH . '/database/samples.php'; }
        return $data[$table] ?? array();
    }
    public static function usesFallback($table)
    {
        if (!in_array($table, array('blog_posts','resources','portfolio'), true) || getSetting('sample_content_enabled', '1') !== '1') { return false; }
        static $empty = array();
        if (!isset($empty[$table])) {
            // Do not replace drafts, hidden records, or a filter with zero matches.
            $count = DB_OK ? dbOne('SELECT COUNT(*) AS total FROM ' . $table) : null;
            /* Defensive: a driver/driver-shim that returns an unexpected row
               shape must never raise a warning here. */
            $total = (is_array($count) && isset($count['total'])) ? (int) $count['total'] : -1;
            $empty[$table] = !DB_OK || ($total === 0);
        }
        return $empty[$table];
    }
    public static function find($table, $field, $value)
    {
        if (!self::usesFallback($table)) { return null; }
        foreach (self::all($table) as $row) { if ((string) $row[$field] === (string) $value) { return $row; } }
        return null;
    }
    public static function posts($options = array())
    {
        $rows = array_values(array_filter(self::all('blog_posts'), function ($row) use ($options) {
            return (empty($options['category']) || $row['category_slug'] === $options['category'])
                && (empty($options['search']) || stripos($row['title'].' '.$row['excerpt'].' '.strip_tags($row['content']), $options['search']) !== false);
        }));
        $perPage = max(1, (int) ($options['per_page'] ?? 9));
        $pages = max(1, (int) ceil(count($rows) / $perPage));
        $page = min($pages, max(1, (int) ($options['page'] ?? 1)));
        return array('posts'=>array_slice($rows, ($page-1)*$perPage, $perPage),'total'=>count($rows),'pages'=>$pages,'page'=>$page);
    }
    public static function seed(PDO $pdo)
    {
        $added = 0;
        $pdo->beginTransaction();
        try {
            foreach (array('blog_posts','resources','portfolio') as $table) {
                foreach (self::all($table) as $row) {
                    $check = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE slug = ?'); $check->execute(array($row['slug']));
                    if ($check->fetchColumn()) { continue; }
                    unset($row['id'], $row['category_name'], $row['category_slug']);
                    $columns = array_keys($row);
                    $sql = 'INSERT INTO ' . $table . ' (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0,count($columns),'?')) . ')';
                    $pdo->prepare($sql)->execute(array_values($row)); $added++;
                }
            }
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
        return $added;
    }
}
