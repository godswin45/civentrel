<?php
require_once __DIR__ . '/src/bootstrap.php';

try {
    $sql = "ALTER TABLE tr_budget_requests ADD COLUMN supporting_document VARCHAR(255) DEFAULT NULL AFTER description";
    $db->exec($sql);
    echo "<h1>SUCCESS!</h1><p>The 'supporting_document' column was successfully added to the live database.</p>";
    echo "<p><strong>Security Warning:</strong> You must now delete this file (run_migration.php) from your code and push to GitHub again so nobody else can run it!</p>";
} catch (\PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<h1>ALREADY DONE!</h1><p>The column already exists. You are good to go!</p>";
    } else {
        echo "<h1>ERROR</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
