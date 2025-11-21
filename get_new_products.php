<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
include 'translations.php';

session_start();
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// ===============================
// 🔤 Taal bepalen
// ===============================
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

// ===============================
// 🧠 Caching — 5 minuten geldig
// ===============================
$cacheFile = __DIR__ . '/cache_new_products_' . $lang . '.json';
$cacheTTL = 300; // 5 minuten

$products = [];
$cacheUsed = false;

// ===============================
// Cache uitlezen
// ===============================
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTTL)) {
    $cached = file_get_contents($cacheFile);
    $cachedData = json_decode($cached, true);
    if (!empty($cachedData['products']) && isset($cachedData['products'][0]['images'])) {
        $products = $cachedData['products'];
        $cacheUsed = true;
    }
}

// ===============================
// ⚙️ Producten ophalen (DB query)
function getNewProducts($lang, $limit = 100) {
    global $conn;

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
        WHERE p.active = 1
        ORDER BY p.created_at DESC
        LIMIT ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $lang, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['price'] = number_format((float)$row['price'], 2, '.', '');

        // Afbeeldingen verwerken
        if (!empty($row['image']) && strpos($row['image'], ';') !== false) {
            $parts = array_map('trim', explode(';', $row['image']));
            $row['image'] = $parts[0];
            $row['images'] = array_slice($parts, 1);
        } else {
            $row['image'] = !empty($row['image']) ? trim($row['image']) : 'images/placeholder.png';
            $row['images'] = [];
        }

        $products[] = $row;
    }
    $stmt->close();

    return $products;
}

// ===============================
// Productlijst ophalen (cache of nieuw)
if (!$cacheUsed) {
    $products = getNewProducts($lang, 100);

    // Cache opslaan (zonder wishlist info)
    $cacheData = ['products' => $products];
    @file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE));
}

// ===============================
// 🧠 Wishlist-status dynamisch toevoegen
if ($userId > 0 && !empty($products)) {
    foreach ($products as &$p) {
        $stmt = $conn->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $userId, $p['id']);
        $stmt->execute();
        $stmt->store_result();
        $p['in_wishlist'] = $stmt->num_rows > 0;
        $stmt->close();
    }
    unset($p);
}

// ===============================
// Willekeurige selectie
// ===============================
$seed = crc32($_SERVER['REMOTE_ADDR'] . date('Y-m-d-H'));
mt_srand($seed);
shuffle($products);

// ===============================
// Output sturen
// ===============================
echo json_encode([
    'success' => true,
    'products' => array_slice($products, 0, 6)
], JSON_UNESCAPED_UNICODE);
?>