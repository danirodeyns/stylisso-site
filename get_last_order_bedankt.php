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

// Producten ophalen
$stmt2 = $conn->prepare("
    SELECT p.name, oi.quantity, oi.price
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id=? AND oi.product_id IS NOT NULL
");
$stmt2->bind_param("i", $order['id']);
$stmt2->execute();
$res2 = $stmt2->get_result();

$items = [];
while ($row = $res2->fetch_assoc()) {
    $items[] = [
        'name' => $row['name'],
        'quantity' => (int)$row['quantity'],
        'price' => (float)$row['price']
    ];
}

$orderCreated = $order['created_at'];

// Vouchers ophalen van deze order
$stmt3 = $conn->prepare("
    SELECT v.code, oi.price
    FROM order_items oi
    JOIN vouchers v ON v.value = oi.price
    WHERE oi.order_id=? 
      AND oi.type='voucher'
      AND v.created_at >= ? 
      AND v.created_at <= NOW()
    ORDER BY v.created_at ASC
");
$stmt3->bind_param("is", $order['id'], $orderCreated);
$stmt3->execute();
$res3 = $stmt3->get_result();

while ($row = $res3->fetch_assoc()) {
    $items[] = [
        'name' => "Cadeaubon: " . $row['code'],
        'quantity' => 1,
        'price' => (float)$row['price']
    ];
}

$order['products'] = $items;

echo json_encode($order);
?>