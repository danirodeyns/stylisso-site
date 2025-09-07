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

$orderId = $order['id'];
$items = [];

// Gewone producten ophalen
$stmtProd = $conn->prepare("
    SELECT p.name, oi.quantity
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id=? AND oi.product_id IS NOT NULL
");
$stmtProd->bind_param("i", $orderId);
$stmtProd->execute();
$resProd = $stmtProd->get_result();
while ($row = $resProd->fetch_assoc()) {
    $items[] = [
        'name' => $row['name'],
        'quantity' => $row['quantity'],
        'type' => 'product'
    ];
}

// Controleren of er vouchers zijn
$stmtVoucher = $conn->prepare("
    SELECT COUNT(*) AS voucher_count
    FROM order_items
    WHERE order_id=? AND type='voucher'
");
$stmtVoucher->bind_param("i", $orderId);
$stmtVoucher->execute();
$resVoucher = $stmtVoucher->get_result();
$rowVoucher = $resVoucher->fetch_assoc();

if ($rowVoucher['voucher_count'] > 0) {
    // Voeg één item toe voor alle vouchers
    $items[] = [
        'name' => "Cadeaubon(nen)",
        'quantity' => 1,
        'type' => 'voucher'
    ];
}

$order['products'] = $items;

echo json_encode($order);
?>