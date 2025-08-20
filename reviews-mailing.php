<?php
session_start();
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

// Ontvanger van de e-mails
$to = "klantendienst@stylisso.be";

// Formuliervelden ophalen
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$beoordeling = isset($_POST['beoordeling']) ? trim($_POST['beoordeling']) : '';
$review = isset($_POST['review']) ? trim($_POST['review']) : '';

// Validatie: zorgen dat velden niet leeg zijn
if (empty($name) || empty($beoordeling) || empty($review)) {
    echo "Fout: Vul alle velden in.";
    exit;
}

// E-mail onderwerp en bericht
$subject = "Nieuwe review van $name";
$message = "
Naam: $name
Beoordeling: $beoordeling sterren
Review:
$review
";

// E-mail headers
$headers = "From: no-reply@stylisso.be\r\n"; // vervang indien nodig
$headers .= "Reply-To: $name\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// E-mail verzenden
if (mail($to, $subject, $message, $headers)) {
    // Succes: terugsturen of bedankpagina
    header("Location: reviews.html?status=success");
    exit;
} else {
    echo "Er is iets misgegaan bij het verzenden van de review.";
}
?>