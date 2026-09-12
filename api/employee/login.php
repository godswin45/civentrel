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

$employeeIdOrEmail = trim($input['employeeId'] ?? $input['email'] ?? $input['username'] ?? '');
$password = trim($input['password'] ?? '');
$recaptchaToken = trim($input['g-recaptcha-response'] ?? $input['recaptchaResponse'] ?? '');
$recaptchaSecret = getenv('RECAPTCHA_SECRET_KEY') ?: '';

if (empty($employeeIdOrEmail) || empty($password)) {
    respond([
        'status' => 'error',
        'message' => 'Please provide both Employee ID / Email and Password.'
    ], 400);
}

if (empty($recaptchaToken)) {
    // Allow localhost to skip reCAPTCHA (dev convenience — production still requires it)
    $host       = $_SERVER['HTTP_HOST']   ?? '';
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocal    = in_array($remoteAddr, ['127.0.0.1', '::1'], true)
               || preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host);

    if (!$isLocal) {
        respond([
            'status'  => 'error',
            'message' => 'reCAPTCHA verification is required. Please check the robot checkbox.'
        ], 400);
    }
    // On localhost: continue without reCAPTCHA token
}

// Verify token with Google API if secret key is present
if (!empty($recaptchaSecret)) {
    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    $postData = http_build_query([
        'secret'   => $recaptchaSecret,
        'response' => $recaptchaToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $verifyOptions = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                         "Content-Length: " . strlen($postData) . "\r\n",
            'content' => $postData,
            'timeout' => 5
        ]
    ];

    $context = stream_context_create($verifyOptions);
    $verifyResponse = @file_get_contents($verifyUrl, false, $context);

    if ($verifyResponse !== false) {
        $responseData = json_decode($verifyResponse, true);
        if (empty($responseData['success'])) {
            respond([
                'status' => 'error',
                'message' => 'reCAPTCHA verification failed. Please try again.'
            ], 400);
        }
    }
}

// System Maintenance Check
if (strtolower($employeeIdOrEmail) === 'maintenance') {
    respond([
        'status' => 'maintenance',
        'message' => 'System maintenance is scheduled for Sunday, 11:00 PM–1:00 AM. Save drafts before then.'
    ], 503);
}

$apiBaseUrl = getenv('EXPO_PUBLIC_API_BASE_URL') ?: 'https://civentral.tech/api/employee';
$remoteUrl = rtrim($apiBaseUrl, '/') . '/login.php';

$result = proxyRequest($remoteUrl, 'POST', [
    'employeeId' => $employeeIdOrEmail,
    'password' => $password,
    'g-recaptcha-response' => $recaptchaToken,
    'recaptchaResponse' => $recaptchaToken
]);

$body = $result['body'] ?? [];

// Populate session for both 'success' and 'otp_required' statuses.
// On 'otp_required' the remote API already validated credentials and returns
// the same user payload — we need the session set so that bypassing OTP in
// the frontend still results in a valid session on the dashboard.
$bodyStatus = $body['status'] ?? null;
if (in_array($bodyStatus, ['success', 'otp_required'], true) && is_array($body)) {
    $user = $body['user'] ?? $body['data'] ?? [];
    if (!empty($user) && is_array($user)) {
        $_SESSION['user_id']     = $user['user_id']     ?? $_SESSION['user_id']     ?? null;
        $_SESSION['employee_id'] = $user['employee_id'] ?? $_SESSION['employee_id'] ?? null;
        $_SESSION['email']       = $user['email']       ?? $_SESSION['email']       ?? null;
        $_SESSION['first_name']  = $user['first_name']  ?? $_SESSION['first_name']  ?? null;
        $_SESSION['last_name']   = $user['last_name']   ?? $_SESSION['last_name']   ?? null;
        $_SESSION['role_id']     = $user['role_id']     ?? $_SESSION['role_id']     ?? null;
        $_SESSION['LAST_ACTIVITY'] = time();
    }

    // Stash email for the OTP bypass endpoint fallback (dev mode).
    // The otp_required response always includes the email even when no user object is present.
    if ($bodyStatus === 'otp_required' && !empty($body['email'])) {
        $_SESSION['otp_pending_email'] = $body['email'];
    }
}

respond($body, $result['code']);
?>
