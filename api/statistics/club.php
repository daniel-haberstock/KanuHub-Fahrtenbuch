<?php
/**
 * API: Vereinsstatistik – für REDAXO
 *
 * Gibt aggregierte Vereinsdaten zurück (kein Bezug zu einzelnen Mitgliedern).
 * Authentifizierung: ?token=... oder Bearer-Header.
 *
 * Rückgabe:
 *   {
 *     success: true,
 *     current_season: {
 *       label: "2025-2026",
 *       start: "2025-10-01",
 *       end: "2026-09-30",
 *       km: 4712.5,
 *       trips: 384
 *     },
 *     last_season: {
 *       label: "2024-2025",
 *       km: 4100.0,
 *       trips: 312
 *     },
 *     season_diff: { km: 612.5, km_text: "+612,5 km", direction: "up" },
 *     current_month: {
 *       label: "März 2026",
 *       km: 320.0,
 *       trips: 28
 *     },
 *     last_year_month: {
 *       label: "März 2025",
 *       km: 290.0,
 *       trips: 24
 *     },
 *     month_diff: { km: 30.0, km_text: "+30,0 km", direction: "up" }
 *   }
 *
 * Verwendung in REDAXO:
 *   $json = file_get_contents(FAHRTENBUCH_BASE_URL . '/api/statistics/club.php'
 *           . '?token=' . REDAXO_API_TOKEN);
 *   $data = json_decode($json, true);
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// ── Token-Validierung ──────────────────────────────────────────────────────
$token = $_GET['token']
    ?? (isset($_SERVER['HTTP_AUTHORIZATION'])
        ? str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'])
        : '');

if (!defined('REDAXO_API_TOKEN') || $token !== REDAXO_API_TOKEN) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiger oder fehlender Token']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // ── Saison-Datumsbereich ───────────────────────────────────────
    $seasonStart     = getCurrentSeasonStartDate();  // z.B. 2025-10-01
    $seasonEnd       = getCurrentSeasonEndDate();    // z.B. 2026-09-30
    $lastSeasonStart = date('Y-m-d', strtotime($seasonStart . ' -1 year'));
    $lastSeasonEnd   = date('Y-m-d', strtotime($seasonEnd   . ' -1 year'));

    // Saison-Labels aus Datumsbereich berechnen
    $currentSeasonLabel = date('Y', strtotime($seasonStart)) . '-' . date('Y', strtotime($seasonEnd));
    $lastSeasonLabel    = date('Y', strtotime($lastSeasonStart)) . '-' . date('Y', strtotime($lastSeasonEnd));

    // ── Aktueller Monat ────────────────────────────────────────────
    $now                 = new DateTime();
    $monthStart          = $now->format('Y-m-01');
    $monthEnd            = $now->format('Y-m-') . $now->format('t');
    $lastYearMonthStart  = date('Y-m-01', strtotime($monthStart . ' -1 year'));
    $lastYearMonthEnd    = date('Y-m-t',  strtotime($monthStart . ' -1 year'));

    // Deutsche Monatsnamen
    $monthNames = [
        1=>'Januar',2=>'Februar',3=>'März',4=>'April',5=>'Mai',6=>'Juni',
        7=>'Juli',8=>'August',9=>'September',10=>'Oktober',11=>'November',12=>'Dezember'
    ];
    $currentMonthLabel   = $monthNames[(int)$now->format('n')] . ' ' . $now->format('Y');
    $lastYearMonthLabel  = $monthNames[(int)$now->format('n')] . ' ' . ($now->format('Y') - 1);

    // ── Hilfsfunktion: km + trips für Zeitraum ────────────────────
    $stmtVerein = $db->prepare("
        SELECT
            COALESCE(SUM(t.distance), 0) AS km,
            COUNT(DISTINCT t.id)         AS trips
        FROM trips t
        JOIN trip_crew tc ON t.id = tc.trip_id
        WHERE t.is_completed = 1
          AND t.start_date BETWEEN ? AND ?
    ");

    $fetch = function(string $start, string $end) use ($stmtVerein): array {
        $stmtVerein->execute([$start, $end]);
        $row = $stmtVerein->fetch();
        $stmtVerein->closeCursor();
        return [
            'km'    => round((float)$row['km'], 1),
            'trips' => (int)$row['trips'],
        ];
    };

    $curSeason   = $fetch($seasonStart,        $seasonEnd);
    $prevSeason  = $fetch($lastSeasonStart,    $lastSeasonEnd);
    $curMonth    = $fetch($monthStart,          $monthEnd);
    $prevMonth   = $fetch($lastYearMonthStart, $lastYearMonthEnd);

    // ── Differenzen ────────────────────────────────────────────────
    $seasonKmDiff = $curSeason['km'] - $prevSeason['km'];
    $monthKmDiff  = $curMonth['km']  - $prevMonth['km'];

    $formatDiff = function(float $diff): array {
        return [
            'km'        => round(abs($diff), 1),
            'km_text'   => ($diff >= 0 ? '+' : '-') . number_format(abs($diff), 1, ',', '.') . ' km',
            'direction' => $diff >= 0 ? 'up' : 'down',
        ];
    };

    echo json_encode([
        'success' => true,
        'current_season' => [
            'label'  => $currentSeasonLabel,
            'start'  => $seasonStart,
            'end'    => $seasonEnd,
            'km'     => $curSeason['km'],
            'km_formatted' => number_format($curSeason['km'], 1, ',', '.'),
            'trips'  => $curSeason['trips'],
        ],
        'last_season' => [
            'label'  => $lastSeasonLabel,
            'km'     => $prevSeason['km'],
            'km_formatted' => number_format($prevSeason['km'], 1, ',', '.'),
            'trips'  => $prevSeason['trips'],
        ],
        'season_diff'  => $formatDiff($seasonKmDiff),
        'current_month' => [
            'label'  => $currentMonthLabel,
            'km'     => $curMonth['km'],
            'km_formatted' => number_format($curMonth['km'], 1, ',', '.'),
            'trips'  => $curMonth['trips'],
        ],
        'last_year_month' => [
            'label'  => $lastYearMonthLabel,
            'km'     => $prevMonth['km'],
            'km_formatted' => number_format($prevMonth['km'], 1, ',', '.'),
            'trips'  => $prevMonth['trips'],
        ],
        'month_diff' => $formatDiff($monthKmDiff),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/statistics/club.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage() ?: get_class($e)]);
}
