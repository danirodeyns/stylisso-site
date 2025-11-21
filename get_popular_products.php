<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
require_once 'config.php';
include 'translations.php';

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;

// ===============================
// 🔤 Taal bepalen
// ===============================
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

// ===============================
// 🧠 Caching — 5 minuten geldig
// ===============================
$cacheFile = __DIR__ . '/cache_popular_products_' . $lang . '.json';
$cacheTTL = 300; // 5 minuten

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTTL)) {
    echo file_get_contents($cacheFile);
    exit;
}

// ===============================
// ⚙️ Functie: Populaire producten ophalen (GA4 of fallback)
// ===============================
function getGA4PopularProducts($limit = 6, $lang = 'be-nl') {
    global $conn;

    $products = [];

    // Probeer GA4
    if (class_exists('Google\Analytics\Data\V1beta\BetaAnalyticsDataClient')) {
        try {
            $client = new BetaAnalyticsDataClient([
                'credentials' => GA4_SERVICE_ACCOUNT_JSON
            ]);

            $response = $client->runReport([
                'property' => 'properties/' . GA4_PROPERTY_ID,
                'dimensions' => [['name' => 'product_id']],
                'metrics' => [['name' => 'screenPageViews']],
                'limit' => 100
            ]);

            foreach ($response->getRows() as $row) {
                $itemId = (int)$row->getDimensionValues()[0]->getValue();
                $views  = (int)$row->getMetricValues()[0]->getValue();

                $stmt = $conn->prepare("
                    SELECT 
                        p.id,
                        COALESCE(pt.name, p.name) AS name,
                        p.price,
                        p.image
                    FROM products p
                    LEFT JOIN product_translations pt 
                        ON pt.product_id = p.id AND pt.lang = ?
                    WHERE p.id = ? AND p.active = 1      -- ✅ Alleen actieve producten
                    LIMIT 1
                ");
                $stmt->bind_param('si', $lang, $itemId);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res && $prod = $res->fetch_assoc()) {
                    $prod['id'] = (int)$prod['id'];
                    $prod['price'] = number_format((float)$prod['price'], 2, '.', '');
                    $prod['popularity'] = $views;

                    // --- Afbeeldingen verwerken ---
                    if (!empty($prod['image'])) {
                        $parts = array_map('trim', explode(';', $prod['image']));
                        $prod['image'] = $parts[0]; // eerste afbeelding als hoofd
                        $prod['images'] = count($parts) > 1 ? $parts : []; // alle afbeeldingen in images
                        if (count($parts) === 1) $prod['images'] = [];
                    } else {
                        $prod['image'] = 'images/placeholder.png';
                        $prod['images'] = [];
                    }

                    $products[] = $prod;
                }
                $stmt->close();
            }

            if (!empty($products)) {
                return ['success' => true, 'products' => array_slice($products, 0, $limit)];
            }

        } catch (Exception $e) {
            // GA4 mislukt → fallback
        }
    }

    // ===============================
    // 📊 Fallback via database
    // ===============================
    $sql = "
        SELECT 
            p.id,
            COALESCE(pt.name, p.name) AS name,
            p.price,
            p.image,
            COUNT(oi.id) AS popularity
        FROM products p
        LEFT JOIN product_translations pt 
            ON pt.product_id = p.id AND pt.lang = ?
        LEFT JOIN order_items oi 
            ON oi.product_id = p.id
        WHERE p.active = 1      -- ✅ Alleen actieve producten
        GROUP BY p.id
        ORDER BY popularity DESC, p.id ASC
        LIMIT 100
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $lang);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        return ['success' => false, 'message' => 'Databasefout bij ophalen producten (fallback)'];
    }

    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['price'] = number_format((float)$row['price'], 2, '.', '');
        $row['popularity'] = (int)$row['popularity'];

        // --- Afbeeldingen verwerken ---
        if (!empty($row['image'])) {
            $parts = array_map('trim', explode(';', $row['image']));
            $row['image'] = $parts[0]; // eerste afbeelding als hoofd
            $row['images'] = count($parts) > 1 ? $parts : []; // alle afbeeldingen in images
            if (count($parts) === 1) $row['images'] = [];
        } else {
            $row['image'] = 'images/placeholder.png';
            $row['images'] = [];
        }

        $products[] = $row;
    }
    $stmt->close();

    // Shuffle groepen met gelijke populariteit
    $grouped = [];
    foreach ($products as $p) $grouped[$p['popularity']][] = $p;
    $final = [];
    foreach ($grouped as $items) {
        shuffle($items);
        foreach ($items as $item) $final[] = $item;
    }

    $selected = array_slice($final, 0, $limit);
    shuffle($selected);

    return ['success' => true, 'products' => $selected];
}

// ===============================
// 🧾 Endpoint uitvoeren
// ===============================
$result = getGA4PopularProducts(6, $lang);
$output = json_encode($result, JSON_UNESCAPED_UNICODE);

// Cache opslaan
@file_put_contents($cacheFile, $output);

// Output sturen
echo $output;
?>