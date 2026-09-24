<?php
/**
 * Secure file upload helper - images and PDFs
 */

require_once __DIR__ . '/../config/app.php';

function uploadFile(array $file, string $subdir): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        return null;
    }

    if ($file['size'] > 20 * 1024 * 1024) {
        return null;
    }

    $ext = $allowed[$mime];
    $uploadDir = __DIR__ . '/../assets/uploads/' . $subdir . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $fullPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        return url('assets/uploads/' . $subdir . '/' . $filename);
    }

    return null;
}

/** @deprecated Use uploadFile() */
function uploadImage(array $file, string $subdir): ?string
{
    return uploadFile($file, $subdir);
}
