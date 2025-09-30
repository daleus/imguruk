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

// Get all admin todos
function getAllTodos() {
    $db = getDB();
    $result = $db->query('
        SELECT t.*,
               u1.username as created_by_name,
               u2.username as assigned_to_name
        FROM admin_todos t
        LEFT JOIN users u1 ON t.created_by = u1.id
        LEFT JOIN users u2 ON t.assigned_to = u2.id
        ORDER BY t.created_at DESC
    ');

    $todos = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $todos[] = $row;
    }

    return $todos;
}

// Create todo
function createTodo($title, $description, $createdBy) {
    if (!isAdmin($createdBy)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    if (empty($title)) {
        return ['success' => false, 'error' => 'Title is required'];
    }

    $db = getDB();
    $stmt = $db->prepare('INSERT INTO admin_todos (title, description, created_by) VALUES (:title, :description, :created_by)');
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $stmt->bindValue(':created_by', $createdBy, SQLITE3_INTEGER);

    try {
        $stmt->execute();
        return ['success' => true, 'message' => 'Todo created', 'id' => $db->lastInsertRowID()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to create todo'];
    }
}

// Update todo status
function updateTodoStatus($todoId, $status, $adminUserId) {
    if (!isAdmin($adminUserId)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    $allowedStatuses = ['todo', 'in_progress', 'done'];
    if (!in_array($status, $allowedStatuses)) {
        return ['success' => false, 'error' => 'Invalid status'];
    }

    $db = getDB();
    $stmt = $db->prepare('UPDATE admin_todos SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->bindValue(':id', $todoId, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Status updated'];
}

// Update todo
function updateTodo($todoId, $title, $description, $assignedTo, $adminUserId) {
    if (!isAdmin($adminUserId)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    $db = getDB();
    $stmt = $db->prepare('UPDATE admin_todos SET title = :title, description = :description, assigned_to = :assigned_to, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $stmt->bindValue(':assigned_to', $assignedTo ? $assignedTo : null, SQLITE3_INTEGER);
    $stmt->bindValue(':id', $todoId, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Todo updated'];
}

// Delete todo
function deleteTodo($todoId, $adminUserId) {
    if (!isAdmin($adminUserId)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    $db = getDB();
    $stmt = $db->prepare('DELETE FROM admin_todos WHERE id = :id');
    $stmt->bindValue(':id', $todoId, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Todo deleted'];
}

// Check proxy health
function checkProxyHealth($proxyId, $adminUserId) {
    if (!isAdmin($adminUserId)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    $db = getDB();

    // Get proxy details
    $stmt = $db->prepare('SELECT p.*, u.api_token FROM user_proxies p JOIN users u ON p.user_id = u.id WHERE p.id = :id');
    $stmt->bindValue(':id', $proxyId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $proxy = $result->fetchArray(SQLITE3_ASSOC);

    if (!$proxy) {
        return ['success' => false, 'error' => 'Proxy not found'];
    }

    // Build health check URL
    $healthUrl = rtrim($proxy['proxy_url'], '/') . '?health=check';

    // Perform health check
    $startTime = microtime(true);
    $ch = curl_init($healthUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Token: ' . $proxy['api_token']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseTime = round((microtime(true) - $startTime) * 1000); // Convert to ms
    curl_close($ch);

    // Determine health status
    $healthStatus = 'unhealthy';
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['status']) && $data['status'] === 'healthy') {
            $healthStatus = 'healthy';
        }
    }

    // Update proxy health in database
    $updateStmt = $db->prepare('UPDATE user_proxies SET health_status = :status, response_time = :response_time, last_check = CURRENT_TIMESTAMP WHERE id = :id');
    $updateStmt->bindValue(':status', $healthStatus, SQLITE3_TEXT);
    $updateStmt->bindValue(':response_time', $responseTime, SQLITE3_INTEGER);
    $updateStmt->bindValue(':id', $proxyId, SQLITE3_INTEGER);
    $updateStmt->execute();

    return [
        'success' => true,
        'health_status' => $healthStatus,
        'response_time' => $responseTime,
        'http_code' => $httpCode
    ];
}

// Check all proxies health
function checkAllProxiesHealth($adminUserId) {
    if (!isAdmin($adminUserId)) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    $db = getDB();
    $result = $db->query('SELECT id FROM user_proxies');

    $results = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $checkResult = checkProxyHealth($row['id'], $adminUserId);
        $results[] = [
            'proxy_id' => $row['id'],
            'result' => $checkResult
        ];
    }

    return ['success' => true, 'results' => $results];
}

// Get proxy request logs
function getProxyLogs($limit = 100, $proxyId = null) {
    $logFile = '/www/imguruk.com/log/proxy_requests.log';

    if (!file_exists($logFile)) {
        return ['success' => true, 'logs' => []];
    }

    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return ['success' => false, 'error' => 'Failed to read log file'];
    }

    // Reverse to get newest first
    $lines = array_reverse($lines);

    $logs = [];
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            // Filter by proxy_id if specified
            if ($proxyId !== null && $entry['proxy_id'] != $proxyId) {
                continue;
            }

            $logs[] = $entry;

            // Stop if we've hit the limit
            if (count($logs) >= $limit) {
                break;
            }
        }
    }

    return ['success' => true, 'logs' => $logs];
}