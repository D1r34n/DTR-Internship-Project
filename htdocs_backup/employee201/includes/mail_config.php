<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendResetEmail($toEmail, $toName, $resetLink) {
    $mail = new PHPMailer(true);

    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // or your mail server
        $mail->SMTPAuth = true;
        $mail->Username = 'rey070125@gmail.com'; // <-- change this
        $mail->Password = 'giqg mrcu lktq tgee';    // <-- app password, not normal Gmail password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Sender info
        $mail->setFrom('your_email@gmail.com', 'E201 System Support');
        $mail->addAddress($toEmail, $toName);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body = "
            <h3>Password Reset Request</h3>
            <p>Hello <strong>$toName</strong>,</p>
            <p>We received a request to reset your password. Click the button below to set a new one:</p>
            <p><a href='$resetLink' 
                  style='background:#1f2937;color:#fff;padding:10px 15px;border-radius:6px;
                         text-decoration:none;'>Reset Password</a></p>
            <p>This link will expire in 15 minutes. If you didn’t request a reset, ignore this email.</p>
            <hr>
            <p style='font-size:12px;color:#6b7280;'>© 2025 HSNP E201. All rights reserved.</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mail error: " . $mail->ErrorInfo);
        return false;
    }
}
