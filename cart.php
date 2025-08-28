<?php
session_start();
include 'db_connect.php';
include 'csrf.php';

ini_set('display_errors', 0); 
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Je moet ingelogd zijn.']);
    exit;
}

$user_id = $_SESSION['user_id'];

function csrf_validate_ajax() {
    if (!isset($_SESSION['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF-token ontbreekt in sessie']);
        exit;
    }    
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ongeldig CSRF-token']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_ajax();

    $data = json_decode(file_get_contents('php://input'), true);
    $product_id = isset($data['product_id']) ? (int)$data['product_id'] : 0;
    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 0;

    if ($product_id <= 0 || $quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Ongeldige product ID of hoeveelheid.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("iii", $quantity, $user_id, $product_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $user_id, $product_id, $quantity);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Product toegevoegd aan winkelwagen.']);
    exit;
}

// GET: producten ophalen
$stmt = $conn->prepare("
    SELECT cart.id, products.id AS product_id, products.name, products.price, products.image, cart.quantity
    FROM cart
    JOIN products ON cart.product_id = products.id
    WHERE cart.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart = [];
while ($row = $result->fetch_assoc()) {
    $cart[] = $row;
}

// Eventuele vouchers uit de sessie toevoegen
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $voucher) {
        if ($voucher['type'] === 'voucher') {
            $cart[] = [
                'id' => 'voucher_' . uniqid(),
                'product_id' => null,
                'name' => 'Cadeaubon (' . htmlspecialchars($voucher['email']) . ')',
                'price' => $voucher['amount'],
                'image' => 'images/voucher.png', // placeholder
                'quantity' => 1
            ];
        }
    }
}

echo json_encode(['success' => true, 'cart' => $cart]);
?>