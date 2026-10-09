<?php
require_once __DIR__ . '/../src/bootstrap.php';

try {
    $pdo = $db->getPdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get an app
    $app = $pdo->query("SELECT * FROM tr_business_apps LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$app) die("No apps.");
    
    echo "Before: " . ($app['barangay'] ?? 'null') . "\n";
    
    // Simulate the POST request logic exactly as in business.php
    $treasuryService = new \App\Services\TreasuryService(new \App\Repositories\TreasuryRepository($db), null);
    
    $_POST = [
        'action' => 'edit_application_details',
        'app_id' => $app['id'],
        'business_name' => 'Simulated Test Name',
        'owner_name' => 'Simulated Owner',
        'barangay' => 'Simulated Barangay',
        'line_of_business' => 'Simulated Line',
        'permit_no' => 'Simulated Permit'
    ];
    
    // Extract the exact code block from business.php
    $app2 = $treasuryService->getBusinessApp((int) $_POST['app_id']);
    if (!$app2) throw new Exception('Application not found.');
    foreach (['business_name', 'owner_name'] as $required) {
        if (trim($_POST[$required] ?? '') === '') throw new Exception('Business name and owner name are required.');
    }
    
    $res = $treasuryService->setBusinessAppStatus($app2['id'], $app2['status'], [
        'business_name'    => trim($_POST['business_name']),
        'owner_name'       => trim($_POST['owner_name']),
        'barangay'         => trim($_POST['barangay'] ?? '') ?: null,
        'line_of_business' => trim($_POST['line_of_business'] ?? '') ?: null,
        'permit_no'        => trim($_POST['permit_no'] ?? '') ?: null,
    ]);
    
    echo "Result of setBusinessAppStatus: \n";
    print_r($res);
    
    $appAfter = $pdo->query("SELECT * FROM tr_business_apps WHERE id = " . (int)$app['id'])->fetch(PDO::FETCH_ASSOC);
    echo "After Simulation: " . ($appAfter['barangay'] ?? 'null') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
