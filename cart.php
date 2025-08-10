<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "Je moet ingelogd zijn.";
    exit;
}

$user_id = $_SESSION['user_id'];

// Product toevoegen aan cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    // Check of product al in cart zit
    $sql = "SELECT id, quantity FROM cart WHERE user_id=$user_id AND product_id=$product_id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Updaten
        $row = $result->fetch_assoc();
        $new_qty = $row['quantity'] + $quantity;
        $conn->query("UPDATE cart SET quantity=$new_qty WHERE id=" . $row['id']);
    } else {
        // Toevoegen
        $conn->query("INSERT INTO cart (user_id, product_id, quantity) VALUES ($user_id, $product_id, $quantity)");
    }
    echo "Product toegevoegd aan winkelwagen.<br>";
}

// Toon cart
$sql = "SELECT cart.id, products.name, products.price, cart.quantity 
        FROM cart 
        JOIN products ON cart.product_id = products.id 
        WHERE cart.user_id = $user_id";

$result = $conn->query($sql);

echo "<h2>Jouw winkelwagen</h2>";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo htmlspecialchars($row['name']) . " - €" . $row['price'] . " x " . $row['quantity'] . "<br>";
    }
} else {
    echo "Winkelwagen is leeg.";
}
?>

<form method="post">
    Product ID: <input type="number" name="product_id" required><br>
    Aantal: <input type="number" name="quantity" value="1" min="1" required><br>
    <button type="submit">Toevoegen</button>
</form>