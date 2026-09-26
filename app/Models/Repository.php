<?php
class Repository
{
    public static function createContact($data)
    {
        return dbInsert('INSERT INTO contact_submissions (name, email, phone, company, service, budget, message, source, status, notes, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "new", "", ?, ?)',
            array($data['name'], $data['email'], $data['phone'], $data['company'], $data['service'], $data['budget'], $data['message'], $data['source'], $data['ip_address'], $data['user_agent']));
    }
    public static function search($query)
    {
        if (mb_strlen(trim($query)) < 2) { return array(); }
        $results = array();
        $like = '%' . $query . '%';
        foreach (dbAll("SELECT title, slug, excerpt FROM blog_posts WHERE status = 'published' AND (title LIKE ? OR excerpt LIKE ?) LIMIT 20", array($like, $like)) as $row) {
            $results[] = array('title' => $row['title'], 'url' => url('blog/' . $row['slug']), 'description' => strip_tags($row['excerpt']));
        }
        foreach (dbAll('SELECT title, slug, description FROM resources WHERE is_active = 1 AND (title LIKE ? OR description LIKE ?) LIMIT 20', array($like, $like)) as $row) {
            $results[] = array('title' => $row['title'], 'url' => url('resources/' . $row['slug']), 'description' => strip_tags($row['description']));
        }
        foreach (pieServices() as $service) {
            if (stripos($service['name'] . ' ' . $service['desc'], $query) !== false) {
                $results[] = array('title' => $service['name'], 'url' => url('services/' . $service['key']), 'description' => $service['desc']);
            }
        }
        return $results;
    }
}
