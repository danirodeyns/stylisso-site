<?php
session_start();
include 'db_connect.php';
include 'translations.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login_registreren.html');
    exit;
}

$user_id = $_SESSION['user_id'];

// ================================
// 1. Laatste shipping adres ophalen uit DB
// ================================
$stmt = $conn->prepare("
    SELECT a.id AS address_id, a.street, a.house_number, a.postal_code, a.city, a.country, 
        u.name, u.email, u.company_name, u.vat_number
    FROM addresses a
    INNER JOIN users u ON u.id = a.user_id
    WHERE a.user_id = ? AND a.type = 'shipping'
    ORDER BY a.id DESC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Als geen adres aanwezig, lege velden
if (!$user) {
    $user = [
        'address_id' => null,
        'street' => '',
        'house_number' => '',
        'postal_code' => '',
        'city' => '',
        'country' => '',
        'name' => '',
        'email' => '',
        'company_name' => '',
        'vat_number' => ''
    ];
}

// ================================
// 2. Producten uit winkelwagen ophalen (inclusief maat)
// ================================
$stmt = $conn->prepare("
    SELECT c.product_id, c.type, COALESCE(p.price, c.price) AS price, c.quantity, 
        COALESCE(p.name, 'Cadeaubon') AS name, c.maat
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
    $items[] = $row; // 'maat' zit nu ook in $row
    $total += $row['price'] * $row['quantity'];
}

// ================================
// 3. Cadeaubonnen uit sessie toevoegen
// ================================
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $voucher) {
        if ($voucher['type'] === 'voucher') {
            $items[] = [
                'type' => 'voucher',
                'name' => 'Cadeaubon',
                'price' => $voucher['amount'],
                'quantity' => 1,
                'email' => $voucher['email'],
                'maat' => null // geen maat voor vouchers
            ];
            $total += $voucher['amount'];
        }
    }
}

// ================================
// 4. Controleer of winkelwagen leeg is
// ================================
if (empty($items)) {
    echo "Je winkelwagen is leeg.";
    exit;
}

// ================================
// 5. Bepaal of alles vouchers zijn
// ================================
$allVouchers = true;
foreach ($items as $i) {
    if ($i['type'] !== 'voucher') {
        $allVouchers = false;
        break;
    }
}

// ================================
// 6. Dynamische verzendkosten
// ================================
$shipping = 5.00;
if ($total >= 50 || $allVouchers) {
    $shipping = 0.00;
}

// ================================
// 7. Sessiedata klaarmaken voor afrekenen
// ================================
$_SESSION['checkout'] = [
    'user' => $user,
    'cart_items' => $items,
    'subtotal' => $total,
    'vat' => $total * 0.21, // 21% BTW
    'shipping' => $shipping,
    'total' => $total + $shipping,
    'voucher_discount' => 0 // placeholder
];

// ================================
// 8. Redirect naar afrekenen.html
// ================================
header('Location: afrekenen.html');
exit;
?>