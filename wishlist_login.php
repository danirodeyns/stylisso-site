<?php
session_start();

// Check of gebruiker ingelogd is
if (isset($_SESSION['user_id'])) {
    // Ingelogd → naar wishlist
    header("Location: wishlist.html");
    exit;
} else {
    // Niet ingelogd → naar login/registreren
    header("Location: login_registreren.html");
    exit;
}
?>