<?php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['register-name'] ?? '');
    $email = trim($_POST['register-email'] ?? '');
    $password_raw = $_POST['register-password'] ?? '';
    $password2_raw = $_POST['register-password2'] ?? '';
    $address = '';

    // Bewaar oude waarden om formulier te kunnen opvullen
    $_SESSION['old_name'] = $name;
    $_SESSION['old_email'] = $email;

    if (empty($name) || empty($email) || empty($password_raw) || empty($password2_raw)) {
        $_SESSION['register_error'] = ['field' => 'general', 'message' => 'Vul alle velden in.'];
        header('Location: login_registreren.html');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = ['field' => 'email', 'message' => 'Ongeldig e-mailadres.'];
        header('Location: login_registreren.html');
        exit;
    }

    if ($password_raw !== $password2_raw) {
        $_SESSION['register_error'] = ['field' => 'password2', 'message' => 'Wachtwoorden komen niet overeen.'];
        header('Location: login_registreren.html');
        exit;
    }

    // Controleer of email al bestaat
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        $_SESSION['register_error'] = ['field' => 'general', 'message' => 'Database fout: ' . $conn->error];
        header('Location: login_registreren.html');
        exit;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['register_error'] = ['field' => 'email', 'message' => 'Email bestaat al.'];
        header('Location: login_registreren.html');
        exit;
    }

    // Wachtwoord hashen
    $password = password_hash($password_raw, PASSWORD_DEFAULT);

    // Nieuwe gebruiker toevoegen
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, address) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        $_SESSION['register_error'] = ['field' => 'general', 'message' => 'Database fout: ' . $conn->error];
        header('Location: login_registreren.html');
        exit;
    }
    $stmt->bind_param("ssss", $name, $email, $password, $address);

    if ($stmt->execute()) {
        // Registratie succesvol, oude data en errors verwijderen
        unset($_SESSION['old_name'], $_SESSION['old_email'], $_SESSION['register_error']);

        $_SESSION['user_id'] = $conn->insert_id;
        $_SESSION['user_name'] = $name;
        header('Location: index.php');  // Pas aan naar jouw homepage
        exit;
    } else {
        $_SESSION['register_error'] = ['field' => 'general', 'message' => 'Fout bij registratie: ' . $conn->error];
        header('Location: login_registreren.html');
        exit;
    }
} else {
    header('Location: login_registreren.html');
    exit;
}
?>