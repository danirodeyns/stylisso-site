<?php
// Vertalingen centraal in één bestand
$translations = [
    'be-nl' => [
        // afrekenen.php
        'email_subject' => 'Jouw Stylisso cadeaubon',
        'email_message' => "Bedankt voor je aankoop!\n\nJe cadeauboncode: {code}\nWaarde: €{price}\nGeldig tot: {expires_at}\n\nVeel shopplezier bij Stylisso!",
        
        // contact-mailing.php
        'contact_form_success' => 'Bedankt! Je bericht is verzonden.',
        'contact_form_error' => 'Er is een fout opgetreden. Probeer het later opnieuw.',

        // create_invoice.php
        'invoice_title' => 'Factuur',
        'order_number' => 'Ordernummer',
        'order_date' => 'Datum',
        'customer' => 'Klant',
        'email' => 'E-mail',
        'address' => 'Adres',
        'quantity' => 'Aantal',
        'product' => 'Product',
        'price_per_item' => 'Prijs per stuk',
        'total' => 'Totaal',
        'gift_voucher' => 'Cadeaubon',
        'total_to_pay' => 'Totaal te betalen',
        'status' => 'Status',
        'select_voucher_first' => 'Kies eerst een bon',
        'voucher_applied' => 'Bon toegepast',
        'voucher_redeem_error' => 'Er is een fout opgetreden bij het toepassen van de bon',

        // create_credit_nota.php
        'credit_note_title' => 'Creditnota',
        'credit_note_number' => 'Creditnota nummer',
        'credit_note_date' => 'Datum creditnota',
        'credit_note_total' => 'Totaal gecrediteerd',
        'credit_reason' => 'Reden van creditnota',
        'credit_against_invoice' => 'Creditnota tegen factuur',
        'total_to_refund' => 'Totaal terug te betalen',

        // delete_account.php
        'account_delete_subject' => 'Bevestiging accountverwijdering',
        'account_delete_message' => "Beste,\n\nJe account en persoonsgegevens zijn verwijderd uit ons systeem. Bestellingen en retourgegevens blijven bewaard voor de wettelijke boekhoudtermijn.\n\nMet vriendelijke groet,\nStylisso",

        // redeem_voucher.php
        'voucher_login_required' => 'Je moet ingelogd zijn om een bon in te wisselen.',
        'voucher_invalid_code' => 'Voer een geldige boncode in.',
        'voucher_not_found_or_expired' => 'Deze boncode bestaat niet, is volledig gebruikt of is verlopen.',
        'voucher_already_linked' => 'Deze cadeaubon is al aan jouw account gekoppeld.',
        'voucher_link_success' => 'Cadeaubon succesvol gekoppeld aan je account! Waarde:',
        'voucher_already_claimed' => 'Deze cadeaubon is al door een andere gebruiker geclaimd.',

        // reviews-mailing.php
        'review_success' => 'Bedankt voor je review!',
        'review_error'   => 'Er is iets misgegaan bij het verzenden van je review.',

        // wachtwoord vergeten.php
        'invalid_email' => 'Ongeldig e-mailadres.',
        'db_prepare_failed' => 'Database voorbereiding mislukt',
        'email_not_found' => 'Dit e-mailadres is niet bij ons bekend.',
        'password_reset_subject' => 'Wachtwoord resetten - Stylisso',
        'password_reset_message' => "Hallo,\n\nKlik op onderstaande link om je wachtwoord te resetten:\n{resetLink}\n\nDeze link verloopt over {expires}.",
        'password_reset_sent' => 'Er is een e-mail verstuurd met instructies om je wachtwoord te resetten.',
        'password_reset_failed' => 'Er ging iets mis bij het verzenden van de e-mail.',
        'hour' => 'uur'
    ],
    'be-fr' => [
        // afrekenen.php
        'email_subject' => 'Ton bon Stylisso',
        'email_message' => "Merci pour votre achat!\n\nVotre code cadeau: {code}\nValeur: €{price}\nValable jusqu'au: {expires_at}\n\nBon shopping sur Stylisso!",
        
        // contact-mailing.php
        'contact_form_success' => 'Merci ! Votre message a été envoyé.',
        'contact_form_error' => 'Une erreur est survenue. Veuillez réessayer plus tard.',

        // create_invoice.php
        'invoice_title' => 'Facture',
        'order_number' => 'Numéro de commande',
        'order_date' => 'Date',
        'customer' => 'Client',
        'email' => 'E-mail',
        'address' => 'Adresse',
        'quantity' => 'Quantité',
        'product' => 'Produit',
        'price_per_item' => 'Prix unitaire',
        'total' => 'Total',
        'gift_voucher' => 'Bon cadeau',
        'total_to_pay' => 'Total à payer',
        'status' => 'Statut',
        'select_voucher_first' => 'Sélectionnez d’abord un bon',
        'voucher_applied' => 'Bon appliqué',
        'voucher_redeem_error' => 'Une erreur est survenue lors de l’application du bon',

        // create_credit_nota.php
        'credit_note_title' => 'Note de crédit',
        'credit_note_number' => 'Numéro de la note de crédit',
        'credit_note_date' => 'Date de la note de crédit',
        'credit_note_total' => 'Montant total crédité',
        'credit_reason' => 'Raison de la note de crédit',
        'credit_against_invoice' => 'Note de crédit relative à la facture',
        'total_to_refund' => 'Montant total à rembourser',
        
        // delete_account.php
        'account_delete_subject' => "Confirmation de suppression de compte",
        'account_delete_message' => "Bonjour,\n\nVotre compte et vos données personnelles ont été supprimés de notre système. Les commandes et données de retour restent conservées conformément au délai légal de comptabilité.\n\nCordialement,\nStylisso",

        // redeem_voucher.php
        'voucher_login_required' => 'Vous devez être connecté pour utiliser un bon.',
        'voucher_invalid_code' => 'Veuillez saisir un code de bon valide.',
        'voucher_not_found_or_expired' => "Ce code n'existe pas, a été entièrement utilisé ou a expiré.",
        'voucher_already_linked' => 'Ce bon est déjà associé à votre compte.',
        'voucher_link_success' => 'Bon cadeau associé avec succès à votre compte ! Valeur :',
        'voucher_already_claimed' => 'Ce bon cadeau a déjà été utilisé par un autre utilisateur.',

        // wachtwoord vergeten.php
        'invalid_email' => "Adresse e-mail invalide.",
        'db_prepare_failed' => 'Préparation de la base de données échouée',
        'email_not_found' => "Cet e-mail n'est pas connu chez nous.",
        'password_reset_subject' => 'Réinitialiser le mot de passe - Stylisso',
        'password_reset_message' => "Bonjour,\n\nCliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :\n{resetLink}\n\nCe lien expire dans {expires}.",
        'password_reset_sent' => 'Un e-mail contenant les instructions pour réinitialiser votre mot de passe a été envoyé.',
        'password_reset_failed' => "Une erreur s'est produite lors de l'envoi de l'e-mail.",
        'hour' => 'heure'
    ],
    'be-en' => [
        // afrekenen.php
        'email_subject' => 'Your Stylisso gift voucher',
        'email_message' => "Thank you for your purchase!\n\nYour gift voucher code: {code}\nValue: €{price}\nValid until: {expires_at}\n\nHappy shopping at Stylisso!",
        
        // contact-mailing.php
        'contact_form_success' => 'Thank you! Your message has been sent.',
        'contact_form_error' => 'An error occurred. Please try again later.',

        // create_invoice.php
        'invoice_title' => 'Invoice',
        'order_number' => 'Order Number',
        'order_date' => 'Date',
        'customer' => 'Customer',
        'email' => 'E-mail',
        'address' => 'Address',
        'quantity' => 'Quantity',
        'product' => 'Product',
        'price_per_item' => 'Price per item',
        'total' => 'Total',
        'gift_voucher' => 'Gift Voucher',
        'total_to_pay' => 'Total to Pay',
        'status' => 'Status',
        'select_voucher_first' => 'Select a voucher first',
        'voucher_applied' => 'Voucher applied',
        'voucher_redeem_error' => 'An error occurred while applying the voucher',

        // create_credit_nota.php
        'credit_note_title' => 'Credit Note',
        'credit_note_number' => 'Credit Note Number',
        'credit_note_date' => 'Credit Note Date',
        'credit_note_total' => 'Total Credited',
        'credit_reason' => 'Reason for Credit Note',
        'credit_against_invoice' => 'Credit Note against Invoice',
        'total_to_refund' => 'Total to Refund',
        
        // delete_account.php
        'account_delete_subject' => "Account deletion confirmation",
        'account_delete_message' => "Dear customer,\n\nYour account and personal data have been deleted from our system. Orders and return data are retained for the statutory accounting period.\n\nBest regards,\nStylisso",

        // redeem_voucher.php
        'voucher_login_required' => 'You must be logged in to redeem a voucher.',
        'voucher_invalid_code' => 'Please enter a valid voucher code.',
        'voucher_not_found_or_expired' => 'This voucher code does not exist, is fully used, or has expired.',
        'voucher_already_linked' => 'This gift voucher is already linked to your account.',
        'voucher_link_success' => 'Gift voucher successfully linked to your account! Value:',
        'voucher_already_claimed' => 'This voucher has already been claimed by another user.',

        // wachtwoord vergeten.php
        'invalid_email' => 'Invalid email address.',
        'db_prepare_failed' => 'Database preparation failed',
        'email_not_found' => 'This email address is not known to us.',
        'password_reset_subject' => 'Reset your password - Stylisso',
        'password_reset_message' => "Hello,\n\nClick the link below to reset your password:\n{resetLink}\n\nThis link expires in {expires}.",
        'password_reset_sent' => 'An email with instructions to reset your password has been sent.',
        'password_reset_failed' => 'Something went wrong sending the email.',
        'hour' => 'hour'
    ]
];

// Helper functie om een vertaling op te halen
function t($key) {
    global $translations;
    $lang = $_COOKIE['siteLanguage'] ?? 'be-nl'; // cookie bepaalt taal, fallback be-nl
    return $translations[$lang][$key] ?? $key;
}
?>