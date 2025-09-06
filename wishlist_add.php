<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];
$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if ($productId <= 0) {
    echo json_encode(['error' => 'Geen product opgegeven']);
    exit;
}

// Voeg toe aan wishlist (IGNORE voorkomt dubbele entries)
$sql = "INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $productId);

if ($stmt->execute()) {
    echo json_encode(['success' => 'Toegevoegd aan wishlist']);
} else {
    echo json_encode(['error' => 'Kon product niet toevoegen']);
}

$stmt->close();
$conn->close();
?>