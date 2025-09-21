<?php
require_once 'db_connect.php';
include 'translations.php';

// Parameters uit URL
$category_id = isset($_GET['cat']) ? intval($_GET['cat']) : 0;
$subcategory_id = isset($_GET['sub']) ? intval($_GET['sub']) : 0;

// Response array
$response = [
    'selected' => ['category' => '', 'subcategory' => '']
];

// Haal naam van hoofdcategorie
if ($category_id) {
    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $response['selected']['category'] = $res['name'] ?? '';
}

// Haal naam van subcategorie
if ($subcategory_id) {
    $stmt = $conn->prepare("SELECT name FROM subcategories WHERE id = ?");
    $stmt->bind_param("i", $subcategory_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $response['selected']['subcategory'] = $res['name'] ?? '';
}

// JSON-output
header('Content-Type: application/json');
echo json_encode($response);
?>