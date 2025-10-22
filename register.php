<?php
session_start();
include 'db_connect.php';
include 'translations.php';
include 'csrf.php';
csrf_validate();
include 'mailing.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['register-name'] ?? '');
    $email          = trim($_POST['register-email'] ?? '');
    $password_raw   = $_POST['register-password'] ?? '';
    $password2_raw  = $_POST['register-password2'] ?? '';
    $newsletter     = isset($_POST['newsletter']) ? 1 : 0;
    $terms_accepted = isset($_POST['terms_of_use']) ? 1 : 0;

    // --- VALIDATIE ---
    if (empty($name)) { header('Location: login_registreren.html?error_name=empty&old_email=' . urlencode($email)); exit; }
    if (empty($email)) { header('Location: login_registreren.html?error_email=empty&old_name=' . urlencode($name)); exit; }
    if (empty($password_raw)) { header('Location: login_registreren.html?error_password=empty&old_name=' . urlencode($name) . '&old_email=' . urlencode($email)); exit; }
    if (empty($password2_raw)) { header('Location: login_registreren.html?error_password2=empty&old_name=' . urlencode($name) . '&old_email=' . urlencode($email)); exit; }
    if (!$terms_accepted) { header('Location: login_registreren.html?error_terms=required&old_name=' . urlencode($name) . '&old_email=' . urlencode($email)); exit; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { header('Location: login_registreren.html?error_email=invalid&old_name=' . urlencode($name)); exit; }
    if ($password_raw !== $password2_raw) { header('Location: login_registreren.html?error_password2=nomatch&old_name=' . urlencode($name) . '&old_email=' . urlencode($email)); exit; }

    // --- CHECK BESTAANDE EMAIL ---
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        header('Location: login_registreren.html?error_email=exists&old_name=' . urlencode($name));
        exit;
    }

    // --- WACHTWOORD HASHEN ---
    $password_hashed = password_hash($password_raw, PASSWORD_DEFAULT);

    // --- GEBRUIKER TOEVOEGEN ---
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, newsletter, terms_accepted) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssii", $name, $email, $password_hashed, $newsletter, $terms_accepted);

    if ($stmt->execute()) {
        // --- AUTOMATISCH INLOGGEN ---
        $_SESSION['user_id'] = $conn->insert_id;
        $_SESSION['user_name'] = $name;

        // --- REMEMBER COOKIE ---
        $token = bin2hex(random_bytes(16));
        $update = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $update->bind_param("si", $token, $_SESSION['user_id']);
        $update->execute();
        setcookie('user_login', json_encode(['id'=>$_SESSION['user_id'], 'name'=>$name, 'token'=>$token]), time() + (30*24*60*60), "/");

        // --- WELCOME MAIL TRIGGEREN ---
        sendWelcomeMail($email, $name);

        // --- REDIRECT NAAR HOMEPAGE ---
        header("Location: index.html");
        exit;
    } else {
        header('Location: login_registreren.html?error_general=insert_failed&old_name=' . urlencode($name) . '&old_email=' . urlencode($email));
        exit;
    }
} else {
    header('Location: login_registreren.html');
    exit;
}
?>