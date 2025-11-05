<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';
include 'translations.php';

$conn->set_charset("utf8mb4");

// --- Controleer of ingelogd en staff ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];
$stmtUser = $conn->prepare("SELECT toegang FROM users WHERE id = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();

if (!$userData || $userData['toegang'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Geen toegang']);
    exit;
}

// --- Order ID ---
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ongeldig order ID']);
    exit;
}

// --- Taal instellen ---
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

// --- Ophalen order + gebruiker ---
$stmtOrder = $conn->prepare("
    SELECT o.id AS order_id, o.user_id, o.total_price, o.status AS order_status, o.created_at AS order_date,
        u.name AS customer_name, u.email AS customer_email, u.company_name, u.vat_number
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
    LIMIT 1
");
$stmtOrder->bind_param("i", $orderId);
$stmtOrder->execute();
$order = $stmtOrder->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'message' => t('order_not_found')]);
    exit;
}

// --- Ophalen addresses ---
function get_address($conn, $user_id, $type) {
    $stmt = $conn->prepare("
        SELECT street, house_number, postal_code, city, country
        FROM addresses
        WHERE user_id = ? AND type = ?
        LIMIT 1
    ");
    $stmt->bind_param("is", $user_id, $type);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$shippingAddress = get_address($conn, $order['user_id'], 'shipping');
$billingAddress  = get_address($conn, $order['user_id'], 'billing');

// --- Ophalen orderitems met vertaling ---
$sqlItems = "
    SELECT 
        oi.id AS order_item_id, 
        oi.quantity, 
        oi.price, 
        oi.type,
        COALESCE(pt.name, p.name) AS product_name,
        p.image AS image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.lang = ?
    WHERE oi.order_id = ?
";
$stmtItems = $conn->prepare($sqlItems);
$stmtItems->bind_param("si", $lang, $orderId);
$stmtItems->execute();
$items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Afbeeldingen verwerken ---
foreach ($items as &$item) {
    if (!empty($item['image'])) {
        $parts = array_map('trim', explode(';', $item['image']));
        $item['image'] = $parts[0]; // eerste afbeelding
        $item['images'] = count($parts) > 1 ? $parts : [];
        if (count($parts) === 1) $item['images'] = [];
    } else {
        $item['image'] = 'images/placeholder.png';
        $item['images'] = [];
    }
}

// --- Ophalen retouritems van deze order ---
$stmtReturn = $conn->prepare("
    SELECT 
        r.id AS return_id, 
        r.order_item_id, 
        r.quantity, 
        r.status AS return_status, 
        r.comment
    FROM returns r
    JOIN order_items oi ON r.order_item_id = oi.id
    WHERE oi.order_id = ?
");
$stmtReturn->bind_param("i", $orderId);
$stmtReturn->execute();
$returnItems = $stmtReturn->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Response ---
$response = [
    'success' => true,
    'order' => [
        'order_id' => $order['order_id'],
        'order_date' => $order['order_date'],
        'order_status' => $order['order_status'],
        'total_price' => $order['total_price'],
        'customer_name' => $order['customer_name'],
        'customer_email' => $order['customer_email'],
        'company_name' => $order['company_name'],
        'vat_number' => $order['vat_number'],
        'address_shipping' => $shippingAddress ?: null,
        'address_billing'  => $billingAddress ?: null,
        'items' => $items,
        'return_items' => $returnItems
    ]
];

echo json_encode($response);
?>