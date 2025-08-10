<?php
// login.php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['login-email'] ?? '';
    $password = $_POST['login-password'] ?? '';

    // Prepared statement voor veiligheid
    $stmt = $conn->prepare("SELECT id, password, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: index.html");
            exit;
        } else {
            // Verkeerd wachtwoord
            header('Location: login_registreren.html?error=wrong_password&old_email=' . urlencode($email));
            exit;
        }
    } else {
        // E-mail niet gevonden
        header('Location: login_registreren.html?error=email_not_found&old_email=' . urlencode($email));
        exit;
    }
}
?>