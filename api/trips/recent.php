<?php
/**
 * API: Letzte abgeschlossene Vereinsfahrten
 *
 * GET ?limit=5
 * Rückgabe: [{id, date, start_time, end_time, boat_name, boat_type, route, km, crew_names, is_active}]
 * is_active = false (nur abgeschlossene Fahrten)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$limit = min(20, max(1, (int)($_GET['limit'] ?? 5)));

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT
            t.id,
            t.start_date                      AS date,
            TIME_FORMAT(t.start_time,'%H:%i') AS start_time,
            TIME_FORMAT(t.end_time,  '%H:%i') AS end_time,
            COALESCE(b.boat_name, t.boat_name) AS boat_name,
            b.boat_type,
            COALESCE(r.description, t.route_custom, '') AS route,
            COALESCE(t.distance, 0)           AS km
        FROM trips t
        LEFT JOIN boats  b ON t.boat_id  = b.id
        LEFT JOIN routes r ON t.route_id = r.id
        WHERE t.is_completed = 1
        ORDER BY t.start_date DESC, t.start_time DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crew pro Fahrt laden
    $crewStmt = $db->prepare("
        SELECT tc.trip_id,
               COALESCE(CONCAT(m.first_name,' ',m.last_name), tc.member_name) AS name
        FROM trip_crew tc
        LEFT JOIN members m ON tc.member_id = m.id
        WHERE tc.trip_id = :tid
        ORDER BY tc.seat_position
    ");

    foreach ($trips as &$t) {
        $crewStmt->execute([':tid' => $t['id']]);
        $names = array_column($crewStmt->fetchAll(PDO::FETCH_ASSOC), 'name');
        $t['crew_names'] = implode(', ', $names);
        $t['km']         = round((float)$t['km'], 1);
        $t['is_active']  = false;
    }
    unset($t);

    echo json_encode(['success' => true, 'trips' => $trips]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch trips/recent] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
