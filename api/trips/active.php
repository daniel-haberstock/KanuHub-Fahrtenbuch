<?php
/**
 * API: Aktive Fahrten abrufen (Boote auf Fahrt)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();

    // Aktive Fahrten aus beiden Tabellen (trips und trailer_trips)
    $query = "
        SELECT * FROM (
            SELECT
                t.id,
                t.boat_id,
                COALESCE(b.boat_name, t.boat_name) as boat_name,
                b.boat_type,
                t.start_date,
                t.start_time as start_time_raw,
                t.end_date,
                t.end_time,
                TIME_FORMAT(t.start_time, '%H:%i') as start_time,
                t.is_overdue,
                t.trip_group_id,
                tg.name as trip_group_name,
                'trips' as source_table
            FROM trips t
            LEFT JOIN boats b ON t.boat_id = b.id
            LEFT JOIN trip_groups tg ON t.trip_group_id = tg.id
            WHERE t.is_completed = 0

            UNION ALL

            SELECT
                t.id,
                t.boat_id,
                COALESCE(b.boat_name, t.boat_name) as boat_name,
                b.boat_type,
                t.start_date,
                t.start_time as start_time_raw,
                t.end_date,
                t.end_time,
                TIME_FORMAT(t.start_time, '%H:%i') as start_time,
                t.is_overdue,
                t.trip_group_id,
                tg.name as trip_group_name,
                'trailer_trips' as source_table
            FROM trailer_trips t
            LEFT JOIN boats b ON t.boat_id = b.id
            LEFT JOIN trip_groups tg ON t.trip_group_id = tg.id
            WHERE t.is_completed = 0
        ) AS combined_trips
        ORDER BY start_date DESC, start_time_raw DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $trips = $stmt->fetchAll();

    // Crew-Namen für jede Fahrt abrufen
    foreach ($trips as &$trip) {
        // WICHTIG: Bestimme is_trailer basierend auf dem ECHTEN boat_type
        // (unabhängig davon, in welcher Tabelle die Fahrt aktuell liegt)
        $trip['is_trailer'] = ($trip['boat_type'] === 'Anhänger');

        // Für Crew-Abfrage: Nutze die ECHTE Tabelle (source_table), wo die Daten aktuell liegen
        $crewTable = ($trip['source_table'] === 'trailer_trips') ? 'trailer_trip_crew' : 'trip_crew';

        $crewQuery = "
            SELECT
                tc.member_id,
                tc.member_name,
                m.first_name,
                m.last_name
            FROM $crewTable tc
            LEFT JOIN members m ON tc.member_id = m.id
            WHERE tc.trip_id = :trip_id
            ORDER BY tc.seat_position ASC
        ";

        $crewStmt = $db->prepare($crewQuery);
        $crewStmt->execute(['trip_id' => $trip['id']]);
        $crew = $crewStmt->fetchAll();

        $crewNames = [];
        foreach ($crew as $c) {
            if ($c['member_id']) {
                $crewNames[] = $c['first_name'] . ' ' . $c['last_name'];
            } else {
                $crewNames[] = $c['member_name'];
            }
        }

        $trip['crew_names'] = implode(', ', $crewNames);

        // Prüfe, ob überfällig
        if ($trip['end_date'] && $trip['end_time']) {
            $endDateTime = new DateTime($trip['end_date'] . ' ' . $trip['end_time']);
            $now = new DateTime();
            if ($endDateTime < $now) {
                $trip['is_overdue'] = 1;

                // Update in Datenbank (richtige Tabelle)
                $updateTable = $trip['source_table'];
                $updateStmt = $db->prepare("UPDATE $updateTable SET is_overdue = 1 WHERE id = :id");
                $updateStmt->execute(['id' => $trip['id']]);
            }
        }
    }

    jsonResponse(['success' => true, 'trips' => $trips]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/trips/active.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
