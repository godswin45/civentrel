<?php

namespace App\Services;

class AuthService
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    public function isLoggedIn()
    {
        return !empty($_SESSION['user_id']) || !empty($_SESSION['employee_id']);
    }

    public function currentUserId()
    {
        return $_SESSION['user_id'] ?? ($_SESSION['employee_id'] ?? null);
    }

    public function login($userId)
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['LAST_ACTIVITY'] = time();
    }

    public function logout()
    {
        session_unset();
        session_destroy();
    }

    public function requireAuth($basePath = '../')
    {
        if (!$this->isLoggedIn()) {
            header("Location: " . $basePath . "login.php");
            exit;
        }
    }

    public function getJwtToken($bodyPayload = null)
    {
        require_once __DIR__ . '/../../config/proxy.php';
        return \getJwtToken($bodyPayload);
    }

    public function verifyJwtToken($bearerToken = null)
    {
        require_once __DIR__ . '/../../config/proxy.php';
        return \verifyJwtToken($bearerToken);
    }
}