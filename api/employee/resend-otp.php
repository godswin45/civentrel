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


$apiBaseUrl = getenv('EXPO_PUBLIC_API_BASE_URL') ?: 'https://civentral.tech/api/employee';
$remoteUrl = rtrim($apiBaseUrl, '/') . '/resend-otp.php';

// ── Local OTP Resend ────────────────────────────────────────────────────────
// If login.php generated a local OTP, resend it locally instead of proxying.
if (!empty($_SESSION['otp_pending_email'])) {
    $recipientEmail = $_SESSION['otp_pending_email'];
    $firstName      = $_SESSION['first_name'] ?? 'User';
    $lastName       = $_SESSION['last_name']  ?? '';

    $localOtp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['local_otp_code']    = $localOtp;
    $_SESSION['local_otp_expires'] = time() + 300;

    try {
        require_once __DIR__ . '/../../config/mailer.php';
        $subject  = 'CIVENTRAL - Your Login Verification Code (Resent)';
        $htmlBody = '
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e2e8f0;border-radius:8px;padding:30px;">
            <h2 style="text-align:center;color:#0f172a;margin-top:0;font-size:22px;">CIVENTRAL PORTAL</h2>
            <p style="text-align:center;color:#3b82f6;font-size:11px;font-weight:bold;text-transform:uppercase;margin-bottom:30px;">CALOOCAN MUNICIPAL MANAGEMENT SYSTEM</p>
            <hr style="border:0;border-top:1px solid #e2e8f0;margin:20px 0;">
            <p style="color:#475569;font-size:14px;">Hello <strong>' . $firstName . ' ' . $lastName . '</strong>,</p>
            <p style="color:#475569;font-size:14px;line-height:1.6;">Your requested new two-factor authentication code for CIVENTRAL Portal login is:</p>
            <div style="text-align:center;margin:25px 0;">
                <span style="font-size:42px;font-weight:900;font-family:monospace;letter-spacing:12px;color:#0f172a;background:#f8fafc;padding:16px 24px;border-radius:12px;border:2px solid #e2e8f0;display:inline-block;">' . $localOtp . '</span>
            </div>
            <p style="color:#94a3b8;font-size:12px;text-align:center;">This code expires in <strong>5 minutes</strong>. Do not share it with anyone.</p>
        </div>';
        sendSystemEmail($recipientEmail, "$firstName $lastName", $subject, $htmlBody);
        respond(['status' => 'success', 'message' => 'A new verification code has been sent.']);
    } catch (\Throwable $e) {
        respond(['status' => 'error', 'message' => 'Failed to resend code.'], 500);
    }
}

// Fallback to proxying if no local OTP is pending
$result = proxyRequest($remoteUrl, 'POST', null);

respond($result['body'], $result['code']);
?>
