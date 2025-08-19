<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['register-name'] ?? '');
    $email        = trim($_POST['register-email'] ?? '');
    $password_raw = $_POST['register-password'] ?? '';
    $password2_raw= $_POST['register-password2'] ?? '';
    $address      = '';
    $newsletter     = isset($_POST['newsletter']) ? 1 : 0;
    $terms_accepted = isset($_POST['terms_of_use']) ? 1 : 0;

    // 1. Controleer lege velden (per veld apart)
    if (empty($name)) {
        header('Location: login_registreren.html?error_name=empty&old_email=' . urlencode($email));
        exit;
    }
    if (empty($email)) {
        header('Location: login_registreren.html?error_email=empty&old_name=' . urlencode($name));
        exit;
    }
    if (empty($password_raw)) {
        header('Location: login_registreren.html?error_password=empty&old_name=' . urlencode($name) . '&old_email=' . urlencode($email));
        exit;
    }
    if (empty($password2_raw)) {
        header('Location: login_registreren.html?error_password2=empty&old_name=' . urlencode($name) . '&old_email=' . urlencode($email));
        exit;
    }
    // Controleer of algemene voorwaarden zijn aangevinkt
    if (!$terms_accepted) {
        header('Location: login_registreren.html?error_terms=required&old_name=' . urlencode($name) . '&old_email=' . urlencode($email));
        exit;
    }

    // 2. Ongeldig e-mailadres
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: login_registreren.html?error_email=invalid&old_name=' . urlencode($name));
        exit;
    }

    // 3. Wachtwoorden komen niet overeen
    if ($password_raw !== $password2_raw) {
        header('Location: login_registreren.html?error_password2=nomatch&old_name=' . urlencode($name) . '&old_email=' . urlencode($email));
        exit;
    }

    // 4. Controleer of email al bestaat
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        header('Location: login_registreren.html?error_general=db_error&old_name=' . urlencode($name) . '&old_email=' . urlencode($email));
        exit;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header('Location: login_registreren.html?error_email=exists&old_name=' . urlencode($name));
        exit;
    }

    // 5. Wachtwoord hashen
    $password = password_hash($password_raw, PASSWORD_DEFAULT);

    // 6. Nieuwe gebruiker toevoegen (inclusief newsletter en terms_accepted)
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, address, newsletter, terms_accepted) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        header('Location: login_registreren.html?error_general=db_error&old_name=' . urlencode($name) . '&old_email=' . urlencode($email));
        exit;
    }
    $stmt->bind_param("sssiii", $name, $email, $password, $address, $newsletter, $terms_accepted);

    if ($stmt->execute()) {
        header('Location: login_registreren.html?success=registered');
        exit;
    } else {
        header('Location: login_registreren.html?error_general=insert_failed&old_name=' . urlencode($name) . '&old_email=' . urlencode($email));
        exit;
    }
} else { 
    header('Location: login_registreren.html'); exit;
}
?>