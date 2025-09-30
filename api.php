<?php
require_once 'auth.php';
require_once 'upload.php';
require_once 'proxy.php';

// Set JSON header for API responses (except for download_script)
$action = $_GET['action'] ?? '';
if ($action !== 'download_script') {
    header('Content-Type: application/json');
}

switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // Extract honeypot fields
        $honeypot = [
            'website' => $data['website'] ?? '',
            'fullname' => $data['fullname'] ?? ''
        ];

        $result = register($username, $email, $password, $honeypot);
        echo json_encode($result);
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $result = login($username, $password);
        echo json_encode($result);
        break;

    case 'logout':
        $result = logout();
        header('Location: /');
        exit;

    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $file = $_FILES['image'] ?? null;
        $result = uploadImage($file);
        echo json_encode($result);
        break;

    case 'get_api_token':
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $token = getUserApiToken(getCurrentUserId());
        echo json_encode(['success' => true, 'token' => $token]);
        break;

    case 'regenerate_token':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $db = getDB();
        $userId = getCurrentUserId();
        $newToken = generateApiToken();

        $stmt = $db->prepare('UPDATE users SET api_token = :token WHERE id = :user_id');
        $stmt->bindValue(':token', $newToken, SQLITE3_TEXT);
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->execute();

        echo json_encode(['success' => true, 'token' => $newToken]);
        break;

    case 'download_script':
        if (!isLoggedIn()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $token = getUserApiToken(getCurrentUserId());
        $script = generateProxyScript($token);

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="imguruk-proxy.php"');
        echo $script;
        exit;

    case 'get_proxies':
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $proxies = getUserProxies(getCurrentUserId());
        echo json_encode(['success' => true, 'proxies' => $proxies]);
        break;

    case 'add_proxy':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $proxyUrl = $data['proxy_url'] ?? '';

        $result = addProxy(getCurrentUserId(), $proxyUrl);
        echo json_encode($result);
        break;

    case 'toggle_proxy':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $proxyId = $data['proxy_id'] ?? 0;

        $result = toggleProxy($proxyId, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'delete_proxy':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $proxyId = $data['proxy_id'] ?? 0;

        $result = deleteProxy($proxyId, getCurrentUserId());
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}