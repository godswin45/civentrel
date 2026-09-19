<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
try {
    $db = Database::getInstance();
    $results = [];
    foreach (['users', 'employees', 'employee', 'local_users'] as $tbl) {
        try {
            $rows = $db->query("SELECT * FROM `{$tbl}` WHERE employee_id='TRMG-2026-362' LIMIT 1");
            if (!empty($rows)) {
                $results[$tbl] = $rows[0];
            }
        } catch (\Throwable $e) {}
    }
    
    // Test password
    $testPass = 'Civentral@8076';
    $hashResults = [];
    foreach ($results as $tbl => $user) {
        $hashResults[$tbl] = password_verify($testPass, $user['password'] ?? '');
    }
    
    echo json_encode([
        'users' => $results,
        'passwords_valid' => $hashResults
    ]);
} catch (\Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
