<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include 'translations.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$orderItems = [];
foreach ($orders as $order) {
    $stmt2 = $pdo->prepare("
        SELECT oi.product_id, p.name, oi.quantity
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt2->execute([$order['id']]);
    $orderItems[$order['id']] = $stmt2->fetchAll();
}

echo json_encode([
    'orders' => $orders,
    'orderItems' => $orderItems
]);
?>