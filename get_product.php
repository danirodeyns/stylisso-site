<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Geen product geselecteerd.']);
    exit;
}

$product_id = intval($_GET['id']);

// Productgegevens ophalen
$stmt = $conn->prepare("
    SELECT id, name, description, price, image, created_at
    FROM products
    WHERE id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    echo json_encode(['error' => 'Product niet gevonden.']);
    exit;
}

// Eventueel: extra info zoals voorraad of type kan hier toegevoegd worden
echo json_encode($product);
?>