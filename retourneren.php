<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';
include 'translations.php';

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if (!$userId) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

// SQL: haal productnaam/afbeelding uit product_translations indien beschikbaar, anders fallback op products
$sql = "
    SELECT 
        oi.id AS order_item_id,
        oi.order_id,
        oi.product_id,
        oi.quantity,
        oi.price AS item_price,
        oi.maat AS size,
        o.created_at AS order_date,
        COALESCE(pt.name, p.name) AS product_name,
        p.image AS image,
        r.status AS return_status
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_translations pt 
        ON pt.product_id = p.id AND pt.lang = ?
    LEFT JOIN returns r ON r.order_item_id = oi.id
    WHERE o.user_id = ? AND oi.type = 'product'
    ORDER BY o.created_at DESC, oi.id ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Database prepare fout: ' . $conn->error]);
    exit;
}

$stmt->bind_param('si', $lang, $userId);

if (!$stmt->execute()) {
    echo json_encode(['error' => 'Database execute fout: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$result = $stmt->get_result();

$orderItems = [];
while ($row = $result->fetch_assoc()) {
    // --- Afbeeldingen verwerken ---
    if (!empty($row['image'])) {
        $parts = array_map('trim', explode(';', $row['image']));
        $image = $parts[0]; // eerste afbeelding
        $images = count($parts) > 1 ? $parts : [];
    } else {
        $image = 'images/placeholder.png';
        $images = [];
    }

    $orderItems[] = [
        'order_item_id' => (int)$row['order_item_id'],
        'order_id'      => (int)$row['order_id'],
        'product_id'    => (int)$row['product_id'],
        'quantity'      => (int)$row['quantity'],
        'item_price'    => (float)$row['item_price'],
        'size'          => $row['size'],           // ongewijzigde string; JS toont dit als tekst
        'order_date'    => $row['order_date'],
        'product_name'  => $row['product_name'],
        'image'         => $image,
        'images'        => $images,
        'return_status' => $row['return_status']   // NULL of status string
    ];
}

$stmt->close();
$conn->close();

echo json_encode($orderItems);
exit;
?>