<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

include 'db_connect.php';
include 'translations.php';
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
            'maat'     => $row['maat'] ?? null,  // maat toevoegen
            'type'     => 'product'
        ];
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
        $items[] = [
            'id'       => null,
            'name'     => "Cadeaubon: " . $row['code'],
            'quantity' => 1,
            'price'    => (float)$row['price'],
            'image'    => null,
            'maat'     => null,
            'type'     => 'voucher'
        ];
    }

    $order['products'] = $items;
    $orders[] = $order;
}

echo json_encode($orders);
?>