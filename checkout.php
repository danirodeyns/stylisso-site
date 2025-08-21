<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login_registreren.html');
    exit;
}

$user_id = $_SESSION['user_id'];

// Adres ophalen uit DB
$stmt = $conn->prepare("SELECT address, name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$current_address = $user['address'] ?? '';

// Winkelwagen ophalen
$stmt = $conn->prepare("
    SELECT cart.product_id, products.price, cart.quantity, products.name
    FROM cart
    JOIN products ON cart.product_id = products.id
    WHERE cart.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$total = 0;
$items = [];

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
    $total += $row['price'] * $row['quantity'];
}

if (empty($items)) {
    echo "Je winkelwagen is leeg.";
    exit;
}

// Sessiedata klaarmaken voor afrekenen
$_SESSION['checkout'] = [
    'user' => $user,
    'cart_items' => $items,
    'subtotal' => $total,
    'vat' => $total * 0.21, // 21% BTW
    'shipping' => 5.00,     // vaste verzendkosten
    'total' => $total * 1.21 + 5.00
];

// Redirect naar afrekenen.html
header('Location: afrekenen.html');
exit;
?>