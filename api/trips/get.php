<?php
/**
 * API: Fahrt-Details abrufen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $tripId = $_GET['id'] ?? null;
    $isTrailer = ($_GET['is_trailer'] ?? '0') === '1';

    if (!$tripId) {
        jsonResponse(['success' => false, 'message' => 'Fahrt-ID erforderlich'], 400);
    }

    $db = Database::getInstance()->getConnection();

    // Tabelle basierend auf is_trailer Parameter wählen
    $tripTable = $isTrailer ? 'trailer_trips' : 'trips';
    $sourceTable = $isTrailer ? 'trailer_trips' : 'trips';

    // Fahrt aus der richtigen Tabelle laden
    $query = "
        SELECT
            t.*,
            COALESCE(b.boat_name, t.boat_name) as boat_name,
            b.boat_type,
            b.seats,
            r.distance as route_distance,
            r.description as route_description,
            DATE_FORMAT(t.start_date, '%d.%m.%Y') as start_date_formatted,
            TIME_FORMAT(t.start_time, '%H:%i') as start_time_formatted,
            tg.name as trip_group_name,
            tg.source_type as trip_group_source_type,
            '$sourceTable' as source_table
        FROM $tripTable t
        LEFT JOIN boats b ON t.boat_id = b.id
        LEFT JOIN routes r ON t.route_id = r.id
        LEFT JOIN trip_groups tg ON t.trip_group_id = tg.id
        WHERE t.id = :trip_id
    ";

    $stmt = $db->prepare($query);
    $stmt->execute(['trip_id' => $tripId]);
    $trip = $stmt->fetch();

    if (!$trip) {
        jsonResponse(['success' => false, 'message' => 'Fahrt nicht gefunden'], 404);
    }

    // Crew abrufen (aus der richtigen Tabelle)
    $crewTable = $isTrailer ? 'trailer_trip_crew' : 'trip_crew';
    $crewQuery = "
        SELECT
            tc.seat_position,
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
    $crewStmt->execute(['trip_id' => $tripId]);
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
    $trip['crew'] = $crew;

    // GPS-Distanz aus tracker_stats laden (falls vorhanden)
    $trip['gps_distance_km'] = null;
    if (!$isTrailer) {
        $gpsStmt = $db->prepare("
            SELECT ts.distance_m
            FROM tracker_sessions sess
            JOIN tracker_stats ts ON ts.session_id = sess.id
            WHERE sess.trip_id = :trip_id
            LIMIT 1
        ");
        $gpsStmt->execute(['trip_id' => $tripId]);
        $gpsStats = $gpsStmt->fetch();
        if ($gpsStats && $gpsStats['distance_m'] > 0) {
            $trip['gps_distance_km'] = round($gpsStats['distance_m'] / 1000, 1);
        }
    }

    // Gruppen-Trips laden (andere aktive Fahrten in derselben Gruppe)
    $trip['group_trips'] = [];
    if (!empty($trip['trip_group_id'])) {
        $groupTripsQuery = "
            SELECT
                t.id,
                COALESCE(b.boat_name, t.boat_name) as boat_name,
                t.is_completed
            FROM trips t
            LEFT JOIN boats b ON t.boat_id = b.id
            WHERE t.trip_group_id = :group_id AND t.id != :trip_id AND t.is_completed = 0
            ORDER BY t.start_time ASC
        ";
        $groupTripsStmt = $db->prepare($groupTripsQuery);
        $groupTripsStmt->execute([
            'group_id' => $trip['trip_group_id'],
            'trip_id'  => $tripId,
        ]);
        $groupTrips = $groupTripsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Crew-Namen pro Gruppen-Trip
        foreach ($groupTrips as &$gt) {
            $gtCrewStmt = $db->prepare("
                SELECT tc.member_id, tc.member_name, m.first_name, m.last_name
                FROM trip_crew tc
                LEFT JOIN members m ON tc.member_id = m.id
                WHERE tc.trip_id = :trip_id
                ORDER BY tc.seat_position ASC
            ");
            $gtCrewStmt->execute(['trip_id' => $gt['id']]);
            $gtCrew = $gtCrewStmt->fetchAll(PDO::FETCH_ASSOC);

            $gtCrewNames = [];
            foreach ($gtCrew as $c) {
                $gtCrewNames[] = $c['member_id']
                    ? $c['first_name'] . ' ' . $c['last_name']
                    : $c['member_name'];
            }
            $gt['crew'] = implode(', ', $gtCrewNames);
        }
        unset($gt);

        $trip['group_trips'] = $groupTrips;
    }

    // Zuletzt gefahrene Strecken der Crew (nur normale Fahrten)
    $trip['recent_routes'] = [];
    if (!$isTrailer) {
        $memberIds = array_values(array_filter(
            array_column($crew, 'member_id'),
            fn($id) => $id !== null
        ));
        $memberIds = array_map('intval', $memberIds);

        if (!empty($memberIds)) {
            $inPlaceholders = implode(',', array_map(fn($i) => ':mid' . $i, array_keys($memberIds)));
            $recentSql = "
                SELECT
                    t.route_id,
                    t.route_custom,
                    COALESCE(r.description, t.route_custom) AS route_label,
                    COALESCE(t.distance, r.distance)        AS distance,
                    MAX(t.end_date)                         AS last_used
                FROM trips t
                JOIN trip_crew tc ON tc.trip_id = t.id
                LEFT JOIN routes r ON r.id = t.route_id
                WHERE tc.member_id IN ($inPlaceholders)
                  AND t.is_completed = 1
                  AND t.id != :current_trip_id
                  AND (t.route_id IS NOT NULL OR (t.route_custom IS NOT NULL AND t.route_custom != ''))
                GROUP BY t.route_id, t.route_custom, route_label, t.distance, r.distance
                ORDER BY last_used DESC
                LIMIT 5
            ";
            $params = ['current_trip_id' => (int)$tripId];
            foreach ($memberIds as $i => $mid) { $params['mid' . $i] = $mid; }
        } else {
            // Fallback: gleiches Boot
            $recentSql = "
                SELECT
                    t.route_id,
                    t.route_custom,
                    COALESCE(r.description, t.route_custom) AS route_label,
                    COALESCE(t.distance, r.distance)        AS distance,
                    MAX(t.end_date)                         AS last_used
                FROM trips t
                LEFT JOIN routes r ON r.id = t.route_id
                WHERE t.boat_id = :boat_id
                  AND t.is_completed = 1
                  AND t.id != :current_trip_id
                  AND (t.route_id IS NOT NULL OR (t.route_custom IS NOT NULL AND t.route_custom != ''))
                GROUP BY t.route_id, t.route_custom, route_label, t.distance, r.distance
                ORDER BY last_used DESC
                LIMIT 5
            ";
            $params = ['boat_id' => (int)$trip['boat_id'], 'current_trip_id' => (int)$tripId];
        }

        $recentStmt = $db->prepare($recentSql);
        $recentStmt->execute($params);
        $trip['recent_routes'] = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    jsonResponse(['success' => true, 'trip' => $trip]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/trips/get.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
