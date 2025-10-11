<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include_once 'create_credit_nota.php';
include 'translations.php';

// --- Controleer ingelogd en staff ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => t('processing_retours_alert_not_logged_in')]);
    exit;
}

$userId = $_SESSION['user_id'];
$stmtUser = $conn->prepare("SELECT toegang FROM users WHERE id = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();

if (!$userData || $userData['toegang'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => t('processing_retours_alert_no_access')]);
    exit;
}

// --- JSON POST uitlezen ---
$input = json_decode(file_get_contents('php://input'), true);

$orderId = intval($input['order_id'] ?? 0);
$approvedItems = $input['approved_items'] ?? [];
$rejectedItems = $input['rejected_items'] ?? [];

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => t('processing_retours_alert_invalid_id')]);
    exit;
}

// --- Helper: update status ---
function updateReturnStatus($conn, $itemId, $newStatus) {
    $stmt = $conn->prepare("UPDATE returns SET status = ? WHERE order_item_id = ? LIMIT 1");
    $stmt->bind_param("si", $newStatus, $itemId);
    return $stmt->execute();
}

// --- Verwerk approved items ---
foreach ($approvedItems as $itemId) {
    $itemId = intval($itemId);

    // Controleer huidige status
    $stmtCheck = $conn->prepare("SELECT status, quantity FROM returns WHERE order_item_id = ? LIMIT 1");
    $stmtCheck->bind_param("i", $itemId);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result()->fetch_assoc();
    if (!$result) continue;

    $currentStatus = $result['status'];
    if ($currentStatus !== 'processed') {
        updateReturnStatus($conn, $itemId, 'approved');

        // --- Update amount in return_ledger naar negatief van prijs * quantity ---
        $stmtAmount = $conn->prepare("
            UPDATE return_ledger rl
            JOIN returns r ON rl.return_id = r.id
            JOIN order_items oi ON r.order_item_id = oi.id
            SET rl.amount = -1 * (oi.price * r.quantity)
            WHERE r.order_item_id = ? AND rl.amount <> -1 * (oi.price * r.quantity)
        ");
        $stmtAmount->bind_param("i", $itemId);
        $stmtAmount->execute();
    }
}

// --- Maak 1 creditnota voor alle goedgekeurde items van deze order ---
if (!empty($approvedItems) && function_exists('create_credit_nota')) {
    create_credit_nota($orderId, date('Y-m-d'), $conn);
}

// --- Verwerk rejected items ---
foreach ($rejectedItems as $itemId) {
    $itemId = intval($itemId);

    updateReturnStatus($conn, $itemId, 'rejected');

    // Zet bedrag op 0 in return_ledger
    $stmtLedger = $conn->prepare("
        UPDATE return_ledger rl
        JOIN returns r ON rl.return_id = r.id
        SET rl.amount = 0
        WHERE r.order_item_id = ?
    ");
    $stmtLedger->bind_param("i", $itemId);
    $stmtLedger->execute();
}

// --- Controleer of alle items van deze order approved of processed zijn ---
$stmtAllItems = $conn->prepare("
    SELECT COUNT(*) AS total_items,
        SUM(CASE WHEN r.status IN ('approved','processed') THEN 1 ELSE 0 END) AS processed_items
    FROM order_items oi
    LEFT JOIN returns r ON oi.id = r.order_item_id
    WHERE oi.order_id = ?
");
$stmtAllItems->bind_param("i", $orderId);
$stmtAllItems->execute();
$resultAll = $stmtAllItems->get_result()->fetch_assoc();

if ($resultAll && $resultAll['total_items'] > 0 && $resultAll['total_items'] == $resultAll['processed_items']) {
    // --- Update order status naar cancelled ---
    $stmtUpdateOrder = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? LIMIT 1");
    $stmtUpdateOrder->bind_param("i", $orderId);
    $stmtUpdateOrder->execute();
}

// Stuur een vertaald succesbericht terug
echo json_encode(['success' => true, 'message' => t('processing_retours_alert_success')]);
?>