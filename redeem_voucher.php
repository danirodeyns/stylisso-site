<?php
session_start();
require 'db_connect.php';
include 'translations.php';
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

header('Content-Type: application/json'); // JSON response

// Controleer of gebruiker ingelogd is
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => t('voucher_login_required')]);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Controleer of voucher_code aanwezig is
    if (!isset($_POST['voucher_code']) || empty(trim($_POST['voucher_code']))) {
        echo json_encode(["error" => t('voucher_invalid_code')]);
        exit;
    }

    $code = strtoupper(trim($_POST['voucher_code']));

    // Controleer of bon bestaat, nog waarde heeft en niet verlopen is
    $stmt = $conn->prepare("
        SELECT * FROM vouchers 
        WHERE code = ? 
          AND remaining_value > 0
          AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1
    ");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $voucher = $result->fetch_assoc();
    $stmt->close();

    if (!$voucher) {
        echo json_encode(["error" => t('voucher_not_found_or_expired')]);
        exit;
    }

    // Controleer of voucher al door iemand geclaimd is
    $stmt = $conn->prepare("SELECT user_id FROM user_vouchers WHERE voucher_id = ?");
    $stmt->bind_param("i", $voucher['id']);
    $stmt->execute();
    $claimed = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($claimed) {
        if ($claimed['user_id'] == $user_id) {
            echo json_encode(["error" => t("voucher_already_linked")]);
        } else {
            echo json_encode(["error" => t("voucher_already_claimed")]);
        }
        exit;
    }

    // Koppel bon aan gebruiker
    $stmt = $conn->prepare("INSERT INTO user_vouchers (user_id, voucher_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $voucher['id']);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        "success" => t('voucher_link_success') . " €" . number_format($voucher['remaining_value'], 2, ',', '.')
    ]);
}
?>