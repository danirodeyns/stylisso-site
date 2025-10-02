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

// Laatste order ophalen
$stmt = $conn->prepare("
    SELECT id, total_price, status, created_at 
    FROM orders 
    WHERE user_id=? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$orderResult = $stmt->get_result();
$order = $orderResult->fetch_assoc();

if (!$order) {
    echo json_encode(['error' => 'Nog geen bestellingen']);
    exit;
}

$orderId = $order['id'];
$items   = [];

// Gewone producten ophalen inclusief maat
$stmtProd = $conn->prepare("
    SELECT oi.product_id, p.name, p.image, oi.quantity, oi.price, oi.maat
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id=? AND oi.product_id IS NOT NULL
");
$stmtProd->bind_param("i", $orderId);
$stmtProd->execute();
$resProd = $stmtProd->get_result();
while ($row = $resProd->fetch_assoc()) {
    $items[] = [
        'id'       => (int)$row['product_id'],
        'name'     => $row['name'],
        'quantity' => (int)$row['quantity'],
        'price'    => (float)$row['price'],
        'image'    => $row['image'] ? $row['image'] : 'placeholder.jpg',
        'maat'     => $row['maat'],        // maat toegevoegd
        'type'     => 'product'
    ];
}

// Vouchers ophalen
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
    $items[] = [
        'id'       => null,
        'name'     => "Cadeaubon: " . $row['code'],
        'quantity' => 1,
        'price'    => (float)$row['price'],
        'image'    => null,
        'maat'     => null,                // geen maat voor vouchers
        'type'     => 'voucher'
    ];
}

$order['products'] = $items;

echo json_encode($order);
?>