<?php
/**
 * API: Eigenes Mitgliedsprofil aktualisieren
 * POST (session-geschützt): vorname, nachname, email, telefon
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST'], 405);
}

$memberId = !empty($_SESSION['stats_member_id']) ? (int)$_SESSION['stats_member_id'] : null;
if (!$memberId) {
    jsonResponse(['success' => false, 'message' => 'Nicht eingeloggt'], 401);
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name']  ?? '');
$email     = trim($_POST['email']      ?? '');
$phone     = trim($_POST['phone']      ?? '');

if (!$firstName || !$lastName) {
    jsonResponse(['success' => false, 'message' => 'Vor- und Nachname erforderlich'], 400);
}

try {
    $db = Database::getInstance()->getConnection();
    $db->prepare("
        UPDATE members SET first_name = :fn, last_name = :ln, email = :em, phone = :ph
        WHERE id = :id
    ")->execute([':fn' => $firstName, ':ln' => $lastName, ':em' => $email, ':ph' => $phone, ':id' => $memberId]);

    // Update session name
    $_SESSION['stats_member_name'] = $firstName . ' ' . $lastName;

    jsonResponse(['success' => true, 'message' => 'Gespeichert']);
} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/members/update.php] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Fehler beim Speichern'], 500);
}
