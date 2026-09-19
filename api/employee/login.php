<?php
// Prevent session lock issues during long DB queries
if (session_status() === PHP_SESSION_NONE) {
    // Scope the session cookie to the app root so it is shared across
    // /api/, /pages/, etc. — not just the current subdirectory.
    $cookiePath = '/';
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        // Detect the app root e.g. "/civentrel/"
        if (preg_match('#^(/[^/]+/civentrel)/#', $_SERVER['SCRIPT_NAME'], $m)) {
            $cookiePath = $m[1] . '/';
        } elseif (preg_match('#^(/civentrel)/#', $_SERVER['SCRIPT_NAME'], $m)) {
            $cookiePath = $m[1] . '/';
        }
    }
    session_set_cookie_params(['path' => $cookiePath, 'httponly' => true, 'samesite' => 'Lax']);
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

// ── Local User Login (for locally-created accounts) ──────────────
try {
    require_once __DIR__ . '/../../config/database.php';
    $db = Database::getInstance();
    $localUser = $db->query(
        "SELECT * FROM local_users WHERE (email = ? OR employee_id = ?) AND status = 'active' LIMIT 1",
        [$employeeIdOrEmail, $employeeIdOrEmail]
    );
    if (!empty($localUser)) {
        $lu = $localUser[0];
        if (password_verify($password, $lu['password'])) {
            // Populate session exactly like live login does
            $_SESSION['user_id']        = $lu['user_id'];
            $_SESSION['employee_id']    = $lu['employee_id'];
            $_SESSION['email']          = $lu['email'];
            $_SESSION['first_name']     = $lu['first_name'];
            $_SESSION['last_name']      = $lu['last_name'];
            $_SESSION['role_id']        = $lu['role_id'];
            $_SESSION['role_name']      = $lu['role_name'];
            $_SESSION['role_prefix']    = $lu['role_prefix'];
            $_SESSION['dept_id']        = $lu['department_id'];
            $_SESSION['is_superadmin']  = (int)$lu['is_superadmin'];
            $_SESSION['is_global_access'] = (int)$lu['is_global_access'];
            $_SESSION['LAST_ACTIVITY']  = time();
            // Cache user details so HeaderService shows real name without remote API call
            $_SESSION['current_user_details'] = [
                'user_id'        => $lu['user_id'],
                'employee_id'    => $lu['employee_id'],
                'first_name'     => $lu['first_name'],
                'middle_name'    => $lu['middle_name'] ?? '',
                'last_name'      => $lu['last_name'],
                'email'          => $lu['email'],
                'mobile_number'  => $lu['mobile_number'] ?? '',
                'role_id'        => $lu['role_id'],
                'role_name'      => $lu['role_name'],
                'role_prefix'    => $lu['role_prefix'],
                'department_id'  => $lu['department_id'],
                'position_name'  => $lu['position_name'] ?? '',
                'is_superadmin'  => (int)$lu['is_superadmin'],
                'is_global_access' => (int)$lu['is_global_access'],
                'profile_picture'=> $lu['profile_picture'] ?? 'default-avatar.png',
            ];
            respond([
                'status' => 'success',
                'message' => 'Login successful.',
                'user' => [
                    'user_id'      => $lu['user_id'],
                    'employee_id'  => $lu['employee_id'],
                    'first_name'   => $lu['first_name'],
                    'last_name'    => $lu['last_name'],
                    'email'        => $lu['email'],
                    'role_id'      => $lu['role_id'],
                    'role_name'    => $lu['role_name'],
                    'role_prefix'  => $lu['role_prefix'],
                    'department_id'=> $lu['department_id'],
                    'position'     => $lu['position_name'],
                    'is_superadmin'=> (int)$lu['is_superadmin'],
                ],
            ]);
        } else {
            respond(['status' => 'error', 'message' => 'Invalid password.'], 401);
        }
    }
} catch (\Throwable $localErr) {
    // local_users table may not exist yet — fall through to live server
}


$result = proxyRequest($remoteUrl, 'POST', [
    'employeeId' => $employeeIdOrEmail,
    'password' => $password,
    'g-recaptcha-response' => $recaptchaToken,
    'recaptchaResponse' => $recaptchaToken
]);

$body = $result['body'] ?? [];

