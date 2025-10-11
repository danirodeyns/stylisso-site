<?php
// get_related_products.php
session_start();
header('Content-Type: application/json');

require 'db_connect.php';
include 'translations.php';

// --- Parameters ---
$lang     = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl'; // standaard taal
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$exclude  = isset($_GET['exclude']) ? (int)$_GET['exclude'] : 0;

// --- Huidige gebruiker (voor wishlist check) ---
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($category <= 0) {
    echo json_encode([]);
    exit;
}

// --- SQL statement met LEFT JOIN op wishlist en translations ---
$sql = "
    SELECT p.id, 
        COALESCE(t.name, p.name) AS name,
        p.price,
        p.image,
        CASE WHEN w.product_id IS NOT NULL THEN 1 ELSE 0 END AS in_wishlist
    FROM products p
    LEFT JOIN product_translations t 
        ON t.product_id = p.id AND t.lang = ?
    LEFT JOIN wishlist w 
        ON w.product_id = p.id AND w.user_id = ?
    WHERE p.category_id = ? AND p.id != ?
    ORDER BY p.created_at DESC
    LIMIT 6
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("siii", $lang, $userId, $category, $exclude);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        // Cast in_wishlist naar boolean
        $row['in_wishlist'] = (bool)$row['in_wishlist'];
        $products[] = $row;
    }

    echo json_encode($products);
    $stmt->close();
} else {
    echo json_encode(["error" => "Database error: " . $conn->error]);
}

$conn->close();
?>