<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'db_connect.php';
require_once 'csrf.php';
include 'translations.php';
include 'mailing.php';

// --- CSRF-validatie ---
try {
    csrf_validate();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

// --- Ophalen van onderwerp en bericht uit POST ---
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$subject || !$message) {
    echo json_encode(['success' => false, 'error' => 'Onderwerp of bericht ontbreekt']);
    exit;
}

// --- Verstuur de testmail via de functie sendMailTester ---
$response = sendMailTester($subject, $message);

// --- Controleer of het antwoord geldig is ---
if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'Verbinding met mailing.php mislukt']);
    exit;
}

// --- Response verwerken ---
echo json_encode([
    'success' => true,
    'message' => 'Test e-mail verzonden'
]);

exit;
?>