<?php
require_once __DIR__ . '/../src/bootstrap.php';
try {
    $db = new \App\Core\Database();
    $db->query("ALTER TABLE tr_business_apps MODIFY COLUMN transaction_type ENUM('new', 'renewal', 'retirement') NOT NULL");
    echo "SUCCESS";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
