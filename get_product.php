<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include 'translations.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Geen product geselecteerd.']);
    exit;
}

$product_id = intval($_GET['id']);

// Huidige gebruiker (voor wishlist check)
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Productgegevens ophalen, inclusief wishlist status en maat-opties
$sql = "
    SELECT p.id, p.name, p.description, p.price, p.image, p.created_at, 
           p.category_id, p.specifications, p.maat,
           CASE WHEN w.product_id IS NOT NULL THEN 1 ELSE 0 END AS in_wishlist
    FROM products p
    LEFT JOIN wishlist w 
      ON w.product_id = p.id AND w.user_id = ?
    WHERE p.id = ?
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ii", $userId, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        echo json_encode(['error' => 'Product niet gevonden.']);
        exit;
    }

    // Cast in_wishlist naar boolean
    $product['in_wishlist'] = (bool)$product['in_wishlist'];

    // Als maat niet leeg is, splitsen in een array
    if (!empty($product['maat'])) {
        $product['maat'] = explode(";", $product['maat']);
    } else {
        $product['maat'] = null; // geen maat nodig
    }

    echo json_encode($product);

    $stmt->close();
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>