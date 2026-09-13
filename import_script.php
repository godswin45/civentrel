<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'revenue-and-treasury-civentraldb-prugqc'; // Internal Dokploy network host
$port = '3306'; // Internal port
$db   = 'treasury';
$user = 'treasury_admin';
$pass = 'Putangina_2';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
    ]);
    
    $file = 'civentrel_backup.sql';
    if (!file_exists($file)) {
        die("ERROR: $file not found on server.");
    }

    $sql = file_get_contents($file);
    if (empty(trim($sql))) {
        die("ERROR: SQL file is empty.");
    }
    
    // Execute the SQL file
    $pdo->exec($sql);
    echo "SUCCESS: Database imported successfully.";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
