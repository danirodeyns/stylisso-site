<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include 'db_connect.php';
include 'translations.php';

// ====================================
// 🔧 DEBUG HELPER
// ====================================
function debugLog($msg) {
    file_put_contents('debug.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

// ====================================
// 🧩 DATABASE CHECK
// ====================================
if ($conn->connect_errno) {
    $error = "Database connectie mislukt: " . $conn->connect_error;
    debugLog($error);
    echo json_encode(["error" => $error]);
    exit;
}

// ====================================
// 🌐 TAAL DETECTIE
// ====================================
$lang = isset($_GET['lang']) ? trim($_GET['lang']) : 'be-nl';
debugLog("Taalparameter ontvangen: $lang");

// ====================================
// 🔍 ZOEKTERM
// ====================================
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
debugLog("Zoekterm ontvangen: '$q'");

if ($q === '') {
    debugLog("Geen zoekterm opgegeven, return lege array.");
    echo json_encode([]);
    exit;
}

// Split zoektermen op spaties
$terms = preg_split('/\s+/', $q);
debugLog("Gesplitste zoektermen: " . implode(", ", $terms));

// Huidige gebruiker voor wishlist check
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
debugLog("User ID: $userId");

// ====================================
// 🧠 SQL MET VERKOCHTE AANTALLEN
// ====================================
// sold_count berekenen via order_items
$sql = "
    SELECT 
        p.id,
        COALESCE(pt.name, p.name) AS name,
        COALESCE(pt.description, p.description) AS description,
        COALESCE(pt.specifications, p.specifications) AS specifications,
        COALESCE(pt.maat, p.maat) AS maat,
        p.price,
        p.image,
        p.created_at,
        COALESCE(SUM(oi.quantity), 0) AS sold_count,
        c.name AS category_name,
        s.name AS subcategory_name,
        MAX(CASE WHEN w.product_id IS NOT NULL THEN 1 ELSE 0 END) AS in_wishlist
    FROM products p
    LEFT JOIN product_translations pt 
        ON pt.product_id = p.id AND pt.lang = ?
    LEFT JOIN order_items oi
        ON oi.product_id = p.id AND oi.type = 'product'
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN subcategories s ON p.subcategory_id = s.id
    LEFT JOIN wishlist w ON w.product_id = p.id AND w.user_id = ?
    WHERE 1=1
";

$params = [$lang, $userId];
$types = "si";

// Dynamische LIKE voorwaarden
foreach ($terms as $term) {
    $sql .= " AND (
        p.name LIKE ? OR
        p.description LIKE ? OR
        p.specifications LIKE ? OR
        pt.name LIKE ? OR
        pt.description LIKE ? OR
        pt.specifications LIKE ? OR
        c.name LIKE ? OR
        s.name LIKE ?
    )";
    $like = "%$term%";
    for ($i = 0; $i < 8; $i++) {
        $params[] = $like;
    }
    $types .= str_repeat("s", 8);
}

// Groeperen per product zodat SUM(oi.quantity) werkt
$sql .= "
    GROUP BY 
        p.id, pt.name, pt.description, pt.specifications, pt.maat, 
        p.price, p.image, p.created_at, c.name, s.name
    ORDER BY p.created_at DESC
";

debugLog("SQL statement voorbereid: $sql");

// ====================================
// 🧩 PREPARE & EXECUTE
// ====================================
$stmt = $conn->prepare($sql);
if (!$stmt) {
    $error = "Prepare fout: " . $conn->error;
    debugLog($error);
    echo json_encode(["error" => $error]);
    exit;
}

$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    $error = "Execute fout: " . $stmt->error;
    debugLog($error);
    echo json_encode(["error" => $error]);
    exit;
}

// ====================================
// 📦 RESULTATEN
// ====================================
$result = $stmt->get_result();
$products = [];

while ($row = $result->fetch_assoc()) {
    // fallback image
    $row['image'] = $row['image'] ?: 'images/placeholder.png';

    // meerdere afbeeldingen verwerken
    $imagesArray = explode(";", $row['image']);
    $row['image'] = trim($imagesArray[0]); // eerste afbeelding als hoofd
    $row['images'] = count($imagesArray) > 1 ? array_map('trim', $imagesArray) : [];

    // prijs formatteren
    $row['price'] = number_format((float)$row['price'], 2, '.', '');

    // sold_count als integer
    $row['sold_count'] = intval($row['sold_count']);

    // in_wishlist als boolean
    $row['in_wishlist'] = (bool)$row['in_wishlist'];

    $products[] = [
        "id" => (int)$row["id"],
        "name" => $row["name"],
        "description" => $row["description"],
        "specifications" => $row["specifications"],
        "maat" => $row["maat"],
        "price" => $row["price"],
        "image" => $row["image"],
        "images" => $row["images"],
        "category" => $row["category_name"],
        "subcategory" => $row["subcategory_name"],
        "sold_count" => $row["sold_count"],
        "in_wishlist" => $row["in_wishlist"]
    ];
}

debugLog("Aantal unieke producten gevonden: " . count($products));
echo json_encode($products, JSON_UNESCAPED_UNICODE);

// ====================================
// 🧹 CLEANUP
// ====================================
$stmt->close();
$conn->close();
?>