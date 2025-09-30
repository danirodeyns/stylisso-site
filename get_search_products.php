<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include 'db_connect.php';
include 'translations.php';

// Debug helper
function debugLog($msg) {
    file_put_contents('debug.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

// Check database verbinding
if ($conn->connect_errno) {
    $error = "Database connectie mislukt: " . $conn->connect_error;
    debugLog($error);
    echo json_encode(["error" => $error]);
    exit;
}

// Zoekterm ophalen
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

// Basis SQL
$sql = "
    SELECT 
        p.id, p.name, p.description, p.specifications AS specifications, p.price, p.image,
        c.name AS category_name, s.name AS subcategory_name,
        CASE WHEN w.product_id IS NOT NULL THEN 1 ELSE 0 END AS in_wishlist
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN subcategories s ON p.subcategory_id = s.id
    LEFT JOIN wishlist w ON w.product_id = p.id AND w.user_id = ?
    WHERE 1=1
";

$params = [$userId];
$types = "i";

// Voeg voor elk woord een LIKE-check toe
foreach ($terms as $term) {
    $sql .= " AND (
        p.name LIKE ? OR 
        p.description LIKE ? OR 
        p.specifications LIKE ? OR 
        c.name LIKE ? OR 
        s.name LIKE ?
    )";
    $like = "%$term%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $like;
    }
    $types .= str_repeat("s", 5);
}

$sql .= " ORDER BY p.created_at DESC";

debugLog("SQL statement voorbereid: $sql");

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $error = "Prepare fout: " . $conn->error;
    debugLog($error);
    echo json_encode(["error" => $error]);
    exit;
}

// Bind dynamische parameters
$stmt->bind_param($types, ...$params);

// Execute statement
if (!$stmt->execute()) {
    $error = "Execute fout: " . $stmt->error;
    debugLog($error);
    echo json_encode(["error" => $error]);
    exit;
}

// Result ophalen
$result = $stmt->get_result();
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = [
        "id" => (int)$row["id"],
        "name" => $row["name"],
        "description" => $row["description"],
        "specifications" => $row["specifications"],
        "price" => (float)$row["price"],
        "image" => $row["image"],
        "category" => $row["category_name"],
        "subcategory" => $row["subcategory_name"],
        "in_wishlist" => (bool)$row["in_wishlist"]
    ];
}

debugLog("Aantal producten gevonden: " . count($products));

echo json_encode($products);

$stmt->close();
$conn->close();
?>