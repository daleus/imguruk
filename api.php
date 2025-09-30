<?php
require_once 'auth.php';
require_once 'upload.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

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

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}