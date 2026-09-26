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
