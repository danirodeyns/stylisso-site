<?php
include 'translations.php';
header('Content-Type: application/json');

// Haal IP van gebruiker
$ip = $_SERVER['REMOTE_ADDR'];

// Gebruik een gratis API om country te detecteren (ip-api.com)
$countryCode = null;
$response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode");
if ($response) {
    $data = json_decode($response, true);
    if ($data && isset($data['countryCode'])) {
        $countryCode = strtolower($data['countryCode']); // bv. "be", "nl", "fr"
    }
}

// fallback als iets misgaat
if (!$countryCode) $countryCode = 'be';

echo json_encode(['country' => $countryCode]);
?>