<?php
include 'db_connect.php';

header('Content-Type: application/json');

$sql = "SELECT id, name, description, price, image FROM products";
$result = $conn->query($sql);

$products = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

echo json_encode($products);
?>