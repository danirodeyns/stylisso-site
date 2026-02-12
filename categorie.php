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
$subcategories = [];

// sub kan een array zijn door sub[]=10&sub[]=11
if (!empty($_GET['sub'])) {
    if (is_array($_GET['sub'])) {
        $subcategories = array_map('intval', $_GET['sub']);
    } else {
        $subcategories[] = intval($_GET['sub']);
    }
}

// filter negatieve/0 waarden
$subcategories = array_filter($subcategories, fn($v) => $v > 0);

// ===============================
// Response array
// ===============================
$response = [
    'selected' => ['category' => '', 'subcategory' => '']
];

// ===============================
// Haal naam van hoofdcategorie
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
// Subcategorie naam bepalen
// ===============================
if (count($subcategories) === 1) {
    // Eén subcategorie
    $stmt = $conn->prepare("
        SELECT st.name
        FROM subcategories_translations st
        WHERE st.subcategory_id = ? AND st.lang = ?
        LIMIT 1
    ");
    $stmt->bind_param("is", $subcategories[0], $lang);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $response['selected']['subcategory'] = $res['name'] ?? '';
    $stmt->close();

} elseif (count($subcategories) > 1) {
    // Meerdere subs → check voor custom combinaties
    $customNames = [
        '27,28,29,30,31' => t('products_subgroup1'),
        '32,33,34' => t('products_subgroup2'),
        '35,36' => t('products_subgroup3'),
    ];

    // Sorteer en maak key
    $subsSorted = $subcategories;
    sort($subsSorted);
    $key = implode(',', $subsSorted);

    if (isset($customNames[$key])) {
        $response['selected']['subcategory'] = $customNames[$key];
    } else {
        // Anders: alle namen ophalen en concatenaten
        $placeholders = implode(',', array_fill(0, count($subcategories), '?'));
        $types = str_repeat('i', count($subcategories)) . 's';
        $params = [...$subcategories, $lang];

        $sql = "SELECT st.name
                FROM subcategories_translations st
                WHERE st.subcategory_id IN ($placeholders) AND st.lang = ?";
        $stmt = $conn->prepare($sql);

        // Bind params dynamisch
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $names = [];
        while ($row = $res->fetch_assoc()) {
            $names[] = $row['name'];
        }
        $stmt->close();

        $response['selected']['subcategory'] = implode(', ', $names);
    }
}

// ===============================
// JSON-output
// ===============================
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>