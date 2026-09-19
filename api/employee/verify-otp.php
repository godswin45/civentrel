<?php
// Prevent session lock issues during long DB queries
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// 1. Dynamic CORS Configuration
$allowedOrigins = [
    'http://localhost',
    'http://localhost:80',
    'http://localhost:3000',
    'http://127.0.0.1',
    'http://127.0.0.1:80'
];

if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    if (in_array($origin, $allowedOrigins) || preg_match('/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/', $origin)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
    }
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Preflight Handling
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../config/proxy.php';

// Response Helper
function respond(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    respond([
        'status' => 'error',
        'message' => 'Method Not Allowed.'
    ], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$otpCode = trim($input['otp'] ?? $input['otp_code'] ?? '');

if (empty($otpCode)) {
    respond([
        'status'  => 'error',
        'message' => 'Please enter a valid 6-digit verification code.'
    ], 400);
}

// ── Local OTP verification (for staff accounts) ───────────────────────────
// When login.php generated a local OTP (source:'local'), verify it here.
if (!empty($_SESSION['local_otp_code'])) {
    $storedOtp   = $_SESSION['local_otp_code'];
    $otpExpires  = $_SESSION['local_otp_expires'] ?? 0;

    if (time() > $otpExpires) {
        unset($_SESSION['local_otp_code'], $_SESSION['local_otp_expires']);
        respond(['status' => 'error', 'message' => 'Verification code has expired. Please log in again.'], 401);
    }

    if ($otpCode !== $storedOtp) {
        respond(['status' => 'error', 'message' => 'Invalid verification code. Please try again.'], 401);
    }

    // OTP is correct — clean up and confirm session is active
    unset($_SESSION['local_otp_code'], $_SESSION['local_otp_expires'], $_SESSION['otp_pending_email']);
    $_SESSION['LAST_ACTIVITY'] = time();

    respond(['status' => 'success', 'message' => 'Login verified successfully.']);
}

// ── Live server OTP verification (for Super Admin accounts) ──────────────
$apiBaseUrl = getenv('EXPO_PUBLIC_API_BASE_URL') ?: 'https://civentral.tech/api/employee';
$remoteUrl = rtrim($apiBaseUrl, '/') . '/verify-otp.php';

$result = proxyRequest($remoteUrl, 'POST', [
    'otp' => $otpCode
]);

if (isset($result['body']['status']) && $result['body']['status'] === 'success') {
    $user = $result['body']['user'] ?? [];
    $_SESSION['user_id']      = $user['user_id']     ?? null;
    $_SESSION['employee_id']  = $user['employee_id'] ?? null;
    $_SESSION['email']        = $user['email']        ?? ($user['employee_id'] ?? null);
    $_SESSION['first_name']   = $user['first_name']  ?? null;
    $_SESSION['last_name']    = $user['last_name']   ?? null;
    $_SESSION['role_id']      = $user['role_id']     ?? null;
    $_SESSION['LAST_ACTIVITY'] = time();

    // Fetch full profile details from get-profile.php
    $profileUrl    = rtrim($apiBaseUrl, '/') . '/get-profile.php';
    $profileResult = proxyRequest($profileUrl, 'GET', null);
    if (isset($profileResult['body']['status']) && $profileResult['body']['status'] === 'success') {
        $_SESSION['current_user_details'] = $profileResult['body']['data'];
    }
}

respond($result['body'], $result['code']);
?>
