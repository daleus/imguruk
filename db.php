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