<?php
require_once 'db_connect.php';
include 'translations.php';

$category_id = isset($_GET['cat']) ? intval($_GET['cat']) : 0;
$subcategory_id = isset($_GET['sub']) ? intval($_GET['sub']) : 0;

$filters = [
    'maat' => [],
    'materiaal' => [],
    'pasvorm' => [],
    'stijl' => [],
    'halslijn' => [],
    'seizoen' => [],
    'status' => ['nieuw','populair']
];

foreach (array_keys($filters) as $key) {
    if ($key !== 'status') {
        $sql = "SELECT DISTINCT $key FROM products WHERE $key IS NOT NULL AND category_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()){
            $filters[$key][] = $row[$key];
        }
    }
}

echo json_encode($filters);
?>