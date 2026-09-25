<?php
/**
 * ---------------------------------------------------------------------------
 *  CSV exports: submissions (all / filtered / selected) and subscribers.
 * ---------------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$type = isset($_GET['type']) ? (string) $_GET['type'] : '';

if ($type === 'subscribers') {
    $rows = dbAll('SELECT email, name, is_active, subscribed_at FROM newsletter_subscribers ORDER BY subscribed_at DESC');
    $out  = array();
    foreach ($rows as $row) {
        $out[] = array($row['email'], $row['name'], $row['is_active'] ? 'active' : 'inactive', $row['subscribed_at']);
    }
    csvDownload('newsletter-subscribers-' . date('Y-m-d') . '.csv', array('Email', 'Name', 'Status', 'Subscribed At'), $out);
}

if ($type === 'submissions') {
    $where  = array('1=1');
    $params = array();
    if (!empty($_GET['ids'])) {
        $ids = array_filter(array_map('intval', explode(',', (string) $_GET['ids'])));
        if ($ids) {
            $where[] = 'id IN (' . implode(',', $ids) . ')';
        }
    }
    if (!empty($_GET['q'])) {
        $where[] = '(name LIKE ? OR email LIKE ? OR message LIKE ?)';
        $like = '%' . sanitize($_GET['q']) . '%';
        $params = array($like, $like, $like);
    }
    $rows = dbAll('SELECT * FROM contact_submissions WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC', $params);
    $out  = array();
    foreach ($rows as $row) {
        $out[] = array(
            $row['id'], $row['name'], $row['email'], $row['phone'], $row['company'],
            $row['service'], $row['budget'], $row['message'], $row['source'],
            $row['status'], $row['notes'], $row['ip_address'], $row['created_at'],
        );
    }
    csvDownload('contact-submissions-' . date('Y-m-d') . '.csv',
        array('ID', 'Name', 'Email', 'Phone', 'Company', 'Service', 'Budget', 'Message', 'Source', 'Status', 'Notes', 'IP', 'Received'),
        $out);
}

header('Location: index.php');
exit;
