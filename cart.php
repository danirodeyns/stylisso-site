<?php
session_start();
include 'db_connect.php';
include 'csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Je moet ingelogd zijn.']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
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

// GET: haal cart op
$stmt = $conn->prepare("
    SELECT cart.id, products.name, products.price, products.image, cart.quantity, cart.variant
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

echo json_encode(['success' => true, 'cart' => $cart]);