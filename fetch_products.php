<?php 
require_once 'db_connect.php';
include 'translations.php';

session_start();

// ===============================
// Taal bepalen
// ===============================
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

// ===============================
// User ID uit sessie
// ===============================
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// ===============================
// Categorie / Subcategorie
// ===============================
$category_id = isset($_GET['cat']) ? intval($_GET['cat']) : 0;
$subcategory_id = isset($_GET['sub']) ? intval($_GET['sub']) : null;

// ===============================
// SQL query met vertalingen
// ===============================
$sql = "
    SELECT 
        p.id,
        p.category_id,
        p.subcategory_id,
        COALESCE(pt.name, p.name) AS name,
        COALESCE(pt.description, p.description) AS description,
        COALESCE(pt.specifications, p.specifications) AS specifications,
        COALESCE(pt.maat, p.maat) AS maat,
        p.price,
        p.image
    FROM products p
    LEFT JOIN product_translations pt 
        ON pt.product_id = p.id AND pt.lang = ?
    WHERE p.category_id = ?
";
$params = [$lang, $category_id];
$types = "si";

if ($subcategory_id) {
    $sql .= " AND p.subcategory_id = ?";
    $types .= "i";
    $params[] = $subcategory_id;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ===============================
// Producten verwerken
// ===============================
$products = [];
while ($row = $result->fetch_assoc()) {
    // standaard false
    $row['in_wishlist'] = false;

    if ($user_id > 0) {
        $check = $conn->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1");
        $check->bind_param("ii", $user_id, $row['id']);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $row['in_wishlist'] = true;
        }
        $check->close();
    }

    // fallback image
    $row['image'] = $row['image'] ?: 'images/placeholder.png';
    $row['price'] = number_format((float)$row['price'], 2, '.', '');

    $products[] = $row;
}

// ===============================
// JSON-output
// ===============================
header('Content-Type: application/json; charset=utf-8');
echo json_encode($products, JSON_UNESCAPED_UNICODE);
?>