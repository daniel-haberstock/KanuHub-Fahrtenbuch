<?php
/**
 * API: Fahrt von Reservierung starten
 * Wandelt eine Reservierung in eine aktive Fahrt um
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

    // Reservierungsdetails abrufen
    $reservationQuery = "
        SELECT
            br.id,
            br.boat_id,
            br.member_id,
            br.member_name,
            b.boat_name,
            b.boat_type,
            b.seats
        FROM boat_reservations br
        JOIN boats b ON br.boat_id = b.id
        WHERE br.id = :reservation_id
    ";

    $reservationStmt = $db->prepare($reservationQuery);
    $reservationStmt->execute(['reservation_id' => $reservationId]);
    $reservation = $reservationStmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        jsonResponse(['success' => false, 'message' => 'Reservierung nicht gefunden'], 404);
    }

    $db->beginTransaction();

    // Bootstyp prüfen (für Anhänger-Logik)
    $isTrailer = ($reservation['boat_type'] === 'Anhänger');
    $tripTable = $isTrailer ? 'trailer_trips' : 'trips';
    $crewTable = $isTrailer ? 'trailer_trip_crew' : 'trip_crew';

    // Aktuelle Zeit als Startzeit
    $now = new DateTime();
    $startDate = $now->format('Y-m-d');
    $startTime = $now->format('H:i:s');

    // Neue Fahrt erstellen
    $tripQuery = "
        INSERT INTO $tripTable (boat_id, boat_name, start_date, start_time, is_completed, is_backlog)
        VALUES (:boat_id, :boat_name, :start_date, :start_time, 0, 0)
    ";

    $tripStmt = $db->prepare($tripQuery);
    $tripStmt->execute([
        'boat_id' => $reservation['boat_id'],
        'boat_name' => $reservation['boat_name'],
        'start_date' => $startDate,
        'start_time' => $startTime
    ]);

    $tripId = $db->lastInsertId();

    // Crew hinzufügen (Person aus Reservierung als Fahrer/erste Person)
    $crewQuery = "
        INSERT INTO $crewTable (trip_id, seat_position, member_id, member_name)
        VALUES (:trip_id, :seat_position, :member_id, :member_name)
    ";

    $crewStmt = $db->prepare($crewQuery);
    $crewStmt->execute([
        'trip_id' => $tripId,
        'seat_position' => 1,
        'member_id' => $reservation['member_id'],
        'member_name' => $reservation['member_name']
    ]);

    // Weitere Crew-Plätze hinzufügen (falls mehr Sitze vorhanden)
    if ($reservation['seats'] > 1) {
        for ($i = 2; $i <= $reservation['seats']; $i++) {
            $crewStmt->execute([
                'trip_id' => $tripId,
                'seat_position' => $i,
                'member_id' => null,
                'member_name' => ''
            ]);
        }
    }

    // Reservierung löschen
    $deleteQuery = "DELETE FROM boat_reservations WHERE id = :reservation_id";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->execute(['reservation_id' => $reservationId]);

    $db->commit();

    jsonResponse(['success' => true, 'message' => 'Fahrt wurde erfolgreich gestartet', 'trip_id' => $tripId]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/reservations/start-trip.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
