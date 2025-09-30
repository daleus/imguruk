<?php
require_once 'db.php';
require_once 'auth.php';

// Upload image
function uploadImage($file) {
    // Check if user is logged in
    if (!isLoggedIn()) {
        return ['success' => false, 'error' => 'Must be logged in to upload'];
    }

    // Validate file
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error'];
    }

    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File too large (max 10MB)'];
    }

    // Get file extension
    $originalFilename = $file['name'];
    $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

    // Validate extension
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    if (!in_array($extension, $allowedExtensions)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }

    // Verify it's actually an image
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'error' => 'File is not a valid image'];
    }

    $db = getDB();
    $userId = getCurrentUserId();

    // Insert into database first to get the ID
    $stmt = $db->prepare('INSERT INTO images (user_id, filename, original_filename, file_size) VALUES (:user_id, :filename, :original_filename, :file_size)');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':filename', 'temp', SQLITE3_TEXT);
    $stmt->bindValue(':original_filename', $originalFilename, SQLITE3_TEXT);
    $stmt->bindValue(':file_size', $file['size'], SQLITE3_INTEGER);

    if (!$stmt->execute()) {
        return ['success' => false, 'error' => 'Database error'];
    }

    // Get the inserted ID
    $imageId = $db->lastInsertRowID();

    // Generate filename with base62 encoding
    $filename = generateFilename($imageId, $extension);
    $uploadPath = '/www/imguruk.com/web/uploads/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Rollback: delete database entry
        $db->exec("DELETE FROM images WHERE id = $imageId");
        return ['success' => false, 'error' => 'Failed to save file'];
    }

    // Update database with actual filename
    $updateStmt = $db->prepare('UPDATE images SET filename = :filename WHERE id = :id');
    $updateStmt->bindValue(':filename', $filename, SQLITE3_TEXT);
    $updateStmt->bindValue(':id', $imageId, SQLITE3_INTEGER);
    $updateStmt->execute();

    return [
        'success' => true,
        'filename' => $filename,
        'url' => 'https://i.imguruk.com/' . $filename
    ];
}