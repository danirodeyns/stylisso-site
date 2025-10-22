<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
include 'translations.php';

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
    $stmt = $conn->prepare(
        "INSERT INTO vouchers (code, value, remaining_value, expires_at) VALUES (?, ?, ?, ?)"
    );
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
    // Controleer of gebruiker bestaat
    $stmt_check_user = $conn->prepare("SELECT id, name FROM users WHERE id = ?");
    $stmt_check_user->bind_param("i", $assign_user_id);
    $stmt_check_user->execute();
    $stmt_check_user->store_result();
    $stmt_check_user->bind_result($uid, $uname);
    if ($stmt_check_user->num_rows > 0) {
        $stmt_check_user->fetch();
        $user_info = ['id' => $uid, 'name' => $uname];

        // Voucher toewijzen
        $stmt_assign = $conn->prepare("INSERT INTO user_vouchers (user_id, voucher_id) VALUES (?, ?)");
        $stmt_assign->bind_param("ii", $uid, $voucher['voucher_id']);
        $stmt_assign->execute();
        $stmt_assign->close();
    }
    $stmt_check_user->close();
}

// 3. Factuur aanmaken via create_own_voucher_invoice.php
$invoice_script = __DIR__ . '/create_own_voucher_invoice.php';
if (file_exists($invoice_script)) {
    include $invoice_script;
    $invoice_result = create_own_voucher_invoice($voucher['code'], $voucher['amount'], $create_date, $reason, $lang);
} else {
    $invoice_result = ['success' => false, 'message' => 'Invoice script niet gevonden'];
}

// 4. Resultaat teruggeven
echo json_encode([
    'success' => true,
    'voucher' => $voucher,
    'user' => $user_info,
    'invoice' => $invoice_result
], JSON_UNESCAPED_UNICODE);
exit;
?>