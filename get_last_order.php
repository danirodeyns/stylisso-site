<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];

// Laatste order ophalen
$stmt = $conn->prepare("SELECT id, total_price, status, created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$orderResult = $stmt->get_result();
$order = $orderResult->fetch_assoc();

if (!$order) {
    echo json_encode(['error' => 'Nog geen bestellingen']);
    exit;
}

// Producten van deze order ophalen (geen vouchers)
$stmt2 = $conn->prepare("
    SELECT p.name, oi.quantity
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id=? AND oi.product_id IS NOT NULL
");
$stmt2->bind_param("i", $order['id']);
$stmt2->execute();
$res2 = $stmt2->get_result();

$products = [];
while ($row = $res2->fetch_assoc()) {
    $products[] = [
        'name' => $row['name'],
        'quantity' => $row['quantity']
    ];
}

$order['products'] = $products;

echo json_encode($order);
?>