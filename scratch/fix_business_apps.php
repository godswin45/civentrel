<?php
/**
 * FIX SCRIPT FOR BUSINESS APPS
 * Run once to fix the transaction_type and audit log module names for Business Permits.
 */
require_once __DIR__ . '/../src/bootstrap.php';

try {
    $pdo = $db->getPdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fix 1: The seeder accidentally set transaction_type = 'renewal' even for 'new' apps
    $affectedApps = $pdo->exec("UPDATE tr_business_apps SET transaction_type = 'new' WHERE application_type = 'new' AND transaction_type = 'renewal'");
    
    // Fix 2: The seeder logged audit history as 'business' but the page expects 'business_permit'
    $affectedLogs = $pdo->exec("UPDATE tr_audit_log SET module = 'business_permit' WHERE module = 'business' AND table_name = 'tr_business_apps'");
    
    echo "<h1>Fix Applied Successfully!</h1>";
    echo "<p>Fixed Business Applications: <b>{$affectedApps}</b></p>";
    echo "<p>Fixed Audit Logs: <b>{$affectedLogs}</b></p>";
    echo "<p><a href='../pages/treasury/business.php'>Go back to Business Permits</a></p>";
    
} catch (\Throwable $e) {
    echo "<h1>Error</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
