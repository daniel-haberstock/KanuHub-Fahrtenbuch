<?php
/**
 * API: Fahrt aktualisieren
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

    // Validierung
    $boatId = !empty($_POST['boat_id']) ? $_POST['boat_id'] : null;
    $boatName = $_POST['boat_name'] ?? null;
    $startDate = $_POST['start_date'] ?? null;
    $startTime = $_POST['start_time'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $endTime = $_POST['end_time'] ?? null;
    $routeCustom = $_POST['route_custom'] ?? null;
    $routeId = $_POST['route_id'] ?? null;
    $distance = isset($_POST['distance']) ? str_replace(',', '.', $_POST['distance']) : null;
    $comments = $_POST['comments'] ?? null;
    $isCompleted = isset($_POST['is_completed']) && $_POST['is_completed'] == '1';

    if (!$boatName || !$startDate || !$startTime) {
        jsonResponse(['success' => false, 'message' => 'Boot, Start-Datum und Start-Uhrzeit sind erforderlich'], 400);
    }

    $db->beginTransaction();

    // Unterschiedliche Tabellen für Anhänger und normale Boote
    $tripTable = $isTrailer ? 'trailer_trips' : 'trips';
    $crewTable = $isTrailer ? 'trailer_trip_crew' : 'trip_crew';

    // Fahrt aktualisieren
    $updateQuery = "
        UPDATE $tripTable
        SET boat_id = :boat_id,
            boat_name = :boat_name,
            start_date = :start_date,
            start_time = :start_time,
            end_date = :end_date,
            end_time = :end_time,
            route_id = :route_id,
            route_custom = :route_custom,
            distance = :distance,
            comments = :comments,
            is_completed = :is_completed
        WHERE id = :trip_id
    ";

    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([
        'trip_id' => $tripId,
        'boat_id' => $boatId,
        'boat_name' => $boatName,
        'start_date' => $startDate,
        'start_time' => $startTime,
        'end_date' => $endDate ?: null,
        'end_time' => $endTime ?: null,
        'route_id' => $routeId ?: null,
        'route_custom' => $routeCustom ?: null,
        'distance' => $distance ?: null,
        'comments' => $comments ?: null,
        'is_completed' => $isCompleted ? 1 : 0
    ]);

    // Crew aktualisieren - zuerst alle löschen
    $deleteCrewQuery = "DELETE FROM $crewTable WHERE trip_id = :trip_id";
    $deleteCrewStmt = $db->prepare($deleteCrewQuery);
    $deleteCrewStmt->execute(['trip_id' => $tripId]);

    // Crew neu hinzufügen
    $crewQuery = "
        INSERT INTO $crewTable (trip_id, seat_position, member_id, member_name)
        VALUES (:trip_id, :seat_position, :member_id, :member_name)
    ";

    $crewStmt = $db->prepare($crewQuery);

    $seatPosition = 1;
    while (isset($_POST['crew_' . $seatPosition])) {
        $crewName = $_POST['crew_' . $seatPosition];
        $crewId = $_POST['crew_' . $seatPosition . '_id'] ?? null;

        // Wenn crew_id leer ist (leerer String), auf null setzen
        if ($crewId === '' || $crewId === '0') {
            $crewId = null;
        }

        if (!empty($crewName)) {
            $crewStmt->execute([
                'trip_id' => $tripId,
                'seat_position' => $seatPosition,
                'member_id' => $crewId ?: null,
                'member_name' => $crewId ? null : $crewName
            ]);
        }

        $seatPosition++;
    }

    $db->commit();

    jsonResponse(['success' => true, 'message' => 'Fahrt erfolgreich aktualisiert']);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/trips/update.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
