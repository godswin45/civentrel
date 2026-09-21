<?php
require_once __DIR__ . '/config/proxy.php';
$url = 'https://civentral.tech/api/employee/login.php';
$body = [
    'employeeId' => 'RA-2026-001',
    'password' => 'Civentral@1629'
];
$result = proxyRequest($url, 'POST', $body);
print_r($result);
