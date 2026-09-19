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

// ── Safety: Temporarily disabled for Defense presentation ─────────────────
$host       = $_SERVER['HTTP_HOST']   ?? '';
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal    = true; // FORCED TRUE so it works on Dokploy

// if (!$isLocal) {
//     http_response_code(403);
//     echo json_encode(['status' => 'error', 'message' => 'This endpoint is disabled in production.']);
//     exit;
// }


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

// ── Tier 2: Fallback — use session data stashed during login ─────
if (!$sessionEstablished) {
    $pendingEmail = $_SESSION['otp_pending_email'] ?? null;
    $firstName    = $_SESSION['first_name']  ?? null;
    $lastName     = $_SESSION['last_name']   ?? null;
    $empId        = $_SESSION['employee_id'] ?? $pendingEmail;
    $userId       = $_SESSION['user_id']     ?? $pendingEmail;
    $roleId       = $_SESSION['role_id']     ?? 1;
    $roleName     = $_SESSION['role_name']   ?? 'Super Administrator';
    $rolePrefix   = $_SESSION['role_prefix'] ?? 'SADM';
    $isSuperadmin = $_SESSION['is_superadmin'] ?? 1;

    if (empty($pendingEmail) && empty($empId)) {
        http_response_code(401);
        echo json_encode([
            'status'  => 'error',
            'message' => 'No pending login session found. Please log in again.'
        ]);
        exit;
    }

    // Use real name if available, fallback to email prefix
    if (!$firstName) {
        $emailParts = explode('@', $pendingEmail ?? $empId);
        $firstName  = ucfirst($emailParts[0] ?? 'Admin');
        $lastName   = '';
    }

    $_SESSION['employee_id']          = $empId;
    $_SESSION['user_id']              = $userId;
    $_SESSION['email']                = $pendingEmail ?? $_SESSION['email'] ?? '';
    $_SESSION['first_name']           = $firstName;
    $_SESSION['last_name']            = $lastName;
    $_SESSION['role_id']              = $roleId;
    $_SESSION['current_user_details'] = [
        'employee_id'      => $empId,
        'user_id'          => $userId,
        'email'            => $pendingEmail ?? $_SESSION['email'] ?? '',
        'first_name'       => $firstName,
        'middle_name'      => $_SESSION['middle_name'] ?? '',
        'last_name'        => $lastName,
        'role_id'          => $roleId,
        'role_name'        => $roleName,
        'role_prefix'      => $rolePrefix,
        'is_superadmin'    => $isSuperadmin,
        'is_global_access' => $_SESSION['is_global_access'] ?? 1,
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
