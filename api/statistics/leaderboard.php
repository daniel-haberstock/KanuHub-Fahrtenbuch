<?php
/**
 * API: Saisonrangliste — Top N Mitglieder nach Kilometern
 *
 * GET ?season=2025-2026&limit=10
 * Rückgabe: [{rank, member_id, name, km, trips, is_me}]
 * is_me wird gesetzt wenn ?member_id=X übergeben wird (eigener Eintrag)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$season   = $_GET['season']    ?? getCurrentSeason();
$limit    = min(50, max(1, (int)($_GET['limit'] ?? 10)));
$myId     = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

// Saison-Datumsbereich berechnen
[$yearFrom, $yearTo] = array_pad(explode('-', $season, 2), 2, null);
$seasonStart = $yearFrom ? ($yearFrom . '-10-01') : getCurrentSeasonStartDate();
$seasonEnd   = $yearTo   ? ($yearTo   . '-09-30') : getCurrentSeasonEndDate();

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT
            m.id           AS member_id,
            m.first_name,
            m.last_name,
            COUNT(DISTINCT t.id)         AS trips,
            COALESCE(SUM(t.distance), 0) AS km
        FROM members m
        JOIN trip_crew tc ON tc.member_id = m.id
        JOIN trips t      ON tc.trip_id   = t.id
        WHERE t.is_completed = 1
          AND t.start_date >= :s AND t.start_date <= :e
        GROUP BY m.id
        ORDER BY km DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':s',   $seasonStart);
    $stmt->bindValue(':e',   $seasonEnd);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $i => $r) {
        $result[] = [
            'rank'      => $i + 1,
            'member_id' => (int)$r['member_id'],
            'name'      => $r['first_name'] . ' ' . $r['last_name'],
            'km'        => round((float)$r['km'], 1),
            'trips'     => (int)$r['trips'],
            'is_me'     => ($myId && (int)$r['member_id'] === $myId),
        ];
    }

    echo json_encode(['success' => true, 'season' => $season, 'rows' => $result]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch leaderboard] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
