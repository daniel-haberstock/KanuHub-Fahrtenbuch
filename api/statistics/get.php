<?php
/**
 * API: Statistik-Daten abrufen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $memberId = $_GET['member_id'] ?? null;

    if (!$memberId) {
        jsonResponse(['success' => false, 'message' => 'Mitglied-ID erforderlich'], 400);
    }

    $db = Database::getInstance()->getConnection();

    // Mitglied abrufen inkl. Sichtbarkeits-Status
    $memberQuery = "SELECT first_name, last_name, hide_statistics,
                           hide_statistics_locked, hide_statistics_returned_visible,
                           email_notify_after_trip, email_notify_weekly
                    FROM members WHERE id = :member_id";
    $memberStmt = $db->prepare($memberQuery);
    $memberStmt->execute(['member_id' => $memberId]);
    $member = $memberStmt->fetch();

    if (!$member) {
        jsonResponse(['success' => false, 'message' => 'Mitglied nicht gefunden'], 404);
    }

    $memberName = $member['first_name'] . ' ' . $member['last_name'];

    // Aktuelle und vorherige Saison berechnen
    $currentSeasonStart = getCurrentSeasonStartDate();
    $currentSeasonEnd = getCurrentSeasonEndDate();

    $lastSeasonStart = date('Y-m-d', strtotime($currentSeasonStart . ' -1 year'));
    $lastSeasonEnd = date('Y-m-d', strtotime($currentSeasonEnd . ' -1 year'));

    // Aktuelle Saison Statistik
    $currentSeasonQuery = "
        SELECT
            COUNT(DISTINCT t.id) as trips,
            COALESCE(SUM(t.distance), 0) as distance
        FROM trips t
        JOIN trip_crew tc ON t.id = tc.trip_id
        WHERE tc.member_id = :member_id
        AND t.is_completed = 1
        AND t.start_date >= :season_start
        AND t.start_date <= :season_end
    ";

    $stmt = $db->prepare($currentSeasonQuery);
    $stmt->execute([
        'member_id' => $memberId,
        'season_start' => $currentSeasonStart,
        'season_end' => $currentSeasonEnd
    ]);
    $currentSeason = $stmt->fetch();
    $stmt->closeCursor();

    // Vorherige Saison Statistik
    $stmt = $db->prepare($currentSeasonQuery);
    $stmt->execute([
        'member_id' => $memberId,
        'season_start' => $lastSeasonStart,
        'season_end' => $lastSeasonEnd
    ]);
    $lastSeason = $stmt->fetch();

    // Differenz berechnen
    $diff = $currentSeason['distance'] - $lastSeason['distance'];
    $diffText = $diff >= 0 ? "Bereits $diff km mehr als Vorsaison!" : "Noch " . abs($diff) . " km bis zur Vorsaison";

    // Letzte Fahrten dieser Saison
    $recentTripsQuery = "
        SELECT
            t.id AS trip_id,
            DATE_FORMAT(t.start_date, '%d.%m.%Y') as date,
            b.boat_name,
            t.route_custom as route,
            t.distance,
            EXISTS(SELECT 1 FROM tracker_sessions ts WHERE ts.trip_id = t.id) AS has_track
        FROM trips t
        JOIN trip_crew tc ON t.id = tc.trip_id
        JOIN boats b ON t.boat_id = b.id
        WHERE tc.member_id = :member_id
        AND t.is_completed = 1
        AND t.start_date >= :season_start
        AND t.start_date <= :season_end
        ORDER BY t.start_date DESC, t.start_time DESC
        LIMIT 10
    ";

    $stmt = $db->prepare($recentTripsQuery);
    $stmt->execute([
        'member_id' => $memberId,
        'season_start' => $currentSeasonStart,
        'season_end' => $currentSeasonEnd
    ]);
    $recentTrips = $stmt->fetchAll();

    // Rangliste dieser Saison (mit Datenschutz: hide_statistics=1 → "Versteckter Paddler")
    $rankingQuery = "
        SELECT
            m.id,
            CASE WHEN m.hide_statistics = 1 THEN 'Versteckter Paddler'
                 ELSE CONCAT(m.first_name, ' ', m.last_name) END AS member_name,
            m.hide_statistics,
            COUNT(DISTINCT t.id) as trips,
            COALESCE(SUM(t.distance), 0) as distance
        FROM members m
        JOIN trip_crew tc ON m.id = tc.member_id
        JOIN trips t ON tc.trip_id = t.id
        WHERE t.is_completed = 1
        AND t.start_date >= :season_start
        AND t.start_date <= :season_end
        GROUP BY m.id
        ORDER BY distance DESC, trips DESC
        LIMIT 200
    ";

    $stmt = $db->prepare($rankingQuery);
    $stmt->execute([
        'season_start' => $currentSeasonStart,
        'season_end' => $currentSeasonEnd
    ]);
    $ranking = $stmt->fetchAll();

    // Markiere aktuellen Benutzer; versteckte Mitglieder erhalten keinen Rang
    foreach ($ranking as &$rank) {
        $rank['is_current'] = $rank['id'] == $memberId;
        $rank['is_hidden']  = (int)$rank['hide_statistics'] === 1;
    }

    jsonResponse([
        'success' => true,
        'member_name' => $memberName,
        'hide_statistics'               => ($member['hide_statistics'] === null) ? null : (int)$member['hide_statistics'],
        'hide_statistics_locked'        => (bool)$member['hide_statistics_locked'],
        'hide_statistics_returned_visible' => (bool)$member['hide_statistics_returned_visible'],
        'email_notify_after_trip'       => (bool)$member['email_notify_after_trip'],
        'email_notify_weekly'           => (bool)$member['email_notify_weekly'],
        'current_season' => [
            'trips' => $currentSeason['trips'],
            'distance' => number_format($currentSeason['distance'], 1, ',', '.')
        ],
        'last_season' => [
            'trips' => $lastSeason['trips'],
            'distance' => number_format($lastSeason['distance'], 1, ',', '.')
        ],
        'season_diff' => [
            'distance' => number_format(abs($diff), 1, ',', '.'),
            'text' => $diffText
        ],
        'recent_trips' => $recentTrips,
        'ranking' => $ranking
    ]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/statistics/get.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
