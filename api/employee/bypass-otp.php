<?php
/**
 * DEV-ONLY: OTP Bypass endpoint
 *
 * This file MUST be removed or disabled before production deployment.
 * It is already hard-blocked for non-localhost requests as a safety net.
 *
 * Strategy (two-tier):
 *  1. Try fetching the full profile from the remote API using the remote
 *     PHPSESSID captured during login — works if the remote server allows it.
 *  2. If that fails (remote blocks profile until OTP is done), fall back to
 *     the email stashed in $_SESSION['otp_pending_email'] and build a minimal
 *     but valid local session so isLoggedIn() returns true.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// ── Safety: only works on localhost ──────────────────────────────────────────
$host       = $_SERVER['HTTP_HOST']   ?? '';
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal    = in_array($host, ['localhost', '127.0.0.1', 'localhost:80', '127.0.0.1:80'], true)
           || preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host)
           || in_array($remoteAddr, ['127.0.0.1', '::1'], true);

if (!$isLocal) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'This endpoint is disabled in production.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../../config/proxy.php';

$apiBaseUrl = getenv('EXPO_PUBLIC_API_BASE_URL') ?: 'https://civentral.tech/api/employee';
$sessionEstablished = false;

// ── Tier 1: Try remote get-profile.php ───────────────────────────────────────
if (!empty($_SESSION['remote_phpsessid'])) {
    $profileUrl    = rtrim($apiBaseUrl, '/') . '/get-profile.php';
    $profileResult = proxyRequest($profileUrl, 'GET', null);

    if (!empty($profileResult['body']) && $profileResult['code'] === 200) {
        $body = $profileResult['body'];
        $user = $body['data'] ?? $body['user'] ?? null;

        if (!empty($user) && is_array($user)) {
            $_SESSION['user_id']              = $user['user_id']     ?? $user['id']      ?? null;
            $_SESSION['employee_id']          = $user['employee_id'] ?? $user['user_id'] ?? null;
            $_SESSION['email']                = $user['email']       ?? null;
            $_SESSION['first_name']           = $user['first_name']  ?? null;
            $_SESSION['last_name']            = $user['last_name']   ?? null;
            $_SESSION['role_id']              = $user['role_id']     ?? null;
            $_SESSION['current_user_details'] = $user;
            $_SESSION['LAST_ACTIVITY']        = time();
            $sessionEstablished = true;
        }
    }
}

// ── Tier 2: Fallback — use the email stashed during login ────────────────────
if (!$sessionEstablished) {
    $pendingEmail = $_SESSION['otp_pending_email'] ?? null;

    if (empty($pendingEmail)) {
        http_response_code(401);
        echo json_encode([
            'status'  => 'error',
            'message' => 'No pending login session found. Please log in again.'
        ]);
        exit;
    }

    // Build a minimal session. isLoggedIn() only needs employee_id or user_id
    // to be non-empty. HeaderService falls back gracefully when profile is thin.
    $_SESSION['employee_id']          = $pendingEmail;
    $_SESSION['user_id']              = $pendingEmail;
    $_SESSION['email']                = $pendingEmail;
    $_SESSION['first_name']           = 'Dev';
    $_SESSION['last_name']            = 'User';
    $_SESSION['role_id']              = 1;
    $_SESSION['current_user_details'] = [
        'employee_id'    => $pendingEmail,
        'user_id'        => $pendingEmail,
        'email'          => $pendingEmail,
        'first_name'     => 'Dev',
        'last_name'      => 'User',
        'full_name'      => 'Dev User',
        'initials'       => 'DU',
        // These fields are read by HeaderService to set is_superadmin = true
        'role_name'      => 'Super Administrator',
        'role_prefix'    => 'SADM',
        'is_superadmin'  => true,
        'is_global_access' => true,
        'profile_picture'  => 'default-avatar.png',
    ];
    $_SESSION['LAST_ACTIVITY'] = time();
    $sessionEstablished = true;
}

unset($_SESSION['otp_pending_email']); // clean up

http_response_code(200);
echo json_encode([
    'status'  => 'success',
    'message' => 'Session established via OTP bypass (dev mode).',
]);
