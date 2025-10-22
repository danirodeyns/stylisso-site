<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'db_connect.php';
require_once 'csrf.php';
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

// --- Haal e-mails op van gebruikers die de nieuwsbrief ontvangen ---
$sql = "SELECT email FROM users WHERE newsletter = 1 AND email IS NOT NULL AND email != ''";
$result = $conn->query($sql);

$emails = [];
while ($row = $result->fetch_assoc()) {
    $emails[] = $row['email'];
}
$conn->close();

if (empty($emails)) {
    echo json_encode(['success' => false, 'error' => 'Geen nieuwsbriefontvangers gevonden']);
    exit;
}

// --- Verstuur via mailing.php ---
$response = sendNewsletter($emails, $subject, $message);

// --- Controleer of het antwoord geldig is ---
if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'Verbinding met mailing.php mislukt']);
    exit;
}

// --- Response verwerken ---
if (is_array($response)) {
    // Als sendNewsletter al 'sent' en 'total' terugstuurt
    $sent = $response['sent'] ?? 0;
    $total = $response['total'] ?? count($emails);
    echo json_encode([
        'success' => $sent === $total,
        'sent' => $sent,
        'total' => $total,
        'message' => $sent === $total ? 'Nieuwsbrief verzonden' : 'Niet alle e-mails konden worden verzonden'
    ]);
} else {
    // fallback
    echo json_encode(['success' => true, 'message' => 'Nieuwsbrief verzonden']);
}

exit;
?>