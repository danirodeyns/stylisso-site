<?php
// login.php
session_start();
include 'db_connect.php';
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

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
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            // --- Migreer sessie-cart naar database ---
            if (!empty($_SESSION['cart_products'])) {
                foreach ($_SESSION['cart_products'] as $p) {
                    $product_id = (int)$p['product_id'];
                    $quantity   = (int)$p['quantity'];
                    $price      = (float)$p['price'];

                    // Check of product al in DB-cart bestaat → update quantity
                    $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + ? 
                                            WHERE user_id = ? AND product_id = ? AND type = 'product'");
                    $stmt->bind_param("iii", $quantity, $user['id'], $product_id);
                    $stmt->execute();

                    if ($stmt->affected_rows === 0) {
                        // Bestond nog niet, dus nieuw record
                        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, price) 
                                                VALUES (?, ?, 'product', ?, ?)");
                        $stmt->bind_param("iiid", $user['id'], $product_id, $quantity, $price);
                        $stmt->execute();
                    }
                }
                unset($_SESSION['cart_products']); // sessie leegmaken
            }

            if (!empty($_SESSION['cart_vouchers'])) {
                foreach ($_SESSION['cart_vouchers'] as $v) {
                    $quantity = (int)$v['quantity'];
                    $amount   = (float)$v['amount'];

                    // Voor vouchers gebruiken we product_id = NULL
                    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, price) 
                                            VALUES (?, NULL, 'voucher', ?, ?)");
                    $stmt->bind_param("iid", $user['id'], $quantity, $amount);
                    $stmt->execute();
                }
                unset($_SESSION['cart_vouchers']); // sessie leegmaken
            }

            // Zet langdurige cookie enkel als banner geaccepteerd
            if ($cookiesAccepted === "1") {
                // Genereer token
                $token = bin2hex(random_bytes(16));

                // Update database
                $update = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $update->bind_param("si", $token, $user['id']);
                $update->execute();
                
                // Zet cookie
                $cookie_name = "user_login";
                $cookie_value = json_encode([
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'token' => $token
                ]);
                setcookie($cookie_name, $cookie_value, time() + (30 * 24 * 60 * 60), "/"); // 30 dagen
            }

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