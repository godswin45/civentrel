<?php
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['employee_id'] ?? 0);

if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'status' => 'unauthenticated',
        'message' => 'Session expired or not logged in'
    ]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'last_activity' => $_SESSION['LAST_ACTIVITY'] ?? time(),
    'remaining' => 300
]);
