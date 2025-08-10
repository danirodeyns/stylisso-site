<?php
// register.php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['register-name']);
    $email = $conn->real_escape_string($_POST['register-email']);
    $password_raw = $_POST['register-password'];
    $password2_raw = $_POST['register-password2'];

    // Adres wordt leeg gelaten bij registratie
    $address = '';

    // Check of wachtwoorden overeenkomen
    if ($password_raw !== $password2_raw) {
        echo "Wachtwoorden komen niet overeen.";
        exit;
    }

    // Hash het wachtwoord
    $password = password_hash($password_raw, PASSWORD_DEFAULT);

    // Check of email al bestaat
    $sql = "SELECT id FROM users WHERE email='$email'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        echo "Email bestaat al.";
        exit;
    }

    // Insert nieuwe gebruiker
    $sql = "INSERT INTO users (name, email, password, address) VALUES ('$name', '$email', '$password', '$address')";
    if ($conn->query($sql) === TRUE) {
        echo "Registratie gelukt.";
    } else {
        echo "Fout: " . $conn->error;
    }
}
?>