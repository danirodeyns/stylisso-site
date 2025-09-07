<?php
// get_invoice.php

// Basiscontrole: is order_id en date aanwezig en geldig?
if (!isset($_GET['order_id']) || !isset($_GET['date'])) {
    http_response_code(400);
    echo "Ongeldige parameters.";
    exit;
}

$order_id = intval($_GET['order_id']);
$date = preg_replace('/[^0-9\-]/', '', $_GET['date']); // enkel cijfers en streepjes

// Pad naar de folder waar je PDF's opslaat
$pdfDir = __DIR__ . '/invoices/';
$pdfFile = $pdfDir . $date . '-' . $order_id . '.pdf';

// Controleren of bestand bestaat
if (!file_exists($pdfFile)) {
    http_response_code(404);
    echo "Factuur niet gevonden.";
    exit;
}

// Stuur de juiste headers
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($pdfFile) . '"');
header('Content-Length: ' . filesize($pdfFile));

// Stuur het PDF-bestand naar de browser
readfile($pdfFile);
exit;
?>