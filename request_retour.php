<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include 'translations.php';
include 'mailing.php';

// --- Taal ophalen (fallback: be-nl) ---
$lang = $_POST['lang'] ?? $_GET['lang'] ?? 'be-nl';

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
$stmtUser->close();

if (!$userData || $userData['toegang'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => t('processing_retours_alert_no_access')]);
    exit;
}

// --- JSON POST uitlezen ---
$input = json_decode(file_get_contents('php://input'), true);
$orderId = intval($input['order_id'] ?? 0);
$items = $input['items'] ?? [];
$reason = trim($input['reason'] ?? '');

if ($orderId <= 0 || empty($items)) {
    echo json_encode(['success' => false, 'message' => t('processing_retours_alert_invalid_id', $lang)]);
    exit;
}

// --- Controleer of order bestaat ---
$stmtOrder = $conn->prepare("SELECT id FROM orders WHERE id = ? LIMIT 1");
$stmtOrder->bind_param("i", $orderId);
$stmtOrder->execute();
$orderExists = $stmtOrder->get_result()->fetch_assoc();
$stmtOrder->close();

if (!$orderExists) {
    echo json_encode(['success' => false, 'message' => t('processing_retours_alert_order_not_found', $lang)]);
    exit;
}

// --- Haal klantgegevens van de bestelling op ---
$stmtUserData = $conn->prepare("
    SELECT u.email, u.name 
    FROM users u
    JOIN orders o ON o.user_id = u.id
    WHERE o.id = ?
    LIMIT 1
");
$stmtUserData->bind_param("i", $orderId);
$stmtUserData->execute();
$userRow = $stmtUserData->get_result()->fetch_assoc();
$stmtUserData->close();

$userEmail = $userRow['email'] ?? '';
$userName  = $userRow['name'] ?? 'Klant';

// ===================================
// --- Retourrecords aanmaken ---
// ===================================
$inserted = 0;
foreach ($items as $itemId) {
    $itemId = intval($itemId);
    if ($itemId <= 0) continue;

    // Controleer of er al een retour bestaat voor dit item
    $stmtCheck = $conn->prepare("SELECT id FROM returns WHERE order_item_id = ? LIMIT 1");
    $stmtCheck->bind_param("i", $itemId);
    $stmtCheck->execute();
    $exists = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if ($exists) continue; // al retourrecord, skip

    // Ophalen itemgegevens
    $stmtItem = $conn->prepare("SELECT product_id, quantity, price, maat FROM order_items WHERE id = ? AND order_id = ?");
    $stmtItem->bind_param("ii", $itemId, $orderId);
    $stmtItem->execute();
    $itemData = $stmtItem->get_result()->fetch_assoc();
    $stmtItem->close();

    if (!$itemData) continue;

    $quantity = intval($itemData['quantity']);
    $productId = intval($itemData['product_id']);
    $price = floatval($itemData['price']);
    $size = $itemData['maat'] ?? '-';
    $amount = -1 * ($price * $quantity);

    // Productnaam ophalen
    $stmtProd = $conn->prepare("SELECT name FROM product_translations WHERE product_id = ? AND lang = ? LIMIT 1");
    $stmtProd->bind_param("is", $productId, $lang);
    $stmtProd->execute();
    $resultProd = $stmtProd->get_result();
    $productRow = $resultProd->fetch_assoc();
    $stmtProd->close();
    $productName = $productRow['name'] ?? 'Onbekend product';

    // Retour toevoegen
    $stmtInsert = $conn->prepare("
        INSERT INTO returns (order_item_id, user_id, quantity, status, comment, requested_at)
        VALUES (?, ?, ?, 'requested', ?, NOW())
    ");
    $stmtInsert->bind_param("iiis", $itemId, $userId, $quantity, $reason);
    if ($stmtInsert->execute()) {
        $inserted++;
        $returnId = $stmtInsert->insert_id;
        $stmtInsert->close();

        // Ledger entry aanmaken
        $stmtLedger = $conn->prepare("
            INSERT INTO return_ledger (return_id, product_id, quantity, amount, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmtLedger->bind_param("iiid", $returnId, $productId, $quantity, $amount);
        $stmtLedger->execute();
        $stmtLedger->close();

        // ✅ Mail sturen
        sendReturnConfirmationMail($userEmail, $userName, $orderId, $productName, $size, $quantity, $price, $lang);
    }
}

// ===================================
// --- Controle en reactie ---
// ===================================
if ($inserted > 0) {
    echo json_encode([
        'success' => true,
        'message' => t('script_processing_retours_request_success', $lang),
        'created' => $inserted
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => t('script_processing_retours_alert_request_failed', $lang)
    ]);
}
?>