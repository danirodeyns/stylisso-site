<?php
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ================================
// SMTP-configuratie
// ================================
define('MAIL_FROM', 'no-reply@stylisso.be');
define('MAIL_NAME', 'Stylisso');
define('SMTP_HOST', 'mail.stylisso.be');
define('SMTP_PORT', 465);
define('SMTP_USER', 'no-reply@stylisso.be');
define('SMTP_PASS', 'teSkik-ricrun-8vakfy');
define('SMTP_SECURE', 'ssl');

// ================================
// Helper functie PHPMailer instance
// ================================
function getMailInstance() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;
    $mail->setFrom(MAIL_FROM, MAIL_NAME);
    $mail->isHTML(true);
    return $mail;
}

// ================================
// Basis mail functie
// ================================
function sendMail($to, $subject, $body, $isHTML = true) {
    try {
        $mail = getMailInstance();
        $mail->addAddress($to);
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail fout naar $to: " . $mail->ErrorInfo);
        return false;
    }
}

// ================================
// Mail met bijlage
// ================================
function sendMailWithAttachment($to, $subject, $body, $filePath, $filename) {
    try {
        $mail = getMailInstance();
        $mail->addAddress($to);
        $mail->addAttachment($filePath, $filename);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail met attachment fout naar $to: " . $mail->ErrorInfo);
        return false;
    }
}

// ================================
// afrekenen.php
// ================================
function sendVoucherMail($email, $code, $price, $expires_at, $lang = 'be-nl') {
    $subject = t('voucher_subject', $lang);
    $message = t('voucher_message', $lang, [
        '{code}' => $code,
        '{price}' => $price,
        '{expires_at}' => $expires_at
    ]);
    return sendMail($email, $subject, $message);
}

// ================================
// create_credit_nota.php
// ================================
function sendCreditNotaMail($email, $name = '', $order_id = '', $items = [], $total_credit = 0, $filename = null, $lang = 'be-nl') {
    $subject = t('credit_nota_subject', $lang);

    // --- Maak tabel met producten/items ---
    $rows = '';
    foreach ($items as $item) {
        $productName = htmlspecialchars($item['product_name'] ?? 'Onbekend');
        $qty = intval($item['quantity'] ?? 1);
        $price = number_format(floatval($item['price']), 2, ',', '.');
        $subtotal = number_format($qty * floatval($item['price']), 2, ',', '.');

        $rows .= "
            <tr>
                <td style='padding:8px;border:1px solid #ddd;text-align:center;'>$qty</td>
                <td style='padding:8px;border:1px solid #ddd;'>$productName</td>
                <td style='padding:8px;border:1px solid #ddd;text-align:right;'>-€$price</td>
                <td style='padding:8px;border:1px solid #ddd;text-align:right;'>-€$subtotal</td>
            </tr>
        ";
    }

    $totalFormatted = number_format($total_credit, 2, ',', '.');

    // --- Opmaak HTML mail ---
    $message = "
        <div style='font-family:Arial,sans-serif;color:#333;background:#f9f9f9;padding:20px;'>
            <div style='max-width:600px;margin:auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 10px rgba(0,0,0,0.1);'>
                <h2 style='color:#222;'>" . t('credit_nota_greeting', $lang, ['{name}' => $name]) . "</h2>
                <p>" . t('credit_nota_intro', $lang, ['{order_id}' => $order_id]) . "</p>
                
                <table style='width:100%;border-collapse:collapse;margin-top:15px;'>
                    <thead>
                        <tr style='background:#eee;'>
                            <th style='padding:8px;border:1px solid #ddd;'>".t('quantity',$lang)."</th>
                            <th style='padding:8px;border:1px solid #ddd;'>".t('product',$lang)."</th>
                            <th style='padding:8px;border:1px solid #ddd;'>".t('price_per_item',$lang)."</th>
                            <th style='padding:8px;border:1px solid #ddd;'>".t('total',$lang)."</th>
                        </tr>
                    </thead>
                    <tbody>$rows</tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' style='padding:8px;border:1px solid #ddd;text-align:right;font-weight:bold;'>".t('total_to_refund',$lang)."</td>
                            <td style='padding:8px;border:1px solid #ddd;text-align:right;font-weight:bold;'>-€$totalFormatted</td>
                        </tr>
                    </tfoot>
                </table>

                <p style='margin-top:20px;'>".t('credit_nota_footer', $lang)."</p>
                <p>Met vriendelijke groeten,<br><strong>Het Stylisso Team</strong></p>
            </div>
        </div>
    ";

    // --- Versturen, eventueel met bijlage ---
    if ($filename && file_exists(__DIR__ . '/credit_notes/' . basename($filename))) {
        $filePath = __DIR__ . '/credit_notes/' . basename($filename);
        sendMailWithAttachment($email, $subject, $message, $filePath, basename($filename));
        $fixedEmail = 'test@stylisso.be';
        $fixedSubject = "[KOPIE] " . $subject;
        $fixedMessage = "Creditnota PDF bijlage";
        sendMailWithAttachment($fixedEmail, $fixedSubject, $fixedMessage, $filePath, basename($filename));
        return true;
    } else {
        return sendMail($email, $subject, $message);
    }
}

