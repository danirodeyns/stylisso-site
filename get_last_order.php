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
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

// -------------------------
// Laatste order ophalen
// -------------------------
$stmt = $conn->prepare("SELECT id, total_price, status, created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
if (!$stmt) {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}
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

// -------------------------
// Gewone producten ophalen (met vertaling)
// -------------------------
$sqlProd = "
    SELECT oi.id AS order_item_id, oi.quantity,
        COALESCE(pt.name, p.name) AS product_name,
        p.image AS product_image,
        oi.maat AS size, oi.type
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_translations pt 
        ON pt.product_id = p.id AND pt.lang = ?
    WHERE oi.order_id=? AND oi.product_id IS NOT NULL
";

$stmtProd = $conn->prepare($sqlProd);
if (!$stmtProd) {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}
$stmtProd->bind_param("si", $lang, $orderId);
$stmtProd->execute();
$resProd = $stmtProd->get_result();

while ($row = $resProd->fetch_assoc()) {
    // --- Afbeeldingen verwerken ---
    $row['product_image'] = $row['product_image'] ?: 'images/placeholder.png';
    $imagesArray = explode(';', $row['product_image']);
    $mainImage = trim($imagesArray[0]);
    $allImages = (count($imagesArray) > 1) ? array_map('trim', $imagesArray) : [];

    $items[] = [
        'order_item_id' => $row['order_item_id'],
        'product_name'  => $row['product_name'],
        'image'         => $mainImage,
        'images'        => $allImages,
        'quantity'      => (int)$row['quantity'],
        'size'          => $row['size'],
        'type'          => 'product'
    ];
}

// -------------------------
// Controleren op vouchers
// -------------------------
$sqlVoucher = "SELECT id FROM order_items WHERE order_id=? AND type='voucher'";
$stmtVoucher = $conn->prepare($sqlVoucher);
if (!$stmtVoucher) {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}
$stmtVoucher->bind_param("i", $orderId);
$stmtVoucher->execute();
$resVoucher = $stmtVoucher->get_result();

while ($rowVoucher = $resVoucher->fetch_assoc()) {
    $items[] = [
        'order_item_id' => $rowVoucher['id'],
        'product_name'  => 'Cadeaubon(nen)',
        'image'         => 'cadeaubon/voucher.png',
        'images'        => [],
        'quantity'      => 1,
        'type'          => 'voucher'
    ];
}

// -------------------------
// Resultaat teruggeven
// -------------------------
$order['products'] = $items;
echo json_encode($order, JSON_UNESCAPED_UNICODE);

$stmt->close();
$stmtProd->close();
$stmtVoucher->close();
$conn->close();
?>