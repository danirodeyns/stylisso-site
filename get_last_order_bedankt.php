<?php
session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'translations.php';

// --- Taalparameter instellen ---
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl'; // standaard taal

// --- Check login ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];

// --- Laatste order ophalen ---
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

// --- Producten ophalen met vertaling ---
$stmtProd = $conn->prepare("
    SELECT 
        oi.product_id, 
        COALESCE(pt.name, p.name) AS name, 
        COALESCE(pt.description, p.description) AS description,
        p.image, 
        oi.quantity, 
        oi.price, 
        oi.maat
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_translations pt 
        ON pt.product_id = p.id AND pt.lang  = ?
    WHERE oi.order_id = ? AND oi.product_id IS NOT NULL
");
$stmtProd->bind_param("si", $lang, $orderId);
$stmtProd->execute();
$resProd = $stmtProd->get_result();

while ($row = $resProd->fetch_assoc()) {
    $items[] = [
        'id'       => (int)$row['product_id'],
        'name'     => $row['name'],
        'quantity' => (int)$row['quantity'],
        'price'    => (float)$row['price'],
        'image'    => $row['image'] ?: 'placeholder.jpg',
        'maat'     => $row['maat'],
        'type'     => 'product',
    ];
}

// --- Vouchers ophalen ---
$stmtVoucher = $conn->prepare("
    SELECT v.code, oi.price
    FROM order_items oi
    JOIN vouchers v ON oi.voucher_id = v.id
    WHERE oi.order_id = ? AND oi.type = 'voucher'
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
        'type'     => 'voucher',
    ];
}

// --- Combineer alles ---
$order['products'] = $items;

echo json_encode($order);
?>