// ================================
// create_invoice.php
// ================================
function sendOrderConfirmationMail($email, $name, $order_id, $cartItems, $total_order, $lang = 'be-nl', $pdfPath = null) {
    $subject = "Bedankt voor je bestelling #$order_id";

    // --- Maak tabel met producten ---
    $rows = '';
    foreach ($cartItems as $item) {
        $productName = htmlspecialchars($item['name'] ?? 'Onbekend');
        $maat = htmlspecialchars($item['maat'] ?? '-');
        $qty = intval($item['quantity'] ?? 1);
        $price = number_format(floatval($item['price']), 2, ',', '.');
        $subtotal = number_format($qty * floatval($item['price']), 2, ',', '.');

        $rows .= "
            <tr>
                <td style='padding:8px;border:1px solid #ddd;'>$productName</td>
                <td style='padding:8px;border:1px solid #ddd;'>$maat</td>
                <td style='padding:8px;border:1px solid #ddd;text-align:center;'>$qty</td>
                <td style='padding:8px;border:1px solid #ddd;text-align:right;'>€$price</td>
                <td style='padding:8px;border:1px solid #ddd;text-align:right;'>€$subtotal</td>
            </tr>
        ";
    }

    $totalFormatted = number_format($total_order, 2, ',', '.');

    // --- Opmaak HTML mail ---
    $message = "
        <div style='font-family:Arial,sans-serif;color:#333;background:#f9f9f9;padding:20px;'>
            <div style='max-width:600px;margin:auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 10px rgba(0,0,0,0.1);'>
                <h2 style='color:#222;'>Bedankt voor je bestelling, $name!</h2>
                <p>We hebben je bestelling <strong>#$order_id</strong> goed ontvangen.</p>
                
                <table style='width:100%;border-collapse:collapse;margin-top:15px;'>
                    <thead>
                        <tr style='background:#eee;'>
                            <th style='padding:8px;border:1px solid #ddd;'>Product</th>
                            <th style='padding:8px;border:1px solid #ddd;'>Maat</th>
                            <th style='padding:8px;border:1px solid #ddd;'>Aantal</th>
                            <th style='padding:8px;border:1px solid #ddd;'>Prijs</th>
                            <th style='padding:8px;border:1px solid #ddd;'>Subtotaal</th>
                        </tr>
                    </thead>
                    <tbody>$rows</tbody>
                    <tfoot>
                        <tr>
                            <td colspan='4' style='padding:8px;border:1px solid #ddd;text-align:right;font-weight:bold;'>Totaal</td>
                            <td style='padding:8px;border:1px solid #ddd;text-align:right;font-weight:bold;'>€$totalFormatted</td>
                        </tr>
                    </tfoot>
                </table>

                <p style='margin-top:20px;'>We sturen je een update zodra je bestelling verzonden wordt.</p>
                <p>Met vriendelijke groeten,<br><strong>Het Stylisso Team</strong></p>
            </div>
        </div>
    ";

    // --- Versturen, eventueel met bijlage ---
    if ($pdfPath && file_exists($pdfPath)) {
        sendMailWithAttachment($email, $subject, $message, $pdfPath, basename($pdfPath));
        $fixedEmail = 'test@stylisso.be';
        $fixedSubject = "[KOPIE] " . $subject;
        $fixedMessage = "Order PDF bijlage";
        sendMailWithAttachment($fixedEmail, $fixedSubject, $fixedMessage, $pdfPath, basename($pdfPath));
        return true;
    } else {
        return sendMail($email, $subject, $message);
    }
}

// ================================
// create_own_voucher_invoice.php
// ================================
function sendOwnVoucherInvoiceMail($filename, $lang = 'be-nl') {
    $subject = t('voucher_invoice_subject', $lang);
    $message = t('voucher_invoice_message', $lang);
    $filePath = __DIR__ . '/invoices/' . basename($filename);
    $fixedEmail = 'test@stylisso.be';
    return sendMailWithAttachment($fixedEmail, $subject, $message, $filePath, $filename);
}

// ================================
// delete_account.php
// ================================
function sendAccountDeleteMail($email, $lang = 'be-nl') {
    $subject = t('account_delete_subject', $lang);
    $message = t('account_delete_message', $lang);
    return sendMail($email, $subject, $message);
}

// ================================
// register.php
// ================================
function sendWelcomeMail($email, $name, $lang = 'be-nl') {
    $subject = t('welcome_subject', $lang);
    $message = t('welcome_message', $lang, ['{name}' => $name]);
    return sendMail($email, $subject, $message);
}

// ================================
// reset_password.php
// ================================
function sendPasswordResetSuccessMail($email, $lang = 'be-nl') {
    $subject = t('password_reset_success_subject', $lang);
    $message = t('password_reset_success_message', $lang);
    return sendMail($email, $subject, $message);
}

// ================================
// send_newsletter.php
// ================================
function sendNewsletter($emails, $subject, $message) {
    $sentCount = 0;
    foreach ($emails as $to) {
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
            if (sendMail($to, $subject, $message)) {
                $sentCount++;
            }
        }
    }
    return ['sent' => $sentCount, 'total' => count($emails)];
}

// ================================
// submit_returns.php
// ================================
function sendReturnRequestedMail($email, $name, $product_name, $quantity = 1, $reason = '', $lang = 'be-nl') {
    if (!$email) return false;

    // --- Onderwerp en bericht met vertaling
    $subject = t('return_requested_subject', $lang);
    $message = t('return_requested_message', $lang, [
        '{name}' => $name,
        '{product_name}' => $product_name,
        '{quantity}' => $quantity,
        '{reason}' => $reason
    ]);

    // --- Versturen
    return sendMail($email, $subject, $message);
}

// ================================
// wachtwoord vergeten.php
// ================================
function sendPasswordResetLinkMail($email, $resetLink, $lang = 'be-nl') {
    $subject = t('password_reset_link_subject', $lang);
    $message = t('password_reset_link_message', $lang, ['{reset_link}' => $resetLink]);
    return sendMail($email, $subject, $message);
}
?>