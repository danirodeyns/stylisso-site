<?php
session_start();
require 'db_connect.php'; // jouw database connectie

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT v.code, v.value, v.is_used, uv.redeemed_at
    FROM user_vouchers uv
    JOIN vouchers v ON uv.voucher_id = v.id
    WHERE uv.user_id = :user_id
    ORDER BY uv.redeemed_at DESC
");
$stmt->execute([':user_id' => $user_id]);
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($vouchers);
?>