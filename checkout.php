<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "Je moet ingelogd zijn.";
    exit;
}

$user_id = $_SESSION['user_id'];

// Adres ophalen uit DB
$stmt = $conn->prepare("SELECT address FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$current_address = $user['address'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adres uit formulier
    $new_address = trim($_POST['address']);

    // Adres updaten in DB
    $stmt = $conn->prepare("UPDATE users SET address = ? WHERE id = ?");
    $stmt->bind_param("si", $new_address, $user_id);
    $stmt->execute();

    // Haal items op uit winkelwagen
    $sql = "SELECT cart.product_id, products.price, cart.quantity 
            FROM cart 
            JOIN products ON cart.product_id = products.id 
            WHERE cart.user_id = $user_id";

    $result = $conn->query($sql);

    $total = 0;
    $items = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
            $total += $row['price'] * $row['quantity'];
        }
    } else {
        echo "Winkelwagen is leeg.";
        exit;
    }

    // Voeg order toe aan orders tabel
    $sql = "INSERT INTO orders (user_id, total_price) VALUES ($user_id, $total)";
    if ($conn->query($sql) === TRUE) {
        $order_id = $conn->insert_id;

        // Optioneel: hier order details toevoegen (niet in jouw huidige setup)

        // Leeg winkelwagen
        $conn->query("DELETE FROM cart WHERE user_id = $user_id");

        echo "Bestelling succesvol geplaatst! Totaal: €" . number_format($total, 2);
        exit;
    } else {
        echo "Fout bij plaatsen bestelling: " . $conn->error;
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Checkout</title>
</head>
<body>
    <h1>Checkout</h1>
    <form method="post" action="checkout.php">
        <label for="address">Adres</label><br>
        <textarea id="address" name="address" required><?= htmlspecialchars($current_address) ?></textarea><br><br>
        <button type="submit">Bestelling plaatsen</button>
    </form>
</body>
</html>