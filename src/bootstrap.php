<?php

if (!ob_get_level()) {
    ob_start();
}
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    // Scope the session cookie to the app root (e.g. /civentrel/) so it is
    // shared between /api/ and /pages/ subdirectories.
    $cookiePath = '/';
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        if (preg_match('#^(/[^/]+/civentrel)/#', $_SERVER['SCRIPT_NAME'], $m)) {
            $cookiePath = $m[1] . '/';
        } elseif (preg_match('#^(/civentrel)/#', $_SERVER['SCRIPT_NAME'], $m)) {
            $cookiePath = $m[1] . '/';
        }
    }
    session_set_cookie_params(['path' => $cookiePath, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Load .env variables
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

// Load Database Config
$configPath = __DIR__ . '/../config/database.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

// Load Repositories
require_once __DIR__ . '/Repositories/UserRepository.php';
require_once __DIR__ . '/Repositories/PermissionRepository.php';
if (file_exists(__DIR__ . '/Repositories/TreasuryRepository.php')) {
    require_once __DIR__ . '/Repositories/TreasuryRepository.php';
}

// Load Services
require_once __DIR__ . '/Services/AuthService.php';
require_once __DIR__ . '/Services/UserService.php';
require_once __DIR__ . '/Services/PermissionService.php';
require_once __DIR__ . '/Services/HeaderService.php';
if (file_exists(__DIR__ . '/Services/TreasuryService.php')) {
    require_once __DIR__ . '/Services/TreasuryService.php';
}
if (file_exists(__DIR__ . '/Services/AuditService.php')) {
    require_once __DIR__ . '/Services/AuditService.php';
}
if (file_exists(__DIR__ . '/Services/CitizenPaymentService.php')) {
    require_once __DIR__ . '/Services/CitizenPaymentService.php';
}

// Load Middleware
require_once __DIR__ . '/Middleware/SessionTimeout.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/Middleware/PermissionMiddleware.php';

// Initialize Core & Middleware
$authService = new \App\Services\AuthService();

// Support dynamic basePath if defined before requiring bootstrap.php
$currentBasePath = $basePath ?? '../';
$sessionTimeout = new \App\Middleware\SessionTimeout(1800, $currentBasePath);
$sessionTimeout->handle();

// Initialize Repositories
$userRepo = new \App\Repositories\UserRepository(null);
$permRepo = new \App\Repositories\PermissionRepository(null);
$treasuryRepo = class_exists('\App\Repositories\TreasuryRepository') ? new \App\Repositories\TreasuryRepository($db ?? null) : null;

// Initialize Services
$userService = new \App\Services\UserService($userRepo);
$permService = new \App\Services\PermissionService($permRepo);
$treasuryService = class_exists('\App\Services\TreasuryService') ? new \App\Services\TreasuryService($treasuryRepo, $db ?? null) : null;
$auditService = class_exists('\App\Services\AuditService') ? new \App\Services\AuditService($treasuryRepo, $db ?? null) : null;
$paymentService = class_exists('\App\Services\CitizenPaymentService') ? new \App\Services\CitizenPaymentService($db ?? null) : null;

// Initialize Header Service (and build user)
$headerService = new \App\Services\HeaderService($userService, $permService, $authService);
$headerUser = $headerService->buildHeaderUser();