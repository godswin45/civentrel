<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header(''Content-Type: application/json; charset=utf-8'');
 = [''http://localhost'',''http://localhost:80'',''http://localhost:3000'',''http://127.0.0.1'',''http://127.0.0.1:80''];
if (isset($_SERVER[''HTTP_ORIGIN''])) {
    $origin = $_SERVER[''HTTP_ORIGIN''];
    if (in_array($origin, $allowedOrigins) || preg_match(''/^http:\\/\\/(localhost|127\\.0\\.0\\.1)(:\\d+)?$/'', $origin)) {
        header(Access-Control-Allow-Origin: {$origin});
        header(''Access-Control-Allow-Credentials: true'');
    }
}
header(''Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS'');
header(''Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With'');
if ($_SERVER[''REQUEST_METHOD''] === ''OPTIONS'') { http_response_code(204); exit; }
require_once __DIR__ . ''/../../config/proxy.php'';
function respond(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}
