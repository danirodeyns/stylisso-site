<?php
// login.php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT id, password, name FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            echo "Inloggen gelukt!";
            // header("Location: dashboard.php"); // Later redirect
        } else {
            echo "Verkeerd wachtwoord.";
        }
    } else {
        echo "Email niet gevonden.";
    }
}
?>

<form method="post">
    Email: <input type="email" name="email" required><br>
    Wachtwoord: <input type="password" name="password" required><br>
    <button type="submit">Inloggen</button>
</form>