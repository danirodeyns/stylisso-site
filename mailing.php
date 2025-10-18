<?php
session_start();
header('Content-Type: application/json');

include 'translations.php';
include 'csrf.php';
csrf_validate();

// ==================================
// GLOBALE MAIL INSTELLINGEN
// ==================================
define('MAIL_FROM', 'no-reply@stylisso.be');
define('MAIL_NAME', 'Stylisso');

// ==================================
// Algemene mailfunctie zonder bijlage
// ==================================
function sendMail($to, $subject, $message) {
    $headers  = "From: " . MAIL_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    return mail($to, $subject, $message, $headers);
}

// ==================================
// Algemene mailfunctie met bijlage (PDF)
// ==================================
function sendMailWithAttachment($to, $subject, $message, $filePath, $filename) {
    $boundary = md5(time());

    $headers  = "From: " . MAIL_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $message . "\r\n\r\n";

    $fileData = chunk_split(base64_encode(file_get_contents($filePath)));
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
    $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= $fileData . "\r\n\r\n";
    $body .= "--{$boundary}--";

    return mail($to, $subject, $body, $headers);
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
    // create_credit_nota.php
    // ================================
    case 'order_credit_nota':
        $email = $_POST['email'] ?? null;
        $lang  = $_POST['lang'] ?? 'be-nl';
        $filename = $_POST['filename'] ?? null;

        if (!$email || !$filename) {
            echo json_encode(['error' => 'Ontbrekende gegevens voor creditnota-mail']);
            exit;
        }

        $subject = t('credit_nota_subject', $lang);
        $message = t('credit_nota_message', $lang);

        $filePath = __DIR__ . '/credit_notes/' . basename($filename);

        if (!file_exists($filePath)) {
            echo json_encode(['error' => 'Creditnota bestand niet gevonden']);
            exit;
        }

        // --- Mail naar klant ---
        $sent = sendMailWithAttachment($email, $subject, $message, $filePath, $filename);

        // --- Extra mail naar vast mailadres met enkel bijlage ---
        $fixedEmail = 'vast-email@domein.be'; // later aanpasbaar
        sendMailWithAttachment($fixedEmail, $filename, '', $filePath, $filename);

        echo json_encode(['success' => $sent]);
        break;

    // ================================
    // create_invoice.php
    // ================================
    case 'order_invoice':
        $email = $_POST['email'] ?? null;
        $lang  = $_POST['lang'] ?? 'be-nl';
        $filename = $_POST['filename'] ?? null;

        if (!$email || !$filename) {
            echo json_encode(['error' => 'Ontbrekende gegevens voor factuurmail']);
            exit;
        }

        $subject = t('invoice_subject', $lang);
        $message = t('invoice_message', $lang);

        $filePath = __DIR__ . '/invoices/' . basename($filename);

        if (!file_exists($filePath)) {
            echo json_encode(['error' => 'Factuurbestand niet gevonden']);
            exit;
        }

        // --- Mail naar klant ---
        $sent = sendMailWithAttachment($email, $subject, $message, $filePath, $filename);

        // --- Extra mail naar vast mailadres met enkel bijlage ---
        $fixedEmail = 'vast-email@domein.be'; // later aanpasbaar
        sendMailWithAttachment($fixedEmail, $filename, '', $filePath, $filename);

        echo json_encode(['success' => $sent]);
        break;

    // ================================
    // create_own_voucher_invoice.php
    // ================================
    case 'own_voucher_invoice':
        $lang     = $_POST['lang'] ?? 'be-nl';
        $filename = $_POST['filename'] ?? null;

        if (!$filename) {
            echo json_encode(['error' => 'Ontbrekende gegevens voor interne voucherfactuur-mail']);
            exit;
        }

        $subject = t('voucher_invoice_subject', $lang);
        $message = t('voucher_invoice_message', $lang);

        $filePath = __DIR__ . '/invoices/' . basename($filename);

        if (!file_exists($filePath)) {
            echo json_encode(['error' => 'Voucherfactuur bestand niet gevonden']);
            exit;
        }

        // --- Mail naar vast mailadres ---
        $fixedEmail = 'vast-email@domein.be';
        $sent = sendMailWithAttachment($fixedEmail, $subject, $message, $filePath, $filename);

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
    // send_newsletter.php
    // ================================
    case 'newsletter':
        $emailsJson = $_POST['emails'] ?? '';
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$emailsJson || !$subject || !$message) {
            echo json_encode(['error' => 'Ontbrekende nieuwsbriefgegevens']);
            exit;
        }

        $emails = json_decode($emailsJson, true);
        if (!is_array($emails) || empty($emails)) {
            echo json_encode(['error' => 'Geen geldige e-maillijst ontvangen']);
            exit;
        }

        $sentCount = 0;
        foreach ($emails as $to) {
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                if (sendMail($to, $subject, $message)) {
                    $sentCount++;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'sent' => $sentCount,
            'total' => count($emails)
        ]);
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