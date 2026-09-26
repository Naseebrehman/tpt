<?php
class PublicController
{
    /** Compatibility boundary: existing templates and their data remain intact. */
    public static function page($file, $params = array())
    {
        foreach ($params as $key => $value) { $_GET[$key] = $value; }
        require BASE_PATH . '/' . $file;
    }
    public static function search()
    {
        $query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $results = Repository::search($query);
        require BASE_PATH . '/views/public/search.php';
    }
}
