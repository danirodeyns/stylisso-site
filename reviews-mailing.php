<?php 
session_start();
include 'translations.php';
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

header('Content-Type: application/json'); // JSON response

// Ontvanger van de e-mails
$to = "klantendienst@stylisso.be";

// Formuliervelden ophalen
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$beoordeling = isset($_POST['beoordeling']) ? trim($_POST['beoordeling']) : '';
$review = isset($_POST['review']) ? trim($_POST['review']) : '';

// Validatie
if (empty($name) || empty($beoordeling) || empty($review)) {
    echo json_encode(["error" => "Vul alle velden in."]);
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
$headers = "From: no-reply@stylisso.be\r\n";
$headers .= "Reply-To: $name\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// E-mail verzenden
if (mail($to, $subject, $message, $headers)) {
    echo json_encode(["success" => t('review_success')]); 
} else {
    echo json_encode(["error" => t('review_error')]);
}
?>