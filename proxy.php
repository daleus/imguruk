<?php
require_once 'db.php';

// Generate the proxy script for a user
function generateProxyScript($apiToken) {
    $script = <<<'PHP'
<?php
// ImgurUK Contributor Proxy Script
// This script proxies imgur content for the ImgurUK network

define('API_TOKEN', '%%TOKEN%%');
define('ALLOWED_REFERER', 'imguruk.com');

// Health check endpoint
if (isset($_GET['health']) && $_GET['health'] === 'check') {
    $authToken = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if ($authToken === API_TOKEN) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'healthy',
            'timestamp' => time(),
            'version' => '1.0'
        ]);
        exit;
    } else {
        http_response_code(403);
        exit;
    }
}

// Validate the request
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$authToken = $_SERVER['HTTP_X_API_TOKEN'] ?? '';

// Check referer contains imguruk.com
if (strpos($referer, ALLOWED_REFERER) === false) {
    http_response_code(403);
    exit;
}

// Validate API token
if ($authToken !== API_TOKEN) {
    http_response_code(403);
    exit;
}

// Get requested file
$uri = $_SERVER['REQUEST_URI'];
$pathInfo = pathinfo($uri);

// Only allow image files
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
$extension = strtolower($pathInfo['extension'] ?? '');

if (!in_array($extension, $allowedExtensions)) {
    http_response_code(404);
    exit;
}

// Build imgur URL
$filename = basename($uri);
$imgurUrl = 'https://i.imgur.com/' . $filename;

// Fetch from imgur with proper user agent
$ch = curl_init($imgurUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    curl_close($ch);
    exit;
}

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

// Parse and send Content-Type header
if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $matches)) {
    header('Content-Type: ' . trim($matches[1]));
}

// Output the content
echo $body;
PHP;

    return str_replace('%%TOKEN%%', $apiToken, $script);
}

// Get user's proxies
function getUserProxies($userId) {
    $db = getDB();

    $stmt = $db->prepare('SELECT id, proxy_url, is_active, last_used, created_at FROM user_proxies WHERE user_id = :user_id ORDER BY created_at DESC');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $proxies = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $proxies[] = $row;
    }

    return $proxies;
}

// Add new proxy
function addProxy($userId, $proxyUrl) {
    // Validate URL
    if (!filter_var($proxyUrl, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'error' => 'Invalid URL'];
    }

    // Check if URL contains imguruk.com (prevent loops)
    if (strpos($proxyUrl, 'imguruk.com') !== false) {
        return ['success' => false, 'error' => 'Cannot use imguruk.com as proxy'];
    }

    $db = getDB();

    $stmt = $db->prepare('INSERT INTO user_proxies (user_id, proxy_url) VALUES (:user_id, :proxy_url)');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':proxy_url', $proxyUrl, SQLITE3_TEXT);

    try {
        $stmt->execute();
        return ['success' => true, 'message' => 'Proxy added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to add proxy'];
    }
}

// Toggle proxy active status
function toggleProxy($proxyId, $userId) {
    $db = getDB();

    // Verify ownership
    $stmt = $db->prepare('SELECT is_active FROM user_proxies WHERE id = :id AND user_id = :user_id');
    $stmt->bindValue(':id', $proxyId, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $proxy = $result->fetchArray(SQLITE3_ASSOC);

    if (!$proxy) {
        return ['success' => false, 'error' => 'Proxy not found'];
    }

    $newStatus = $proxy['is_active'] ? 0 : 1;

    $updateStmt = $db->prepare('UPDATE user_proxies SET is_active = :status WHERE id = :id');
    $updateStmt->bindValue(':status', $newStatus, SQLITE3_INTEGER);
    $updateStmt->bindValue(':id', $proxyId, SQLITE3_INTEGER);
    $updateStmt->execute();

    return ['success' => true, 'is_active' => $newStatus];
}

// Delete proxy
function deleteProxy($proxyId, $userId) {
    $db = getDB();

    $stmt = $db->prepare('DELETE FROM user_proxies WHERE id = :id AND user_id = :user_id');
    $stmt->bindValue(':id', $proxyId, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    return ['success' => true, 'message' => 'Proxy deleted'];
}