<?php
session_start();
include 'db_connect.php';
include 'translations.php';
header('Content-Type: application/json');

// --- taal bepalen ---
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl'; // standaard taal

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT 
            p.id, 
            COALESCE(t.name, p.name) AS name,
            p.price, 
            p.image,
            CASE WHEN w.product_id IS NOT NULL THEN 1 ELSE 0 END AS in_wishlist
        FROM last_seen l
        JOIN products p ON l.product_id = p.id
        LEFT JOIN product_translations t ON t.product_id = p.id AND t.lang = ?
        LEFT JOIN wishlist w ON w.product_id = p.id AND w.user_id = ?
        WHERE l.user_id = ? AND p.active = 1  -- ✅ Alleen actieve producten
        ORDER BY l.seen_at DESC
        LIMIT 6
    ");
    $stmt->bind_param("sii", $lang, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];

    while ($row = $result->fetch_assoc()) {
        // --- Afbeeldingen verwerken ---
        $row['image'] = $row['image'] ?: 'images/placeholder.png';
        $imagesArray = explode(';', $row['image']);
        $mainImage = trim($imagesArray[0]);
        $allImages = (count($imagesArray) > 1) ? array_map('trim', $imagesArray) : [];

        $products[] = [
            'id'           => (int)$row['id'],
            'name'         => $row['name'],
            'price'        => (float)$row['price'],
            'image'        => $mainImage,
            'images'       => $allImages,
            'in_wishlist'  => (bool)$row['in_wishlist']
        ];
    }

    echo json_encode($products, JSON_UNESCAPED_UNICODE);

} else {
    echo json_encode([]);
}
?>