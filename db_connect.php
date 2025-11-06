<?php
$dbHost = 'localhost';
$dbUser = 'stylisso_website';
$dbPass = 'rIrho7-fixbes-pijsiq';
$dbName = 'stylisso_db';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Verbinding mislukt: " . $conn->connect_error);
}

// Zet standaard karakterset voor alle queries
$conn->set_charset("utf8mb4");

if (!$conn->set_charset("utf8mb4")) {
    die("Fout bij instellen charset: " . $conn->error);
}
?>