<?php
require_once 'db_connect.php';
include 'translations.php';

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
    $products[] = $row;
}

echo json_encode($products);
?>