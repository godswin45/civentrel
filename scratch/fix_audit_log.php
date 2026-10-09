<?php
require_once __DIR__ . '/../src/bootstrap.php';
try {
    $db = Database::getInstance();
    $db->query("ALTER TABLE tr_audit_log MODIFY COLUMN user_id VARCHAR(255) NULL");
    echo "Successfully altered tr_audit_log.user_id to VARCHAR(255)";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
