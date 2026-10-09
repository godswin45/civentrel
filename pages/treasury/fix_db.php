<?php
require_once __DIR__ . '/../../src/bootstrap.php';
try {
    $db = \App\Core\Database::getInstance();
    $db->query("ALTER TABLE tr_business_apps MODIFY COLUMN transaction_type ENUM('new', 'renewal', 'retirement') NOT NULL");
    echo "SUCCESS: altered tr_business_apps\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
