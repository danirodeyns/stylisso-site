<?php
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name    = htmlspecialchars($_POST['name']);
    $email   = htmlspecialchars($_POST['email']);
    $subject = htmlspecialchars($_POST['subject']);
    $message = htmlspecialchars($_POST['message']);

    $to = "klantendienst@stylisso.be";
    $headers = "From: " . $email . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $fullMessage = "Naam: $name\n";
    $fullMessage .= "E-mail: $email\n";
    $fullMessage .= "Onderwerp: $subject\n\n";
    $fullMessage .= "Bericht:\n$message\n";

    if (mail($to, $subject, $fullMessage, $headers)) {
        echo "Bedankt! Je bericht is verzonden.";
    } else {
        echo "Er is een fout opgetreden. Probeer het later opnieuw.";
    }
}
?>