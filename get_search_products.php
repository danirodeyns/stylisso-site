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
// 🧠 SQL MET VOLLEDIGE TAALONDERSTEUNING EN UNIEKE PRODUCTEN
// ====================================
$sql = "
    SELECT 
        p.id,
        COALESCE(pt_main.name, p.name) AS name,
        COALESCE(pt_main.description, p.description) AS description,
        COALESCE(pt_main.specifications, p.specifications) AS specifications,
        COALESCE(pt_main.maat, p.maat) AS maat,
        p.price,
        p.image,
        c.name AS category_name,
        s.name AS subcategory_name,
        MAX(CASE WHEN w.product_id IS NOT NULL THEN 1 ELSE 0 END) AS in_wishlist
    FROM products p
    LEFT JOIN product_translations pt_main 
        ON p.id = pt_main.product_id AND pt_main.lang = ?
    LEFT JOIN product_translations pt_search 
        ON p.id = pt_search.product_id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN subcategories s ON p.subcategory_id = s.id
    LEFT JOIN wishlist w ON w.product_id = p.id AND w.user_id = ?
    WHERE 1=1
";

$params = [$lang, $userId];
$types = "si";

// ====================================
// 🔍 DYNAMISCHE LIKE-VOORWAARDEN (meertalig zoeken)
// ====================================
foreach ($terms as $term) {
    $sql .= " AND (
        p.name LIKE ? OR 
        p.description LIKE ? OR 
        p.specifications LIKE ? OR 
        pt_search.name LIKE ? OR 
        pt_search.description LIKE ? OR 
        pt_search.specifications LIKE ? OR 
        c.name LIKE ? OR 
        s.name LIKE ?
    )";
    $like = "%$term%";
    for ($i = 0; $i < 8; $i++) {
        $params[] = $like;
    }
    $types .= str_repeat("s", 8);
}

// ====================================
// 🔹 GROUPEER EN SORTEER
// ====================================
$sql .= " GROUP BY p.id, pt_main.name, pt_main.description, pt_main.specifications, pt_main.maat, p.price, p.image, c.name, s.name
          ORDER BY p.created_at DESC";

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
    $products[] = [
        "id" => (int)$row["id"],
        "name" => $row["name"],
        "description" => $row["description"],
        "specifications" => $row["specifications"],
        "maat" => $row["maat"],
        "price" => (float)$row["price"],
        "image" => $row["image"],
        "category" => $row["category_name"],
        "subcategory" => $row["subcategory_name"],
        "in_wishlist" => (bool)$row["in_wishlist"]
    ];
}

debugLog("Aantal unieke producten gevonden: " . count($products));
echo json_encode($products);

// ====================================
// 🧹 CLEANUP
// ====================================
$stmt->close();
$conn->close();
?>