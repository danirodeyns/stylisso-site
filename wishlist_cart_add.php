<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include 'translations.php';
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

// Check of gebruiker ingelogd is
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];
$productId = $_POST['product_id'] ?? null;
$quantity = $_POST['quantity'] ?? 1;
$maat = isset($_POST['size']) && $_POST['size'] !== '' ? trim($_POST['size']) : null; // maat uit JS

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
$price = $product['price'] ?? 0;

// --- Controleer of product met dezelfde maat al in winkelwagen zit ---
if ($maat) {
    $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ? AND maat = ?");
    $stmt->bind_param("iis", $userId, $productId, $maat);
} else {
    $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ? AND maat IS NULL");
    $stmt->bind_param("ii", $userId, $productId);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Product al in cart: verhoog quantity
    $row = $result->fetch_assoc();
    $newQuantity = $row['quantity'] + $quantity;

    if ($maat) {
        $updateStmt = $conn->prepare("UPDATE cart SET quantity = ?, price = ? WHERE user_id = ? AND product_id = ? AND maat = ?");
        $updateStmt->bind_param("diiis", $newQuantity, $price, $userId, $productId, $maat);
    } else {
        $updateStmt = $conn->prepare("UPDATE cart SET quantity = ?, price = ? WHERE user_id = ? AND product_id = ? AND maat IS NULL");
        $updateStmt->bind_param("diii", $newQuantity, $price, $userId, $productId);
    }
    $updateStmt->execute();
} else {
    // Nieuw product toevoegen
    $insertStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, price, maat) VALUES (?, ?, ?, ?, ?)");
    $insertStmt->bind_param("iiids", $userId, $productId, $quantity, $price, $maat);
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