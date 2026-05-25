<?php
/**
 * API: Aktive Gruppen und Solo-Fahrten abrufen
 *
 * GET /api/trips/active-groups.php
 * GET /api/trips/active-groups.php?group_id=7  (nur eine bestimmte Gruppe)
 *
 * Gibt aktive trip_groups mit ihren Fahrten zurück,
 * sowie Solo-Paddler (aktive Fahrten ohne Gruppe).
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();
    $groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

    // ── 1. Aktive Gruppen laden ─────────────────────────────────────
    if ($groupId) {
        $groupQuery = "
            SELECT tg.* FROM trip_groups tg
            WHERE tg.id = :id AND tg.is_completed = 0
              AND EXISTS (SELECT 1 FROM trips t WHERE t.trip_group_id = tg.id AND t.is_completed = 0)
        ";
        $groupStmt = $db->prepare($groupQuery);
        $groupStmt->execute(['id' => $groupId]);
    } else {
        $groupQuery = "
            SELECT tg.* FROM trip_groups tg
            WHERE tg.is_completed = 0
              AND EXISTS (SELECT 1 FROM trips t WHERE t.trip_group_id = tg.id AND t.is_completed = 0)
            ORDER BY tg.started_at DESC
        ";
        $groupStmt = $db->prepare($groupQuery);
        $groupStmt->execute();
    }
    $groups = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

    // Trips pro Gruppe laden
    $result = [];
    foreach ($groups as $group) {
        $tripQuery = "
            SELECT
                t.id,
                t.boat_id,
                COALESCE(b.boat_name, t.boat_name) as boat_name,
                b.boat_type,
                t.start_date,
                TIME_FORMAT(t.start_time, '%H:%i') as start_time,
                t.is_completed
            FROM trips t
            LEFT JOIN boats b ON t.boat_id = b.id
            WHERE t.trip_group_id = :group_id AND t.is_completed = 0
            ORDER BY t.start_time ASC
        ";
        $tripStmt = $db->prepare($tripQuery);
        $tripStmt->execute(['group_id' => $group['id']]);
        $trips = $tripStmt->fetchAll(PDO::FETCH_ASSOC);

        // Crew-Namen pro Trip
        foreach ($trips as &$trip) {
            $crewQuery = "
                SELECT tc.member_id, tc.member_name, m.first_name, m.last_name
                FROM trip_crew tc
                LEFT JOIN members m ON tc.member_id = m.id
                WHERE tc.trip_id = :trip_id
                ORDER BY tc.seat_position ASC
            ";
            $crewStmt = $db->prepare($crewQuery);
            $crewStmt->execute(['trip_id' => $trip['id']]);
            $crew = $crewStmt->fetchAll(PDO::FETCH_ASSOC);

            $crewNames = [];
            foreach ($crew as $c) {
                $crewNames[] = $c['member_id']
                    ? $c['first_name'] . ' ' . $c['last_name']
                    : $c['member_name'];
            }
            $trip['crew'] = implode(', ', $crewNames);
        }
        unset($trip);

        $result[] = [
            'trip_group_id' => (int)$group['id'],
            'name'          => $group['name'],
            'source_type'   => $group['source_type'],
            'started_at'    => $group['started_at'],
            'trips'         => $trips,
        ];
    }

    // ── 2. Solo-Fahrten (aktive Fahrten ohne Gruppe) ────────────────
    $soloTrips = [];
    if (!$groupId) {
        $soloQuery = "
            SELECT
                t.id,
                t.boat_id,
                COALESCE(b.boat_name, t.boat_name) as boat_name,
                b.boat_type,
                t.start_date,
                TIME_FORMAT(t.start_time, '%H:%i') as start_time
            FROM trips t
            LEFT JOIN boats b ON t.boat_id = b.id
            WHERE t.is_completed = 0 AND t.trip_group_id IS NULL
            ORDER BY t.start_time DESC
        ";
        $soloStmt = $db->prepare($soloQuery);
        $soloStmt->execute();
        $soloTrips = $soloStmt->fetchAll(PDO::FETCH_ASSOC);

        // Crew-Namen pro Solo-Trip
        foreach ($soloTrips as &$solo) {
            $crewQuery = "
                SELECT tc.member_id, tc.member_name, m.first_name, m.last_name
                FROM trip_crew tc
                LEFT JOIN members m ON tc.member_id = m.id
                WHERE tc.trip_id = :trip_id
                ORDER BY tc.seat_position ASC
            ";
            $crewStmt = $db->prepare($crewQuery);
            $crewStmt->execute(['trip_id' => $solo['id']]);
            $crew = $crewStmt->fetchAll(PDO::FETCH_ASSOC);

            $crewNames = [];
            foreach ($crew as $c) {
                $crewNames[] = $c['member_id']
                    ? $c['first_name'] . ' ' . $c['last_name']
                    : $c['member_name'];
            }
            $solo['crew'] = implode(', ', $crewNames);
        }
        unset($solo);
    }

    jsonResponse([
        'success'    => true,
        'groups'     => $result,
        'solo_trips' => $soloTrips,
    ]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/trips/active-groups] Fehler: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
