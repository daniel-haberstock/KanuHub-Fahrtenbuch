<?php
/**
 * API: Statistik-Dashboard für ein Mitglied (read-only)
 *
 * Authentifizierung: Bearer-Token (Server-to-Server, z.B. REDAXO → Fahrtenbuch)
 * Parameter: membership_no (GET) — Mitgliedsnummer
 * Optional:  saison (GET) — z.B. "2025-2026" (Default: aktuelle Saison)
 *
 * Liefert: Saisonstatistik, Rangliste, Badges, Fahrten, Chart-Daten
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Badge-Klassen laden
$badgesAvailable = false;
try {
    require_once __DIR__ . '/../../includes/BadgeChecker.php';
    require_once __DIR__ . '/../../includes/BadgeRenderer.php';
    $badgesAvailable = true;
} catch (\Throwable $e) {
    error_log('[Fahrtenbuch API dashboard] Badge-Klassen nicht verfügbar: ' . $e->getMessage());
}

// ── Bearer-Token prüfen ─────────────────────────────────────────────────
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    $token = $m[1];
}

if (!defined('REDAXO_API_TOKEN') || $token !== REDAXO_API_TOKEN || empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ── Parameter ───────────────────────────────────────────────────────────
$membershipNo = trim($_GET['membership_no'] ?? '');
if (empty($membershipNo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'membership_no erforderlich']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Mitglied suchen
    $stmtMember = $db->prepare("
        SELECT id, first_name, last_name, membership_no, hide_statistics,
               hide_statistics_locked, hide_statistics_returned_visible
        FROM members
        WHERE membership_no = :membership_no
        LIMIT 1
    ");
    $stmtMember->execute(['membership_no' => $membershipNo]);
    $member = $stmtMember->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Mitglied nicht gefunden']);
        exit;
    }

    $memberId = (int)$member['id'];

    // ── Saison berechnen ────────────────────────────────────────────
    $now      = new DateTime();
    $curMonth = (int)$now->format('m');
    $curYear  = (int)$now->format('Y');
    $defaultSaison = $curMonth >= 10
        ? $curYear . '-' . ($curYear + 1)
        : ($curYear - 1) . '-' . $curYear;

    $selectedSaison = $_GET['saison'] ?? $defaultSaison;
    if (!preg_match('/^\d{4}-\d{4}$/', $selectedSaison)) {
        $selectedSaison = $defaultSaison;
    }

    [$sY1, $sY2]  = explode('-', $selectedSaison);
    $saisonStart   = $sY1 . '-10-01';
    $saisonEnd     = $sY2 . '-09-30';

    // ── Alle Saisonen des Mitglieds ─────────────────────────────────
    $stmtSaisonen = $db->prepare("
        SELECT DISTINCT
            CONCAT(
                YEAR(DATE_SUB(t.start_date, INTERVAL IF(MONTH(t.start_date)>=10,0,1) YEAR)),
                '-',
                YEAR(DATE_SUB(t.start_date, INTERVAL IF(MONTH(t.start_date)>=10,0,1) YEAR))+1
            ) AS saison
        FROM trips t
        JOIN trip_crew tc ON t.id = tc.trip_id
        WHERE tc.member_id = ? AND t.is_completed = 1
        ORDER BY saison DESC
    ");
    $stmtSaisonen->execute([$memberId]);
    $allSaisonen = $stmtSaisonen->fetchAll(PDO::FETCH_COLUMN);

    // ── Stats aktuelle Saison ───────────────────────────────────────
    $stmtStats = $db->prepare("
        SELECT COUNT(DISTINCT t.id) AS fahrten, COALESCE(SUM(t.distance),0) AS km,
               COALESCE(AVG(t.distance),0) AS avg_km
        FROM trips t
        JOIN trip_crew tc ON t.id = tc.trip_id
        WHERE tc.member_id = ? AND t.is_completed = 1
        AND t.start_date BETWEEN ? AND ?
    ");
    $stmtStats->execute([$memberId, $saisonStart, $saisonEnd]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    $stmtStats->closeCursor();

    // ── Stats Vorsaison ─────────────────────────────────────────────
    $lastSaisonStart = date('Y-m-d', strtotime($saisonStart . ' -1 year'));
    $lastSaisonEnd   = date('Y-m-d', strtotime($saisonEnd   . ' -1 year'));
    $stmtStats->execute([$memberId, $lastSaisonStart, $lastSaisonEnd]);
    $statsVJ = $stmtStats->fetch(PDO::FETCH_ASSOC);
    $stmtStats->closeCursor();

    // ── Rangliste ───────────────────────────────────────────────────
    $stmtRang = $db->prepare("
        SELECT m.id,
            CASE WHEN m.hide_statistics=1 THEN 'Versteckter Paddler'
                 ELSE CONCAT(m.first_name,' ',m.last_name) END AS name,
            m.hide_statistics,
            COALESCE(SUM(t.distance),0) AS km,
            COUNT(DISTINCT t.id) AS fahrten
        FROM members m
        JOIN trip_crew tc ON m.id=tc.member_id
        JOIN trips t ON tc.trip_id=t.id
        WHERE t.is_completed=1 AND t.start_date BETWEEN ? AND ?
        GROUP BY m.id ORDER BY km DESC
    ");
    $stmtRang->execute([$saisonStart, $saisonEnd]);
    $rangliste = $stmtRang->fetchAll(PDO::FETCH_ASSOC);

    $meinRang = null;
    foreach ($rangliste as $idx => $r) {
        if ((int)$r['id'] === $memberId) { $meinRang = $idx + 1; break; }
    }

    // Rang Vorsaison
    $rangVJ = null;
    try {
        $stmtRang->execute([$lastSaisonStart, $lastSaisonEnd]);
        $ranglisteVJ = $stmtRang->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ranglisteVJ as $idx => $r) {
            if ((int)$r['id'] === $memberId) { $rangVJ = $idx + 1; break; }
        }
    } catch (\Throwable $e) {}

    // ── Fahrten der Saison ──────────────────────────────────────────
    $stmtFahrten = $db->prepare("
        SELECT t.id, t.start_date, t.start_time, t.end_date, t.end_time,
               COALESCE(t.boat_name, b.boat_name, '–') AS boot_name,
               t.route_custom, t.distance,
               DATE_FORMAT(t.start_date,'%d.%m.%Y') AS datum_fmt,
               EXISTS(SELECT 1 FROM tracker_sessions ts WHERE ts.trip_id = t.id) AS has_track
        FROM trips t
        JOIN trip_crew tc ON t.id = tc.trip_id
        LEFT JOIN boats b ON t.boat_id = b.id
        WHERE tc.member_id = ? AND t.is_completed = 1
        AND t.start_date BETWEEN ? AND ?
        ORDER BY t.start_date DESC, t.start_time DESC
    ");
    $stmtFahrten->execute([$memberId, $saisonStart, $saisonEnd]);
    $fahrten = $stmtFahrten->fetchAll(PDO::FETCH_ASSOC);

    // ── Badges ──────────────────────────────────────────────────────
    $badges       = ['earned' => [], 'new' => []];
    $almostBadges = [];
    $badgeDetails = [];
    if ($badgesAvailable) {
        try {
            $badges       = BadgeChecker::getMemberBadges($memberId, $db);
            $almostBadges = BadgeChecker::getAlmostBadges($memberId, $db);

            // Badge-Details für Frontend (Label, Tier, Milestone, Bild-URL, Kategorie)
            foreach (BadgeRenderer::getAll() as $key => $def) {
                $badgeDetails[$key] = [
                    'label'     => implode(' ', $def['label']),
                    'tier'      => $def['tier'],
                    'ms'        => $def['ms'],
                    'category'  => BadgeRenderer::getBadgeCategory($key),
                    'image_url' => BadgeRenderer::getBadgeImageUrl($key),
                ];
            }
        } catch (\Throwable $e) {
            error_log('[Fahrtenbuch API dashboard] Badge-Fehler: ' . $e->getMessage());
        }
    }

    // ── Chart-Daten: Monats-km ──────────────────────────────────────
    $seasonMonths   = [10,11,12,1,2,3,4,5,6,7,8,9];
    $chartMonthly   = [];
    $chartClubMonthly = [];

    // Mitglied: alle Saisonen monatlich
    $chartAllSeasons = !empty($allSaisonen) ? $allSaisonen : [$selectedSaison];
    foreach ($chartAllSeasons as $cs) {
        if (!preg_match('/^(\d{4})-(\d{4})$/', $cs, $m)) continue;
        $csStart = $m[1] . '-10-01';
        $csEnd   = $m[2] . '-09-30';
        $cStmt = $db->prepare("
            SELECT MONTH(t.start_date) AS m, COALESCE(SUM(t.distance),0) AS km
            FROM trips t
            JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = ? AND t.is_completed = 1
            AND t.start_date BETWEEN ? AND ?
            GROUP BY MONTH(t.start_date)
        ");
        $cStmt->execute([$memberId, $csStart, $csEnd]);
        $rows = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        $cStmt->closeCursor();
        $monthMap = [];
        foreach ($rows as $r) { $monthMap[(int)$r['m']] = round((float)$r['km'], 1); }
        $data = [];
        foreach ($seasonMonths as $sm) { $data[] = $monthMap[$sm] ?? 0; }
        $chartMonthly[$cs] = $data;
    }

    // Verein: aktuelle + 2 Vorsaisonen monatlich
    foreach ([$selectedSaison, ($sY1-1) . '-' . ($sY2-1), ($sY1-2) . '-' . ($sY2-2)] as $cs) {
        if (!preg_match('/^(\d{4})-(\d{4})$/', $cs, $m)) continue;
        $csStart = $m[1] . '-10-01';
        $csEnd   = $m[2] . '-09-30';
        $cStmt = $db->prepare("
            SELECT MONTH(t.start_date) AS m, COALESCE(SUM(t.distance),0) AS km
            FROM trips t
            WHERE t.is_completed = 1
            AND t.start_date BETWEEN ? AND ?
            GROUP BY MONTH(t.start_date)
        ");
        $cStmt->execute([$csStart, $csEnd]);
        $rows = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        $cStmt->closeCursor();
        $monthMap = [];
        foreach ($rows as $r) { $monthMap[(int)$r['m']] = round((float)$r['km'], 1); }
        $data = [];
        foreach ($seasonMonths as $sm) { $data[] = $monthMap[$sm] ?? 0; }
        $chartClubMonthly[$cs] = $data;
    }

    // ── Saison-Übersicht ────────────────────────────────────────────
    $seasonOverview = [];
    $overviewSeasons = !empty($allSaisonen) ? $allSaisonen : [$selectedSaison];
    foreach ($overviewSeasons as $os) {
        if (!preg_match('/^(\d{4})-(\d{4})$/', $os, $om)) continue;
        $osStart = $om[1] . '-10-01';
        $osEnd   = $om[2] . '-09-30';
        $oStmt = $db->prepare("
            SELECT COUNT(DISTINCT t.id) AS fahrten, COALESCE(SUM(t.distance),0) AS km,
                   COALESCE(AVG(t.distance),0) AS avg_km
            FROM trips t
            JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = ? AND t.is_completed = 1
            AND t.start_date BETWEEN ? AND ?
        ");
        $oStmt->execute([$memberId, $osStart, $osEnd]);
        $oRow = $oStmt->fetch(PDO::FETCH_ASSOC);
        $oStmt->closeCursor();
        $seasonOverview[] = [
            'saison'  => $os,
            'km'      => round((float)$oRow['km'], 1),
            'fahrten' => (int)$oRow['fahrten'],
            'avg_km'  => round((float)$oRow['avg_km'], 1),
        ];
    }

    // ── Arbeitsdienst (read-only) ───────────────────────────────────
    $workEntries = [];
    $workTotal   = 0;
    $workYear    = (int)date('Y');
    try {
        $wStmt = $db->prepare("
            SELECT work_date, hours, description, work_leader, created_by, created_at
            FROM work_hours
            WHERE member_id = ? AND YEAR(work_date) = ?
            ORDER BY work_date DESC, created_at DESC
        ");
        $wStmt->execute([$memberId, $workYear]);
        $workEntries = $wStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($workEntries as $we) { $workTotal += (float)$we['hours']; }
    } catch (\Throwable $e) {
        error_log('[Fahrtenbuch API dashboard] Arbeitsdienst-Fehler: ' . $e->getMessage());
    }

    // ── Antwort ─────────────────────────────────────────────────────
    echo json_encode([
        'success' => true,
        'member'  => [
            'id'             => $memberId,
            'first_name'     => $member['first_name'],
            'last_name'      => $member['last_name'],
            'membership_no'  => $member['membership_no'],
            'hide_statistics' => (int)$member['hide_statistics'],
        ],
        'saison' => [
            'selected'  => $selectedSaison,
            'default'   => $defaultSaison,
            'all'       => $allSaisonen,
        ],
        'stats' => [
            'fahrten' => (int)($stats['fahrten'] ?? 0),
            'km'      => round((float)($stats['km'] ?? 0), 1),
            'avg_km'  => round((float)($stats['avg_km'] ?? 0), 1),
        ],
        'stats_vj' => [
            'fahrten' => (int)($statsVJ['fahrten'] ?? 0),
            'km'      => round((float)($statsVJ['km'] ?? 0), 1),
            'avg_km'  => round((float)($statsVJ['avg_km'] ?? 0), 1),
        ],
        'rang'    => $meinRang,
        'rang_vj' => $rangVJ,
        'rangliste'      => $rangliste,
        'fahrten'        => $fahrten,
        'badges'         => $badges,
        'badge_details'  => $badgeDetails,
        'almost_badges'  => $almostBadges,
        'chart_monthly'      => $chartMonthly,
        'chart_club_monthly' => $chartClubMonthly,
        'season_overview'    => $seasonOverview,
        'work' => [
            'year'    => $workYear,
            'total'   => round($workTotal, 1),
            'entries' => $workEntries,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch API dashboard] Fehler: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Interner Fehler']);
}
