<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php'; // jouw bestaande mysqli connectie

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];

// Bereid query voor en haal wishlist items op
$sql = "SELECT p.id, p.name, p.price, p.image
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);

if (!$stmt->execute()) {
    echo json_encode(['error' => 'Database fout']);
    exit;
}

$result = $stmt->get_result();
$items = [];

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($items);
?>