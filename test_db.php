<?php
require_once __DIR__ . '/src/bootstrap.php';

try {
    $db = Database::getInstance();
    
    // Check if table exists
    $db->query("CREATE TABLE IF NOT EXISTS `tr_online_payments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `payment_reference` varchar(50) NOT NULL,
      `citizen_name` varchar(100) NOT NULL,
      `payment_source` varchar(100) NOT NULL,
      `amount` decimal(10,2) NOT NULL,
      `payment_gateway` varchar(50) NOT NULL,
      `status` varchar(20) NOT NULL,
      `or_number` varchar(50) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    )");

    $dummies = [
        [
            'payment_reference' => 'PAY-2026-X8F9A1',
            'citizen_name' => 'Maria Santos',
            'payment_source' => 'Real Property Tax',
            'amount' => 4500.00,
            'payment_gateway' => 'GCash',
            'status' => 'pending',
            'or_number' => null,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ],
        [
            'payment_reference' => 'PAY-2026-B3J7K2',
            'citizen_name' => 'Juan Dela Cruz',
            'payment_source' => 'Business Permit',
            'amount' => 12500.00,
            'payment_gateway' => 'PayMaya',
            'status' => 'completed',
            'or_number' => 'OR-2026-F981CA',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ],
        [
            'payment_reference' => 'PAY-2026-C9M4Q8',
            'citizen_name' => 'Ana Reyes',
            'payment_source' => 'Market Stall Rental',
            'amount' => 8000.00,
            'payment_gateway' => 'Credit Card',
            'status' => 'processing',
            'or_number' => null,
            'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes'))
        ],
        [
            'payment_reference' => 'PAY-2026-P2L5R9',
            'citizen_name' => 'Carlos Mendoza',
            'payment_source' => 'Zoning Fee',
            'amount' => 1500.00,
            'payment_gateway' => 'Bank Transfer',
            'status' => 'failed',
            'or_number' => null,
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ]
    ];

    foreach ($dummies as $data) {
        $db->insert('tr_online_payments', $data);
    }
    
    echo "Dummy payments inserted successfully!";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
