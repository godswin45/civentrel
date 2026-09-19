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
            header('Location: ' . $appRoot . 'pages/logout.php');
            exit;
        }

        $_SESSION['LAST_ACTIVITY'] = time();
    }
}
