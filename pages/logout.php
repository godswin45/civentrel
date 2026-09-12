<?php
session_start();

// Record logout time via API instead of local database
if (isset($_SESSION['login_id']) || isset($_SESSION['session_id'])) {
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
    require_once __DIR__ . '/../config/proxy.php';
    $apiBaseUrl = getenv('EXPO_PUBLIC_API_BASE_URL') ?: 'https://civentral.tech/api/employee';
    $loginHistoryUrl = rtrim($apiBaseUrl, '/') . '/login-history.php';

    try {
        // Update logout time in login history via API
        if (isset($_SESSION['login_id'])) {
            proxyRequest($loginHistoryUrl, 'PATCH', [
                'login_id'    => $_SESSION['login_id'],
                'logout_time' => date('Y-m-d H:i:s')
            ]);
        }
    } catch (Exception $e) {
        // Ignore error and proceed to logout
    }
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to login page
header("Location: /civentrel/login.php");
exit;
?>
