<?php
require_once __DIR__ . '/../src/bootstrap.php';

try {
    $pdo = $db->getPdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get the first application
    $app = $pdo->query("SELECT * FROM tr_business_apps LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$app) die("No apps found.");
    
    echo "Before update: barangay=" . ($app['barangay'] ?? 'null') . "\n";
    
    // Update it manually using the repository
    $repo = new \App\Repositories\TreasuryRepository($db);
    $repo->updateBusinessApp((int) $app['id'], [
        'barangay' => 'Test Barangay 123',
        'line_of_business' => 'Test Line 123'
    ]);
    
    // Read it back
    $appAfter = $pdo->query("SELECT * FROM tr_business_apps WHERE id = " . (int)$app['id'])->fetch(PDO::FETCH_ASSOC);
    echo "After Repo update: barangay=" . ($appAfter['barangay'] ?? 'null') . "\n";
    
    // Now test setBusinessAppStatus
    $service = new \App\Services\TreasuryService($repo, null);
    $service->setBusinessAppStatus((int) $app['id'], $app['status'], [
        'barangay' => 'Status Test Barangay',
        'line_of_business' => 'Status Test Line'
    ]);
    
    $appAfter2 = $pdo->query("SELECT * FROM tr_business_apps WHERE id = " . (int)$app['id'])->fetch(PDO::FETCH_ASSOC);
    echo "After Service update: barangay=" . ($appAfter2['barangay'] ?? 'null') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
