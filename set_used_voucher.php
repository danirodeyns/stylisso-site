<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

if (!isset($_POST['used_voucher'])) {
    // Geen voucher meegegeven: eventuele oude keuze wissen
    unset($_SESSION['used_voucher']);
    echo json_encode(['success' => true]);
    exit;
}

// JSON string -> array
$data = json_decode($_POST['used_voucher'], true);
if (!is_array($data) || !isset($data['code'], $data['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

// Eenvoudige validatie/sanitization
$code   = preg_replace('/[^A-Z0-9\-]/i', '', (string)$data['code']);
$amount = (float)$data['amount'];
if ($amount < 0) $amount = 0.0;

// Sla nette array op in sessie
$_SESSION['used_voucher'] = [
    'code'   => $code,
    'amount' => $amount,
];

echo json_encode(['success' => true]);
?>