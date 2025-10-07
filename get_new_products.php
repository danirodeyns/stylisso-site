<?php
// get_new_products.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
include 'translations.php';

// ===============================
// Eenvoudige caching — 5 minuten geldig
// ===============================
$cacheFile = __DIR__ . '/cache_new_products.json';
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
    SELECT id, category_id, subcategory_id, name, description, price, image, created_at
    FROM products
    ORDER BY created_at DESC
    LIMIT 100
";

$result = $conn->query($query);

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