<?php
require_once __DIR__ . '/../../src/bootstrap.php';
try {
    $db = Database::getInstance();
    $cols = $db->query("SHOW COLUMNS FROM tr_business_apps")->fetchAll();
    echo "<pre>"; print_r($cols); echo "</pre>";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
