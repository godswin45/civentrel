<?php
$host = 'revenue-and-treasury-civentraldb-prugqc'; // Internal Dokploy network host
$port = '3306'; // Internal port
$db   = 'treasury';
$user = 'treasury_admin';
$pass = 'Putangina_2';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $sql = file_get_contents('civentrel_backup.sql');
    
    // Execute the SQL file
    $pdo->exec($sql);
    echo "SUCCESS: Database imported successfully.";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
