<?php
// Vertalingen centraal in één bestand
$translations = [
    'be-nl' => [
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

        // get_order_retour.php
        'order_not_found' => 'Order niet gevonden',

        // mailing.php
        'unknown_task' => 'Onbekende task',
        'voucher_subject' => 'Je Stylisso voucher',
        'voucher_message' => 'Bedankt voor je aankoop!<br>Hier is je voucher: <strong>{code}</strong><br>Waarde: €{price}<br>Geldig tot: {expires_at}',
        'voucher_missing_data' => 'Ontbrekende data voor voucher mail',
        'account_delete_subject' => 'Je account is verwijderd',
        'account_delete_message' => 'Je account is succesvol verwijderd.',
        'account_delete_missing_email' => 'Ontbrekende email voor account delete mail',
        'retour_approved_subject' => 'Je retour is goedgekeurd',
        'retour_approved_message_intro' => 'Beste klant,<br>De volgende producten van uw retour zijn goedgekeurd:',
        'retour_approved_message_outro' => '<br>Met vriendelijke groet,<br>Stylisso',
        'retour_approved_missing_data' => 'Ontbrekende data voor retour mail',
        'quantity_label' => 'Aantal',
        'price_label' => 'Prijs',
        'welcome_subject' => 'Welkom bij Stylisso',
        'welcome_message' => 'Beste {name},<br>Welkom bij Stylisso! We zijn blij dat je je hebt geregistreerd.<br>Veel plezier met winkelen op onze website!<br>Met vriendelijke groet,<br>Stylisso',
        'welcome_missing_data' => 'Ontbrekende data voor welkom mail',
        'password_reset_success_subject' => 'Wachtwoord succesvol aangepast',
        'password_reset_success_message' => 'Beste klant,<br>Uw wachtwoord is succesvol aangepast.<br>U kunt nu inloggen met uw nieuwe wachtwoord.<br>Met vriendelijke groet,<br>Stylisso',
        'password_reset_success_missing_email' => 'Ontbrekende data voor wachtwoord-reset mail',
        'return_requested_subject' => 'Retouraanvraag ontvangen',
        'return_requested_message' => 'Beste {name},<br>We hebben je retouraanvraag voor het product <strong>{product_name}</strong> (aantal: {quantity}) ontvangen.<br>Reden: {reason}<br>We proberen je retour zo snel mogelijk te verwerken.<br>Met vriendelijke groet,<br>Stylisso',
        'return_requested_missing_email' => 'Geen e-mail opgegeven',
        'password_reset_link_subject' => 'Reset je Stylisso wachtwoord',
        'password_reset_link_message' => 'Beste klant,<br>Er is gevraagd om je wachtwoord te resetten.<br>Klik op onderstaande link om je wachtwoord opnieuw in te stellen:<br><a href="{reset_link}">{reset_link}</a><br>Deze link is 1 uur geldig.<br>Met vriendelijke groet,<br>Stylisso',
        'password_reset_link_missing_data' => 'Ontbrekende data voor wachtwoord reset mail',

        // processing_retours.php
        'processing_retours_alert_not_logged_in' => 'Je moet ingelogd zijn om retouren te verwerken.',
        'processing_retours_alert_no_access' => 'Je hebt geen toegang om retouren te verwerken.',
        'processing_retours_alert_success' => 'Retouren succesvol verwerkt.',

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
        'password_reset_sent' => 'Er is een e-mail verstuurd met instructies om je wachtwoord te resetten.',
    ],
    'be-fr' => [
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

        // get_order_retour.php
        'order_not_found' => 'Commande non trouvée',

        // mailing.php
        'unknown_task' => 'Tâche inconnue',
        'voucher_subject' => 'Votre bon Stylisso',
        'voucher_message' => 'Merci pour votre achat !<br>Voici votre bon : <strong>{code}</strong><br>Valeur : €{price}<br>Valable jusqu\'au : {expires_at}',
        'voucher_missing_data' => 'Données manquantes pour le mail du bon',
        'account_delete_subject' => 'Votre compte a été supprimé',
        'account_delete_message' => 'Votre compte a été supprimé avec succès.',
        'account_delete_missing_email' => 'Email manquant pour le mail de suppression de compte',
        'retour_approved_subject' => 'Votre retour a été approuvé',
        'retour_approved_message_intro' => 'Cher client,<br>Les produits suivants de votre retour ont été approuvés :',
        'retour_approved_message_outro' => '<br>Cordialement,<br>Stylisso',
        'retour_approved_missing_data' => 'Données manquantes pour le mail de retour',
        'quantity_label' => 'Quantité',
        'price_label' => 'Prix',
        'welcome_subject' => 'Bienvenue chez Stylisso',
        'welcome_message' => 'Cher {name},<br>Bienvenue chez Stylisso ! Nous sommes ravis que vous vous soyez inscrit.<br>Bon shopping sur notre site !<br>Cordialement,<br>Stylisso',
        'welcome_missing_data' => 'Données manquantes pour le mail de bienvenue',
        'password_reset_success_subject' => 'Mot de passe modifié avec succès',
        'password_reset_success_message' => 'Cher client,<br>Votre mot de passe a été modifié avec succès.<br>Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.<br>Cordialement,<br>Stylisso',
        'password_reset_success_missing_email' => 'Données manquantes pour le mail de réinitialisation de mot de passe',
        'return_requested_subject' => 'Demande de retour reçue',
        'return_requested_message' => 'Cher {name},<br>Nous avons reçu votre demande de retour pour le produit <strong>{product_name}</strong> (quantité : {quantity}).<br>Raison : {reason}<br>Nous traiterons votre retour dès que possible.<br>Cordialement,<br>Stylisso',
        'return_requested_missing_email' => 'Aucun email fourni',
        'password_reset_link_subject' => 'Réinitialisez votre mot de passe Stylisso',
        'password_reset_link_message' => 'Cher client,<br>Une demande de réinitialisation de votre mot de passe a été effectuée.<br>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :<br><a href="{reset_link}">{reset_link}</a><br>Ce lien est valable 1 heure.<br>Cordialement,<br>Stylisso',
        'password_reset_link_missing_data' => 'Données manquantes pour le mail de réinitialisation de mot de passe',

        // processing_retours.php
        'script_processing_retours_alert_not_logged_in' => 'Vous devez être connecté pour traiter les retours.',
        'script_processing_retours_alert_no_access' => 'Vous n\'avez pas accès pour traiter les retours.',
        'script_processing_retours_alert_success' => 'Retours traités avec succès.',

        // redeem_voucher.php
        'voucher_login_required' => 'Vous devez être connecté pour utiliser un bon.',
        'voucher_invalid_code' => 'Veuillez saisir un code de bon valide.',
        'voucher_not_found_or_expired' => "Ce code n'existe pas, a été entièrement utilisé ou a expiré.",
        'voucher_already_linked' => 'Ce bon est déjà associé à votre compte.',
        'voucher_link_success' => 'Bon cadeau associé avec succès à votre compte ! Valeur :',
        'voucher_already_claimed' => 'Ce bon cadeau a déjà été utilisé par un autre utilisateur.',

        // reviews-mailing.php
        'review_success' => 'Merci pour votre avis !',
        'review_error'   => 'Une erreur s’est produite lors de l’envoi de votre avis.',

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

        // get_order_retour.php
        'order_not_found' => 'Order not found',

        // mailing.php
        'unknown_task' => 'Unknown task',
        'voucher_subject' => 'Your Stylisso voucher',
        'voucher_message' => 'Thank you for your purchase!<br>Here is your voucher: <strong>{code}</strong><br>Value: €{price}<br>Valid until: {expires_at}',
        'voucher_missing_data' => 'Missing data for voucher mail',
        'account_delete_subject' => 'Your account has been deleted',
        'account_delete_message' => 'Your account has been successfully deleted.',
        'account_delete_missing_email' => 'Missing email for account delete mail',
        'retour_approved_subject' => 'Your return has been approved',
        'retour_approved_message_intro' => 'Dear customer,<br>The following products from your return have been approved:',
        'retour_approved_message_outro' => '<br>Best regards,<br>Stylisso',
        'retour_approved_missing_data' => 'Missing data for return mail',
        'quantity_label' => 'Quantity',
        'price_label' => 'Price',
        'welcome_subject' => 'Welcome to Stylisso',
        'welcome_message' => 'Dear {name},<br>Welcome to Stylisso! We are glad you registered.<br>Enjoy shopping on our website!<br>Best regards,<br>Stylisso',
        'welcome_missing_data' => 'Missing data for welcome mail',
        'password_reset_success_subject' => 'Password successfully changed',
        'password_reset_success_message' => 'Dear customer,<br>Your password has been successfully changed.<br>You can now log in with your new password.<br>Best regards,<br>Stylisso',
        'password_reset_success_missing_email' => 'Missing data for password reset mail',
        'return_requested_subject' => 'Return request received',
        'return_requested_message' => 'Dear {name},<br>We have received your return request for the product <strong>{product_name}</strong> (quantity: {quantity}).<br>Reason: {reason}<br>We will process your return as soon as possible.<br>Best regards,<br>Stylisso',
        'return_requested_missing_email' => 'No email provided',
        'password_reset_link_subject' => 'Reset your Stylisso password',
        'password_reset_link_message' => 'Dear customer,<br>A request has been made to reset your password.<br>Click the link below to reset your password:<br><a href="{reset_link}">{reset_link}</a><br>This link is valid for 1 hour.<br>Best regards,<br>Stylisso',
        'password_reset_link_missing_data' => 'Missing data for password reset mail',

        // processing_retours.php
        'script_processing_retours_alert_not_logged_in' => 'You must be logged in to process returns.',
        'script_processing_retours_alert_no_access' => 'You do not have access to process returns.',
        'script_processing_retours_alert_success' => 'Returns processed successfully.',

        // redeem_voucher.php
        'voucher_login_required' => 'You must be logged in to redeem a voucher.',
        'voucher_invalid_code' => 'Please enter a valid voucher code.',
        'voucher_not_found_or_expired' => 'This voucher code does not exist, is fully used, or has expired.',
        'voucher_already_linked' => 'This gift voucher is already linked to your account.',
        'voucher_link_success' => 'Gift voucher successfully linked to your account! Value:',
        'voucher_already_claimed' => 'This voucher has already been claimed by another user.',

        // reviews-mailing.php
        'review_success' => 'Thank you for your review!',
        'review_error'   => 'There was an error submitting your review.',

        // wachtwoord vergeten.php
        'invalid_email' => 'Invalid email address.',
        'db_prepare_failed' => 'Database preparation failed',
        'email_not_found' => 'This email address is not known to us.',
        'password_reset_subject' => 'Reset your password - Stylisso',
        'password_reset_message' => "Hello,\n\nClick the link below to reset your password:\n{resetLink}\n\nThis link expires in {expires}.",
        'password_reset_sent' => 'An email with instructions to reset your password has been sent.',
        'password_reset_failed' => 'Something went wrong sending the email.',
        'hour' => 'hour'
    ],
    'be-de' => [
        // contact-mailing.php
        'contact_form_success' => 'Danke! Ihre Nachricht wurde gesendet.',
        'contact_form_error' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.',

        // create_invoice.php
        'invoice_title' => 'Rechnung',
        'order_number' => 'Bestellnummer',
        'order_date' => 'Datum',
        'customer' => 'Kunde',
        'email' => 'E-Mail',
        'address' => 'Adresse',
        'quantity' => 'Anzahl',
        'product' => 'Produkt',
        'price_per_item' => 'Stückpreis',
        'total' => 'Gesamt',
        'gift_voucher' => 'Geschenkgutschein',
        'total_to_pay' => 'Zu zahlender Gesamtbetrag',
        'status' => 'Status',
        'select_voucher_first' => 'Bitte zuerst einen Gutschein auswählen',
        'voucher_applied' => 'Gutschein angewendet',
        'voucher_redeem_error' => 'Beim Anwenden des Gutscheins ist ein Fehler aufgetreten',

        // create_credit_nota.php
        'credit_note_title' => 'Gutschrift',
        'credit_note_number' => 'Gutschriftsnummer',
        'credit_note_date' => 'Datum der Gutschrift',
        'credit_note_total' => 'Gesamterstattungsbetrag',
        'credit_reason' => 'Grund der Gutschrift',
        'credit_against_invoice' => 'Gutschrift zu Rechnung',
        'total_to_refund' => 'Gesamtbetrag zur Rückerstattung',

        // get_order_retour.php
        'order_not_found' => 'Bestellung nicht gefunden',

        // mailing.php
        'unknown_task' => 'Unbekannte Aufgabe',
        'voucher_subject' => 'Ihr Stylisso Gutschein',
        'voucher_message' => 'Vielen Dank für Ihren Einkauf!<br>Hier ist Ihr Gutschein: <strong>{code}</strong><br>Wert: €{price}<br>Gültig bis: {expires_at}',
        'voucher_missing_data' => 'Fehlende Daten für Gutschein-Mail',
        'account_delete_subject' => 'Ihr Konto wurde gelöscht',
        'account_delete_message' => 'Ihr Konto wurde erfolgreich gelöscht.',
        'account_delete_missing_email' => 'Fehlende E-Mail für Kontolöschungs-Mail',
        'retour_approved_subject' => 'Ihre Rücksendung wurde genehmigt',
        'retour_approved_message_intro' => 'Sehr geehrter Kunde,<br>Die folgenden Produkte Ihrer Rücksendung wurden genehmigt:',
        'retour_approved_message_outro' => '<br>Mit freundlichen Grüßen,<br>Stylisso',
        'retour_approved_missing_data' => 'Fehlende Daten für Rücksendungs-Mail',
        'quantity_label' => 'Menge',
        'price_label' => 'Preis',
        'welcome_subject' => 'Willkommen bei Stylisso',
        'welcome_message' => 'Sehr geehrter {name},<br>Willkommen bei Stylisso! Wir freuen uns, dass Sie sich registriert haben.<br>Viel Spaß beim Einkaufen auf unserer Website!<br>Mit freundlichen Grüßen,<br>Stylisso',
        'welcome_missing_data' => 'Fehlende Daten für Willkommens-Mail',
        'password_reset_success_subject' => 'Passwort erfolgreich geändert',
        'password_reset_success_message' => 'Sehr geehrter Kunde,<br>Ihr Passwort wurde erfolgreich geändert.<br>Sie können sich nun mit Ihrem neuen Passwort anmelden.<br>Mit freundlichen Grüßen,<br>Stylisso',
        'password_reset_success_missing_email' => 'Fehlende Daten für Passwort-Reset-Mail',
        'return_requested_subject' => 'Rücksendeanfrage erhalten',
        'return_requested_message' => 'Sehr geehrter {name},<br>Wir haben Ihre Rücksendeanfrage für das Produkt <strong>{product_name}</strong> (Menge: {quantity}) erhalten.<br>Grund: {reason}<br>Wir werden Ihre Rücksendung so schnell wie möglich bearbeiten.<br>Mit freundlichen Grüßen,<br>Stylisso',
        'return_requested_missing_email' => 'Keine E-Mail angegeben',
        'password_reset_link_subject' => 'Setzen Sie Ihr Stylisso Passwort zurück',
        'password_reset_link_message' => 'Sehr geehrter Kunde,<br>Es wurde eine Anfrage zur Zurücksetzung Ihres Passworts gestellt.<br>Klicken Sie auf den untenstehenden Link, um Ihr Passwort zurückzusetzen:<br><a href="{reset_link}">{reset_link}</a><br>Dieser Link ist 1 Stunde gültig.<br>Mit freundlichen Grüßen,<br>Stylisso',
        'password_reset_link_missing_data' => 'Fehlende Daten für Passwort-Reset-Mail',

        // processing_retours.php
        'processing_retours_alert_not_logged_in' => 'Sie müssen eingeloggt sein, um Rücksendungen zu bearbeiten.',
        'processing_retours_alert_no_access' => 'Sie haben keinen Zugriff, um Rücksendungen zu bearbeiten.',
        'processing_retours_alert_success' => 'Rücksendungen erfolgreich bearbeitet.',

        // redeem_voucher.php
        'voucher_login_required' => 'Sie müssen eingeloggt sein, um einen Gutschein einzulösen.',
        'voucher_invalid_code' => 'Bitte geben Sie einen gültigen Gutscheincode ein.',
        'voucher_not_found_or_expired' => 'Dieser Gutscheincode existiert nicht, wurde vollständig verwendet oder ist abgelaufen.',
        'voucher_already_linked' => 'Dieser Geschenkgutschein ist bereits mit Ihrem Konto verknüpft.',
        'voucher_link_success' => 'Geschenkgutschein erfolgreich mit Ihrem Konto verknüpft! Wert:',
        'voucher_already_claimed' => 'Dieser Geschenkgutschein wurde bereits von einem anderen Nutzer eingelöst.',

        // reviews-mailing.php
        'review_success' => 'Danke für Ihre Bewertung!',
        'review_error'   => 'Beim Senden Ihrer Bewertung ist ein Fehler aufgetreten.',

        // wachtwoord vergeten.php
        'invalid_email' => 'Ungültige E-Mail-Adresse.',
        'db_prepare_failed' => 'Datenbankvorbereitung fehlgeschlagen',
        'email_not_found' => 'Diese E-Mail-Adresse ist uns nicht bekannt.',
        'password_reset_subject' => 'Passwort zurücksetzen - Stylisso',
        'password_reset_message' => "Hallo,\n\nKlicken Sie auf den untenstehenden Link, um Ihr Passwort zurückzusetzen:\n{resetLink}\n\nDieser Link läuft in {expires} ab.",
        'password_reset_sent' => 'Eine E-Mail mit Anweisungen zum Zurücksetzen Ihres Passworts wurde gesendet.',
        'password_reset_failed' => 'Beim Senden der E-Mail ist ein Fehler aufgetreten.',
        'hour' => 'Stunde'
    ]
];

// Helper functie om een vertaling op te halen
function t($key) {
    global $translations;
    $lang = $_COOKIE['siteLanguage'] ?? 'be-nl'; // cookie bepaalt taal, fallback be-nl
    return $translations[$lang][$key] ?? $key;
}
?>