<?php

/**
 * Session Bridge for JWT Authentication
 * Converts JWT token to PHP session for legacy views
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Janstro\InventorySystem\Utils\JWT;

session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token required']);
    exit;
}

try {
    $decoded = JWT::validate($input['token']);

    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit;
    }

    // Populate session
    $_SESSION['user_id'] = $decoded->user_id;
    $_SESSION['username'] = $decoded->username;
    $_SESSION['role'] = $decoded->role;
    $_SESSION['role_id'] = $decoded->role_id;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    echo json_encode([
        'success' => true,
        'message' => 'Session created',
        'session_id' => session_id()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    error_log("Session creation error: " . $e->getMessage());
}
