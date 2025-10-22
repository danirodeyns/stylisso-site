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

// Mysqli connectie
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connectie mislukt: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset("utf8mb4");

// Haal orders van de gebruiker op
$stmt = $conn->prepare("
    SELECT id, created_at 
    FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

$orderItems = [];

foreach ($orders as $order) {
    $stmt2 = $conn->prepare("
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
    $stmt2->bind_param("si", $lang, $order['id']);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $items = [];
    while ($row2 = $result2->fetch_assoc()) {
        $items[] = $row2;
    }
    $orderItems[$order['id']] = $items;
    $stmt2->close();
}

$conn->close();

echo json_encode([
    'orders' => $orders,
    'orderItems' => $orderItems
]);
?>