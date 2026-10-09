<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$_GET['id'] = 2; // Mock ID
$_SESSION['employee_id'] = 1; // Mock Auth

try {
    ob_start();
    require __DIR__ . '/../pages/treasury/print-order-of-payment.php';
    $output = ob_get_clean();
    echo "Success length: " . strlen($output);
} catch (\Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine();
}
