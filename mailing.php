<?php
session_start();
header('Content-Type: application/json');

include 'translations.php';
include 'csrf.php';
csrf_validate();

function sendMail($to, $subject, $message, $from = 'no-reply@stylisso.be', $fromName = 'Stylisso') {
    $headers = "From: {$fromName} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    return mail($to, $subject, $message, $headers);
}

$task = $_POST['task'] ?? null;

if (!$task) {
    echo json_encode(['error' => 'Geen task opgegeven']);
    exit;
}

switch ($task) {

    // ================================
    // afrekenen.php
    // ================================
    case 'voucher':
        $email = $_POST['email'] ?? null;
        $code = $_POST['code'] ?? null;
        $price = $_POST['price'] ?? null;
        $expires_at = $_POST['expires_at'] ?? null;
        $lang = $_POST['lang'] ?? 'be-nl';

        if (!$email || !$code || !$price || !$expires_at) {
            echo json_encode(['error' => t('voucher_missing_data', $lang)]);
            exit;
        }

        $subject = t('voucher_subject', $lang);
        $message = t('voucher_message', $lang, [
            '{code}' => $code,
            '{price}' => $price,
            '{expires_at}' => $expires_at
        ]);

        $sent = sendMail($email, $subject, $message);
        echo json_encode(['success' => $sent]);
        break;

    // ================================
    // delete_account.php
    // ================================
    case 'account_delete':
        $email = $_POST['email'] ?? null;
        $lang = $_POST['lang'] ?? 'be-nl';

        if (!$email) {
            echo json_encode(['error' => t('account_delete_missing_email', $lang)]);
            exit;
        }

        $subject = t('account_delete_subject', $lang);
        $message = t('account_delete_message', $lang);

        $sent = sendMail($email, $subject, $message);
        echo json_encode(['success' => $sent]);
        break;

    // ================================
    // processing_retour.php
    // ================================
    case 'retour_approved':
        $email = $_POST['email'] ?? null;
        $lang = $_POST['lang'] ?? 'be-nl';
        $approvedProductsJson = $_POST['approved_products'] ?? null;

        if (!$email || !$approvedProductsJson) {
            echo json_encode(['error' => t('retour_approved_missing_data', $lang)]);
            exit;
        }

        $approvedProducts = json_decode($approvedProductsJson, true) ?? [];

        $subject = t('retour_approved_subject', $lang);

        $message = t('retour_approved_message_intro', $lang) . "<ul>";
        foreach ($approvedProducts as $prod) {
            $message .= "<li>" . htmlspecialchars($prod['name']) . " (".t('quantity_label', $lang).": {$prod['quantity']}, ".t('price_label', $lang).": €{$prod['price']})</li>";
        }
        $message .= "</ul>" . t('retour_approved_message_outro', $lang);

        $sent = sendMail($email, $subject, $message);
        echo json_encode(['success' => $sent]);
        break;

    // ================================
    // register.php
    // ================================
    case 'welcome':
        $email = $_POST['email'] ?? null;
        $name = $_POST['name'] ?? null;
        $lang = $_POST['lang'] ?? 'be-nl';

        if (!$email || !$name) {
            echo json_encode(['error' => t('welcome_missing_data', $lang)]);
            exit;
        }

        $subject = t('welcome_subject', $lang);
        $message = t('welcome_message', $lang, ['{name}' => $name]);

        $sent = sendMail($email, $subject, $message);
        echo json_encode(['success' => $sent]);
        break;

    // ================================
    // reset_password.php
    // ================================
    case 'password_reset_success':
        $email = $_POST['email'] ?? null;
        $lang  = $_POST['lang'] ?? 'be-nl';

        if (!$email) {
            echo json_encode(['error' => t('password_reset_success_missing_email', $lang)]);
            exit;
        }

        $subject = t('password_reset_success_subject', $lang);
        $message = t('password_reset_success_message', $lang);

        $sent = sendMail($email, $subject, $message);
        echo json_encode(['success' => $sent]);
        break;

    // ================================
    // submit_returns.php
    // ================================
    case 'return_requested':
        $email = $_POST['email'] ?? null;
        $name = $_POST['name'] ?? 'Klant';
        $product_name = $_POST['product_name'] ?? '';
        $quantity = $_POST['quantity'] ?? 1;
        $reason = $_POST['reason'] ?? '';
        $lang = $_POST['lang'] ?? 'be-nl';

        if (!$email) {
            echo json_encode(['error' => t('return_requested_missing_email', $lang)]);
            exit;
        }

        $subject = t('return_requested_subject', $lang);
        $message = t('return_requested_message', $lang, [
            '{name}' => $name,
            '{product_name}' => $product_name,
            '{quantity}' => $quantity,
            '{reason}' => $reason
        ]);

        $sent = sendMail($email, $subject, $message);
        echo json_encode(['success' => $sent]);
        break;

    // ================================
    // wachtwoord vergeten.php
    // ================================
    case 'password_reset_link':
        $email = $_POST['email'] ?? null;
        $resetLink = $_POST['reset_link'] ?? null;
        $lang = $_POST['lang'] ?? 'be-nl';

        if (!$email || !$resetLink) {
            echo json_encode(['error' => t('password_reset_link_missing_data', $lang)]);
            exit;
        }

        $subject = t('password_reset_link_subject', $lang);
        $message = t('password_reset_link_message', $lang, ['{reset_link}' => $resetLink]);

        $sent = sendMail($email, $subject, $message);
        echo json_encode(['success' => $sent]);
        break;

    default:
        echo json_encode(['error' => t('unknown_task', $lang)]);
        exit;
}
?>