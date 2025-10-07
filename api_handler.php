<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'db_connect.php';
require_once 'config.php';
include 'translations.php';

// Altijd includen (als het beschikbaar is via Composer)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;

// In je functie check je alsnog of de class bestaat
function getGA4PopularProducts($limit = 6) {
    global $conn;

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

            $products = [];
            foreach ($response->getRows() as $row) {
                $itemId = (int)$row->getDimensionValues()[0]->getValue();
                $views  = (int)$row->getMetricValues()[0]->getValue();

                $stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id = ? LIMIT 1");
                $stmt->bind_param('i', $itemId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $prod = $res->fetch_assoc()) {
                    $prod['price'] = number_format((float)$prod['price'], 2, '.', '');
                    $prod['image'] = $prod['image'] ?: 'images/placeholder.png';
                    $prod['popularity'] = $views;
                    $products[] = $prod;
                }
                $stmt->close();
            }

            if (!empty($products)) return ['success' => true, 'products' => $products];

        } catch (Exception $e) {
            // GA4 mislukte, valt terug op database
        }
    }

    // ----------------------------
    // Fallback: populaire producten via database
    // ----------------------------
    $sql = "
        SELECT p.id, p.name, p.price, p.image, COUNT(oi.id) AS popularity
        FROM products p
        LEFT JOIN order_items oi ON oi.product_id = p.id
        GROUP BY p.id
        ORDER BY popularity DESC, p.id ASC
        LIMIT 100
    ";
    $result = $conn->query($sql);
    if (!$result) return ['success' => false, 'message' => 'Databasefout bij ophalen producten (fallback)'];

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['price'] = number_format((float)$row['price'], 2, '.', '');
        $row['image'] = $row['image'] ?: 'images/placeholder.png';
        $row['popularity'] = (int)$row['popularity'];
        $products[] = $row;
    }
    $result->free();

    // Shuffle bij gelijke populariteit
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

// ----------------------------
// API-endpoint
// ----------------------------
if (isset($_GET['action']) && $_GET['action'] === 'popular') {
    $result = getGA4PopularProducts(6);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// fallback
echo json_encode(['success' => false, 'message' => 'Geen geldige actie meegegeven']);
?>