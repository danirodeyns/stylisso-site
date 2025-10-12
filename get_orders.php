<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include 'translations.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

// Haal orders van deze gebruiker
$sqlOrders = "SELECT id, total_price, status, created_at FROM orders WHERE user_id=? ORDER BY created_at DESC";
if ($stmt = $conn->prepare($sqlOrders)) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $resOrders = $stmt->get_result();

    $orders = [];

    while ($order = $resOrders->fetch_assoc()) {
        $orderId = $order['id'];
        $items = [];

        // Gewone producten ophalen inclusief vertaling
        $sqlProducts = "
            SELECT oi.product_id, 
                COALESCE(pt.name, p.name) AS name,
                oi.quantity,
                oi.price,
                p.image,
                COALESCE(pt.maat, p.maat) AS maat
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.lang = ?
            WHERE oi.order_id=? AND oi.product_id IS NOT NULL
        ";
        if ($stmtProd = $conn->prepare($sqlProducts)) {
            $stmtProd->bind_param("si", $lang, $orderId);
            $stmtProd->execute();
            $resProd = $stmtProd->get_result();
            while ($row = $resProd->fetch_assoc()) {
                // Afbeeldingen verwerken
                if (!empty($row['image'])) {
                    $parts = array_map('trim', explode(';', $row['image']));
                    $mainImage = $parts[0];
                    $images = count($parts) > 1 ? $parts : [];
                    if (count($parts) === 1) $images = [];
                } else {
                    $mainImage = 'placeholder.jpg';
                    $images = [];
                }

                $items[] = [
                    'id'       => (int)$row['product_id'],
                    'name'     => $row['name'],
                    'quantity' => (int)$row['quantity'],
                    'price'    => (float)$row['price'],
                    'image'    => $mainImage,
                    'images'   => $images,
                    'maat'     => $row['maat'] ?: null,
                    'type'     => 'product'
                ];
            }
            $stmtProd->close();
        }

        // Vouchers ophalen
        $voucherLabel = $translations[$lang]['script_order_voucher'] ?? 'Cadeaubon';
        $sqlVoucher = "
            SELECT v.code, oi.price
            FROM order_items oi
            JOIN vouchers v ON oi.voucher_id = v.id
            WHERE oi.order_id=? AND oi.type='voucher'
            ORDER BY v.created_at ASC
        ";
        if ($stmtVoucher = $conn->prepare($sqlVoucher)) {
            $stmtVoucher->bind_param("i", $orderId);
            $stmtVoucher->execute();
            $resVoucher = $stmtVoucher->get_result();
            while ($row = $resVoucher->fetch_assoc()) {
                $items[] = [
                    'id'       => null,
                    'name'     => $voucherLabel . ": " . $row['code'],
                    'quantity' => 1,
                    'price'    => (float)$row['price'],
                    'image'    => null,
                    'images'   => [],
                    'maat'     => null,
                    'type'     => 'voucher'
                ];
            }
            $stmtVoucher->close();
        }

        $order['products'] = $items;
        $orders[] = $order;
    }

    echo json_encode($orders);
    $stmt->close();
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>