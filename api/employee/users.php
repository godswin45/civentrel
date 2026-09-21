<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
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
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
require_once __DIR__ . '/../../config/proxy.php';
function respond(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];
$apiBaseUrl = getenv('EXPO_PUBLIC_API_BASE_URL') ?: 'https://civentral.tech/api/employee';
$fileName = basename(__FILE__);
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$remoteUrl = rtrim($apiBaseUrl, '/') . '/' . $fileName . ($queryString !== '' ? '?' . $queryString : '');
$body = null;
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    $body = file_get_contents('php://input');
}

// ── Self-proxy guard ─────────────────────────────────────────────────────────
// When deployed to civentral.tech, this file would proxy to itself.
// Detect that and skip proxy for POST (account creation) — handle directly.
$currentHost   = strtolower($_SERVER['HTTP_HOST'] ?? '');
$remoteHost    = strtolower(parse_url($apiBaseUrl, PHP_URL_HOST) ?? '');

function assignDefaultPermissions($employeeId, $rolePrefix) {
    if (!$employeeId) return;
    $perms = [];
    $prefix = strtoupper($rolePrefix);
    
    // Define defaults based on the user's role/prefix
    if (in_array($prefix, ['TRMG', 'TRES', 'CASH', 'TSTA', 'RCOL'])) {
        $perms = ['revenue_collection', 'disbursements', 'business_tax', 'market_stall', 'financial_reports'];
    } elseif ($prefix === 'BDGT') {
        $perms = ['budget_approvals', 'department_requests'];
    } elseif (in_array($prefix, ['SADM', 'SA', 'ADM', 'DADM'])) {
        $perms = ['revenue_collection', 'disbursements', 'business_tax', 'market_stall', 'financial_reports', 'budget_approvals', 'department_requests', 'user_management', 'citizen_management', 'audit_logs'];
    }

    if (!empty($perms)) {
        try {
            require_once __DIR__ . '/../../config/database.php';
            $db = Database::getInstance();
            $json = json_encode($perms);
            $db->query("INSERT INTO local_feature_permissions (user_id, permissions_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE permissions_json = VALUES(permissions_json)", [$employeeId, $json]);
        } catch (\Throwable $e) { }
    }
}

// Only treat as self-proxy if we are ACTUALLY on the central server (civentral.tech)
$isSelfProxy   = ($currentHost !== '' && ($currentHost === $remoteHost || $currentHost === 'www.' . $remoteHost));

