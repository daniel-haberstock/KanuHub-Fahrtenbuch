<?php
/**
 * API: Eigene Fahrten eines Mitglieds
 * GET ?member_id=X&season=2025-2026&limit=50
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$season   = $_GET['season'] ?? getCurrentSeason();
$limit    = min(200, max(1, (int)($_GET['limit'] ?? 50)));

if (!$memberId) {
    jsonResponse(['success' => false, 'message' => 'member_id erforderlich'], 400);
}

[$yearFrom, $yearTo] = array_pad(explode('-', $season, 2), 2, null);
$seasonStart = $yearFrom ? ($yearFrom . '-10-01') : getCurrentSeasonStartDate();
$seasonEnd   = $yearTo   ? ($yearTo   . '-09-30') : getCurrentSeasonEndDate();

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT
            t.id,
            t.start_date,
            TIME_FORMAT(t.start_time, '%H:%i') AS start_time,
            TIME_FORMAT(t.end_time,   '%H:%i') AS end_time,
            t.distance,
            t.boat_name,
            COALESCE(r.description, CONCAT(r.start_point, IF(r.end_point != '', CONCAT(' – ', r.end_point), '')), t.route_custom) AS route_label,
            GROUP_CONCAT(DISTINCT tc2.member_name ORDER BY tc2.seat_position SEPARATOR ', ') AS crew_names
        FROM trips t
        JOIN trip_crew tc  ON tc.trip_id = t.id AND tc.member_id = :mid
        LEFT JOIN routes r ON t.route_id = r.id
        LEFT JOIN trip_crew tc2 ON tc2.trip_id = t.id
        WHERE t.is_completed = 1
          AND t.start_date BETWEEN :s AND :e
        GROUP BY t.id
        ORDER BY t.start_date DESC, t.start_time DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':mid', $memberId, PDO::PARAM_INT);
    $stmt->bindValue(':s',   $seasonStart);
    $stmt->bindValue(':e',   $seasonEnd);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Season list (detect all seasons that have trips for this member)
    $sStmt = $db->prepare("
        SELECT DISTINCT
            CONCAT(YEAR(DATE_SUB(t.start_date, INTERVAL IF(MONTH(t.start_date) < 10, 10, -2) MONTH)),
                   '-',
                   YEAR(DATE_SUB(t.start_date, INTERVAL IF(MONTH(t.start_date) < 10, 10, -2) MONTH)) + 1
            ) AS season
        FROM trips t
        JOIN trip_crew tc ON tc.trip_id = t.id AND tc.member_id = :mid
        WHERE t.is_completed = 1
        ORDER BY season DESC
    ");
    $sStmt->execute([':mid' => $memberId]);
    $seasons = $sStmt->fetchAll(PDO::FETCH_COLUMN);

    jsonResponse(['success' => true, 'trips' => $trips, 'seasons' => $seasons, 'season' => $season]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/trips/me.php] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Fehler beim Laden'], 500);
}
