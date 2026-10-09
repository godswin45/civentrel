<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
try {
    $db = Database::getInstance();
    $tables = $db->query('SHOW TABLES');
    $schema = [];
    foreach ($tables as $t) {
        $table = array_values($t)[0];
        $cols = $db->query("SHOW COLUMNS FROM `$table`");
        $schema[$table] = $cols;
    }
    echo json_encode(['status' => 'success', 'schema' => $schema]);
} catch (\Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
