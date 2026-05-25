<?php
/**
 * API: Reservierung abbrechen (löschen)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $db = Database::getInstance()->getConnection();

    $reservationId = $_POST['reservation_id'] ?? null;

    if (!$reservationId) {
        jsonResponse(['success' => false, 'message' => 'Reservierungs-ID ist erforderlich'], 400);
    }

    // Prüfen, ob Reservierung existiert
    $checkQuery = "SELECT id FROM boat_reservations WHERE id = :reservation_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute(['reservation_id' => $reservationId]);
    $reservation = $checkStmt->fetch();

    if (!$reservation) {
        jsonResponse(['success' => false, 'message' => 'Reservierung nicht gefunden'], 404);
    }

    // Reservierung löschen
    $deleteQuery = "DELETE FROM boat_reservations WHERE id = :reservation_id";
    $deleteStmt = $db->prepare($deleteQuery);
    $result = $deleteStmt->execute(['reservation_id' => $reservationId]);

    if ($result) {
        jsonResponse(['success' => true, 'message' => 'Reservierung erfolgreich abgebrochen']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Fehler beim Abbrechen der Reservierung'], 500);
    }

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/reservations/cancel.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
