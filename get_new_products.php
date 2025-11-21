<?php
// get_new_products.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
include 'translations.php';

// ===============================
// Taal bepalen
// ===============================
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

// ===============================
// Cache instellingen
// ===============================
$cacheFile = __DIR__ . '/cache_new_products_' . $lang . '.json';
$cacheTTL = 300; // 5 minuten

// --- Cache negeren indien verouderde structuur ---
$useCache = false;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTTL)) {
    $cacheContent = json_decode(file_get_contents($cacheFile), true);
    if (isset($cacheContent['products'][0]['images'])) {
        $useCache = true;
    }
}

// ===============================
// Cache lezen (indien geldig)
// ===============================
if ($useCache) {
    $products = $cacheContent['products'];
    $seed = crc32($_SERVER['REMOTE_ADDR'] . date('Y-m-d-H'));
    mt_srand($seed);
    shuffle($products);
    echo json_encode([
        'success' => true,
        'products' => array_slice($products, 0, 6)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===============================
// Query uitvoeren
// ===============================
$query = "
    SELECT 
        p.id, 
        p.category_id, 
        p.subcategory_id,
        COALESCE(pt.name, p.name) AS name,
        COALESCE(pt.description, p.description) AS description,
        p.price, 
        p.image, 
        p.created_at
    FROM products p
    LEFT JOIN product_translations pt 
        ON pt.product_id = p.id AND pt.lang = ?
    WHERE p.active = 1      -- ✅ Alleen actieve producten
    ORDER BY p.created_at DESC
    LIMIT 100
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $lang);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Databasefout bij ophalen producten']);
    exit;
}

$products = [];
while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['price'] = number_format((float)$row['price'], 2, '.', '');

    // --- Afbeeldingen verwerken ---
    if (!empty($row['image']) && strpos($row['image'], ';') !== false) {
        $parts = array_map('trim', explode(';', $row['image']));
        $row['image'] = $parts[0];           // eerste afbeelding als main
        $row['images'] = array_slice($parts, 1); // de rest in images
    } else {
        $row['image'] = !empty($row['image']) ? trim($row['image']) : 'images/placeholder.png';
        $row['images'] = [];                 // leeg als er maar één afbeelding is
    }

    $products[] = $row;
}
$stmt->close();

// ===============================
// Cache opslaan met nieuwe structuur
// ===============================
$output = json_encode(['success' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);
@file_put_contents($cacheFile, $output);

// ===============================
// Willekeurige selectie
// ===============================
$seed = crc32($_SERVER['REMOTE_ADDR'] . date('Y-m-d-H'));
mt_srand($seed);
shuffle($products);

echo json_encode([
    'success' => true,
    'products' => array_slice($products, 0, 6)
], JSON_UNESCAPED_UNICODE);
?>