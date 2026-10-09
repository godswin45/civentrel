<?php
require_once __DIR__ . '/../src/bootstrap.php';

try {
    // Test what TreasuryRepository does
    $repo = new \App\Repositories\TreasuryRepository($db);
    
    // We can't access filterTableColumns directly (it's private), so we use reflection
    $reflection = new ReflectionClass($repo);
    $method = $reflection->getMethod('filterTableColumns');
    $method->setAccessible(true);
    
    $data = [
        'business_name' => 'Test',
        'barangay' => 'Test Barangay',
        'line_of_business' => 'Test Line',
        'permit_no' => '123'
    ];
    
    $filtered = $method->invokeArgs($repo, ['tr_business_apps', $data]);
    
    echo "Data in:\n";
    print_r($data);
    echo "\nData out:\n";
    print_r($filtered);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
