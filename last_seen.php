<?php
session_start();
include 'db_connect.php'; // connectie naar DB
include 'translations.php'; // voor vertalingen

header('Content-Type: application/json');

// Check user via sessie
$userId = $_SESSION['user_id'] ?? false;
$productId = intval($_POST['product_id'] ?? 0);

if (!$productId) {
    echo json_encode(['error' => 'Geen product opgegeven']);
    exit;
}

if ($userId) {
    // In DB opslaan (max 6 laatste producten)
    $stmt = $conn->prepare("INSERT INTO last_seen (user_id, product_id, seen_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE seen_at = NOW()");
    $stmt->bind_param("ii", $userId, $productId);
    $stmt->execute();

    // Houd max 6
    $stmt = $conn->prepare("
        DELETE FROM last_seen 
        WHERE user_id = ? AND id NOT IN (
            SELECT id FROM (
                SELECT id FROM last_seen WHERE user_id = ? ORDER BY seen_at DESC LIMIT 6
            ) tmp
        )
    ");
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
}

echo json_encode(['success' => true]);
?>