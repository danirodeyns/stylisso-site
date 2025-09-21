<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';
include 'translations.php';

$userId = $_SESSION['user_id'] ?? 0;

if (!$userId) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

// Haal order_items + return_status
$sql = "
    SELECT 
        oi.id AS order_item_id,
        oi.order_id,
        oi.product_id,
        oi.quantity,
        oi.price AS item_price,
        o.created_at AS order_date,
        p.name AS product_name,
        p.image AS product_image,
        r.status AS return_status
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN returns r ON r.order_item_id = oi.id
    WHERE o.user_id = ? AND oi.type = 'product'
    ORDER BY o.created_at DESC, oi.id ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$orderItems = [];
while ($row = $result->fetch_assoc()) {
    $orderItems[] = [
        'order_item_id' => $row['order_item_id'],
        'order_id'      => $row['order_id'],
        'product_id'    => $row['product_id'],
        'quantity'      => $row['quantity'],
        'item_price'    => $row['item_price'],
        'order_date'    => $row['order_date'],
        'product_name'  => $row['product_name'],
        'product_image' => $row['product_image'],
        'return_status' => $row['return_status'] // NULL, requested, approved, processed, rejected
    ];
}

echo json_encode($orderItems);
exit;
?>