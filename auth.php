<?php
require_once 'db.php';

session_start();

// Register a new user
function register($username, $email, $password, $honeypot = []) {
    $db = getDB();

    // Check honeypot fields - if any are filled, reject silently
    if (!empty($honeypot['website']) || !empty($honeypot['fullname'])) {
        return ['success' => false, 'error' => 'Invalid submission'];
    }

    // Validate input
    if (empty($username) || empty($email) || empty($password)) {
        return ['success' => false, 'error' => 'All fields are required'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address'];
    }

    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters'];
    }

    // Hash password and generate API token
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $apiToken = generateApiToken();

    // Insert user
    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, api_token) VALUES (:username, :email, :password_hash, :api_token)');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':password_hash', $passwordHash, SQLITE3_TEXT);
    $stmt->bindValue(':api_token', $apiToken, SQLITE3_TEXT);

    try {
        $stmt->execute();
        $userId = $db->lastInsertRowID();

        // Auto-login after registration
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;

        return ['success' => true, 'message' => 'Registration successful'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Username or email already exists'];
    }
}

// Login user
function login($username, $password) {
    $db = getDB();

    $stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = :username');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        return ['success' => true, 'message' => 'Login successful'];
    }

    return ['success' => false, 'error' => 'Invalid username or password'];
}

// Logout user
function logout() {
    session_destroy();
    return ['success' => true, 'message' => 'Logged out'];
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}