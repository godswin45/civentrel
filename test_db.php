<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
foreach (['users', 'employees', 'local_users'] as $tbl) {
    try {
        $rows = $db->query("SELECT email, employee_id, password FROM `$tbl` WHERE employee_id='TRMG-2026-362'");
        if (!empty($rows)) {
            echo "Found in $tbl:\n";
            print_r($rows);
        }
    } catch (\Throwable $e) {}
}
