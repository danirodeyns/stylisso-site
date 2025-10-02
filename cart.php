<?php
session_start();
include 'db_connect.php';
include 'csrf.php';
include 'translations.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

// --- POST-handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate(); // alleen bij POST

    // Probeer JSON te lezen
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);

    // Als JSON leeg is, fallback naar $_POST (FormData)
    if (!$data) $data = $_POST;

    $action = $_GET['action'] ?? '';

    // --- REMOVE ITEM ---
    if ($action === 'remove_item') {
        $cart_id = isset($data['id']) && is_numeric($data['id']) ? (int)$data['id'] : null;
        $cart_index = isset($data['index']) && is_numeric($data['index']) ? (int)$data['index'] : null;
        $type = $data['type'] ?? null;

        if (!$cart_id && $cart_index === null) {
            echo json_encode(['success' => false, 'message' => 'Ongeldige verwijderdata']);
            exit;
        }

        if ($user_id && $cart_id) {
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cart_id, $user_id);
            $stmt->execute();
        } else {
            if ($type === 'voucher' && isset($_SESSION['cart_vouchers'][$cart_index])) {
                array_splice($_SESSION['cart_vouchers'], $cart_index, 1);
            } elseif ($type === 'product' && isset($_SESSION['cart_products'][$cart_index])) {
                array_splice($_SESSION['cart_products'], $cart_index, 1);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // --- UPDATE QUANTITY ---
    if ($action === 'update_quantity') {
        $cart_id = $data['id'] ?? null;
        $quantity = isset($data['quantity']) ? (int)$data['quantity'] : null;
        $itemType = $data['type'] ?? null;
        $index = $data['index'] ?? null;

        if ($user_id && $cart_id) {
            if (!$quantity || $quantity < 1) {
                echo json_encode(['success' => false, 'message' => 'Ongeldige data.']);
                exit;
            }
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
            $stmt->execute();
        } elseif (!$user_id && $index !== null) {
            if ($itemType === 'voucher' && !empty($_SESSION['cart_vouchers']) && isset($_SESSION['cart_vouchers'][$index])) {
                $_SESSION['cart_vouchers'][$index]['quantity'] = $quantity;
            } elseif ($itemType === 'product' && !empty($_SESSION['cart_products']) && isset($_SESSION['cart_products'][$index])) {
                $_SESSION['cart_products'][$index]['quantity'] = $quantity;
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // --- ADD ITEM ---
    $product_id = isset($data['product_id']) ? (int)$data['product_id'] : null;
    $type = $data['type'] ?? 'product';
    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
    $price = isset($data['price']) ? floatval($data['price']) : null;
    $maat = $data['maat'] ?? null; // ✅ maat toevoegen

    if (($type === 'product' && !$product_id) || $quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Ongeldige data.']);
        exit;
    }

    if ($user_id) {
        // UPDATE met maat
        $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id <=> ? AND type = ? AND (maat <=> ?)");
        $stmt->bind_param("iisss", $quantity, $user_id, $product_id, $type, $maat);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            // INSERT met maat
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, price, maat) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisids", $user_id, $product_id, $type, $quantity, $price, $maat);
            $stmt->execute();
        }
    } else {
        if ($type === 'voucher') {
            if (!isset($_SESSION['cart_vouchers'])) $_SESSION['cart_vouchers'] = [];
            $_SESSION['cart_vouchers'][] = ['price' => $price, 'quantity' => $quantity];
        } else {
            if (!isset($_SESSION['cart_products'])) $_SESSION['cart_products'] = [];
            $_SESSION['cart_products'][] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'price' => $price,
                'maat' => $maat // ✅ maat toevoegen aan sessie-cart
            ];
        }
    }

    echo json_encode(['success' => true, 'message' => ucfirst($type) . ' toegevoegd aan winkelwagen.']);
    exit;
}

// --- GET: ophalen cart ---
$cart = [];

if ($user_id) {
    $stmt = $conn->prepare("
        SELECT 
            c.id, 
            c.product_id, 
            c.type, 
            c.quantity, 
            c.price, 
            c.maat, -- ✅ maat toevoegen
            CASE 
                WHEN c.type = 'voucher' THEN 'Cadeaubon' 
                ELSE p.name 
            END AS name,
            CASE 
                WHEN c.type = 'voucher' THEN 'cadeaubon/voucher.png'
                WHEN p.image IS NOT NULL THEN p.image
                ELSE 'placeholder.png'
            END AS image,
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
    while ($row = $result->fetch_assoc()) $cart[] = $row;
} else {
    if (!empty($_SESSION['cart_products'])) {
        foreach ($_SESSION['cart_products'] as $i => $p) {
            $cart[] = [
                'id' => null,
                'product_id' => $p['product_id'],
                'type' => 'product',
                'quantity' => $p['quantity'],
                'price' => $p['price'],
                'maat' => $p['maat'] ?? null, // ✅ maat toevoegen
                'name' => 'Product #' . $p['product_id'],
                'image' => 'placeholder.png',
                'dark_image' => null,
                'index' => $i
            ];
        }
    }
    if (!empty($_SESSION['cart_vouchers'])) {
        foreach ($_SESSION['cart_vouchers'] as $i => $v) {
            $cart[] = [
                'id' => null,
                'product_id' => null,
                'type' => 'voucher',
                'quantity' => $v['quantity'],
                'price' => $v['price'],
                'name' => 'Cadeaubon',
                'image' => 'cadeaubon/voucher.png',
                'dark_image' => 'cadeaubon/voucher (dark mode).png',
                'index' => $i
            ];
        }
    }
}

echo json_encode(['success' => true, 'cart' => $cart]);
error_log('POST remove_item ontvangen: ' . file_get_contents('php://input'));
?>