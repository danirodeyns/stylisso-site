<?php
session_start();
header('Content-Type: application/json');

// Controleer of de gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

// Database connectie
include 'db_connect.php';

$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    
    // Selecteer gebruikersgegevens inclusief bedrijf en BTW-nummer
    $stmt = $pdo->prepare("
        SELECT name, email, address, newsletter, company, vat
        FROM users
        WHERE id = :id
    ");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        echo json_encode($user);
    } else {
        echo json_encode(['error' => 'Gebruiker niet gevonden']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>