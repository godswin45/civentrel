<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function proxyRequest($url, $method = 'POST', $body = null, $sendCookie = true, $customHeaders = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $headers = array_merge([
        'Content-Type: application/json'
    ], $customHeaders);
    
    $remoteSessId = $_SESSION['remote_phpsessid'] ?? $_COOKIE['remote_phpsessid'] ?? $_COOKIE['PHPSESSID'] ?? null;
    if ($sendCookie && !empty($remoteSessId)) {
        $headers[] = 'Cookie: PHPSESSID=' . $remoteSessId;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);   // fail if can't connect in 5s
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);          // fail if total request > 10s
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'code' => 500,
            'body' => [
                'status' => 'error',
                'message' => 'Proxy request failed: ' . $error
            ]
        ];
    }
    
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $headerStr = substr($response, 0, $headerSize);
    $bodyStr = substr($response, $headerSize);
    
    // Parse cookies from headers
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headerStr, $matches);
    foreach ($matches[1] as $cookie) {
        $parts = explode('=', $cookie, 2);
        if (count($parts) === 2 && trim($parts[0]) === 'PHPSESSID') {
            $_SESSION['remote_phpsessid'] = trim($parts[1]);
        }
    }
    
    return [
        'code' => $httpCode,
        'body' => json_decode($bodyStr, true) ?? $bodyStr
    ];
}

function getJwtToken($bodyPayload = null) {
    $url = 'https://civentral.tech/api/v1/auth/token/';
    return proxyRequest($url, 'POST', $bodyPayload, true);
}

function verifyJwtToken($bearerToken = null) {
    $url = 'https://civentral.tech/api/v1/auth/verify/';
    $headers = [];
    if (!empty($bearerToken)) {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers[] = 'Authorization: ' . $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    return proxyRequest($url, 'GET', null, true, $headers);
}
