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
// SQL query met vertalingen + verkochte aantallen
// ===============================
// We tellen het totaal aantal verkochte stuks per product via order_items (alleen type 'product')
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
        p.image,
        p.created_at,
        COALESCE(SUM(oi.quantity), 0) AS sold_count
    FROM products p
    LEFT JOIN product_translations pt 
        ON pt.product_id = p.id AND pt.lang = ?
    LEFT JOIN order_items oi 
        ON oi.product_id = p.id AND oi.type = 'product'
    WHERE p.category_id = ?
";

$params = [$lang, $category_id];
$types = "si";

if ($subcategory_id) {
    $sql .= " AND p.subcategory_id = ?";
    $types .= "i";
    $params[] = $subcategory_id;
}

// Groeperen per product zodat SUM(oi.quantity) werkt
$sql .= " GROUP BY p.id";

// (optioneel) je kunt hier default ordering toevoegen, maar ik laat dit over aan de frontend
// $sql .= " ORDER BY sold_count DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    // prepare faalde — log en geef fout terug (handig tijdens dev)
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(["error" => "Prepare fout: " . $conn->error]);
    $conn->close();
    exit;
}

if (!$stmt->bind_param($types, ...$params)) {
    error_log("Bind param failed: " . $stmt->error);
    echo json_encode(["error" => "Bind param fout: " . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(["error" => "Execute fout: " . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$result = $stmt->get_result();

// ===============================
// Producten verwerken
// ===============================
$products = [];
while ($row = $result->fetch_assoc()) {
    // standaard false
    $row['in_wishlist'] = false;

    // Wishlist check
    if ($user_id > 0) {
        $check = $conn->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1");
        if ($check) {
            $check->bind_param("ii", $user_id, $row['id']);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $row['in_wishlist'] = true;
            }
            $check->close();
        }
    }

    // fallback image
    $row['image'] = $row['image'] ?: 'images/placeholder.png';

    // meerdere afbeeldingen verwerken
    $imagesArray = explode(";", $row['image']);
    $row['image'] = trim($imagesArray[0]); // eerste afbeelding als hoofd
    if (count($imagesArray) > 1) {
        $row['images'] = array_map('trim', $imagesArray);
    } else {
        $row['images'] = [];
    }

    // prijs formatteren (strings naar consistent formaat)
    $row['price'] = number_format((float)$row['price'], 2, '.', '');

    // sold_count als integer
    $row['sold_count'] = intval($row['sold_count']);

    $products[] = $row;
}

// ===============================
// JSON-output
// ===============================
header('Content-Type: application/json; charset=utf-8');
echo json_encode($products, JSON_UNESCAPED_UNICODE);

// ===============================
// CLEANUP
// ===============================
$stmt->close();
$conn->close();
?>