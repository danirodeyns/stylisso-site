<?php
session_start();
include 'db_connect.php';
include 'translations.php';
header('Content-Type: application/json');

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl'; // standaard taal

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT p.id, 
            COALESCE(t.name, p.name) AS name,
            p.price, 
            p.image,
            CASE WHEN w.product_id IS NOT NULL THEN 1 ELSE 0 END AS in_wishlist
        FROM last_seen l
        JOIN products p ON l.product_id = p.id
        LEFT JOIN product_translations t ON t.product_id = p.id AND t.lang = ?
        LEFT JOIN wishlist w ON w.product_id = p.id AND w.user_id = ?
        WHERE l.user_id = ?
        ORDER BY l.seen_at DESC
        LIMIT 6
    ");
    $stmt->bind_param("sii", $lang, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);

    // Cast in_wishlist naar boolean
    foreach ($products as &$p) {
        $p['in_wishlist'] = (bool)$p['in_wishlist'];
    }

    echo json_encode($products);

} else {
    echo json_encode([]);
}
?>