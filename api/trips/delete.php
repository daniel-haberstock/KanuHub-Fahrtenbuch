<?php
/**
 * API: Fahrt löschen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $db = Database::getInstance()->getConnection();

    $tripId = $_POST['trip_id'] ?? null;
    $isTrailer = isset($_POST['is_trailer']) && $_POST['is_trailer'] == '1';

    if (!$tripId) {
        jsonResponse(['success' => false, 'message' => 'Fahrt-ID erforderlich'], 400);
    }

    $db->beginTransaction();

    // Unterschiedliche Tabellen für Anhänger und normale Boote
    $tripTable = $isTrailer ? 'trailer_trips' : 'trips';
    $crewTable = $isTrailer ? 'trailer_trip_crew' : 'trip_crew';

    // Crew löschen
    $deleteCrewQuery = "DELETE FROM $crewTable WHERE trip_id = :trip_id";
    $deleteCrewStmt = $db->prepare($deleteCrewQuery);
    $deleteCrewStmt->execute(['trip_id' => $tripId]);

    // Fahrt löschen
    $deleteTripQuery = "DELETE FROM $tripTable WHERE id = :trip_id";
    $deleteTripStmt = $db->prepare($deleteTripQuery);
    $deleteTripStmt->execute(['trip_id' => $tripId]);

    $db->commit();

    jsonResponse(['success' => true, 'message' => 'Fahrt erfolgreich gelöscht']);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/trips/delete.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
