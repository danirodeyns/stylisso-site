<?php
// register.php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Adres wordt leeg gelaten bij registratie
    $address = '';

    // Check of email al bestaat
    $sql = "SELECT id FROM users WHERE email='$email'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        echo "Email bestaat al.";
        exit;
    }

    $sql = "INSERT INTO users (name, email, password, address) VALUES ('$name', '$email', '$password', '$address')";
    if ($conn->query($sql) === TRUE) {
        echo "Registratie gelukt.";
    } else {
        echo "Fout: " . $conn->error;
    }
}
?>

<form method="post">
    Naam: <input type="text" name="name" required><br>
    Email: <input type="email" name="email" required><br>
    Wachtwoord: <input type="password" name="password" required><br>
    <button type="submit">Registeren</button>
</form>