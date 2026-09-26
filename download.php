<?php
/**
 * ---------------------------------------------------------------------------
 *  Resource download endpoint — tracks download_count then streams the file.
 * ---------------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';

$id       = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$resource = $id ? dbOne('SELECT * FROM resources WHERE id = ? AND is_active = 1', array($id)) : null;

if (!$resource && $id < 0) { $resource = SampleContent::find('resources', 'id', $id); }

if (!$resource || empty($resource['file_path'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'This resource is not available. Head back to ' . SITE_URL . ' for our latest guides.';
    exit;
}

$path = BASE_PATH . '/' . ltrim((string) $resource['file_path'], '/');
if (strpos(realpath($path) ?: $path, realpath(BASE_PATH) ?: BASE_PATH) !== 0 || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not found on server.';
    exit;
}

dbExec('UPDATE resources SET download_count = download_count + 1 WHERE id = ?', array($id));

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = array('pdf' => 'application/pdf', 'zip' => 'application/zip', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'csv' => 'text/csv', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
$type = isset($mime[$ext]) ? $mime[$ext] : 'application/octet-stream';

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Description: File Transfer');
header('Content-Type: ' . $type);
header('Content-Disposition: attachment; filename="' . slugify($resource['title']) . '.' . $ext . '"');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
