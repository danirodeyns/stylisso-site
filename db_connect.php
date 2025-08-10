<?php
// db_connect.php
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'mijnproject'; // Pas aan naar jouw db naam

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Verbinding mislukt: " . $conn->connect_error);
}
?>