<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'revenue-and-treasury-civentraldb-prugqc'; 
$db   = 'treasury';
$user = 'treasury_admin';
$pass = 'Putangina_2';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    die("ERROR: Failed to connect to MySQL: " . $mysqli->connect_error);
}

$file = 'civentrel_backup.sql';
if (!file_exists($file)) die("ERROR: $file not found on server.");
$sql = file_get_contents($file);

if ($mysqli->multi_query($sql)) {
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
    echo "SUCCESS: Database imported successfully.";
} else {
    echo "ERROR: " . $mysqli->error;
}
$mysqli->close();
?>
