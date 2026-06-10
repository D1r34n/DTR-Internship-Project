<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendMail(string $toEmail, string $toName, string $subject, string $body): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->Timeout    = 10;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception) {
        error_log('Mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send the same subject to multiple recipients over a single SMTP connection.
 * $recipients = [['email' => '...', 'name' => '...', 'body' => '...'], ...]
 * Returns the number of emails successfully sent.
 */
function sendMailBulk(array $recipients, string $subject): int
{
    if (empty($recipients)) return 0;

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host          = MAIL_HOST;
    $mail->SMTPAuth      = true;
    $mail->Username      = MAIL_USERNAME;
    $mail->Password      = MAIL_PASSWORD;
    $mail->SMTPSecure    = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port          = MAIL_PORT;
    $mail->Timeout       = 10;
    $mail->SMTPKeepAlive = true;
    $mail->isHTML(true);
    $mail->Subject       = $subject;
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

    $sent = 0;
    foreach ($recipients as $r) {
        try {
            $mail->clearAddresses();
            $mail->addAddress($r['email'], $r['name']);
            $mail->Body = $r['body'];
            $mail->send();
            $sent++;
        } catch (Exception $e) {
            error_log('Mailer bulk error for ' . $r['email'] . ': ' . $mail->ErrorInfo);
        }
    }

    $mail->smtpClose();
    return $sent;
}
