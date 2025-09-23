<?php
require_once 'db_connect.php';
include 'translations.php';

session_start();

// Zorg dat je user_id in de sessie hebt staan na inloggen
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

$category_id = isset($_GET['cat']) ? intval($_GET['cat']) : 0;
$subcategory_id = isset($_GET['sub']) ? intval($_GET['sub']) : null;

// SQL query met categorie en subcategorie
$sql = "SELECT * FROM products WHERE category_id = ?";
$params = [$category_id];
$types = "i";

if ($subcategory_id) {
    $sql .= " AND subcategory_id = ?";
    $types .= "i";
    $params[] = $subcategory_id;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    // standaard false
    $row['in_wishlist'] = false;

    if ($user_id > 0) {
        $check = $conn->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1");
        $check->bind_param("ii", $user_id, $row['id']);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $row['in_wishlist'] = true;
        }
        $check->close();
    }

    $products[] = $row;
}

echo json_encode($products);
?>