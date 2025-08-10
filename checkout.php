<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "Je moet ingelogd zijn.";
    exit;
}

$user_id = $_SESSION['user_id'];

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

    // Hier zou je order details kunnen toevoegen in een aparte tabel (niet aanwezig nu)

    // Leeg winkelwagen
    $conn->query("DELETE FROM cart WHERE user_id = $user_id");

    echo "Bestelling succesvol geplaatst! Totaal: €" . number_format($total, 2);
} else {
    echo "Fout bij plaatsen bestelling: " . $conn->error;
}
?>