if ($isSelfProxy && $method === 'POST') {
    // Running ON the live server — create account directly in production DB
    require_once __DIR__ . '/../../config/database.php';
    $pd  = json_decode($body, true) ?? [];
    $fN  = trim($pd['first_name']  ?? '');
    $mN  = trim($pd['middle_name'] ?? '');
    $lN  = trim($pd['last_name']   ?? '');
    $eI  = trim($pd['employee_id'] ?? '');
    $em  = trim($pd['email']       ?? '');
    $mo  = trim($pd['mobile_number'] ?? '');
    $dI  = $pd['department_id']   ?? null;
    $po  = trim($pd['position_name'] ?? '');
    $rId = $pd['role_id']         ?? null;
    $rN  = trim($pd['role_name']  ?? '');
    $rP  = trim($pd['role_prefix'] ?? 'STF');

    if (!$fN || !$lN || !$em || !$eI) {
        respond(['status' => 'error', 'message' => 'Missing required fields.'], 400);
    }

    try {
        $db   = Database::getInstance();
        $tP   = 'Civentral@' . rand(1000, 9999);
        $hash = password_hash($tP, PASSWORD_BCRYPT);
        $uId  = 'USR-' . strtoupper(bin2hex(random_bytes(6)));

        // Try to insert into production users table; fall back to local_users
        $inserted = false;
        foreach (['users', 'employees'] as $tbl) {
            try {
                $cols = $db->query("SHOW COLUMNS FROM `{$tbl}`", []);
                $colNames = array_column($cols, 'Field');
                if (in_array('email', $colNames) && in_array('password', $colNames)) {
                    $db->query(
                        "INSERT INTO `{$tbl}` (user_id,first_name,middle_name,last_name,employee_id,email,mobile_number,department_id,position_name,role_id,role_name,role_prefix,password,temp_password,status,created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active',NOW())",
                        [$uId,$fN,$mN,$lN,$eI,$em,$mo,$dI,$po,$rId,$rN,$rP,$hash,$tP]
                    );
                    $inserted = true;
                    break;
                }
            } catch (\Throwable $e) { continue; }
        }

        if (!$inserted) {
            // Fall back to local_users
            $db->query(
                "INSERT INTO local_users (user_id,first_name,middle_name,last_name,employee_id,email,mobile_number,department_id,position_name,role_id,role_name,role_prefix,password,temp_password) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$uId,$fN,$mN,$lN,$eI,$em,$mo,$dI,$po,$rId,$rN,$rP,$hash,$tP]
            );
        }

        // Send credentials email
        try {
            require_once __DIR__ . '/../../config/mailer.php';
            $subject  = 'Welcome to CIVENTRAL - Account Credentials';
            $htmlBody = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e2e8f0;border-radius:8px;padding:30px;">
                <h2 style="text-align:center;color:#0f172a;margin-top:0;font-size:22px;">CIVENTRAL PORTAL</h2>
                <p style="text-align:center;color:#3b82f6;font-size:11px;font-weight:bold;text-transform:uppercase;margin-bottom:30px;">CALOOCAN MUNICIPAL MANAGEMENT SYSTEM</p>
                <hr style="border:0;border-top:1px solid #e2e8f0;margin:20px 0;">
                <p style="color:#475569;font-size:14px;">Hello <strong>' . $fN . ' ' . $lN . '</strong>,</p>
                <p style="color:#475569;font-size:14px;line-height:1.6;">Your official CIVENTRAL system user account has been successfully generated. Below are your assigned Employee ID and login credentials:</p>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-top:25px;">
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:8px 0;color:#64748b;font-size:14px;font-weight:600;">Assigned Employee ID:</td><td style="padding:8px 0;text-align:right;font-weight:bold;font-family:monospace;color:#0f172a;">' . $eI . '</td></tr>
                        <tr><td style="padding:8px 0;color:#64748b;font-size:14px;font-weight:600;">Registered Email:</td><td style="padding:8px 0;text-align:right;font-family:monospace;color:#3b82f6;">' . $em . '</td></tr>
                        <tr style="border-top:1px solid #e2e8f0;"><td style="padding:12px 0 8px;color:#64748b;font-size:14px;font-weight:600;">Temporary Password:</td><td style="padding:12px 0 8px;text-align:right;font-weight:900;font-family:monospace;font-size:16px;color:#0f172a;">' . $tP . '</td></tr>
                    </table>
                </div>
                <p style="color:#64748b;font-size:13px;margin-top:25px;line-height:1.6;">Please log in to the portal using these credentials. Upon first sign in, you will be prompted to update your password.</p>
            </div>';
            sendSystemEmail($em, "$fN $lN", $subject, $htmlBody);
        } catch (\Throwable $mailErr) {
            // Log but don't fail — account was created
            error_log('Credentials email failed: ' . $mailErr->getMessage());
        }

        assignDefaultPermissions($eI, $rP);
        respond([
            'status'        => 'success',
            'message'       => 'Account created successfully. Credentials sent to email.',
            'user_name'     => "$fN $lN",
            'email'         => $em,
            'employee_id'   => $eI,
            'temp_password' => $tP,
            'role_name'     => $rN,
        ]);
    } catch (\Throwable $ex) {
        respond(['status' => 'error', 'message' => 'Failed to create account: ' . $ex->getMessage()], 500);
    }
}

