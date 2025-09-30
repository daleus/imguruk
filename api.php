<?php
require_once 'auth.php';
require_once 'upload.php';
require_once 'proxy.php';
require_once 'admin.php';

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

    // Admin endpoints
    case 'admin_get_users':
        if (!isLoggedIn() || !isAdmin(getCurrentUserId())) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $users = getAllUsers();
        echo json_encode(['success' => true, 'users' => $users]);
        break;

    case 'admin_get_images':
        if (!isLoggedIn() || !isAdmin(getCurrentUserId())) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $images = getAllImages();
        echo json_encode(['success' => true, 'images' => $images]);
        break;

    case 'admin_get_proxies':
        if (!isLoggedIn() || !isAdmin(getCurrentUserId())) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $proxies = getAllProxies();
        echo json_encode(['success' => true, 'proxies' => $proxies]);
        break;

    case 'admin_delete_image':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $imageId = $data['image_id'] ?? 0;

        $result = deleteImage($imageId, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'admin_change_password':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['user_id'] ?? 0;
        $newPassword = $data['new_password'] ?? '';

        $result = changeUserPassword($userId, $newPassword, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'admin_toggle_active':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['user_id'] ?? 0;

        $result = toggleUserActive($userId, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'admin_toggle_admin':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['user_id'] ?? 0;

        $result = toggleUserAdmin($userId, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'admin_delete_user':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['user_id'] ?? 0;

        $result = deleteUser($userId, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'admin_delete_proxy':
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

        $result = deleteProxyAdmin($proxyId, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'admin_get_todos':
        if (!isLoggedIn() || !isAdmin(getCurrentUserId())) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $todos = getAllTodos();
        echo json_encode(['success' => true, 'todos' => $todos]);
        break;

    case 'admin_create_todo':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';

        $result = createTodo($title, $description, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'admin_update_todo_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $todoId = $data['todo_id'] ?? 0;
        $status = $data['status'] ?? '';

        $result = updateTodoStatus($todoId, $status, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'admin_update_todo':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $todoId = $data['todo_id'] ?? 0;
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        $assignedTo = $data['assigned_to'] ?? null;

        $result = updateTodo($todoId, $title, $description, $assignedTo, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'admin_delete_todo':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $todoId = $data['todo_id'] ?? 0;

        $result = deleteTodo($todoId, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'admin_check_proxy_health':
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

        $result = checkProxyHealth($proxyId, getCurrentUserId());
        echo json_encode($result);
        break;

    case 'admin_check_all_proxies_health':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in']);
            exit;
        }

        $result = checkAllProxiesHealth(getCurrentUserId());
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}