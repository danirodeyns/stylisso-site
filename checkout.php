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

// -------------------------
// Producten uit winkelwagen
// -------------------------
$stmt = $conn->prepare("
    SELECT c.product_id, c.type, COALESCE(p.price, c.price) AS price, c.quantity, COALESCE(p.name, 'Cadeaubon') AS name
    FROM cart c
    LEFT JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
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

// -------------------------
// Cadeaubonnen uit sessie
// -------------------------
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $voucher) {
        if ($voucher['type'] === 'voucher') {
            $items[] = [
                'type' => 'voucher',
                'name' => 'Cadeaubon',
                'price' => $voucher['amount'],
                'quantity' => 1,
                'email' => $voucher['email']
            ];
            $total += $voucher['amount'];
        }
    }
}

// Als er echt niets is
if (empty($items)) {
    echo "Je winkelwagen is leeg.";
    exit;
}

// Bepaal of alles vouchers zijn
$allVouchers = true;
foreach ($items as $i) {
    if ($i['type'] !== 'voucher') {
        $allVouchers = false;
        break;
    }
}

// Dynamische verzendkosten
$shipping = $allVouchers ? 0.00 : 5.00;

// -------------------------
// Sessiedata klaarmaken
// -------------------------
$_SESSION['checkout'] = [
    'user' => $user,
    'cart_items' => $items,
    'subtotal' => $total,
    'vat' => $total * 0.21, // 21% BTW
    'shipping' => $shipping,
    'total' => $total + $shipping, 
    'voucher_discount' => 0 // placeholder
];

// Redirect naar afrekenen.html
header('Location: afrekenen.html');
exit;
?>