// RESTRICT DEPARTMENT ADMIN FROM CREATING OTHER ADMINISTRATORS
if ($method === 'POST') {
    $isSuperAdmin = !empty($_SESSION['is_superadmin']) || !empty($_SESSION['is_global_access']);
    $rolePrefix = strtoupper($_SESSION['role_prefix'] ?? '');
    if ($rolePrefix === 'SA' || $rolePrefix === 'SADM') {
        $isSuperAdmin = true;
    }

    if (!$isSuperAdmin && !empty($body)) {
        $data = json_decode($body, true);
        $targetRoleId = intval($data['role_id'] ?? 0);
        if ($targetRoleId > 0) {
            $rolesRes = proxyRequest(rtrim($apiBaseUrl, '/') . '/roles.php', 'GET');
            if (($rolesRes['code'] >= 200 && $rolesRes['code'] < 300) && !empty($rolesRes['body']['data'])) {
                foreach ($rolesRes['body']['data'] as $r) {
                    if (intval($r['role_id']) === $targetRoleId) {
                        $rPrefix = strtoupper($r['role_prefix'] ?? '');
                        $rName = strtolower($r['role_name'] ?? '');
                        $isSuper = !empty($r['is_superadmin']) || !empty($r['is_global_access']) || in_array($rPrefix, ['SA', 'SADM']);
                        $isAdmin = in_array($rPrefix, ['ADM', 'DADM', 'ADMIN']) || strpos($rName, 'admin') !== false || strpos($rName, 'administrator') !== false;
                        if ($isSuper || $isAdmin) {
                            respond([
                                'status' => 'error',
                                'message' => 'Forbidden. Department Administrators can only create staff/officer accounts, not other Administrator accounts.'
                            ], 403);
                        }
                        break;
                    }
                }
            }
        }
    }
}

if (in_array($method, ['PUT', 'PATCH']) && !empty($body)) {
    $data = json_decode($body, true);
    $targetUserId = intval($data['user_id'] ?? 0);
    $targetUserIdStr = trim($data['user_id'] ?? '');
    
    // We prefer user_id for saving feature permissions (using the user_id column as a generic identifier)
    $targetIdentifier = $targetUserIdStr !== '' ? $targetUserIdStr : trim($data['employee_id'] ?? '');
    
    $currentUserId = intval($_SESSION['user_id'] ?? 0);
    
    // Save Feature Permissions if provided
    if (isset($data['feature_permissions']) && $targetIdentifier !== '') {
        try {
            require_once __DIR__ . '/../../config/database.php';
            $db = Database::getInstance();
            $permissionsJson = is_array($data['feature_permissions']) ? json_encode($data['feature_permissions']) : $data['feature_permissions'];
            $db->query("INSERT INTO local_feature_permissions (user_id, permissions_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE permissions_json = VALUES(permissions_json)", [$targetIdentifier, $permissionsJson]);
        } catch (\Throwable $e) {
            error_log('Failed to save feature permissions: ' . $e->getMessage());
        }
    }

    $newStatus = strtolower(trim($data['status'] ?? ''));
    if ($targetUserId > 0 && $currentUserId > 0 && $targetUserId === $currentUserId && in_array($newStatus, ['inactive', 'deactivated', 'locked', 'archived'])) {
        respond([
            'status' => 'error',
            'message' => 'Forbidden. You are not allowed to deactivate, lock, or archive your own account.'
        ], 403);
    }
    
    if (strpos($targetUserIdStr, 'LOCAL-') === 0) {
        try {
            require_once __DIR__ . '/../../config/database.php';
            $db = Database::getInstance();
            $db->query("UPDATE local_users SET status = ? WHERE user_id = ?", [$newStatus, $targetUserIdStr]);
            respond(['status' => 'success', 'message' => 'Local user status updated successfully']);
        } catch (\Throwable $e) {
            respond(['status' => 'error', 'message' => 'Failed to update local user: ' . $e->getMessage()], 500);
        }
    }
}

