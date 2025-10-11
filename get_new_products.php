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
// Eenvoudige caching — 5 minuten geldig
// ===============================
$cacheFile = __DIR__ . '/cache_new_products_' . $lang . '.json';
$cacheTTL = 300; // seconden = 5 minuten

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTTL)) {
    $output = file_get_contents($cacheFile);
    $data = json_decode($output, true);

    // Als cache geldig is, pas enkel de volgorde aan per gebruiker/uur
    if ($data && isset($data['products'])) {
        $products = $data['products'];

        // Maak seed uniek per gebruiker (IP) en per uur
        $seed = crc32($_SERVER['REMOTE_ADDR'] . date('Y-m-d-H'));
        mt_srand($seed);
        shuffle($products);

        // Neem de eerste 6
        $selected = array_slice($products, 0, 6);
        echo json_encode(['success' => true, 'products' => $selected], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===============================
// Query uitvoeren — nieuwe cache aanmaken
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
    $row['image'] = $row['image'] ?: 'images/placeholder.png';
    $products[] = $row;
}
$result->free();
$stmt->close();

if (empty($products)) {
    echo json_encode(['success' => true, 'products' => []]);
    exit;
}

// Cache opslaan
$output = json_encode(['success' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);
@file_put_contents($cacheFile, $output);

// Willekeurige selectie op basis van IP + uur
$seed = crc32($_SERVER['REMOTE_ADDR'] . date('Y-m-d-H'));
mt_srand($seed);
shuffle($products);

$selected = array_slice($products, 0, 6);

// Resultaat teruggeven
echo json_encode(['success' => true, 'products' => $selected], JSON_UNESCAPED_UNICODE);
?>