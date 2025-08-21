<?php
session_start();
include 'db_connect.php';
include 'csrf.php';

header('Content-Type: application/json');

// Controleer of de gebruiker ingelogd is
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Je moet ingelogd zijn.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Functie voor CSRF-validatie bij AJAX/fetch POSTs
function csrf_validate_ajax() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ongeldig CSRF-token']);
        exit;
    }
}

// POST: update of voeg product toe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_ajax(); // <<< CSRF-validatie voor AJAX POSTs

    $data = json_decode(file_get_contents('php://input'), true);
    $product_id = isset($data['product_id']) ? (int)$data['product_id'] : 0;
    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 0;

    if ($product_id <= 0 || $quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Ongeldige product ID of hoeveelheid.']);
        exit;
    }

    // Update bestaande rij in winkelwagen
    $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("iii", $quantity, $user_id, $product_id);
    $stmt->execute();

    // Voeg toe als er nog geen rij bestond
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
    SELECT cart.id, products.id AS product_id, products.name, products.price, products.image, cart.quantity, cart.variant
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
?>