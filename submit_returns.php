<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];
$orderId = $_POST['order_id'] ?? null;
$productId = $_POST['product_id'] ?? null;
$reason = $_POST['reason'] ?? '';

if ($orderId && $productId && $reason) {
    $stmt = $pdo->prepare("INSERT INTO returns (user_id, order_id, product_id, reason) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$userId, $orderId, $productId, $reason])) {
        echo json_encode(['success' => 'Retouraanvraag succesvol ingediend!']);
    } else {
        echo json_encode(['error' => 'Er is iets misgegaan.']);
    }
} else {
    echo json_encode(['error' => 'Vul alle velden in.']);
}
?>