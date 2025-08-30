<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['used_voucher'])) {
    $_SESSION['used_voucher'] = $_POST['used_voucher'];
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false]);
?>