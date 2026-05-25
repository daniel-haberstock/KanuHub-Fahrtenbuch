<?php
/**
 * API: E-Mail-Benachrichtigungseinstellungen speichern
 *
 * POST-Parameter:
 *   member_id              INT  – Mitglieds-ID (aus Login-Response)
 *   notify_after_trip      0|1  – Nach jeder Fahrt benachrichtigen
 *   notify_weekly          0|1  – Wöchentlich benachrichtigen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

$memberId         = (int)($_POST['member_id']         ?? 0);
$notifyAfterTrip  = isset($_POST['notify_after_trip']) ? (int)(bool)$_POST['notify_after_trip'] : 0;
$notifyWeekly     = isset($_POST['notify_weekly'])     ? (int)(bool)$_POST['notify_weekly']     : 0;

if (!$memberId) {
    jsonResponse(['success' => false, 'message' => 'member_id erforderlich'], 400);
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare(
        "UPDATE members
         SET email_notify_after_trip = :after_trip,
             email_notify_weekly     = :weekly
         WHERE id = :id"
    );
    $stmt->execute([
        'after_trip' => $notifyAfterTrip,
        'weekly'     => $notifyWeekly,
        'id'         => $memberId,
    ]);

    // rowCount kann 0 sein wenn die Werte identisch sind – kein Fehler
    // Prüfe stattdessen ob das Mitglied existiert
    $checkStmt = $db->prepare("SELECT id FROM members WHERE id = :id");
    $checkStmt->execute(['id' => $memberId]);
    if (!$checkStmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Mitglied nicht gefunden'], 404);
    }

    jsonResponse([
        'success'              => true,
        'notify_after_trip'    => (bool)$notifyAfterTrip,
        'notify_weekly'        => (bool)$notifyWeekly,
    ]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/statistics/notification-settings.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
