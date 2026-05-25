<?php
/**
 * API: Neue Fahrt erstellen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $db = Database::getInstance()->getConnection();

    // Validierung
    $boatId = !empty($_POST['boat_id']) ? $_POST['boat_id'] : null;
    $boatName = $_POST['boat_name'] ?? null;
    $startDate = $_POST['start_date'] ?? null;
    $startTime = $_POST['start_time'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $endTime = $_POST['end_time'] ?? null;
    $routeCustom = $_POST['route_custom'] ?? null;
    $routeId = $_POST['route_id'] ?? null;
    $waterCustom = $_POST['water_custom'] ?? null;
    $waterId = $_POST['water_id'] ?? null;
    $distance = !empty($_POST['distance']) ? (float)$_POST['distance'] : null;
    $isBacklog = isset($_POST['is_backlog']) && $_POST['is_backlog'] == '1';
    $tripGroupId = !empty($_POST['trip_group_id']) ? (int)$_POST['trip_group_id'] : null;

    if (!$boatName || !$startDate || !$startTime) {
        jsonResponse(['success' => false, 'message' => 'Boot, Start-Datum und Start-Uhrzeit sind erforderlich'], 400);
    }

    // Bootstyp ermitteln (für Anhänger-Logik)
    $boatType = null;
    if ($boatId) {
        $boatTypeQuery = "SELECT boat_type FROM boats WHERE id = :boat_id";
        $boatTypeStmt = $db->prepare($boatTypeQuery);
        $boatTypeStmt->execute(['boat_id' => $boatId]);
        $boatData = $boatTypeStmt->fetch(PDO::FETCH_ASSOC);
        if ($boatData) {
            $boatType = $boatData['boat_type'];
        }
    }

    $isTrailer = ($boatType === 'Anhänger');

    // Prüfe, ob das End-Datum in der Vergangenheit liegt (Nachtrag)
    $isCompleted = false;
    if ($endDate && $endTime) {
        $endDateTime = new DateTime($endDate . ' ' . $endTime);
        $now = new DateTime();
        if ($endDateTime < $now || $isBacklog) {
            $isCompleted = true;
        }
    }

    // Fahrt erstellen (in unterschiedliche Tabelle je nach Bootstyp)
    $db->beginTransaction();

    // Unterschiedliche Tabellen für Anhänger und normale Boote
    $tripTable = $isTrailer ? 'trailer_trips' : 'trips';
    $crewTable = $isTrailer ? 'trailer_trip_crew' : 'trip_crew';

    // Anhänger haben keine Wasserrouten (route_id) und kein Gewässer
    // Nur route_custom für freie Eingabe (z.B. Zielort)
    if ($isTrailer) {
        $tripQuery = "
            INSERT INTO $tripTable (boat_id, boat_name, start_date, start_time, end_date, end_time, route_custom, distance, trip_group_id, is_completed, is_backlog)
            VALUES (:boat_id, :boat_name, :start_date, :start_time, :end_date, :end_time, :route_custom, :distance, :trip_group_id, :is_completed, :is_backlog)
        ";
    } else {
        $tripQuery = "
            INSERT INTO $tripTable (boat_id, boat_name, start_date, start_time, end_date, end_time, route_id, route_custom, distance, trip_group_id, is_completed, is_backlog)
            VALUES (:boat_id, :boat_name, :start_date, :start_time, :end_date, :end_time, :route_id, :route_custom, :distance, :trip_group_id, :is_completed, :is_backlog)
        ";
    }

    $tripStmt = $db->prepare($tripQuery);

    $tripParams = [
        'boat_id' => $boatId,
        'boat_name' => $boatName,
        'start_date' => $startDate,
        'start_time' => $startTime,
        'end_date' => $endDate ?: null,
        'end_time' => $endTime ?: null,
        'route_custom' => $routeCustom ?: null,
        'trip_group_id' => $tripGroupId,
        'distance' => $distance,
        'is_completed' => $isCompleted ? 1 : 0,
        'is_backlog' => $isBacklog ? 1 : 0
    ];

    // Für normale Boote: Route-ID hinzufügen
    if (!$isTrailer) {
        $tripParams['route_id'] = $routeId ?: null;
    }

    $tripStmt->execute($tripParams);

    $tripId = $db->lastInsertId();

    // Crew hinzufügen
    $crewQuery = "
        INSERT INTO $crewTable (trip_id, seat_position, member_id, member_name)
        VALUES (:trip_id, :seat_position, :member_id, :member_name)
    ";

    $crewStmt = $db->prepare($crewQuery);

    $seatPosition = 1;
    while (isset($_POST['crew_' . $seatPosition])) {
        $crewName = $_POST['crew_' . $seatPosition];
        $crewId = $_POST['crew_' . $seatPosition . '_id'] ?? null;

        // member_id auflösen (direkt oder aus Mitgliedsnummer im Namen)
        $resolvedId = resolveCrewMemberId($crewId, $crewName, $db);

        if (!empty($crewName)) {
            $crewStmt->execute([
                'trip_id' => $tripId,
                'seat_position' => $seatPosition,
                'member_id' => $resolvedId,
                'member_name' => $resolvedId ? null : $crewName
            ]);
        }

        $seatPosition++;
    }

    $db->commit();

    // Badge-Check für Backlog-/Nachtrags-Fahrten (sofort abgeschlossen)
    if ($isCompleted && !$isTrailer) {
        try {
            require_once __DIR__ . '/../../includes/BadgeChecker.php';
            BadgeChecker::checkAfterTrip((int)$tripId, $db);
        } catch (\Throwable $e) {
            error_log('[Fahrtenbuch api/trips/create.php] Badge-Check Fehler: ' . $e->getMessage());
        }
    }

    jsonResponse(['success' => true, 'trip_id' => $tripId, 'message' => 'Fahrt erfolgreich erstellt']);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/trips/create.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
