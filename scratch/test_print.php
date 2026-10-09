<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../src/bootstrap.php';

$_GET['id'] = 1; 
$_SESSION['employee_id'] = 1; 
$_SESSION['user_id'] = 1;

try {
    ob_start();
    require __DIR__ . '/../pages/treasury/print-order-of-payment.php';
    $output = ob_get_clean();
    echo "SUCCESS\n";
} catch (\Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine();
}