$bodyStatus = $body['status'] ?? null;
if (in_array($bodyStatus, ['success', 'otp_required'], true) && is_array($body)) {
    $user = $body['user'] ?? $body['data'] ?? [];
    if (!empty($user) && is_array($user)) {
        $_SESSION['user_id']      = $user['user_id']     ?? $_SESSION['user_id']     ?? null;
        $_SESSION['employee_id']  = $user['employee_id'] ?? $_SESSION['employee_id'] ?? null;
        $_SESSION['email']        = $user['email']       ?? $_SESSION['email']       ?? null;
        $_SESSION['first_name']   = $user['first_name']  ?? $_SESSION['first_name']  ?? null;
        $_SESSION['last_name']    = $user['last_name']   ?? $_SESSION['last_name']   ?? null;
        $_SESSION['role_id']      = $user['role_id']     ?? $_SESSION['role_id']     ?? null;
        $_SESSION['role_name']    = $user['role_name']   ?? $_SESSION['role_name']   ?? null;
        $_SESSION['role_prefix']  = $user['role_prefix'] ?? $_SESSION['role_prefix'] ?? null;
        $_SESSION['is_superadmin']    = $user['is_superadmin']    ?? $_SESSION['is_superadmin']    ?? 0;
        $_SESSION['is_global_access'] = $user['is_global_access'] ?? $_SESSION['is_global_access'] ?? 0;
        $_SESSION['LAST_ACTIVITY'] = time();

        // Cache user details so HeaderService shows real name after OTP
        $_SESSION['current_user_details'] = [
            'user_id'          => $user['user_id']          ?? null,
            'employee_id'      => $user['employee_id']      ?? null,
            'first_name'       => $user['first_name']       ?? '',
            'middle_name'      => $user['middle_name']      ?? '',
            'last_name'        => $user['last_name']        ?? '',
            'email'            => $user['email']            ?? '',
            'mobile_number'    => $user['mobile_number']    ?? '',
            'role_id'          => $user['role_id']          ?? null,
            'role_name'        => $user['role_name']        ?? 'Staff',
            'role_prefix'      => $user['role_prefix']      ?? 'STF',
            'department_id'    => $user['department_id']    ?? null,
            'department_name'  => $user['department_name']  ?? '',
            'position_name'    => $user['position_name'] ?? ($user['position'] ?? ''),
            'is_superadmin'    => $user['is_superadmin']    ?? 0,
            'is_global_access' => $user['is_global_access'] ?? 0,
            'profile_picture'  => $user['profile_picture']  ?? 'default-avatar.png',
        ];
    }

    // ── LOCAL OTP ENFORCEMENT ─────────────────────────────────────────────
    // If the live server returned 'success' directly (skipped OTP for staff),
    // we enforce our own OTP step locally so ALL accounts require verification.
    if ($bodyStatus === 'success') {
        $recipientEmail = $_SESSION['email'] ?? ($user['email'] ?? null);
        $firstName      = $_SESSION['first_name'] ?? ($user['first_name'] ?? 'User');
        $lastName       = $_SESSION['last_name']  ?? ($user['last_name']  ?? '');

        if (!empty($recipientEmail)) {
            $localOtp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['local_otp_code']    = $localOtp;
            $_SESSION['local_otp_expires'] = time() + 300; // 5 minutes
            $_SESSION['otp_pending_email'] = $recipientEmail;

            // Send OTP email via our SMTP
            try {
                require_once __DIR__ . '/../../config/mailer.php';
                $subject  = 'CIVENTRAL - Your Login Verification Code';
                $htmlBody = '
                <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e2e8f0;border-radius:8px;padding:30px;">
                    <h2 style="text-align:center;color:#0f172a;margin-top:0;font-size:22px;">CIVENTRAL PORTAL</h2>
                    <p style="text-align:center;color:#3b82f6;font-size:11px;font-weight:bold;text-transform:uppercase;margin-bottom:30px;">CALOOCAN MUNICIPAL MANAGEMENT SYSTEM</p>
                    <hr style="border:0;border-top:1px solid #e2e8f0;margin:20px 0;">
                    <p style="color:#475569;font-size:14px;">Hello <strong>' . $firstName . ' ' . $lastName . '</strong>,</p>
                    <p style="color:#475569;font-size:14px;line-height:1.6;">Your two-factor authentication code for CIVENTRAL Portal login is:</p>
                    <div style="text-align:center;margin:25px 0;">
                        <span style="font-size:42px;font-weight:900;font-family:monospace;letter-spacing:12px;color:#0f172a;background:#f8fafc;padding:16px 24px;border-radius:12px;border:2px solid #e2e8f0;display:inline-block;">' . $localOtp . '</span>
                    </div>
                    <p style="color:#94a3b8;font-size:12px;text-align:center;">This code expires in <strong>5 minutes</strong>. Do not share it with anyone.</p>
                </div>';
                sendSystemEmail($recipientEmail, "$firstName $lastName", $subject, $htmlBody);
            } catch (\Throwable $e) {
                // Mailer failure — still continue so account isn't locked out
            }

            // Return otp_required to browser so OTP modal opens
            respond([
                'status'  => 'otp_required',
                'message' => 'A verification code has been sent to your email.',
                'email'   => $recipientEmail,
                'source'  => 'local',
            ]);
        }
    }

    // Live server already returned otp_required — stash email for bypass endpoint
    if ($bodyStatus === 'otp_required' && !empty($body['email'])) {
        $_SESSION['otp_pending_email'] = $body['email'];
    }
}

respond($body, $result['code']);
?>
