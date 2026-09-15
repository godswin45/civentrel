<?php
require_once __DIR__ . '/src/bootstrap.php';

try {
    $db = new \PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};port={$_ENV['DB_PORT']}", $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    $sql = "ALTER TABLE tr_budget_requests ADD COLUMN supporting_document VARCHAR(255) DEFAULT NULL AFTER description";
    $db->exec($sql);
    echo "Column added successfully!";
} catch (\PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
