<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
include 'translations.php';
include 'create_own_voucher_invoice.php';

// --- Taal ophalen (fallback: be-nl) ---
$lang = $_POST['lang'] ?? $_GET['lang'] ?? 'be-nl';

// --- Functie: Voucher aanmaken ---
function create_voucher($amount, $expires_at = null) {
    global $conn;

    // Unieke code genereren
    do {
        $code = strtoupper(bin2hex(random_bytes(6)));
        $stmt_check = $conn->prepare("SELECT id FROM vouchers WHERE code = ?");
        $stmt_check->bind_param("s", $code);
        $stmt_check->execute();
        $stmt_check->store_result();
        $exists = $stmt_check->num_rows > 0;
        $stmt_check->close();
    } while ($exists);

    if (!$expires_at) {
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));
    }

    // Voucher in database invoegen
    $stmt = $conn->prepare("
        INSERT INTO vouchers (code, value, remaining_value, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("sdds", $code, $amount, $amount, $expires_at);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        return [
            'success' => true,
            'voucher_id' => $conn->insert_id,
            'code' => $code,
            'amount' => $amount,
            'expires_at' => $expires_at
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Voucher aanmaken mislukt'
        ];
    }
}

// --- Directe aanroep via GET/POST ---
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : (isset($_GET['amount']) ? floatval($_GET['amount']) : 0);
$reason = $_POST['reason'] ?? $_GET['reason'] ?? 'Interne voucheruitgifte';
$assign_user_id = $_POST['assign'] ?? $_GET['assign'] ?? null;
$create_date = date('Y-m-d');

// --- Validatie bedrag ---
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ongeldig bedrag']);
    exit;
}

// 1. Voucher aanmaken
$voucher = create_voucher($amount);
if (!$voucher['success']) {
    echo json_encode($voucher);
    exit;
}

// 2. Optioneel: voucher toewijzen aan gebruiker
$user_info = null;
if ($assign_user_id && is_numeric($assign_user_id)) {
    // Eerst proberen facturatieadres op te halen, anders verzendadres
    $stmt_check_user = $conn->prepare("
        SELECT 
            u.id,
            u.name,
            u.company_name,
            u.vat_number,
            COALESCE(b.street, s.street) AS street,
            COALESCE(b.house_number, s.house_number) AS house_number,
            COALESCE(b.postal_code, s.postal_code) AS postal_code,
            COALESCE(b.city, s.city) AS city,
            COALESCE(b.country, s.country, 'België') AS country
        FROM users u
        LEFT JOIN addresses b ON b.user_id = u.id AND b.type = 'billing'
        LEFT JOIN addresses s ON s.user_id = u.id AND s.type = 'shipping'
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt_check_user->bind_param("i", $assign_user_id);
    $stmt_check_user->execute();
    $result_user = $stmt_check_user->get_result();

    if ($row = $result_user->fetch_assoc()) {
        $user_info = [
            'id' => $row['id'],
            'name' => $row['name'],
            'company_name' => $row['company_name'],
            'vat_number' => $row['vat_number'],
            'street' => $row['street'] ?? '',
            'number' => $row['house_number'] ?? '',
            'zip' => $row['postal_code'] ?? '',
            'city' => $row['city'] ?? '',
            'country' => $row['country'] ?? ''
        ];

        // Voucher toewijzen
        $stmt_assign = $conn->prepare("
            INSERT INTO user_vouchers (user_id, voucher_id)
            VALUES (?, ?)
        ");
        $stmt_assign->bind_param("ii", $row['id'], $voucher['voucher_id']);
        $stmt_assign->execute();
        $stmt_assign->close();
    }
    $stmt_check_user->close();
}

// 3. Factuur aanmaken via create_own_voucher_invoice.php
$order = null;
if (!empty($user_info)) {
    $order = [
        'name' => $user_info['name'],
        'company_name' => $user_info['company_name'],
        'vat_number' => $user_info['vat_number'],
        'street' => $user_info['street'],
        'number' => $user_info['number'],
        'zip' => $user_info['zip'],
        'city' => $user_info['city'],
        'country' => $user_info['country']
    ];
}

$invoice_result = create_own_voucher_invoice(
    $voucher['code'],   // $voucher_code
    $voucher['amount'], // $amount
    $create_date,       // $create_date
    $reason,            // $reason
    $order,             // $order (kan null)
    $lang               // $lang
);

// 4. Resultaat teruggeven
echo json_encode([
    'success' => true,
    'voucher' => $voucher,
    'user' => $user_info,
    'invoice' => $invoice_result
], JSON_UNESCAPED_UNICODE);
exit;
?>