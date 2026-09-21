<?php
require_once __DIR__ . '/config/proxy.php';
$url = 'https://civentral.tech/api/employee/debug_db.php';
$result = proxyRequest($url, 'GET');
print_r($result);
