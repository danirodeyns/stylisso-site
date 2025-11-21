<?php 
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include 'translations.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Geen product geselecteerd.']);
    exit;
}

$product_id = intval($_GET['id']);
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl'; // standaard taal

// Huidige gebruiker (voor wishlist check)
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Product ophalen inclusief wishlist status
$sql = "
    SELECT p.id, 
        COALESCE(pt.name, p.name) AS name,
        COALESCE(pt.description, p.description) AS description,
        COALESCE(pt.specifications, p.specifications) AS specifications,
        COALESCE(pt.maat, p.maat) AS maat,
        p.price, p.image, p.category_id,
        CASE WHEN w.product_id IS NOT NULL THEN 1 ELSE 0 END AS in_wishlist
    FROM products p
    LEFT JOIN product_translations pt 
        ON pt.product_id = p.id AND pt.lang = ?
    LEFT JOIN wishlist w 
        ON w.product_id = p.id AND w.user_id = ?
    WHERE p.id = ? AND p.active = 1      -- ✅ Alleen actieve producten
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("sii", $lang, $userId, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        echo json_encode(['error' => 'Product niet gevonden.']);
        exit;
    }

    // Cast in_wishlist naar boolean
    $product['in_wishlist'] = (bool)$product['in_wishlist'];

    // Maat in array als niet leeg
    if (!empty($product['maat'])) {
        $product['maat'] = explode(";", $product['maat']);
    } else {
        $product['maat'] = null;
    }

    // Afbeeldingen verwerken
    if (!empty($product['image'])) {
        $images = explode(";", $product['image']);
        $product['image'] = $images[0]; // eerste afbeelding als hoofd
        $product['images'] = count($images) > 1 ? $images : []; // enkel als er meerdere zijn
    } else {
        $product['images'] = [];
    }

    echo json_encode($product);

    $stmt->close();
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>