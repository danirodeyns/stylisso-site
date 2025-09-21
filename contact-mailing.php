<?php
include 'csrf.php';
include 'translations.php';
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
        echo t('contact_form_success');
    } else {
        echo t('contact_form_error');
    }
}
?>