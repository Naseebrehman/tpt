<?php
/** Central upload and confined deletion service; legacy function API retained. */
function uploadFile($field, $subdir, $allowedExt = array('jpg', 'jpeg', 'png', 'webp'), $maxBytes = 5242880)
{
    if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $subdir)) { return array('ok' => false, 'path' => '', 'error' => 'Invalid upload destination.'); }
    $allowedExt = array_intersect($allowedExt, array('jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'));
    if (!function_exists('finfo_open')) { return array('ok' => false, 'path' => '', 'error' => 'Fileinfo extension is required.'); }
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return array('ok' => true, 'path' => '', 'error' => ''); /* nothing uploaded */
    }
    $file = $_FILES[$field];
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return array('ok' => true, 'path' => '', 'error' => '');
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        return array('ok' => false, 'path' => '', 'error' => 'Upload failed (server error code ' . (int) $file['error'] . ').');
    }
    if ((int) $file['size'] > $maxBytes) {
        return array('ok' => false, 'path' => '', 'error' => 'File is larger than the 5 MB limit.');
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return array('ok' => false, 'path' => '', 'error' => 'File type .' . $ext . ' is not allowed.');
    }

    /* Real MIME check via finfo */
    $mimeMap = array(
        'jpg'  => array('image/jpeg'),
        'jpeg' => array('image/jpeg'),
        'png'  => array('image/png'),
        'webp' => array('image/webp'),
        'gif'  => array('image/gif'),
        'svg'  => array('image/svg+xml', 'text/plain', 'application/xml', 'text/xml'),
        'pdf'  => array('application/pdf'),
    );
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (isset($mimeMap[$ext]) && !in_array($mime, $mimeMap[$ext], true)) {
            return array('ok' => false, 'path' => '', 'error' => 'File content does not match its extension.');
        }
    }

    $dir = UPLOAD_PATH . trim($subdir, '/');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return array('ok' => false, 'path' => '', 'error' => 'Upload folder is not writable.');
    }

    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $newName)) {
        return array('ok' => false, 'path' => '', 'error' => 'Could not save the uploaded file.');
    }
    @chmod($dir . '/' . $newName, 0644);

    return array('ok' => true, 'path' => 'uploads/' . trim($subdir, '/') . '/' . $newName, 'error' => '');
}

/** Delete an uploaded file (only inside /uploads). */
function deleteUpload($relativePath)
{
    $relativePath = (string) $relativePath;
    if ($relativePath === '' || strpos($relativePath, 'uploads/') !== 0) {
        return false;
    }
    $full = realpath(BASE_PATH . '/' . $relativePath);
    $root = realpath(UPLOAD_PATH);
    if ($full && $root && strpos($full, $root . DIRECTORY_SEPARATOR) === 0 && is_file($full)) {
        return @unlink($full);
    }
    return false;
}

/**
 * Upload a media library file (image, video, document) with strict security validation.
 *
 * @param array $file     Single $_FILES array item (name, type, tmp_name, error, size)
 * @param int   $maxBytes Max file size in bytes (default 50 MB)
 * @return array array('ok'=>bool, 'path'=>string, 'file_name'=>string, 'original_name'=>string, 'file_type'=>string, 'mime_type'=>string, 'file_size'=>int, 'error'=>string)
 */
function uploadMediaItem($file, $maxBytes = 52428800)
{
    if (!isset($file) || !is_array($file)) {
        return array('ok' => false, 'error' => 'No file was provided.');
    }
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return array('ok' => false, 'error' => 'Please select a file to upload.');
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        return array('ok' => false, 'error' => 'Upload failed (server error code ' . (int) $file['error'] . ').');
    }
    if ((int) $file['size'] > $maxBytes) {
        return array('ok' => false, 'error' => 'File exceeds the maximum size limit of ' . round($maxBytes / 1048576) . ' MB.');
    }

    $rawName   = (string) $file['name'];
    $cleanOrig = trim(strip_tags($rawName));
    $cleanOrig = preg_replace('/[^\w\s\.\-\(\)]/u', '', $cleanOrig);
    if ($cleanOrig === '') {
        $cleanOrig = 'media_upload';
    }
    if (mb_strlen($cleanOrig) > 200) {
        $cleanOrig = mb_substr($cleanOrig, 0, 200);
    }

    $ext = strtolower(pathinfo($rawName, PATHINFO_EXTENSION));

    /* Strict blacklist check */
    $blacklist = array('php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'exe', 'sh', 'bash', 'cgi', 'pl', 'py', 'js', 'html', 'htm', 'htaccess');
    if (in_array($ext, $blacklist, true) || strpos($rawName, "\0") !== false) {
        return array('ok' => false, 'error' => 'Executable or scripting file extensions are strictly forbidden.');
    }

    /* Whitelist categories */
    $imageExts    = array('jpg', 'jpeg', 'png', 'webp', 'gif', 'svg');
    $videoExts    = array('mp4', 'webm', 'ogv', 'mov');
    $documentExts = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip');

    $fileType = '';
    $subdir   = '';
    if (in_array($ext, $imageExts, true)) {
        $fileType = 'image';
        $subdir   = 'media/images';
    } elseif (in_array($ext, $videoExts, true)) {
        $fileType = 'video';
        $subdir   = 'media/videos';
    } elseif (in_array($ext, $documentExts, true)) {
        $fileType = 'document';
        $subdir   = 'media/documents';
    } else {
        return array('ok' => false, 'error' => 'File type .' . $ext . ' is not supported in the Media Library.');
    }

    /* MIME verification */
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = array(
            'jpg'  => array('image/jpeg'),
            'jpeg' => array('image/jpeg'),
            'png'  => array('image/png'),
            'webp' => array('image/webp'),
            'gif'  => array('image/gif'),
            'svg'  => array('image/svg+xml', 'text/plain', 'text/xml', 'application/xml'),
            'mp4'  => array('video/mp4', 'application/mp4'),
            'webm' => array('video/webm', 'audio/webm'),
            'ogv'  => array('video/ogg', 'application/ogg'),
            'mov'  => array('video/quicktime'),
            'pdf'  => array('application/pdf'),
            'doc'  => array('application/msword'),
            'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'),
            'xls'  => array('application/vnd.ms-excel'),
            'xlsx' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'),
            'ppt'  => array('application/vnd.ms-powerpoint'),
            'pptx' => array('application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'),
            'txt'  => array('text/plain'),
            'csv'  => array('text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'),
            'zip'  => array('application/zip', 'application/x-zip-compressed'),
        );

        if (isset($allowedMimes[$ext]) && !in_array($mime, $allowedMimes[$ext], true)) {
            return array('ok' => false, 'error' => 'File content (' . $mime . ') does not match extension .' . $ext . '.');
        }
    }
    if ($mime === '') {
        $mime = !empty($file['type']) ? (string) $file['type'] : 'application/octet-stream';
    }

    $targetDir = UPLOAD_PATH . $subdir;
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
        return array('ok' => false, 'error' => 'Upload directory could not be created.');
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = $targetDir . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return array('ok' => false, 'error' => 'Could not save the uploaded file.');
    }
    @chmod($targetPath, 0644);

    return array(
        'ok'            => true,
        'path'          => 'uploads/' . $subdir . '/' . $storedName,
        'file_name'     => $storedName,
        'original_name' => $cleanOrig,
        'file_type'     => $fileType,
        'mime_type'     => $mime,
        'file_size'     => (int) $file['size'],
        'error'         => '',
    );
}
