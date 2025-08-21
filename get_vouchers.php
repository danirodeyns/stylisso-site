<?php
session_start();
require 'db_connect.php'; // mysqli connectie
header('Content-Type: application/json');

// Schakel PHP notices/warnings uit voor JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("
        SELECT v.code, v.value, v.remaining_value, uv.claimed_at
        FROM user_vouchers uv
        JOIN vouchers v ON uv.voucher_id = v.id
        WHERE uv.user_id = ?
        ORDER BY uv.claimed_at DESC
    ");

    if (!$stmt) {
        throw new Exception("Prepare mislukt: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        throw new Exception("Execute mislukt: " . $stmt->error);
    }

    $stmt->bind_result($code, $value, $remaining_value, $claimed_at);
    $vouchers = [];

    while ($stmt->fetch()) {
        $vouchers[] = [
            'code' => $code,
            'value' => $value,
            'is_used' => $remaining_value <= 0 ? 1 : 0,
            'claimed_at' => $claimed_at
        ];
    }

    $stmt->close();

    echo json_encode($vouchers);

} catch (Exception $e) {
    echo json_encode(['error' => 'Er is een fout opgetreden: ' . $e->getMessage()]);
}
?>