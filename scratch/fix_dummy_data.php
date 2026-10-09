<?php
require_once __DIR__ . '/../src/bootstrap.php';

try {
    $pdo = $db->getPdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("UPDATE tr_business_apps SET barangay = 'Brgy. San Jose', line_of_business = 'IT Services' WHERE business_name = 'TechNova Solutions'");
    $pdo->exec("UPDATE tr_business_apps SET barangay = 'Brgy. Poblacion', line_of_business = 'Retail Trade', permit_no = 'BP-2025-00123' WHERE business_name = 'Santos Rice Trading'");
    $pdo->exec("UPDATE tr_business_apps SET barangay = 'Brgy. Central', line_of_business = 'Food Service', permit_no = 'BP-2023-00456' WHERE business_name = 'Lola Flora Eatery'");
    
    echo "Dummy data updated successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
