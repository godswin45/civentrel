<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
try {
    $db->query("CREATE TABLE IF NOT EXISTS local_feature_permissions (
        user_id VARCHAR(64) PRIMARY KEY,
        permissions_json TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", []);
    echo "Table created successfully.";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
