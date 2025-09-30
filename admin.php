<?php
require_once 'db.php';

// Check if user is admin
function isAdmin($userId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    return $user && $user['is_admin'] == 1;
}

// Get all users
function getAllUsers() {
    $db = getDB();
    $result = $db->query('SELECT id, username, email, is_admin, is_active, created_at FROM users ORDER BY created_at DESC');

    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }

    return $users;
}

// Get all images
function getAllImages() {
    $db = getDB();
    $result = $db->query('SELECT i.*, u.username FROM images i LEFT JOIN users u ON i.user_id = u.id ORDER BY i.uploaded_at DESC');

    $images = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $images[] = $row;
    }

    return $images;
}

// Get all proxies
function getAllProxies() {
    $db = getDB();
    $result = $db->query('SELECT p.*, u.username FROM user_proxies p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC');

    $proxies = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $proxies[] = $row;
    }

    return $proxies;
}

// Delete image
function deleteImage($imageId, $adminUserId) {
    if (!isAdmin($adminUserId)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    $db = getDB();

    // Get image details
    $stmt = $db->prepare('SELECT filename FROM images WHERE id = :id');
    $stmt->bindValue(':id', $imageId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $image = $result->fetchArray(SQLITE3_ASSOC);

    if (!$image) {
        return ['success' => false, 'error' => 'Image not found'];
    }

    // Delete file
    $filePath = '/www/imguruk.com/web/uploads/' . $image['filename'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete from database
    $deleteStmt = $db->prepare('DELETE FROM images WHERE id = :id');
    $deleteStmt->bindValue(':id', $imageId, SQLITE3_INTEGER);
    $deleteStmt->execute();

    return ['success' => true, 'message' => 'Image deleted'];
}

// Change user password
function changeUserPassword($userId, $newPassword, $adminUserId) {
    if (!isAdmin($adminUserId)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    $db = getDB();
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $stmt->bindValue(':password_hash', $passwordHash, SQLITE3_TEXT);
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Password changed'];
}

// Toggle user active status
function toggleUserActive($userId, $adminUserId) {
    if (!isAdmin($adminUserId)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    // Can't disable own account
    if ($userId == $adminUserId) {
        return ['success' => false, 'error' => 'Cannot disable your own account'];
    }

    $db = getDB();

    $stmt = $db->prepare('SELECT is_active FROM users WHERE id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        return ['success' => false, 'error' => 'User not found'];
    }

    $newStatus = $user['is_active'] ? 0 : 1;

    $updateStmt = $db->prepare('UPDATE users SET is_active = :status WHERE id = :id');
    $updateStmt->bindValue(':status', $newStatus, SQLITE3_INTEGER);
    $updateStmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $updateStmt->execute();

    return ['success' => true, 'is_active' => $newStatus];
}

// Toggle user admin status
function toggleUserAdmin($userId, $adminUserId) {
    if (!isAdmin($adminUserId)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    // Can't demote yourself
    if ($userId == $adminUserId) {
        return ['success' => false, 'error' => 'Cannot change your own admin status'];
    }

    $db = getDB();

    $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = :id');
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        return ['success' => false, 'error' => 'User not found'];
    }

    $newStatus = $user['is_admin'] ? 0 : 1;

    $updateStmt = $db->prepare('UPDATE users SET is_admin = :status WHERE id = :id');
    $updateStmt->bindValue(':status', $newStatus, SQLITE3_INTEGER);
    $updateStmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $updateStmt->execute();

    return ['success' => true, 'is_admin' => $newStatus];
}

// Delete user
function deleteUser($userId, $adminUserId) {
    if (!isAdmin($adminUserId)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    // Can't delete yourself
    if ($userId == $adminUserId) {
        return ['success' => false, 'error' => 'Cannot delete your own account'];
    }

    $db = getDB();

    // Delete user's images
    $stmt = $db->prepare('SELECT filename FROM images WHERE user_id = :user_id');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    while ($image = $result->fetchArray(SQLITE3_ASSOC)) {
        $filePath = '/www/imguruk.com/web/uploads/' . $image['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Delete from database (cascade will handle images and proxies)
    $db->exec("DELETE FROM images WHERE user_id = $userId");
    $db->exec("DELETE FROM user_proxies WHERE user_id = $userId");
    $db->exec("DELETE FROM users WHERE id = $userId");

    return ['success' => true, 'message' => 'User deleted'];
}

// Delete proxy (admin version)
function deleteProxyAdmin($proxyId, $adminUserId) {
    if (!isAdmin($adminUserId)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    $db = getDB();
    $stmt = $db->prepare('DELETE FROM user_proxies WHERE id = :id');
    $stmt->bindValue(':id', $proxyId, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Proxy deleted'];
}