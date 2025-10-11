<?php
require_once 'db_connect.php';
include 'translations.php';

// ===============================
// Bepaal taal
// ===============================
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

// ===============================
// Parameters uit URL
// ===============================
$category_id = isset($_GET['cat']) ? intval($_GET['cat']) : 0;
$subcategory_id = isset($_GET['sub']) ? intval($_GET['sub']) : 0;

// ===============================
// Response array
// ===============================
$response = [
    'selected' => ['category' => '', 'subcategory' => '']
];

// ===============================
// Haal naam van hoofdcategorie met vertaling
// ===============================
if ($category_id) {
    $stmt = $conn->prepare("
        SELECT ct.name
        FROM categories_translations ct
        WHERE ct.category_id = ? AND ct.lang = ?
        LIMIT 1
    ");
    $stmt->bind_param("is", $category_id, $lang);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $response['selected']['category'] = $res['name'] ?? '';
    $stmt->close();
}

// ===============================
// Haal naam van subcategorie met vertaling
// ===============================
if ($subcategory_id) {
    $stmt = $conn->prepare("
        SELECT st.name
        FROM subcategories_translations st
        WHERE st.subcategory_id = ? AND st.lang = ?
        LIMIT 1
    ");
    $stmt->bind_param("is", $subcategory_id, $lang);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $response['selected']['subcategory'] = $res['name'] ?? '';
    $stmt->close();
}

// ===============================
// JSON-output
// ===============================
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>