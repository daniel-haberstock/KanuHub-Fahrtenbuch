<?php
/**
 * API: Öffentliche Vereins-KPIs für Dashboard-Cards
 * Kein Auth erforderlich — nur aggregierte Vereinsdaten
 *
 * Rückgabe: { today_km, today_trips, season_km, season_label,
 *             month_km, month_label, month_trips }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();

    $today       = date('Y-m-d');
    $monthStart  = date('Y-m-01');
    $monthEnd    = date('Y-m-t');
    $seasonStart = getCurrentSeasonStartDate();
    $seasonEnd   = getCurrentSeasonEndDate();

    // Vorjahres-Monat (gleicher Monat, ein Jahr zurück)
    $prevMonthStart = date('Y-m-01', strtotime('-1 year'));
    $prevMonthEnd   = date('Y-m-t',  strtotime('-1 year'));

    // Vorjahres-Saison (exakt 1 Jahr früher)
    $prevSeasonStart = date('Y-m-d', strtotime($seasonStart . ' -1 year'));
    $prevSeasonEnd   = date('Y-m-d', strtotime($seasonEnd   . ' -1 year'));

    $monthNames = [
        1=>'Jan', 2=>'Feb', 3=>'Mär', 4=>'Apr', 5=>'Mai', 6=>'Jun',
        7=>'Jul', 8=>'Aug', 9=>'Sep', 10=>'Okt', 11=>'Nov', 12=>'Dez'
    ];
    $monthLabel      = $monthNames[(int)date('n')] . ' ' . date('Y');
    $prevMonthLabel  = $monthNames[(int)date('n', strtotime('-1 year'))] . ' ' . date('Y', strtotime('-1 year'));
    $seasonLabel     = date('Y', strtotime($seasonStart)) . '/' . date('y', strtotime($seasonEnd));
    $prevSeasonLabel = date('Y', strtotime($prevSeasonStart)) . '/' . date('y', strtotime($prevSeasonEnd));

    $fetch = function(string $from, string $to) use ($db): array {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(distance), 0) AS km, COUNT(*) AS trips
            FROM trips
            WHERE is_completed = 1 AND start_date BETWEEN ? AND ?
        ");
        $stmt->execute([$from, $to]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['km' => round((float)$r['km'], 1), 'trips' => (int)$r['trips']];
    };

    $today_data      = $fetch($today,           $today);
    $month_data      = $fetch($monthStart,      $monthEnd);
    $prev_month_data = $fetch($prevMonthStart,  $prevMonthEnd);
    $season_data     = $fetch($seasonStart,     $seasonEnd);
    $prev_season_data= $fetch($prevSeasonStart, $prevSeasonEnd);

    $month_diff  = round($month_data['km']  - $prev_month_data['km'],  1);
    $season_diff = round($season_data['km'] - $prev_season_data['km'], 1);

    echo json_encode([
        'success'          => true,
        'today_km'         => $today_data['km'],
        'today_trips'      => $today_data['trips'],
        'month_km'         => $month_data['km'],
        'month_trips'      => $month_data['trips'],
        'month_label'      => $monthLabel,
        'month_diff_km'    => $month_diff,
        'month_prev_label' => $prevMonthLabel,
        'season_km'        => $season_data['km'],
        'season_trips'     => $season_data['trips'],
        'season_label'     => $seasonLabel,
        'season_diff_km'   => $season_diff,
        'season_prev_label'=> $prevSeasonLabel,
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch kpis.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false]);
}