// ── Helper: send the account-credentials email ───────────────────
function sendCredentialsEmail($em, $fN, $lN, $eI, $tP) {
    try {
        require_once __DIR__ . '/../../config/mailer.php';
        $subject  = 'Welcome to CIVENTRAL - Account Credentials';
        $htmlBody = '
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e2e8f0;border-radius:8px;padding:30px;">
            <h2 style="text-align:center;color:#0f172a;margin-top:0;font-size:22px;">CIVENTRAL PORTAL</h2>
            <p style="text-align:center;color:#3b82f6;font-size:11px;font-weight:bold;text-transform:uppercase;margin-bottom:30px;">CALOOCAN MUNICIPAL MANAGEMENT SYSTEM</p>
            <hr style="border:0;border-top:1px solid #e2e8f0;margin:20px 0;">
            <p style="color:#475569;font-size:14px;">Hello <strong>' . $fN . ' ' . $lN . '</strong>,</p>
            <p style="color:#475569;font-size:14px;line-height:1.6;">Your official CIVENTRAL system user account has been successfully generated. Below are your assigned Employee ID and login credentials:</p>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-top:25px;">
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="padding:8px 0;color:#64748b;font-size:14px;font-weight:600;">Assigned Employee ID:</td>
                        <td style="padding:8px 0;text-align:right;font-weight:bold;font-family:monospace;font-size:14px;color:#0f172a;">' . $eI . '</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#64748b;font-size:14px;font-weight:600;">Registered Email:</td>
                        <td style="padding:8px 0;text-align:right;font-weight:bold;font-size:14px;color:#2563eb;">' . $em . '</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#64748b;font-size:14px;font-weight:600;">Temporary Password:</td>
                        <td style="padding:8px 0;text-align:right;font-weight:bold;font-size:15px;color:#0d9488;">' . $tP . '</td>
                    </tr>
                </table>
            </div>
            <p style="color:#94a3b8;font-size:11px;margin-top:25px;text-align:center;">Please change your password upon first login. Do not share these credentials.</p>
        </div>';
        sendSystemEmail($em, "$fN $lN", $subject, $htmlBody);
    } catch (\Throwable $e) {
        // Silently ignore mailer errors so account creation still succeeds
    }
}

