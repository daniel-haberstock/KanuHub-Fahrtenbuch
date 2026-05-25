<?php
/**
 * Fahrtenbuch Beta - Haupt-Router
 * Laedt gemeinsame Ressourcen, ermittelt das aktive Layout und delegiert.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/layout.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/']);
    session_start();
}

$loggedIn   = !empty($_SESSION['stats_member_id']);
$memberId   = $loggedIn ? (int)$_SESSION['stats_member_id'] : null;
$memberName = $_SESSION['stats_member_name'] ?? null;

$db            = Database::getInstance()->getConnection();
$currentSeason = getCurrentSeason();

// ── Vereins-Fortschritt ────────────────────────────────────────
$progressSeasonCurrent = 0.0;
$progressSeasonLast    = 0.0;
$progressMonthCurrent  = 0.0;
$progressMonthLast     = 0.0;
$_currentYear  = (int)date('Y');
$_currentMonth = (int)date('m');

try {
    $seasonStart     = getCurrentSeasonStartDate();
    $seasonEnd       = getCurrentSeasonEndDate();
    $lastSeasonStart = date('Y-m-d', strtotime($seasonStart . ' -1 year'));
    $lastSeasonEnd   = date('Y-m-d', strtotime($seasonEnd   . ' -1 year'));
    $lastYear        = (int)date('Y', strtotime('-1 year'));

    $baseQ = "SELECT COALESCE(SUM(distance),0) FROM trips WHERE is_completed=1";

    $s = $db->prepare("$baseQ AND start_date>=? AND start_date<=?");
    $s->execute([$seasonStart, $seasonEnd]);
    $progressSeasonCurrent = (float)$s->fetchColumn();
    $s->closeCursor();

    $s->execute([$lastSeasonStart, $lastSeasonEnd]);
    $progressSeasonLast = (float)$s->fetchColumn();
    $s->closeCursor();

    $s = $db->prepare("$baseQ AND YEAR(start_date)=? AND MONTH(start_date)=?");
    $s->execute([$_currentYear, $_currentMonth]);
    $progressMonthCurrent = (float)$s->fetchColumn();
    $s->closeCursor();

    $s->execute([$lastYear, $_currentMonth]);
    $progressMonthLast = (float)$s->fetchColumn();
    $s->closeCursor();

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch index.php] Vereins-Fortschritt: ' . $e->getMessage());
}

$_seasonPct  = $progressSeasonLast > 0
    ? min(100, round($progressSeasonCurrent / $progressSeasonLast * 100))
    : ($progressSeasonCurrent > 0 ? 100 : 0);
$_monthPct   = $progressMonthLast > 0
    ? min(100, round($progressMonthCurrent / $progressMonthLast * 100))
    : ($progressMonthCurrent > 0 ? 100 : 0);
$_seasonDiff = $progressSeasonCurrent - $progressSeasonLast;
$_monthDiff  = $progressMonthCurrent  - $progressMonthLast;
$_monthNames = ['','Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
$_monthName  = $_monthNames[$_currentMonth];

// Bootstrap-Daten fuer JS (v2-Layout)
$appData = [
    'apiBase'      => APP_BASE_PATH . '/api',
    'basePath'     => APP_BASE_PATH,
    'loggedIn'     => $loggedIn,
    'memberId'     => $memberId,
    'memberName'   => $memberName,
    'currentSeason'=> $currentSeason,
    'clubName'     => CLUB_NAME,
    'weatherConfig'=> [
        'windfinderLocation' => WINDFINDER_LOCATION,
        'windfinderLabel'    => WINDFINDER_LABEL,
        'stormEnabled'       => STORM_WARNING_ENABLED,
        'stormUrl'           => STORM_WARNING_URL,
        'stormLabel'         => STORM_WARNING_LABEL,
        'lat'                => defined('WEATHER_LAT') ? WEATHER_LAT : '47.67',
        'lon'                => defined('WEATHER_LON') ? WEATHER_LON : '9.17',
        'watertempWarm'      => defined('WEATHER_WATERTEMP_WARM') ? (int)WEATHER_WATERTEMP_WARM : 17,
        'windWarn'           => defined('WEATHER_WIND_WARN')      ? (int)WEATHER_WIND_WARN      : 25,
        'windDanger'         => defined('WEATHER_WIND_DANGER')    ? (int)WEATHER_WIND_DANGER    : 35,
        'pegelLow'           => defined('WEATHER_PEGEL_LOW')      ? (int)WEATHER_PEGEL_LOW      : 290,
    ],
    'today' => date('Y-m-d'),
];

// ── Layout-Auswahl ─────────────────────────────────────────────
// Wert kommt aus config/layout.php (vom Admin geschrieben).
// Fallback auf v2 falls Konstante ungültig oder Datei fehlt.
$_layout = in_array(ACTIVE_LAYOUT, ['v2', 'classic'], true) ? ACTIVE_LAYOUT : 'v2';

$_layoutFile = __DIR__ . '/layouts/' . $_layout . '/index.php';
if (!is_file($_layoutFile)) {
    error_log('[Fahrtenbuch Router] Layout-Datei fehlt: ' . $_layoutFile . ' — Fallback auf v2');
    $_layout     = 'v2';
    $_layoutFile = __DIR__ . '/layouts/v2/index.php';
}

require $_layoutFile;
