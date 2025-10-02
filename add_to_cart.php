<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include 'csrf.php';
include 'translations.php';
csrf_validate(); // stopt script als token fout is

// Check of gebruiker ingelogd is
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];
$productId = $_POST['product_id'] ?? null;
$quantity = $_POST['quantity'] ?? 1;
$quantity = max(1, intval($quantity)); // Minimum 1
$maat = $_POST['maat'] ?? null; // <-- key "maat", consistent met JS en DB

if (!$productId) {
    echo json_encode(['error' => 'Geen product opgegeven']);
    exit;
}

// --- Haal productprijs uit products tabel vóór insert/update ---
$priceStmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
$priceStmt->bind_param("i", $productId);
$priceStmt->execute();
$priceResult = $priceStmt->get_result();
$product = $priceResult->fetch_assoc();

if (!$product) {
    echo json_encode(['error' => 'Product niet gevonden']);
    exit;
}

$price = $product['price'];

// --- Controleer of product + maat al in winkelwagen zit ---
if ($maat) {
    $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ? AND type='product' AND maat = ?");
    $stmt->bind_param("iis", $userId, $productId, $maat);
} else {
    $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ? AND type='product'");
    $stmt->bind_param("ii", $userId, $productId);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Product al in cart: verhoog quantity
    $row = $result->fetch_assoc();
    $newQuantity = $row['quantity'] + $quantity;

    if ($maat) {
        $updateStmt = $conn->prepare("UPDATE cart SET quantity = ?, price = ? WHERE user_id = ? AND product_id = ? AND type='product' AND maat = ?");
        $updateStmt->bind_param("diiis", $newQuantity, $price, $userId, $productId, $maat);
    } else {
        $updateStmt = $conn->prepare("UPDATE cart SET quantity = ?, price = ? WHERE user_id = ? AND product_id = ? AND type='product'");
        $updateStmt->bind_param("diii", $newQuantity, $price, $userId, $productId);
    }
    $updateStmt->execute();
} else {
    // Nieuw product toevoegen
    if ($maat) {
        $insertStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, price, maat) VALUES (?, ?, 'product', ?, ?, ?)");
        $insertStmt->bind_param("iiids", $userId, $productId, $quantity, $price, $maat);
    } else {
        $insertStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, price) VALUES (?, ?, 'product', ?, ?)");
        $insertStmt->bind_param("iiid", $userId, $productId, $quantity, $price);
    }
    $insertStmt->execute();
}

// --- Response teruggeven ---
echo json_encode([
    'success' => 'Product toegevoegd aan winkelwagen',
    'product_id' => (int)$productId,
    'quantity' => (int)$quantity,
    'price' => (float)$price,
    'maat' => $maat
]);
?>