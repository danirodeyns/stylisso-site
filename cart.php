<?php
session_start();
include 'db_connect.php';
include 'csrf.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

header('Content-Type: application/json');

// CSRF-validatie voor AJAX POST
function csrf_validate_ajax() {
    if (!isset($_SESSION['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF-token ontbreekt in sessie']);
        exit;
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ongeldig CSRF-token']);
        exit;
    }
}

$user_id = $_SESSION['user_id'] ?? null;

// POST-handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_ajax();

    $data = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';

    // --- Alleen ingelogd: DB acties ---
    if ($user_id) {
        if ($action === 'update_quantity') {
            $cart_id = $data['id'] ?? null;
            $quantity = isset($data['quantity']) ? (int)$data['quantity'] : null;

            if (!$cart_id || !$quantity || $quantity < 1) {
                echo json_encode(['success' => false, 'message' => 'Ongeldige data.']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
            $stmt->execute();

            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'remove_item') {
            $cart_id = $data['id'] ?? null;

            if ($user_id) {
                // Ingelogd -> DB verwijderen
                if (!$cart_id) {
                    echo json_encode(['success' => false, 'message' => 'Ongeldige data.']);
                    exit;
                }
                $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $cart_id, $user_id);
                $stmt->execute();
            } else {
                // Niet ingelogd -> sessie verwijderen
                $index = $data['index'] ?? null;
                $type = $data['type'] ?? null;

                if ($type === 'product' && isset($_SESSION['cart_products'][$index])) {
                    unset($_SESSION['cart_products'][$index]);
                } elseif ($type === 'voucher' && isset($_SESSION['cart_vouchers'][$index])) {
                    unset($_SESSION['cart_vouchers'][$index]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Item niet gevonden in sessie.']);
                    exit;
                }
            }

            echo json_encode(['success' => true]);
            exit;
        }
    }

    // --- Product/voucher toevoegen ---
    $product_id = isset($data['product_id']) ? (int)$data['product_id'] : null;
    $type = $data['type'] ?? 'product';
    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
    $price = isset($data['price']) ? floatval($data['price']) : null;

    if (($type === 'product' && !$product_id) || $quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Ongeldige data.']);
        exit;
    }

    if ($user_id) {
        // Opslaan in DB
        $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id <=> ? AND type = ?");
        $stmt->bind_param("iiss", $quantity, $user_id, $product_id, $type);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisid", $user_id, $product_id, $type, $quantity, $price);
            $stmt->execute();
        }
    } else {
        // Opslaan in sessie
        if ($type === 'voucher') {
            if (!isset($_SESSION['cart_vouchers'])) {
                $_SESSION['cart_vouchers'] = [];
            }
            $_SESSION['cart_vouchers'][] = [
                'amount' => $price,
                'quantity' => $quantity
            ];
        } else {
            if (!isset($_SESSION['cart_products'])) {
                $_SESSION['cart_products'] = [];
            }
            $_SESSION['cart_products'][] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'price' => $price
            ];
        }
    }

    echo json_encode(['success' => true, 'message' => ucfirst($type) . ' toegevoegd aan winkelwagen.']);
    exit;
}

// --- GET: ophalen cart ---
$cart = [];

if ($user_id) {
    // Haal alles uit DB
    $stmt = $conn->prepare("
        SELECT 
            c.id, 
            c.product_id, 
            c.type, 
            c.quantity, 
            c.price, 
            COALESCE(p.name, 'Cadeaubon') AS name,
            COALESCE(p.image, 'cadeaubon/voucher.png') AS image,
            CASE
                WHEN c.type = 'voucher' THEN 'cadeaubon/voucher (dark mode).png'
                ELSE NULL
            END AS dark_image
        FROM cart c
        LEFT JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $cart[] = $row;
    }
} else {
    // Haal alles uit sessie
    if (!empty($_SESSION['cart_products'])) {
        foreach ($_SESSION['cart_products'] as $p) {
            $cart[] = [
                'id' => null,
                'product_id' => $p['product_id'],
                'type' => 'product',
                'quantity' => $p['quantity'],
                'price' => $p['price'],
                'name' => 'Product #' . $p['product_id'], // Optioneel later vervangen door query
                'image' => 'placeholder.png',
                'dark_image' => null
            ];
        }
    }
    if (!empty($_SESSION['cart_vouchers'])) {
        foreach ($_SESSION['cart_vouchers'] as $v) {
            $cart[] = [
                'id' => null,
                'product_id' => null,
                'type' => 'voucher',
                'quantity' => $v['quantity'],
                'price' => $v['amount'],
                'name' => 'Cadeaubon',
                'image' => 'cadeaubon/voucher.png',
                'dark_image' => 'cadeaubon/voucher (dark mode).png'
            ];
        }
    }
}

echo json_encode(['success' => true, 'cart' => $cart]);
?>