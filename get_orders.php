<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

include 'db_connect.php';
$userId = $_SESSION['user_id'];

// Haal alle orders op
$query = "SELECT id, total_price, status, created_at FROM orders WHERE user_id=? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];

while ($order = $result->fetch_assoc()) {
    $orderId = $order['id'];
    $items = [];

    // Gewone producten ophalen
    $stmtProd = $conn->prepare("
        SELECT p.name, oi.quantity, oi.price
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id=? AND oi.product_id IS NOT NULL
    ");
    $stmtProd->bind_param("i", $orderId);
    $stmtProd->execute();
    $resProd = $stmtProd->get_result();
    while ($row = $resProd->fetch_assoc()) {
        $items[] = $row['quantity'] . ' x ' . $row['name'];
    }

    // Vouchers ophalen via voucher_id
    $stmtVoucher = $conn->prepare("
        SELECT v.code, oi.price
        FROM order_items oi
        JOIN vouchers v ON oi.voucher_id = v.id
        WHERE oi.order_id=? AND oi.type='voucher'
        ORDER BY v.created_at ASC
    ");
    $stmtVoucher->bind_param("i", $orderId);
    $stmtVoucher->execute();
    $resVoucher = $stmtVoucher->get_result();
    while ($row = $resVoucher->fetch_assoc()) {
        $items[] = 'Cadeaubon: ' . "€" . number_format($row['price'], 2);
    }

    $order['products'] = $items;
    $orders[] = $order;
}

echo json_encode($orders);
?>