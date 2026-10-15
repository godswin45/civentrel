<?php

namespace App\Middleware;

class SessionTimeout
{
    private $timeoutDuration;
    private $basePath;

    public function __construct($timeoutDuration = 1800, $basePath = '../')
    {
        $this->timeoutDuration = $timeoutDuration;

        if (!empty($_SERVER['PHP_SELF'])) {
            $segments = array_values(array_filter(explode('/', $_SERVER['PHP_SELF']), 'strlen'));
            $pagesIndex = array_search('pages', $segments);
            if ($pagesIndex !== false) {
                $nestedDepth = count(array_slice($segments, $pagesIndex + 1)) + 1;
                $basePath = str_repeat('../', $nestedDepth);
            }
        }

        $this->basePath = $basePath;
    }

    public function handle()
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $this->timeoutDuration) {
            // Build absolute logout URL so it always resolves correctly regardless of page depth
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            // Find the app root (e.g. /civentrel/) by walking up to the project folder
            $appRoot = '/';
            if (preg_match('#^(/[^/]+/civentrel)/#', $scriptName, $m)) {
                $appRoot = $m[1] . '/';
            } elseif (preg_match('#^(/civentrel)/#', $scriptName, $m)) {
                $appRoot = $m[1] . '/';
            }

            $logoutUrl = $appRoot . 'pages/logout.php';

            $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) ||
                     (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) ||
                     (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                     (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

            if ($isApi) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'status' => 'expired',
                    'message' => 'Session timed out due to inactivity',
                    'redirect' => $logoutUrl
                ]);
                exit;
            }

            header('Location: ' . $logoutUrl);
            exit;
        }

        $_SESSION['LAST_ACTIVITY'] = time();
    }
}
