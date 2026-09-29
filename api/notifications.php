<?php
require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['employee_id'] ?? 0);

if (!$userId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthenticated']);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $limit = (int) ($_GET['limit'] ?? 20);
    try {
        $ns = $notificationService ?? new \App\Services\NotificationService();
        $notifications = $ns->getForUser($userId, $limit);
        $unread = array_filter($notifications, fn($n) => strtolower($n['notification_status'] ?? '') === 'unread');
        echo json_encode([
            'status' => 'success',
            'unread_count' => count($unread),
            'data' => array_values($notifications)
        ]);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'unread_count' => 0, 'data' => [], 'message' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    try {
        $ns = $notificationService ?? new \App\Services\NotificationService();
        if (!empty($input['mark_all'])) {
            $ok = $ns->markAllAsRead($userId);
        } else {
            $notifId = (int) ($input['notification_id'] ?? 0);
            $ok = $notifId ? $ns->markAsRead($notifId, $userId) : false;
        }
        echo json_encode(['status' => $ok ? 'success' : 'error']);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
