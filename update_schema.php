<?php
require_once __DIR__ . '/src/bootstrap.php';

try {
    $db = Database::getInstance();
    
    // Check if source_module exists, if not add it
    $result = $db->query("SHOW COLUMNS FROM tr_online_payments LIKE 'source_module'");
    if (empty($result)) {
        $db->query("ALTER TABLE tr_online_payments ADD COLUMN source_module VARCHAR(150) NULL AFTER payment_source");
        echo "Added source_module column.\n";
    }

    // Check if application_id exists, if not add it
    $result = $db->query("SHOW COLUMNS FROM tr_online_payments LIKE 'application_id'");
    if (empty($result)) {
        $db->query("ALTER TABLE tr_online_payments ADD COLUMN application_id VARCHAR(100) NULL AFTER source_module");
        echo "Added application_id column.\n";
    }

    echo "Schema update completed successfully!";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
