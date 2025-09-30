<?php
// Database connection
function getDB() {
    $db = new SQLite3('/www/imguruk.com/imguruk.db');

    // Create users table
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        api_token TEXT,
        is_admin INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    // Create images table
    $db->exec('CREATE TABLE IF NOT EXISTS images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        filename TEXT UNIQUE NOT NULL,
        original_filename TEXT NOT NULL,
        file_size INTEGER NOT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // Create user_proxies table
    $db->exec('CREATE TABLE IF NOT EXISTS user_proxies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        proxy_url TEXT NOT NULL,
        is_active INTEGER DEFAULT 1,
        last_used DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    return $db;
}

// Base62 encoding for short filenames
function base62Encode($num) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $base = strlen($chars);
    $encoded = '';

    while ($num > 0) {
        $remainder = $num % $base;
        $encoded = $chars[$remainder] . $encoded;
        $num = floor($num / $base);
    }

    return $encoded ?: '0';
}

// Generate unique filename with uk- prefix
function generateFilename($imageId, $extension) {
    $encoded = base62Encode($imageId);
    return 'uk-' . $encoded . '.' . $extension;
}

// Generate API token for user
function generateApiToken() {
    return bin2hex(random_bytes(32));
}

// Get or create API token for user
function getUserApiToken($userId) {
    $db = getDB();

    $stmt = $db->prepare('SELECT api_token FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if ($user && !empty($user['api_token'])) {
        return $user['api_token'];
    }

    // Generate new token
    $token = generateApiToken();
    $updateStmt = $db->prepare('UPDATE users SET api_token = :token WHERE id = :user_id');
    $updateStmt->bindValue(':token', $token, SQLITE3_TEXT);
    $updateStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $updateStmt->execute();

    return $token;
}

// Validate API token
function validateApiToken($token) {
    $db = getDB();

    $stmt = $db->prepare('SELECT id, username FROM users WHERE api_token = :token');
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    return $user ?: false;
}

// Get random active proxy
function getRandomProxy() {
    $db = getDB();

    $result = $db->query('SELECT id, proxy_url FROM user_proxies WHERE is_active = 1 ORDER BY RANDOM() LIMIT 1');
    $proxy = $result->fetchArray(SQLITE3_ASSOC);

    if ($proxy) {
        // Update last_used timestamp
        $updateStmt = $db->prepare('UPDATE user_proxies SET last_used = CURRENT_TIMESTAMP WHERE id = :id');
        $updateStmt->bindValue(':id', $proxy['id'], SQLITE3_INTEGER);
        $updateStmt->execute();
    }

    return $proxy ?: false;
}