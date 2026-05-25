<?php
/**
 * API: Verbesserungsvorschlag / Feedback per E-Mail senden
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/phpmailer/Exception.php';
require_once __DIR__ . '/../../includes/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../../includes/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

$message    = trim($_POST['message']    ?? '');
$memberName = trim($_POST['member_name'] ?? '');
$memberId   = intval($_POST['member_id'] ?? 0);

if ($message === '') {
    jsonResponse(['success' => false, 'message' => 'Bitte gib eine Nachricht ein.'], 400);
}

$absender = defined('EMAIL_ABSENDER') ? EMAIL_ABSENDER : '';
$passwort = defined('EMAIL_PASSWORT') ? EMAIL_PASSWORT : '';
$server   = defined('EMAIL_SERVER')   ? EMAIL_SERVER   : 'localhost';
$empfaenger = defined('EMAIL_EMPFAENGER') ? EMAIL_EMPFAENGER : $absender;

$vonName   = $memberName ?: 'Unbekannt';
$betreff   = 'Feedback Fahrtenbuch – ' . $vonName;

$body  = '<html><body style="font-family: Arial, sans-serif;">';
$body .= '<h2 style="color:#0d6efd;">Neues Feedback im Fahrtenbuch</h2>';
$body .= '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;">';
$body .= '<tr><td style="color:#555; white-space:nowrap; padding-right:16px;"><strong>Mitglied:</strong></td>';
$body .= '<td>' . htmlspecialchars($memberName ?: '–') . '</td></tr>';
if ($memberId > 0) {
    $body .= '<tr><td style="color:#555;"><strong>Member-ID:</strong></td>';
    $body .= '<td>' . $memberId . '</td></tr>';
}
$body .= '</table>';
$body .= '<hr style="margin:16px 0;">';
$body .= '<h3 style="color:#333;">Nachricht:</h3>';
$body .= '<p style="white-space:pre-wrap;">' . htmlspecialchars($message) . '</p>';
$body .= '</body></html>';

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $server;
    $mail->SMTPAuth   = true;
    $mail->Username   = $absender;
    $mail->Password   = $passwort;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($absender, 'Fahrtenbuch');
    $mail->addAddress($empfaenger);
    $mail->isHTML(true);
    $mail->Subject = $betreff;
    $mail->Body    = $body;
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

    $mail->send();

    jsonResponse(['success' => true]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/feedback/send.php] Fehler: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'E-Mail konnte nicht gesendet werden: ' . $e->getMessage()], 500);
}
