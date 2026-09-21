<?php
$_SERVER['REQUEST_METHOD'] = 'PUT';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$body = '{"user_id":"LOCAL-B6FE81DEC025", "status":"active", "feature_permissions":["test"]}';
// Mock php://input
file_put_contents('php://memory', $body);
// It's hard to mock php://input, let's just assign $body manually in users.php?
// Wait, users.php reads file_get_contents('php://input').
// Let's just create a temporary file and read from it in users.php? No, we can't edit users.php just for testing.
// Let's try to just capture any parse errors.
try {
    require_once __DIR__ . '/api/employee/users.php';
} catch (\Throwable $e) {
    echo "Caught: " . $e->getMessage();
}
