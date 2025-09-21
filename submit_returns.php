<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include 'translations.php';
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];
$orderItemId = $_POST['order_item_id'] ?? null;
$reason = $_POST['reason'] ?? '';

if (!$orderItemId || empty($reason)) {
    echo json_encode(['error' => 'Vul alle velden in.']);
    exit;
}

// Controleer of order_item bij deze gebruiker hoort
$stmt = $conn->prepare("
    SELECT oi.id, oi.product_id, oi.quantity, oi.price, oi.order_id
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.id = ? AND o.user_id = ?
");
$stmt->bind_param("ii", $orderItemId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$orderItem = $result->fetch_assoc();

if (!$orderItem) {
    echo json_encode(['error' => 'Dit product hoort niet bij jou of bestaat niet']);
    exit;
}

// Voeg toe aan returns tabel
$stmt = $conn->prepare("
    INSERT INTO returns (order_item_id, user_id, quantity, status, comment)
    VALUES (?, ?, ?, 'requested', ?)
");
$stmt->bind_param("iiis", $orderItemId, $userId, $orderItem['quantity'], $reason);

if ($stmt->execute()) {
    $returnId = $conn->insert_id;

    // Voeg boekhoudkundig item toe in return_ledger
    $stmtLedger = $conn->prepare("
        INSERT INTO return_ledger (return_id, product_id, quantity, amount)
        VALUES (?, ?, ?, ?)
    ");
    $amount = -1 * ($orderItem['price'] * $orderItem['quantity']); // negatief bedrag
    $stmtLedger->bind_param("iiid", $returnId, $orderItem['product_id'], $orderItem['quantity'], $amount);
    $stmtLedger->execute();

    // Controleer of ALLE items in deze order retour aangevraagd zijn
    $orderId = $orderItem['order_id'];

    // Tel totaal aantal items in de order
    $stmtTotal = $conn->prepare("SELECT COUNT(*) as total_items FROM order_items WHERE order_id = ?");
    $stmtTotal->bind_param("i", $orderId);
    $stmtTotal->execute();
    $resTotal = $stmtTotal->get_result()->fetch_assoc();
    $totalItems = $resTotal['total_items'];

    // Tel hoeveel unieke order_items retour zijn aangevraagd
    $stmtReturned = $conn->prepare("
        SELECT COUNT(DISTINCT r.order_item_id) as returned_items
        FROM returns r
        JOIN order_items oi ON r.order_item_id = oi.id
        WHERE oi.order_id = ? AND r.status = 'requested'
    ");
    $stmtReturned->bind_param("i", $orderId);
    $stmtReturned->execute();
    $resReturned = $stmtReturned->get_result()->fetch_assoc();
    $returnedItems = $resReturned['returned_items'];

    echo json_encode(['success' => 'Retouraanvraag succesvol ingediend!']);
    exit;
} else {
    echo json_encode(['error' => 'Er is iets misgegaan bij het registreren van de retour.']);
    exit;
}
?>