<?php
require_once __DIR__ . '/../src/bootstrap.php';

try {
    $pdo = $db->getPdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $cols = $pdo->query("SHOW COLUMNS FROM tr_business_apps")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_map(function($c) { return $c['Field']; }, $cols);
    
    echo "Columns before: " . implode(', ', $colNames) . "\n";
    
    if (!in_array('barangay', $colNames)) {
        $pdo->exec("ALTER TABLE tr_business_apps ADD COLUMN barangay VARCHAR(255) NULL");
        echo "Added barangay\n";
    }
    if (!in_array('line_of_business', $colNames)) {
        $pdo->exec("ALTER TABLE tr_business_apps ADD COLUMN line_of_business VARCHAR(255) NULL");
        echo "Added line_of_business\n";
    }
    if (!in_array('permit_no', $colNames)) {
        $pdo->exec("ALTER TABLE tr_business_apps ADD COLUMN permit_no VARCHAR(255) NULL");
        echo "Added permit_no\n";
    }
    
    $colsAfter = $pdo->query("SHOW COLUMNS FROM tr_business_apps")->fetchAll(PDO::FETCH_ASSOC);
    $colNamesAfter = array_map(function($c) { return $c['Field']; }, $colsAfter);
    echo "Columns after: " . implode(', ', $colNamesAfter) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
