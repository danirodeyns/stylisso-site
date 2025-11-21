<?php
session_start();
include 'db_connect.php';
include 'translations.php';
include 'csrf.php';
csrf_validate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['login-email'] ?? '';
    $password = $_POST['login-password'] ?? '';
    $cookiesAccepted = $_POST['cookies_accepted'] ?? '0';

    // Prepared statement voor veiligheid
    $stmt = $conn->prepare("SELECT id, password, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {

            // Zet sessie altijd
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            // --- Migreer sessie-cart naar database ---
            if (!empty($_SESSION['cart_products'])) {
                foreach ($_SESSION['cart_products'] as $p) {
                    $product_id = (int)$p['product_id'];
                    $quantity   = (int)$p['quantity'];
                    $price      = (float)$p['price'];

                    $stmt = $conn->prepare("
                        UPDATE cart 
                        SET quantity = quantity + ? 
                        WHERE user_id = ? AND product_id = ? AND type = 'product'
                    ");
                    $stmt->bind_param("iii", $quantity, $user['id'], $product_id);
                    $stmt->execute();

                    if ($stmt->affected_rows === 0) {
                        $stmt = $conn->prepare("
                            INSERT INTO cart (user_id, product_id, type, quantity, price)
                            VALUES (?, ?, 'product', ?, ?)
                        ");
                        $stmt->bind_param("iiid", $user['id'], $product_id, $quantity, $price);
                        $stmt->execute();
                    }
                }
                unset($_SESSION['cart_products']);
            }

            if (!empty($_SESSION['cart_vouchers'])) {
                foreach ($_SESSION['cart_vouchers'] as $v) {
                    $quantity = (int)$v['quantity'];
                    $price    = (float)$v['price'];

                    $stmt = $conn->prepare("
                        INSERT INTO cart (user_id, product_id, type, quantity, price)
                        VALUES (?, NULL, 'voucher', ?, ?)
                    ");
                    $stmt->bind_param("iid", $user['id'], $quantity, $price);
                    $stmt->execute();
                }
                unset($_SESSION['cart_vouchers']);
            }

            // ================================
            // REMEMBER ME VIA user_tokens TABEL
            // ================================
            if ($cookiesAccepted === "1") {

                $token  = bin2hex(random_bytes(32));
                $device = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

                // Nieuw apparaat-token opslaan
                $insert = $conn->prepare("
                    INSERT INTO user_tokens (user_id, token, device, expires_at, last_used)
                    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 YEAR), NOW())
                ");
                $insert->bind_param("iss", $user['id'], $token, $device);
                $insert->execute();

                // Cookie zetten
                setcookie("user_login", json_encode([
                    'id'    => $user['id'],
                    'token' => $token
                ]), [
                    'expires' => time() + (365*24*60*60),
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            }

            // Klaar
            header("Location: index.html");
            exit;

        } else {
            // Verkeerd wachtwoord
            header('Location: login_registreren.html?error=wrong_password&old_email=' . urlencode($email));
            exit;
        }

    } else {
        // E-mail niet gevonden
        header('Location: login_registreren.html?error=email_not_found&old_email=' . urlencode($email));
        exit;
    }
}
?>