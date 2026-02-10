<?php
/**
 * Food Ordering System - Image Upload Helper
 * Handles secure file uploads for products and user avatars.
 *
 * @package FoodOrderingSystem
 * @subpackage Utils
 */

declare(strict_types=1);

/**
 * Upload an image file to a specified destination
 *
 * @param array  $file        The file array from $_FILES['key']
 * @param string $destination Relative path from backend root (e.g., 'uploads/products')
 * @return string The public URL path to the uploaded file (e.g., '/uploads/products/123.jpg')
 * @throws Exception If upload fails or validation errors occur
 */
function uploadImage(array $file, string $destination = 'uploads/products'): string
{
    // 1. Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(resolveUploadError($file['error']));
    }

    // 2. Validate file size (max 2MB)
    const MAX_SIZE = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > MAX_SIZE) {
        throw new Exception('File size exceeds the maximum limit of 2MB');
    }

    // 3. Validate MIME type
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedMimes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and WebP are allowed.');
    }

    // 4. Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($extension)) {
        // Fallback extension based on mime
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];
        $extension = $extensions[$mimeType] ?? 'jpg';
    }
    
    $filename = uniqid('img_', true) . '.' . $extension;

    // 5. Prepare target directory
    // Resolution: backend root is one level up from utils
    $backendRoot = dirname(__DIR__); 
    $targetDir = $backendRoot . '/' . trim($destination, '/');

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    $targetPath = $targetDir . '/' . $filename;

    // 6. Move file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Return relative path for database storage (accessible via web)
    // Assuming backend/ is the root or aliased properly
    return '/' . trim($destination, '/') . '/' . $filename;
}

/**
 * Resolve PHP upload error codes to messages
 */
function resolveUploadError(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE directive in form',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload',
        default               => 'Unknown upload error',
    };
}
