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
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "Email bestaat al.";
        exit;
    }

    // Insert nieuwe gebruiker
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $password, $address);

    if ($stmt->execute()) {
        // Direct inloggen na registratie
        $_SESSION['user_id'] = $conn->insert_id;
        $_SESSION['user_name'] = $name;

        // Redirect naar startpagina
        header("Location: index.html");
        exit;
    } else {
        echo "Fout: " . $conn->error;
        exit;
    }
}
?>