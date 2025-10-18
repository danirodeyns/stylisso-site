<?php
header('Content-Type: application/json');
require_once 'db_connect.php';
include 'translations.php';

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';
$cat  = isset($_GET['cat'])  ? (int)$_GET['cat']  : 0;
$sub  = isset($_GET['sub'])  ? (int)$_GET['sub']  : 0;

// --- Dynamische WHERE clause ---
$where = [];
$params = [];
$types = "";

if ($cat) {
    $where[] = "p.category_id = ?";
    $params[] = $cat;
    $types .= "i";
}
if ($sub) {
    $where[] = "p.subcategory_id = ?";
    $params[] = $sub;
    $types .= "i";
}
$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// --- SQL query ---
$sql = "
    SELECT COALESCE(pt.specifications, p.specifications) AS specifications
    FROM products p
    LEFT JOIN product_translations pt
        ON pt.product_id = p.id AND pt.lang = ?
    $whereSql
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

// Bind parameters: eerst lang (s), dan cat/sub
$types = "s" . $types;
$params = array_merge([$lang], $params);
$stmt->bind_param($types, ...$params);

$stmt->execute();
$result = $stmt->get_result();

$groups = [];
while ($row = $result->fetch_assoc()) {
    if (empty($row['specifications'])) continue;
    $specs = explode(';', $row['specifications']);
    foreach ($specs as $spec) {
        $spec = trim($spec);
        if (empty($spec)) continue;
        if (strpos($spec, ':') !== false) {
            list($key, $value) = explode(':', $spec, 2);
            $key = strtolower(trim($key));
            $value = trim($value);
            if (!isset($groups[$key])) $groups[$key] = [];
            $groups[$key][$value] = true;
        }
    }
}

// Zet associative arrays om naar normale arrays en verwijder lege groepen
foreach ($groups as $key => $values) {
    if (!empty($values)) {
        $groups[$key] = array_keys($values);
    } else {
        unset($groups[$key]);
    }
}

echo json_encode($groups, JSON_UNESCAPED_UNICODE);
$stmt->close();
$conn->close();
?>