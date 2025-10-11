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

// Haal orders van de gebruiker op
$stmt = $pdo->prepare("
    SELECT id, created_at 
    FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$orderItems = [];
foreach ($orders as $order) {
    $stmt2 = $pdo->prepare("
        SELECT 
            oi.product_id, 
            COALESCE(pt.name, p.name) AS name, 
            oi.quantity
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_translations pt 
            ON pt.product_id = p.id AND pt.lang = ?
        WHERE oi.order_id = ?
    ");
    $stmt2->execute([$lang, $order['id']]);
    $orderItems[$order['id']] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode([
    'orders' => $orders,
    'orderItems' => $orderItems
]);
?>