<?php
// csrf.php
session_start();

// Token genereren (indien nog niet aanwezig)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Geeft het CSRF-token terug
 */
function csrf_token() {
    return $_SESSION['csrf_token'];
}

/**
 * Controleert of het CSRF-token geldig is
 */
function csrf_validate() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Ongeldig token → direct stoppen
        http_response_code(403);
        die("Invalid CSRF token.");
    }
}

// Als dit bestand rechtstreeks aangeroepen wordt (bijv. via fetch("csrf.php"))
if (basename($_SERVER['SCRIPT_FILENAME']) === 'csrf.php') {
    header('Content-Type: application/json');
    echo json_encode(['csrf_token' => csrf_token()]);
    exit;
}