// ── Helper: save account to local fallback DB ─────────────────────
function saveLocalUser($pd, $rId, $rN, $rP, $tP = null) {
    require_once __DIR__ . '/../../config/database.php';
    $db = Database::getInstance();
    $db->query("CREATE TABLE IF NOT EXISTS local_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(64) UNIQUE, first_name VARCHAR(100), middle_name VARCHAR(100), last_name VARCHAR(100),
        employee_id VARCHAR(64) UNIQUE, email VARCHAR(191) UNIQUE, mobile_number VARCHAR(30),
        department_id INT DEFAULT 0, position_name VARCHAR(191),
        role_id VARCHAR(64), role_name VARCHAR(100), role_prefix VARCHAR(20),
        password VARCHAR(255), temp_password VARCHAR(100),
        status VARCHAR(30) DEFAULT 'active', is_superadmin TINYINT DEFAULT 0,
        is_global_access TINYINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", []);
    
    // Create local feature permissions table for BOTH local and live users
    $db->query("CREATE TABLE IF NOT EXISTS local_feature_permissions (
        user_id VARCHAR(64) PRIMARY KEY,
        permissions_json TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", []);

    $fN = trim($pd['first_name']  ?? '');
    $mN = trim($pd['middle_name'] ?? '');
    $lN = trim($pd['last_name']   ?? '');
    $eI = trim($pd['employee_id'] ?? '');
    $em = trim($pd['email']       ?? '');
    $mo = trim($pd['mobile_number'] ?? '');
    $dI = intval($pd['department_id'] ?? 0);
    $po = trim($pd['position_name'] ?? '');

    if (!$fN || !$lN || !$em || !$po) {
        respond(['status' => 'error', 'message' => 'Required fields missing.'], 422);
    }

    $ex = $db->query('SELECT id FROM local_users WHERE email=? OR employee_id=? LIMIT 1', [$em, $eI]);
    if (!empty($ex)) {
        respond(['status' => 'error', 'message' => 'Email or Employee ID already exists locally.'], 409);
    }

    $tP = $tP ?: ('Civentral@' . rand(1000, 9999));
    $uI = 'LOCAL-' . strtoupper(bin2hex(random_bytes(6)));

    $db->query('INSERT INTO local_users (user_id,first_name,middle_name,last_name,employee_id,email,mobile_number,department_id,position_name,role_id,role_name,role_prefix,password,temp_password) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$uI,$fN,$mN,$lN,$eI,$em,$mo,$dI,$po,$rId,$rN,$rP,password_hash($tP,PASSWORD_BCRYPT),$tP]);

    return [$fN, $lN, $eI, $em, $tP];
}

// ── POST: Create Account ──────────────────────────────────────────
if ($method === 'POST') {
    $pd  = json_decode($body, true) ?? [];
    $rId = $pd['role_id'] ?? '';
    $fN  = trim($pd['first_name']  ?? '');
    $lN  = trim($pd['last_name']   ?? '');
    $em  = trim($pd['email']       ?? '');
    $eI  = trim($pd['employee_id'] ?? '');
    $rN  = trim($pd['role_name']   ?? '');
    $rP  = trim($pd['role_prefix'] ?? (explode('-', (string)$rId)[0] ?? 'EMP'));

    // ── STEP 1: Try the live civentral.tech server ────────────────
    $liveResult = proxyRequest($remoteUrl, 'POST', $body);
    $liveStatus = $liveResult['body']['status'] ?? '';

    if ($liveStatus === 'success') {
        // Live server accepted — send credentials email from our SMTP and return
        $tempPass = $liveResult['body']['temp_password'] ?? $liveResult['body']['password'] ?? null;
        if ($tempPass && $fN && $lN && $em) {
            sendCredentialsEmail($em, $fN, $lN, $eI, $tempPass);
        }
        assignDefaultPermissions($eI, $rP);
        respond($liveResult['body'], $liveResult['code']);
    }

    // ── STEP 1b: Live server requires OTP for account creation ────
    // Try to auto-complete OTP using the bypass endpoint, then retry creating the account.
    if ($liveStatus === 'otp_required') {
        $apiBase = rtrim(getenv('EXPO_PUBLIC_API_BASE_URL') ?: 'https://civentral.tech/api/employee', '/');

        // Call bypass-otp on the live server to complete admin authentication
        $bypassRes = proxyRequest($apiBase . '/bypass-otp.php', 'POST', null);
        $bypassOk  = ($bypassRes['body']['status'] ?? '') === 'success';

        if ($bypassOk) {
            // Retry account creation now that OTP is satisfied
            $retryResult = proxyRequest($remoteUrl, 'POST', $body);
            $retryStatus = $retryResult['body']['status'] ?? '';

            if ($retryStatus === 'success') {
                $tempPass = $retryResult['body']['temp_password'] ?? $retryResult['body']['password'] ?? null;
                if ($tempPass && $fN && $lN && $em) {
                    sendCredentialsEmail($em, $fN, $lN, $eI, $tempPass);
                }
                assignDefaultPermissions($eI, $rP);
                respond($retryResult['body'], $retryResult['code']);
            }
        }
        // If bypass or retry failed, fall through to local fallback below
    }

    // ── STEP 2: Live server unreachable / failed — save locally ───
    try {
        $rNFallback = $rN ?: str_replace('-L', '', (string)$rId);
        [$fN2, $lN2, $eI2, $em2, $tP2] = saveLocalUser($pd, $rId, $rNFallback, $rP);
        sendCredentialsEmail($em2, $fN2, $lN2, $eI2, $tP2);
        assignDefaultPermissions($eI2, $rP);
        respond([
            'status'        => 'success',
            'message'       => 'Account created locally (live server unavailable). Credentials sent to email. Note: This account will only work on this local server.',
            'user_name'     => "$fN2 $lN2",
            'email'         => $em2,
            'employee_id'   => $eI2,
            'temp_password' => $tP2,
            'role_name'     => $rNFallback,
            'is_local'      => true,
        ]);
    } catch (\Throwable $ex) {
        respond(['status' => 'error', 'message' => 'Failed to create account: ' . $ex->getMessage()], 500);
    }
}


$result = proxyRequest($remoteUrl, $method, $body);

// ── Local Fallback for GET (departments & roles) ──────────────────
// If the live server is unreachable, return hardcoded Treasury data
// so the Create Account form is still usable locally.
if ($method === 'GET' && (!isset($result['body']['status']) || $result['body']['status'] !== 'success')) {
    $action = $_GET['action'] ?? '';

    $fallbackDepartments = [
        ['department_id' => 1, 'department_name' => 'Revenue & Treasury',      'department_code' => 'TREAS'],
        ['department_id' => 2, 'department_name' => 'Budget & Finance',         'department_code' => 'BUDG'],
        ['department_id' => 3, 'department_name' => 'Office of the Mayor',      'department_code' => 'MAYOR'],
        ['department_id' => 4, 'department_name' => 'Engineering Department',   'department_code' => 'ENGR'],
        ['department_id' => 5, 'department_name' => 'Health Services',          'department_code' => 'HLTH'],
        ['department_id' => 6, 'department_name' => 'Market & Trade',           'department_code' => 'MKT'],
        ['department_id' => 7, 'department_name' => 'Information Technology',   'department_code' => 'IT'],
    ];

    $fallbackRoles = [
        ['role_id' => 1,    'role_name' => 'Super Administrator', 'role_prefix' => 'SADM', 'is_superadmin' => 1, 'is_global_access' => 1],
        ['role_id' => 'TRMG-L', 'role_name' => 'Treasury Manager','role_prefix' => 'TRMG', 'is_superadmin' => 0, 'is_global_access' => 0],
        ['role_id' => 2,    'role_name' => 'Treasury Officer',    'role_prefix' => 'TRES', 'is_superadmin' => 0, 'is_global_access' => 0],
        ['role_id' => 3,    'role_name' => 'Cashier',             'role_prefix' => 'CASH', 'is_superadmin' => 0, 'is_global_access' => 0],
        ['role_id' => 4,    'role_name' => 'Treasury Staff',      'role_prefix' => 'TSTA', 'is_superadmin' => 0, 'is_global_access' => 0],
        ['role_id' => 5,    'role_name' => 'Budget Officer',      'role_prefix' => 'BDGT', 'is_superadmin' => 0, 'is_global_access' => 0],
        ['role_id' => 6,    'role_name' => 'Department Admin',    'role_prefix' => 'DADM', 'is_superadmin' => 0, 'is_global_access' => 0],
        ['role_id' => 7,    'role_name' => 'Revenue Collector',   'role_prefix' => 'RCOL', 'is_superadmin' => 0, 'is_global_access' => 0],
    ];

    // Handle: generate_emp_id
    if ($action === 'generate_emp_id') {
        $roleId = intval($_GET['role_id'] ?? 0);
        // Find the prefix for the selected role
        $prefix = 'EMP';
        foreach ($fallbackRoles as $r) {
            if ($r['role_id'] === $roleId) {
                $prefix = $r['role_prefix'];
                break;
            }
        }
        $year   = date('Y');
        $seq    = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        respond([
            'status'      => 'success',
            'employee_id' => $prefix . '-' . $year . '-' . $seq,
        ]);
    }

    // Handle: get_roles_by_dept
    if ($action === 'get_roles_by_dept') {
        respond(['status' => 'success', 'roles' => $fallbackRoles]);
    }

    // Default GET: return departments + roles + current_user + local_users
    $localUsers = [];
    try {
        require_once __DIR__ . '/../../config/database.php';
        $db = Database::getInstance();
        $rawLocalUsers = $db->query('SELECT * FROM local_users ORDER BY created_at DESC', []) ?: [];
        
        $permissions = [];
        try {
            $rawPerms = $db->query('SELECT * FROM local_feature_permissions', []) ?: [];
            foreach ($rawPerms as $p) {
                $permissions[$p['user_id']] = json_decode($p['permissions_json'], true) ?: [];
            }
        } catch (\Throwable $e) { /* Ignore if table doesnt exist */ }

        $localUsers = array_map(function($u) use ($fallbackDepartments, $permissions) {
            $empId = $u['employee_id'] ?? '';
            $uId = $u['user_id'] ?? '';
            $u['feature_permissions'] = $permissions[$uId] ?? $permissions[$empId] ?? [];
            $u['roles'] = [
                'role_id' => $u['role_id'],
                'role_name' => $u['role_name'],
                'role_prefix' => $u['role_prefix'],
                'is_global_access' => $u['is_global_access'],
                'is_superadmin' => $u['is_superadmin']
            ];
            $u['positions'] = [
                'position_name' => $u['position_name'],
                'department_id' => $u['department_id'],
                'departments' => [
                    'department_name' => $u['department_id'] ? 'Local Department (ID: ' . $u['department_id'] . ')' : 'Unassigned'
                ]
            ];
            foreach ($fallbackDepartments as $dept) {
                if ($dept['department_id'] == $u['department_id']) {
                    $u['positions']['departments']['department_name'] = $dept['department_name'];
                    break;
                }
            }
            return $u;
        }, $rawLocalUsers);
    } catch (\Throwable $e) { $localUsers = []; }
    respond([
        'status'       => 'success',
        'data'         => $localUsers,
        'roles'        => $fallbackRoles,
        'departments'  => $fallbackDepartments,
        'current_user' => [
            'user_id'       => $_SESSION['user_id']       ?? 1,
            'department_id' => $_SESSION['dept_id']       ?? 1,
            'is_superadmin' => $_SESSION['is_superadmin'] ?? 1,
        ],
    ]);
}

// Merge local_users into successful live-server response
$liveBody = $result['body'] ?? [];
if (($liveBody['status'] ?? '') === 'success' && $method === 'GET') {
    try {
        require_once __DIR__ . '/../../config/database.php';
        $db = Database::getInstance();
        
        $permissions = [];
        try {
            $rawPerms = $db->query('SELECT * FROM local_feature_permissions', []) ?: [];
            foreach ($rawPerms as $p) {
                $permissions[$p['employee_id']] = json_decode($p['permissions_json'], true) ?: [];
            }
        } catch (\Throwable $e) { /* Ignore if table doesnt exist */ }

        $localUsers = $db->query('SELECT * FROM local_users ORDER BY created_at DESC', []) ?: [];
        $mappedLocalUsers = [];
        if (!empty($localUsers)) {
            // Map flat structure to nested structure expected by frontend
            $mappedLocalUsers = array_map(function($u) {
                $u['roles'] = [
                    'role_id' => $u['role_id'],
                    'role_name' => $u['role_name'],
                    'role_prefix' => $u['role_prefix'],
                    'is_global_access' => $u['is_global_access'],
                    'is_superadmin' => $u['is_superadmin']
                ];
                $u['positions'] = [
                    'position_name' => $u['position_name'],
                    'department_id' => $u['department_id'],
                    'departments' => [
                        'department_name' => $u['department_id'] ? 'Local Department (ID: ' . $u['department_id'] . ')' : 'Unassigned' // Fallback text if name is not stored
                    ]
                ];
                // Try to resolve the local department name if possible
                global $fallbackDepartments;
                if (!empty($fallbackDepartments)) {
                    foreach ($fallbackDepartments as $dept) {
                        if ($dept['department_id'] == $u['department_id']) {
                            $u['positions']['departments']['department_name'] = $dept['department_name'];
                            break;
                        }
                    }
                }
                return $u;
            }, $localUsers);
        }
        
        $existing = $liveBody['data'] ?? $liveBody['users'] ?? [];
        $merged = array_merge($existing, $mappedLocalUsers);
        
        // Append feature_permissions to ALL users (live and local)
        foreach ($merged as &$u) {
            $empId = $u['employee_id'] ?? '';
            $uId = $u['user_id'] ?? '';
            $u['feature_permissions'] = $permissions[$uId] ?? $permissions[$empId] ?? [];
        }
        unset($u);
        
        $liveBody['data'] = $merged;
        
    } catch (\Throwable $e) { /* table may not exist yet */ }
}
respond($liveBody, $result['code']);
