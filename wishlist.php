<?php
session_start();
header('Content-Type: application/json');
include 'db_connect.php';
include 'translations.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl'; // standaardtaal

$sql = "
    SELECT 
        p.id,
        COALESCE(pt.name, p.name) AS name,
        COALESCE(pt.description, p.description) AS description,
        COALESCE(pt.maat, p.maat) AS maat,
        p.price,
        p.image
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN product_translations pt 
        ON pt.product_id = p.id AND pt.lang = ?
    WHERE w.user_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $lang, $userId);

if (!$stmt->execute()) {
    echo json_encode(['error' => 'Database fout: ' . $conn->error]);
    exit;
}

$result = $stmt->get_result();
$items = [];

while ($row = $result->fetch_assoc()) {
    // ✅ Zet maat-string om naar array
    if (!empty($row['maat'])) {
        $row['sizes'] = explode(";", $row['maat']);
    } else {
        $row['sizes'] = null;
    }
    unset($row['maat']); // optioneel, voor nettere output

    $items[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($items);
?>