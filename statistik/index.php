<?php
/**
 * Fahrtenbuch – Mitglieder-Statistik-Portal
 *
 * Einmaliger Login → Zugriff auf:
 *  - Aktuelle + historische Saisonstatistiken (Navigation per Dropdown)
 *  - Badges & Errungenschaften
 *  - Letzte 3 Fahrten mit Bearbeitungsmöglichkeit (neues Fenster)
 *
 * Auto-Logout: 5 Min Inaktivität → redirect zu /
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Badge-Klassen optional laden – verhindert 500 bei fehlenden Tabellen oder PHP-Fehlern
$badgesAvailable = false;
try {
    require_once __DIR__ . '/../includes/BadgeChecker.php';
    require_once __DIR__ . '/../includes/BadgeRenderer.php';
    $badgesAvailable = true;
} catch (\Throwable $e) {
    error_log('[Fahrtenbuch statistik/index.php] Badge-Klassen konnten nicht geladen werden: ' . $e->getMessage());
}

if (!defined('STATS_SESSION_TIMEOUT')) {
    define('STATS_SESSION_TIMEOUT', 300); // 5 Minuten
}

// ── Auto-Logout prüfen ──────────────────────────────────────────────────────
if (isset($_SESSION['stats_member_id']) && isset($_SESSION['stats_last_activity'])) {
    if (time() - $_SESSION['stats_last_activity'] > STATS_SESSION_TIMEOUT) {
        session_unset();
        header('Location: /');
        exit;
    }
    $_SESSION['stats_last_activity'] = time();
}

// ── Logout ─────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_unset();
    header('Location: /');
    exit;
}

// ── Heartbeat (AJAX-Ping zum Session-Refresh) ──────────────────────────────
if (isset($_GET['heartbeat']) && isset($_SESSION['stats_member_id'])) {
    $_SESSION['stats_last_activity'] = time();
    header('Content-Type: application/json');
    echo '{"ok":true}';
    exit;
}

$error       = null;
$loggedIn    = isset($_SESSION['stats_member_id']);

// ── Login-Verarbeitung ─────────────────────────────────────────────────────
if (!$loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email    = trim($_POST['email'] ?? '');
    $rawPass  = $_POST['password'] ?? '';

    if ($email && $rawPass) {
        try {
            // Authentifizierung via REDAXO-API (Single Source of Truth)
            $apiResult = authenticateViaApi($email, $rawPass);

            if ($apiResult !== false) {
                // API-Auth erfolgreich → Member lokal per membership_no oder email suchen
                $db   = Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    SELECT id, first_name, last_name, membership_no, hide_statistics,
                           hide_statistics_locked, hide_statistics_returned_visible
                    FROM members
                    WHERE ((membership_no = :membership_no AND membership_no != '')
                       OR email = :email)
                    AND (valid_until IS NULL OR valid_until >= CURDATE())
                    LIMIT 1
                ");
                $stmt->execute([
                    'membership_no' => $apiResult['membership_no'] ?? '',
                    'email'         => $email,
                ]);
                $member = $stmt->fetch();

                if ($member) {
                    $_SESSION['stats_member_id']   = (int)$member['id'];
                    $_SESSION['stats_member_name'] = $member['first_name'] . ' ' . $member['last_name'];
                    $_SESSION['stats_member_no']   = $member['membership_no'];
                    $_SESSION['stats_last_activity'] = time();
                    // Login-Zeitstempel speichern
                    try {
                        $db->prepare("UPDATE members SET last_login_stats = NOW() WHERE id = :id")
                           ->execute(['id' => $member['id']]);
                    } catch (\Throwable $e) { /* Spalte noch nicht migriert */ }
                    header('Location: /statistik/');
                    exit;
                } else {
                    $error = 'Dein Account wurde noch nicht synchronisiert. Bitte wende dich an den Vorstand.';
                }
            } else {
                $error = 'Ungültige E-Mail-Adresse oder Passwort.';
            }
        } catch (\Throwable $e) {
            error_log('[Fahrtenbuch statistik login] Fehler: ' . $e->getMessage());
            $error = 'Anmeldung derzeit nicht möglich. Bitte später erneut versuchen.';
        }
    } else {
        $error = 'Bitte E-Mail und Passwort eingeben.';
    }
}

// ── Dashboard-Daten laden (nur wenn eingeloggt) ────────────────────────────
$dashboardData = null;

if ($loggedIn) {
    try {
        $db       = Database::getInstance()->getConnection();
        $memberId = (int)$_SESSION['stats_member_id'];

        // Alle Saisonen bestimmen, in denen das Mitglied Fahrten hat
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

        // Aktuelle Saison als Default
        $now           = new DateTime();
        $curMonth      = (int)$now->format('m');
        $curYear       = (int)$now->format('Y');
        $defaultSaison = $curMonth >= 10
            ? $curYear . '-' . ($curYear + 1)
            : ($curYear - 1) . '-' . $curYear;

        // Angeforderte Saison (aus GET oder Default)
        $selectedSaison = $_GET['saison'] ?? $defaultSaison;
        // Sicherstellen, dass die Saison dem Format YYYY-YYYY entspricht
        if (!preg_match('/^\d{4}-\d{4}$/', $selectedSaison)) {
            $selectedSaison = $defaultSaison;
        }

        // Datumsbereich der gewählten Saison berechnen
        [$sY1, $sY2]  = explode('-', $selectedSaison);
        $saisonStart  = $sY1 . '-10-01';
        $saisonEnd    = $sY2 . '-09-30';

        // Stats für diese Saison
        $stmtStats = $db->prepare("
            SELECT COUNT(DISTINCT t.id) AS fahrten, COALESCE(SUM(t.distance),0) AS km,
                   COALESCE(AVG(t.distance),0) AS avg_km
            FROM trips t
            JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = ? AND t.is_completed = 1
            AND t.start_date BETWEEN ? AND ?
        ");
        $stmtStats->execute([$memberId, $saisonStart, $saisonEnd]);
        $stats = $stmtStats->fetch();
        $stmtStats->closeCursor();

        // Vorjahres-Saison zum Vergleich
        $lastSaisonStart = date('Y-m-d', strtotime($saisonStart . ' -1 year'));
        $lastSaisonEnd   = date('Y-m-d', strtotime($saisonEnd   . ' -1 year'));
        $stmtStats->execute([$memberId, $lastSaisonStart, $lastSaisonEnd]);
        $statsVJ = $stmtStats->fetch();
        $stmtStats->closeCursor();

        // Rangliste dieser Saison
        $stmtRang = $db->prepare("
            SELECT m.id,
                CASE WHEN m.hide_statistics=1 THEN 'Versteckter Paddler'
                     ELSE CONCAT(m.first_name,' ',m.last_name) END AS name,
                m.hide_statistics,
                COALESCE(SUM(t.distance),0) AS km
            FROM members m
            JOIN trip_crew tc ON m.id=tc.member_id
            JOIN trips t ON tc.trip_id=t.id
            WHERE t.is_completed=1 AND t.start_date BETWEEN ? AND ?
            GROUP BY m.id ORDER BY km DESC
        ");
        $stmtRang->execute([$saisonStart, $saisonEnd]);
        $rangliste = $stmtRang->fetchAll();

        $meinRang = null;
        foreach ($rangliste as $idx => $r) {
            if ((int)$r['id'] === $memberId) { $meinRang = $idx + 1; break; }
        }

        // Mitglied-Info (Sichtbarkeit + Benachrichtigungen)
        $stmtMember = $db->prepare("
            SELECT hide_statistics, hide_statistics_locked, hide_statistics_returned_visible,
                   email_notify_after_trip, email_notify_weekly
            FROM members WHERE id = ?
        ");
        $stmtMember->execute([$memberId]);
        $memberInfo = $stmtMember->fetch();

        // Fahrten dieser Saison (für die Tabelle)
        $stmtFahrten = $db->prepare("
            SELECT t.id, t.start_date, t.start_time, t.end_date, t.end_time,
                   COALESCE(t.boat_name, b.boat_name, '–') AS boot_name,
                   t.route_custom, t.distance,
                   DATE_FORMAT(t.start_date,'%d.%m.%Y') AS datum_fmt,
                   t.trip_experiences, t.trip_notes,
                   EXISTS(SELECT 1 FROM tracker_sessions ts WHERE ts.trip_id = t.id) AS has_track
            FROM trips t
            JOIN trip_crew tc ON t.id = tc.trip_id
            LEFT JOIN boats b ON t.boat_id = b.id
            WHERE tc.member_id = ? AND t.is_completed = 1
            AND t.start_date BETWEEN ? AND ?
            ORDER BY t.start_date DESC, t.start_time DESC
        ");
        $stmtFahrten->execute([$memberId, $saisonStart, $saisonEnd]);
        $fahrten = $stmtFahrten->fetchAll();

        // Emoji-Mapping für Erlebnisse
        $expIcons = [
            'alles-super'      => '⭐', 'wind'         => '💨', 'wellen'    => '🌊',
            'sonnenbrand'      => '☀️', 'regen'        => '🌧️', 'gekentert' => '🛶',
            'stechmuecke'      => '🦟', 'nebel'        => '🌫️', 'gegenwind' => '🌬️',
            'neue-strecke'     => '🗺️', 'sehr-anstrengend' => '💪',
            'sonnenaufgang'    => '🌅', 'sonnenuntergang'  => '🌇',
        ];
        $expLabels = [
            'alles-super'      => 'Alles Super',    'wind'         => 'Starker Wind',
            'wellen'           => 'Starke Wellen',  'sonnenbrand'  => 'Sonnenbrand',
            'regen'            => 'Nass durch Regen', 'gekentert'  => 'Gekentert',
            'stechmuecke'      => 'Mückenstiche',   'nebel'        => 'Nebel/Nieselregen',
            'gegenwind'        => 'Gegenwind',       'neue-strecke' => 'Neue Strecke',
            'sehr-anstrengend' => 'Sehr anstrengend',
            'sonnenaufgang'    => 'Sonnenaufgang',  'sonnenuntergang' => 'Sonnenuntergang',
        ];

        // Letzte 3 abgeschlossene Fahrten (über alle Saisonen)
        $stmtLetzte = $db->prepare("
            SELECT t.id, t.start_date, t.start_time, t.end_date, t.end_time,
                   COALESCE(t.boat_name, b.boat_name, '–') AS boot_name,
                   t.route_custom, t.distance,
                   DATE_FORMAT(t.start_date,'%d.%m.%Y') AS datum_fmt
            FROM trips t
            JOIN trip_crew tc ON t.id = tc.trip_id
            LEFT JOIN boats b ON t.boat_id = b.id
            WHERE tc.member_id = ? AND t.is_completed = 1
            ORDER BY t.start_date DESC, t.start_time DESC
            LIMIT 3
        ");
        $stmtLetzte->execute([$memberId]);
        $letztefahrten = $stmtLetzte->fetchAll();

        // Badges (nur wenn Badge-Klassen geladen werden konnten)
        $badges       = ['earned' => [], 'new' => []];
        $badgeHtml    = '';
        $almostBadges = [];
        if ($badgesAvailable) {
            try {
                $badges       = BadgeChecker::getMemberBadges($memberId, $db);
                $badgeHtml    = BadgeRenderer::renderGrid($badges['earned'], $badges['new']);
                $almostBadges = BadgeChecker::getAlmostBadges($memberId, $db);
            } catch (\Throwable $e) {
                error_log('[Fahrtenbuch statistik] Badge-Laden Fehler: ' . $e->getMessage());
            }
        }

        // ── Arbeitsdienst-Daten laden ──────────────────────────────────
        $workEntries  = [];
        $workTotal    = 0;
        $workYear     = (int)date('Y');
        $workSuccess  = null;
        $workError    = null;
        try {
            // Neuen Arbeitseinsatz speichern (falls Formular abgeschickt)
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_work'])) {
                $wDate = $_POST['work_date'] ?? '';
                $wHours = (float)($_POST['hours'] ?? 0);
                $wDesc  = trim($_POST['description'] ?? '');
                if (!empty($wDate) && $wHours > 0 && !empty($wDesc)) {
                    $wStmt = $db->prepare("INSERT INTO work_hours (member_id, work_date, hours, description, created_by) VALUES (?, ?, ?, ?, 'member')");
                    $wStmt->execute([$memberId, $wDate, $wHours, $wDesc]);
                    $workSuccess = 'Arbeitseinsatz erfolgreich erfasst!';
                } else {
                    $workError = 'Bitte alle Felder ausfüllen (Datum, Stunden, Beschreibung).';
                }
            }
            // Arbeitsstunden laden
            $wStmt = $db->prepare("SELECT work_date, hours, description, work_leader, created_by, created_at FROM work_hours WHERE member_id = ? AND YEAR(work_date) = ? ORDER BY work_date DESC, created_at DESC");
            $wStmt->execute([$memberId, $workYear]);
            $workEntries = $wStmt->fetchAll();
            foreach ($workEntries as $we) { $workTotal += (float)$we['hours']; }
        } catch (\Throwable $e) {
            error_log('[Fahrtenbuch statistik] Arbeitsdienst-Laden Fehler: ' . $e->getMessage());
            $workError = 'Arbeitsdienst-Daten konnten nicht geladen werden.';
        }

        // ── Chart-Daten: Monats-km pro Saison ─────────────────────────
        $chartMonthly       = [];  // Mitglied: alle Saisonen monatlich
        $chartClubMonthly   = [];  // Verein: aktuelle + Vor-Saison monatlich
        try {
            // Saison-Monate: Okt(10),Nov(11),Dez(12),Jan(1)..Sep(9)
            $seasonMonths = [10,11,12,1,2,3,4,5,6,7,8,9];

            // Graph 2: Alle Saisonen des Mitglieds (monatlich)
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
                $rows = $cStmt->fetchAll();
                $cStmt->closeCursor();
                $monthMap = [];
                foreach ($rows as $r) { $monthMap[(int)$r['m']] = round((float)$r['km'], 1); }
                $data = [];
                foreach ($seasonMonths as $sm) { $data[] = $monthMap[$sm] ?? 0; }
                $chartMonthly[$cs] = $data;
            }

            // Graph 3: Vereins-Statistik (aktuelle + Vor-Saison + Vor-Vor-Saison monatlich)
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
                $rows = $cStmt->fetchAll();
                $cStmt->closeCursor();
                $monthMap = [];
                foreach ($rows as $r) { $monthMap[(int)$r['m']] = round((float)$r['km'], 1); }
                $data = [];
                foreach ($seasonMonths as $sm) { $data[] = $monthMap[$sm] ?? 0; }
                $chartClubMonthly[$cs] = $data;
            }
        } catch (\Throwable $e) {
            error_log('[Fahrtenbuch statistik] Chart-Daten Fehler: ' . $e->getMessage());
        }

        // ── Saison-Übersicht (letzte Jahre): km, Fahrten, Ø km ─────────
        $seasonOverview = [];
        try {
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
                $oRow = $oStmt->fetch();
                $oStmt->closeCursor();
                $seasonOverview[] = [
                    'saison'  => $os,
                    'km'      => round((float)$oRow['km'], 1),
                    'fahrten' => (int)$oRow['fahrten'],
                    'avg_km'  => round((float)$oRow['avg_km'], 1),
                ];
            }
        } catch (\Throwable $e) {
            error_log('[Fahrtenbuch statistik] Saison-Übersicht Fehler: ' . $e->getMessage());
        }

        // ── Vorjahres-Vergleichsdaten für Stat-Cards ────────────────────
        $fahrtVJ = (int)($statsVJ['fahrten'] ?? 0);
        $avgKmVJ = (float)($statsVJ['avg_km'] ?? 0);

        // Rang in Vorsaison
        $rangVJ = null;
        try {
            $stmtRangVJ = $db->prepare("
                SELECT m.id, COALESCE(SUM(t.distance),0) AS km
                FROM members m
                JOIN trip_crew tc ON m.id=tc.member_id
                JOIN trips t ON tc.trip_id=t.id
                WHERE t.is_completed=1 AND t.start_date BETWEEN ? AND ?
                GROUP BY m.id ORDER BY km DESC
            ");
            $stmtRangVJ->execute([$lastSaisonStart, $lastSaisonEnd]);
            $ranglisteVJ = $stmtRangVJ->fetchAll();
            foreach ($ranglisteVJ as $idx => $r) {
                if ((int)$r['id'] === $memberId) { $rangVJ = $idx + 1; break; }
            }
        } catch (\Throwable $e) {
            error_log('[Fahrtenbuch statistik] Rang-VJ Fehler: ' . $e->getMessage());
        }

        $dashboardData = compact(
            'allSaisonen', 'selectedSaison', 'defaultSaison',
            'stats', 'statsVJ', 'rangliste', 'meinRang',
            'memberInfo', 'fahrten', 'letztefahrten',
            'badges', 'badgeHtml', 'almostBadges', 'memberId',
            'workEntries', 'workTotal', 'workYear', 'workSuccess', 'workError',
            'chartMonthly', 'chartClubMonthly',
            'seasonOverview', 'fahrtVJ', 'avgKmVJ', 'rangVJ',
            'expIcons', 'expLabels'
        );

    } catch (\Throwable $e) {
        error_log('[Fahrtenbuch statistik] Dashboard-Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $dashboardData = ['error' => 'Fehler beim Laden der Daten: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitglieder-Bereich – Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/badges.css">
    <style>
        body { background: #f4f6f9; }
        #idle-timer {
            background: rgba(255,255,255,0.15); color: #fff;
            border-radius: 20px; padding: 3px 10px;
            font-size: 0.78rem; font-family: monospace;
            display: flex; align-items: center; gap: 5px;
            border: 1px solid rgba(255,255,255,0.3);
            transition: background 0.3s;
        }
        #idle-timer.warning { background: rgba(220,53,69,0.85); border-color: #dc3545; }
        .login-wrapper {
            min-height: 100vh; display: flex;
            align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0d6efd 0%, #0a4abf 100%);
        }
        .login-card { max-width: 420px; width: 100%; }

        /* Tab-Navigation */
        .nav-member .nav-link {
            color: #495057; border-radius: 10px; padding: 10px 16px;
            font-weight: 600; font-size: 0.88rem;
            transition: all 0.2s;
        }
        .nav-member .nav-link:hover { background: #e9ecef; }
        .nav-member .nav-link.active {
            background: #0d6efd; color: #fff !important;
            box-shadow: 0 2px 8px rgba(13,110,253,0.3);
        }
        .nav-member .nav-link i { font-size: 1.1rem; }

        /* Stat-Karten */
        .stat-card { border-left: 4px solid #0d6efd; }
        .stat-card .stat-value { font-size: 1.6rem; font-weight: 700; }
        .stat-card.green { border-left-color: #198754; }
        .stat-card.orange { border-left-color: #fd7e14; }
        .stat-card.purple { border-left-color: #6f42c1; }
        .season-nav { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .badges-wrapper { background: #fff; border-radius: 8px; padding: 16px; }
        .trip-row-edit { cursor: pointer; }
        .trip-row-edit:hover { background: #e8f5e9 !important; }
        .tab-pane { animation: fadeIn 0.25s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    </style>
</head>
<body>

<?php if (!$loggedIn): ?>
<!-- ================================================================ -->
<!-- LOGIN                                                             -->
<!-- ================================================================ -->
<div class="login-wrapper">
    <div class="login-card px-3">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white text-center py-4">
                <h3 class="mb-0"><i class="bi bi-person-circle"></i> Mitglieder-Bereich</h3>
                <p class="mb-0 mt-1 opacity-75">Fahrtenbuch</p>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2">
                        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">E-Mail-Adresse</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" name="email"
                                   placeholder="E-Mail Adresse" autocomplete="nope"
                                   readonly onfocus="this.removeAttribute('readonly')"
                                   required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Passwort</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" name="password" placeholder="Passwort" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly')" required>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Anmelden
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center text-muted">
                <small><i class="bi bi-info-circle"></i> Zugangsdaten von der Webseite verwenden</small>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="/" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück zum Fahrtenbuch</a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ================================================================ -->
<!-- DASHBOARD MIT TABS                                                -->
<!-- ================================================================ -->

<!-- Navbar -->
<nav class="navbar navbar-dark bg-primary px-3 py-2">
    <span class="navbar-brand mb-0 h6">
        <i class="bi bi-person-circle"></i> Mitglieder-Bereich
        <span class="ms-2 fw-normal opacity-75">– <?= htmlspecialchars($_SESSION['stats_member_name']) ?></span>
    </span>
    <div class="d-flex align-items-center gap-2">
        <div id="idle-timer" title="Automatischer Logout bei Inaktivität">
            <i class="bi bi-clock"></i>
            <span id="timer-text">5:00</span>
        </div>
        <a href="?logout=1" class="btn btn-outline-light btn-sm">
            <i class="bi bi-box-arrow-right"></i> Abmelden
        </a>
    </div>
</nav>

<div class="container-lg py-3">

    <!-- ── TAB-NAVIGATION ─────────────────────────────────────────────── -->
    <ul class="nav nav-pills nav-member gap-2 mb-4 flex-nowrap overflow-auto pb-1" id="memberTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-stats" data-bs-toggle="pill"
                    data-bs-target="#pane-stats" type="button" role="tab">
                <i class="bi bi-graph-up-arrow"></i> Statistik
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-badges" data-bs-toggle="pill"
                    data-bs-target="#pane-badges" type="button" role="tab">
                <i class="bi bi-award-fill"></i> Errungenschaften
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-rangliste" data-bs-toggle="pill"
                    data-bs-target="#pane-rangliste" type="button" role="tab">
                <i class="bi bi-bar-chart-steps"></i> Rangliste
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-fahrten" data-bs-toggle="pill"
                    data-bs-target="#pane-fahrten" type="button" role="tab">
                <i class="bi bi-list-ul"></i> Fahrten
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-arbeit" data-bs-toggle="pill"
                    data-bs-target="#pane-arbeit" type="button" role="tab">
                <i class="bi bi-tools"></i> Arbeitsdienst
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-boote" data-bs-toggle="pill"
                    data-bs-target="#pane-boote" type="button" role="tab">
                <i class="bi bi-life-preserver"></i> Boote
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-settings" data-bs-toggle="pill"
                    data-bs-target="#pane-settings" type="button" role="tab">
                <i class="bi bi-gear"></i> Einstellungen
            </button>
        </li>
    </ul>

<?php if (!empty($dashboardData['error'])): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($dashboardData['error']) ?></div>
<?php else:
    $d = $dashboardData;
    $km       = (float)$d['stats']['km'];
    $fahrten  = (int)$d['stats']['fahrten'];
    $avgKm    = (float)$d['stats']['avg_km'];
    $kmVJ     = (float)$d['statsVJ']['km'];
    $kmDiff   = $km - $kmVJ;
    $rang     = $d['meinRang'];
    $isHidden = !empty($d['memberInfo']['hide_statistics']);
    $fahrtVJ  = $d['fahrtVJ'] ?? 0;
    $avgKmVJ  = $d['avgKmVJ'] ?? 0;
    $rangVJ   = $d['rangVJ'] ?? null;
    $fahrtDiff = $fahrten - $fahrtVJ;
    $avgKmDiff = $avgKm - $avgKmVJ;
    $rangDiff  = ($rang !== null && $rangVJ !== null) ? ($rangVJ - $rang) : null; // positive = besser
?>

    <div class="tab-content" id="memberTabContent">

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- TAB: STATISTIK                                                  -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <div class="tab-pane fade show active" id="pane-stats" role="tabpanel">

        <?php
        // Saison-Navigator als wiederverwendbare Funktion
        $allOptions = array_unique(array_merge([$d['defaultSaison']], $d['allSaisonen']));
        rsort($allOptions);

        function renderSeasonNav($allOptions, $d, $currentTab = '') {
            $tabHidden = $currentTab ? '<input type="hidden" name="tab" value="' . htmlspecialchars($currentTab) . '">' : '';
            $html = '<div class="card mb-3"><div class="card-body py-2"><div class="season-nav">';
            $html .= '<span class="fw-semibold text-muted"><i class="bi bi-calendar3"></i> Saison:</span>';
            $html .= '<form method="GET" class="d-flex align-items-center gap-2">' . $tabHidden;
            $html .= '<select name="saison" class="form-select form-select-sm" style="max-width:160px;" onchange="this.form.submit()">';
            foreach ($allOptions as $sOpt) {
                $sel = ($sOpt === $d['selectedSaison']) ? 'selected' : '';
                $aktuell = ($sOpt === $d['defaultSaison']) ? ' (aktuell)' : '';
                $html .= '<option value="' . htmlspecialchars($sOpt) . '" ' . $sel . '>' . htmlspecialchars($sOpt) . $aktuell . '</option>';
            }
            $html .= '</select></form>';
            if ($d['selectedSaison'] !== $d['defaultSaison']) {
                $resetUrl = $currentTab ? '/statistik/?tab=' . htmlspecialchars($currentTab) : '/statistik/';
                $html .= '<a href="' . $resetUrl . '" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-clockwise"></i> Aktuelle Saison</a>';
            }
            $html .= '</div></div></div>';
            return $html;
        }
        echo renderSeasonNav($allOptions, $d);
        ?>

        <!-- Stats-Kacheln -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1"><i class="bi bi-water"></i> Kilometer</div>
                        <div class="stat-value"><?= number_format($km, 1, ',', '.') ?></div>
                        <div class="text-muted small">km gepaddelt</div>
                        <?php if ($kmVJ > 0): ?>
                            <div class="mt-1 small <?= $kmDiff >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $kmDiff >= 0 ? '+' : '' ?><?= number_format($kmDiff, 1, ',', '.') ?> km vs. Vorjahr
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card green h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1"><i class="bi bi-flag"></i> Fahrten</div>
                        <div class="stat-value"><?= $fahrten ?></div>
                        <div class="text-muted small">abgeschlossen</div>
                        <?php if ($fahrtVJ > 0): ?>
                            <div class="mt-1 small <?= $fahrtDiff >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $fahrtDiff >= 0 ? '+' : '' ?><?= $fahrtDiff ?> vs. Vorjahr
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card orange h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1"><i class="bi bi-speedometer2"></i> Ø km/Fahrt</div>
                        <div class="stat-value"><?= $fahrten > 0 ? number_format($avgKm, 1, ',', '.') : '–' ?></div>
                        <div class="text-muted small">Durchschnitt</div>
                        <?php if ($avgKmVJ > 0 && $fahrten > 0): ?>
                            <div class="mt-1 small <?= $avgKmDiff >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $avgKmDiff >= 0 ? '+' : '' ?><?= number_format($avgKmDiff, 1, ',', '.') ?> km vs. Vorjahr
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card purple h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1"><i class="bi bi-trophy"></i> Rang</div>
                        <div class="stat-value">
                            <?php if ($rang === 1): ?><span class="text-warning">🥇</span>
                            <?php elseif ($rang === 2): ?><span>🥈</span>
                            <?php elseif ($rang === 3): ?><span>🥉</span>
                            <?php endif; ?>
                            <?= $rang !== null ? '#' . $rang : '–' ?>
                        </div>
                        <div class="text-muted small">in dieser Saison</div>
                        <?php if ($rangDiff !== null): ?>
                            <div class="mt-1 small <?= $rangDiff > 0 ? 'text-success' : ($rangDiff < 0 ? 'text-danger' : 'text-muted') ?>">
                                <?php if ($rangDiff > 0): ?>
                                    <i class="bi bi-arrow-up"></i> <?= $rangDiff ?> Platz<?= $rangDiff > 1 ? 'e' : '' ?> besser
                                <?php elseif ($rangDiff < 0): ?>
                                    <i class="bi bi-arrow-down"></i> <?= abs($rangDiff) ?> Platz<?= abs($rangDiff) > 1 ? 'e' : '' ?> schlechter
                                <?php else: ?>
                                    <i class="bi bi-dash"></i> gleicher Rang
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphen -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header py-2">
                        <small class="fw-bold"><i class="bi bi-graph-up"></i> Saison-Vergleich (Meine km)</small>
                    </div>
                    <div class="card-body py-2">
                        <canvas id="chartSaisonVergleich" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header py-2">
                        <small class="fw-bold"><i class="bi bi-trophy"></i> Verein: Saison-Vergleich</small>
                    </div>
                    <div class="card-body py-2">
                        <canvas id="chartClubVergleich" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php if (count($d['chartMonthly'] ?? []) > 1): ?>
        <div class="card mb-4">
            <div class="card-header py-2">
                <small class="fw-bold"><i class="bi bi-calendar3"></i> Alle Saisonen im Vergleich (Meine km)</small>
            </div>
            <div class="card-body py-2">
                <canvas id="chartAlleSaisonen" height="220"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Letzte 3 Fahrten bearbeiten -->
        <?php if (!empty($d['letztefahrten'])): ?>
        <div class="card">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <span><i class="bi bi-pencil-square"></i> Letzte 3 Fahrten bearbeiten</span>
                <small class="opacity-75">Klick zum Bearbeiten</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Datum</th><th>Boot</th><th>Strecke</th><th>km</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($d['letztefahrten'] as $lf): ?>
                        <tr class="trip-row-edit"
                            onclick="openEditWindow(<?= (int)$lf['id'] ?>)"
                            title="Klicken zum Bearbeiten">
                            <td><?= htmlspecialchars($lf['datum_fmt']) ?></td>
                            <td><?= htmlspecialchars($lf['boot_name']) ?></td>
                            <td class="text-truncate" style="max-width:120px;">
                                <?= htmlspecialchars($lf['route_custom'] ?? '–') ?>
                            </td>
                            <td><?= $lf['distance'] ? number_format((float)$lf['distance'], 1, ',', '.') . ' km' : '–' ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning"
                                        onclick="event.stopPropagation(); openEditWindow(<?= (int)$lf['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Saison-Übersicht Tabelle -->
        <?php if (!empty($d['seasonOverview'])): ?>
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart-line"></i> Saison-Rückblick</span>
                <small class="text-muted"><?= count($d['seasonOverview']) ?> Saisonen</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Saison</th>
                                <th class="text-end">Gepaddelte km</th>
                                <th class="text-end">Fahrten</th>
                                <th class="text-end">Ø km/Fahrt</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($d['seasonOverview'] as $idx => $so):
                            $isCurrent = ($so['saison'] === $d['selectedSaison']);
                            $rowClass  = $isCurrent ? 'table-primary fw-bold' : '';
                        ?>
                            <tr class="<?= $rowClass ?>">
                                <td>
                                    <?= htmlspecialchars($so['saison']) ?>
                                    <?= $isCurrent ? ' <span class="badge bg-primary">aktuell</span>' : '' ?>
                                </td>
                                <td class="text-end"><?= number_format($so['km'], 1, ',', '.') ?> km</td>
                                <td class="text-end"><?= $so['fahrten'] ?></td>
                                <td class="text-end"><?= $so['fahrten'] > 0 ? number_format($so['avg_km'], 1, ',', '.') . ' km' : '–' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /pane-stats -->

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- TAB: BADGES                                                     -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <div class="tab-pane fade" id="pane-badges" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-award-fill text-warning"></i> Errungenschaften
                <?php if ($badgesAvailable): ?>
                <span class="float-end text-muted small">
                    <?= count($d['badges']['earned']) ?> / <?= count(BadgeRenderer::getAllKeys()) ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="card-body badges-wrapper">
                <?php if (!$badgesAvailable): ?>
                    <div class="alert alert-warning py-2 mb-0">
                        <i class="bi bi-exclamation-triangle"></i>
                        Badge-System wird eingerichtet. Bitte wende dich an den Administrator.
                    </div>
                <?php else: ?>
                    <?php if (!empty($d['badges']['new'])): ?>
                        <div class="alert alert-success py-2 mb-3">
                            <i class="bi bi-stars"></i>
                            <strong><?= count($d['badges']['new']) ?> neue<?= count($d['badges']['new'])===1?'':' ' ?> Errungenschaft<?= count($d['badges']['new'])===1?'':'en' ?>!</strong>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($d['almostBadges'])): ?>
                        <div class="card bg-light mb-3">
                            <div class="card-body py-2 px-3">
                                <p class="text-muted small mb-2 fw-bold"><i class="bi bi-hourglass-split"></i> Fast geschafft:</p>
                                <?php foreach ($d['almostBadges'] as $ab):
                                    $barClass = $ab['progress'] >= 90 ? 'bg-success' : ($ab['progress'] >= 80 ? 'bg-warning' : 'bg-info'); ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small class="fw-bold"><?= htmlspecialchars($ab['label']) ?></small>
                                            <small class="text-muted"><?= number_format($ab['current'],0,',','.') ?> / <?= number_format($ab['target'],0,',','.') ?> <?= htmlspecialchars($ab['unit']) ?></small>
                                        </div>
                                        <div class="progress" style="height:7px;border-radius:4px;">
                                            <div class="progress-bar <?= $barClass ?>" style="width:<?= $ab['progress'] ?>%;border-radius:4px;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($d['badges']['earned'])): ?>
                        <p class="text-muted text-center py-3">
                            <i class="bi bi-info-circle"></i> Noch keine Errungenschaften. Starte die erste Fahrt!
                        </p>
                    <?php else: ?>
                        <?= $d['badgeHtml'] ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- /pane-badges -->

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- TAB: RANGLISTE                                                  -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <div class="tab-pane fade" id="pane-rangliste" role="tabpanel">
        <?= renderSeasonNav($allOptions, $d, 'rangliste') ?>
        <?php if ($isHidden): ?>
            <div class="alert alert-secondary">
                <i class="bi bi-eye-slash"></i> Du bist unsichtbar – Rangliste nicht verfügbar.
            </div>
        <?php elseif (!empty($d['rangliste'])): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart-steps"></i> Rangliste <?= htmlspecialchars($d['selectedSaison']) ?></span>
                <span class="badge bg-primary"><?= count($d['rangliste']) ?> Paddler</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:600px; overflow-y:auto;">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr><th style="width:60px;">#</th><th>Name</th><th style="width:100px;" class="text-end">km</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $pos = 0;
                        foreach ($d['rangliste'] as $r):
                            if (!(int)$r['hide_statistics']) $pos++;
                            $istIch  = ((int)$r['id'] === $memberId);
                            $rowCls  = $istIch ? 'table-success fw-bold' : '';
                            $medal   = $pos===1 ? '🥇' : ($pos===2 ? '🥈' : ($pos===3 ? '🥉' : ''));
                        ?>
                            <tr class="<?= $rowCls ?>">
                                <td><?= $pos > 0 ? $pos . ' ' . $medal : '–' ?></td>
                                <td>
                                    <?php if ((int)$r['hide_statistics']): ?>
                                        <em class="text-muted">Versteckter Paddler</em>
                                    <?php else: ?>
                                        <?= htmlspecialchars($r['name']) ?>
                                        <?php if ($istIch): ?> <strong>(ich)</strong><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= number_format((float)$r['km'],1,',','.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
            <div class="alert alert-info"><i class="bi bi-info-circle"></i> Noch keine Daten für diese Saison.</div>
        <?php endif; ?>
    </div><!-- /pane-rangliste -->

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- TAB: FAHRTEN                                                    -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <div class="tab-pane fade" id="pane-fahrten" role="tabpanel">
        <?= renderSeasonNav($allOptions, $d, 'fahrten') ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul"></i> Fahrten <?= htmlspecialchars($d['selectedSaison']) ?></span>
                <span class="badge bg-primary"><?= count($d['fahrten']) ?></span>
            </div>
            <?php if (empty($d['fahrten'])): ?>
                <div class="card-body text-muted text-center py-4">
                    <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                    Keine Fahrten in dieser Saison.
                </div>
            <?php else: ?>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr><th>Datum</th><th>Boot</th><th>Strecke</th><th class="text-end">km</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($d['fahrten'] as $f):
                            $exp   = !empty($f['trip_experiences']) ? (json_decode($f['trip_experiences'], true) ?? []) : [];
                            $notes = trim($f['trip_notes'] ?? '');
                        ?>
                            <tr>
                                <td class="text-nowrap"><?= htmlspecialchars($f['datum_fmt']) ?></td>
                                <td><?= htmlspecialchars($f['boot_name']) ?></td>
                                <td style="max-width:220px;">
                                    <div class="text-truncate"><?= htmlspecialchars($f['route_custom'] ?? '–') ?></div>
                                    <?php if (!empty($exp)): ?>
                                    <div class="mt-1" style="font-size:1rem;line-height:1.4;"><?php
                                        foreach ($exp as $e) {
                                            $icon  = $d['expIcons'][$e]  ?? null;
                                            $label = $d['expLabels'][$e] ?? $e;
                                            if ($icon) echo '<span title="' . htmlspecialchars($label) . '">' . $icon . '</span>';
                                        }
                                    ?></div>
                                    <?php endif; ?>
                                    <?php if ($notes !== ''): ?>
                                    <div class="text-muted small fst-italic text-truncate mt-1" title="<?= htmlspecialchars($notes) ?>">
                                        <i class="bi bi-chat-text"></i> <?= htmlspecialchars($notes) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap"><?= $f['distance'] ? number_format((float)$f['distance'], 1, ',', '.') : '–' ?> km</td>
                                <td class="text-end">
                                    <?php if (!empty($f['has_track'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            title="GPS-Track ansehen"
                                            onclick="openTripTrackModal(<?= (int)$f['id'] ?>, 'Fahrt <?= htmlspecialchars($f['datum_fmt']) ?>')">
                                        <i class="bi bi-map"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div><!-- /pane-fahrten -->

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- TAB: ARBEITSDIENST                                              -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <div class="tab-pane fade" id="pane-arbeit" role="tabpanel">

        <?php if ($d['workSuccess']): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($d['workSuccess']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($d['workError']): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($d['workError']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <!-- Erfassungsformular -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-plus-circle"></i> Arbeitseinsatz erfassen
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/statistik/?tab=arbeit">
                            <div class="mb-3">
                                <label class="form-label">Datum</label>
                                <input type="date" class="form-control" name="work_date"
                                       value="<?= date('Y-m-d') ?>"
                                       max="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Stunden</label>
                                <select class="form-select" name="hours" required>
                                    <option value="">-- Auswählen --</option>
                                    <?php for ($h = 0.5; $h <= 8; $h += 0.5): ?>
                                        <option value="<?= $h ?>"><?= number_format($h, 1, ',', '') ?> h</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Beschreibung</label>
                                <textarea class="form-control" name="description" rows="3"
                                          placeholder="z.B. Bootshalle aufgeräumt, Reparatur..." required></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="add_work" class="btn btn-success">
                                    <i class="bi bi-save"></i> Speichern
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Arbeitsstunden-Übersicht -->
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-clock-history"></i> Arbeitsstunden <?= $d['workYear'] ?></span>
                        <strong><?= number_format($d['workTotal'], 1, ',', '') ?> h</strong>
                    </div>
                    <?php if (empty($d['workEntries'])): ?>
                        <div class="card-body text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            Noch keine Arbeitsstunden für <?= $d['workYear'] ?> erfasst.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height:500px;overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr><th>Datum</th><th>Std.</th><th>Beschreibung</th><th>Erfasst</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($d['workEntries'] as $we): ?>
                                    <tr class="<?= ($we['created_by'] ?? '') === 'admin' ? 'table-warning' : '' ?>">
                                        <td><?= date('d.m.Y', strtotime($we['work_date'])) ?></td>
                                        <td><strong><?= number_format((float)$we['hours'], 1, ',', '') ?> h</strong></td>
                                        <td>
                                            <?= htmlspecialchars($we['description']) ?>
                                            <?php if (!empty($we['work_leader'])): ?>
                                                <br><small class="text-muted"><i class="bi bi-person"></i> <?= htmlspecialchars($we['work_leader']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (($we['created_by'] ?? '') === 'admin'): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-shield-check"></i> Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><i class="bi bi-person"></i> Selbst</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div><!-- /pane-arbeit -->

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- TAB: BOOTSVERWALTUNG                                            -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <div class="tab-pane fade" id="pane-boote" role="tabpanel">
        <div id="boote-container">
            <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                <span class="ms-2 text-muted">Lade Boote...</span>
            </div>
        </div>
    </div><!-- /pane-boote -->

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- TAB: EINSTELLUNGEN                                              -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <div class="tab-pane fade" id="pane-settings" role="tabpanel">
        <?php
        $mi = $d['memberInfo'] ?? [];
        $locked   = !empty($mi['hide_statistics_locked']);
        $hidden   = (int)($mi['hide_statistics'] ?? 0) === 1;
        $returned = !empty($mi['hide_statistics_returned_visible']);
        ?>
        <div class="card">
            <div class="card-header"><i class="bi bi-eye"></i> Sichtbarkeit in der Statistik</div>
            <div class="card-body">
                <?php if ($locked): ?>
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-lock-fill"></i>
                        <strong>Gesperrt:</strong> Dein Sichtbarkeits-Status wurde vom Administrator gesperrt.
                    </div>
                <?php elseif ($hidden && !$returned): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-eye-slash-fill"></i>
                        Du bist aktuell <strong>unsichtbar</strong>. Du kannst dich einmalig wieder einblenden.
                    </div>
                    <form method="POST" action="/statistik/toggle-visibility.php">
                        <input type="hidden" name="action" value="show">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-eye"></i> Wieder einblenden
                        </button>
                    </form>
                <?php elseif (!$hidden && !$returned): ?>
                    <div class="alert alert-success mb-3">
                        <i class="bi bi-eye-fill"></i>
                        Du bist <strong>sichtbar</strong> in der Vereinsstatistik.
                    </div>
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>Achtung:</strong> Du kannst dich <strong>einmalig</strong> ausblenden.
                        Danach ist eine Rückkehr nur einmal möglich, dann wird der Status gesperrt.
                    </div>
                    <form method="POST" action="/statistik/toggle-visibility.php">
                        <input type="hidden" name="action" value="hide">
                        <button type="submit" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-eye-slash"></i> Ausblenden
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i>
                        Deine Sichtbarkeits-Einstellungen sind abgeschlossen.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <!-- E-Mail Benachrichtigung -->
    <div class="card mt-4" style="max-width:600px;">
        <div class="card-header"><i class="bi bi-envelope"></i> E-Mail Benachrichtigung</div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Erhalte nach jeder Fahrt oder einmal wöchentlich (wenn du in der Woche aktiv warst)
                deine Statistik und weitere Informationen per E-Mail.
            </p>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="notify_after_trip"
                    <?= !empty($mi['email_notify_after_trip']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="notify_after_trip">Nach jeder Fahrt</label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="notify_weekly"
                    <?= !empty($mi['email_notify_weekly']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="notify_weekly">Sonntag nach der letzten Fahrt</label>
            </div>
            <button class="btn btn-sm btn-primary" id="saveNotifyBtn">
                <i class="bi bi-check-lg"></i> Speichern
            </button>
            <span id="notifySaveStatus" class="ms-2 small text-success" style="display:none;">Gespeichert!</span>
        </div>
    </div>

    </div><!-- /pane-settings -->

    </div><!-- /tab-content -->

<?php endif; ?>
</div><!-- /container -->

<?php endif; // end logged in ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<?php if ($loggedIn): ?>
<script>
// ── Auto-Logout Timer ───────────────────────────────────────────────────────
(function() {
    const TIMEOUT_MS   = <?= STATS_SESSION_TIMEOUT * 1000 ?>;
    const WARN_MS      = 60000;
    const timerEl      = document.getElementById('timer-text');
    const timerBox     = document.getElementById('idle-timer');
    let remaining      = TIMEOUT_MS;
    let lastHeartbeat  = Date.now();

    function formatTime(ms) {
        const s = Math.max(0, Math.floor(ms / 1000));
        return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
    }
    function tick() {
        remaining -= 1000;
        timerEl.textContent = formatTime(remaining);
        if (remaining <= WARN_MS) timerBox.classList.add('warning');
        if (remaining <= 0) { window.location.href = '/'; }
    }
    function resetTimer() {
        remaining = TIMEOUT_MS;
        timerBox.classList.remove('warning');
        timerEl.textContent = formatTime(remaining);
        const now = Date.now();
        if (now - lastHeartbeat > 30000) {
            lastHeartbeat = now;
            fetch('/statistik/?heartbeat=1').catch(() => {});
        }
    }
    setInterval(tick, 1000);
    timerEl.textContent = formatTime(remaining);
    let lastReset = 0;
    ['mousemove','keydown','click','scroll','touchstart'].forEach(ev => {
        document.addEventListener(ev, () => {
            const now = Date.now();
            if (now - lastReset > 1000) { lastReset = now; resetTimer(); }
        }, { passive: true });
    });
})();

// ── Fahrt-Bearbeitung (Kiosk-kompatibel: Modal statt window.open) ───────────
function openEditWindow(tripId) {
    let modal = document.getElementById('editTripModal');
    if (!modal) {
        const div = document.createElement('div');
        div.innerHTML = `
            <div class="modal fade" id="editTripModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-body p-0">
                            <iframe id="editTripIframe" style="width:100%;height:520px;border:none;border-radius:0.375rem;"></iframe>
                        </div>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(div.firstElementChild);
        modal = document.getElementById('editTripModal');
        modal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('editTripIframe').src = 'about:blank';
            location.reload();
        });
    }
    document.getElementById('editTripIframe').src = '/statistik/bearbeiten.php?trip_id=' + tripId;
    new bootstrap.Modal(modal).show();
}

// ── Tab per URL-Parameter oder Hash steuern ─────────────────────────────────
(function() {
    // ?tab=arbeit oder #tab-arbeit
    const params = new URLSearchParams(window.location.search);
    let tabId = params.get('tab');
    if (!tabId && window.location.hash) {
        tabId = window.location.hash.replace('#tab-', '').replace('#', '');
    }
    if (tabId) {
        const btn = document.getElementById('tab-' + tabId);
        if (btn) {
            new bootstrap.Tab(btn).show();
        }
    }
    // Tab-Wechsel in URL speichern (ohne Reload) – Saison-Parameter beibehalten
    document.querySelectorAll('#memberTabs .nav-link').forEach(btn => {
        btn.addEventListener('shown.bs.tab', function() {
            const id = this.id.replace('tab-', '');
            const p = new URLSearchParams(window.location.search);
            p.set('tab', id);
            history.replaceState(null, '', '?' + p.toString());
        });
    });
})();

// ── Bootsverwaltung laden ───────────────────────────────────────────────────
function loadBoote() {
    fetch('/statistik/boote.php?json=1')
        .then(r => r.ok ? r.json() : Promise.reject(r.statusText || r.status))
        .then(data => {
            const container = document.getElementById('boote-container');
            if (!data.success) {
                container.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> ' +
                    escHtml(data.message || 'Fehler beim Laden') + '</div>';
                return;
            }
            if (!data.boats || data.boats.length === 0) {
                container.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> ' +
                    'Keine eigenen Boote vorhanden. Privatboote werden vom Administrator zugewiesen.</div>';
                return;
            }
            let html = '<div class="row g-3">';
            data.boats.forEach(boat => {
                html += `
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                            <span class="fw-semibold"><i class="bi bi-life-preserver text-warning"></i> ${escHtml(boat.boat_name || '(kein Name)')}</span>
                            <button class="btn btn-sm btn-outline-warning"
                                    onclick="openBoatEdit(${boat.id})"
                                    title="Boot bearbeiten">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                        <div class="card-body py-2 small">
                            ${boat.manufacturer ? `<div class="text-muted">Hersteller: <strong>${escHtml(boat.manufacturer)}</strong></div>` : ''}
                            ${boat.model ? `<div class="text-muted">Modell: <strong>${escHtml(boat.model)}</strong></div>` : ''}
                            ${boat.serial_number ? `<div class="text-muted">Seriennummer: <strong>${escHtml(boat.serial_number)}</strong></div>` : ''}
                            ${boat.purchase_date ? `<div class="text-muted">Kaufdatum: <strong>${escHtml(boat.purchase_date)}</strong></div>` : ''}
                            ${boat.purchase_price ? `<div class="text-muted">Kaufpreis: <strong>${escHtml(boat.purchase_price)} &euro;</strong></div>` : ''}
                        </div>
                    </div>
                </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(err => {
            console.error('[KCS] Boote laden:', err);
            document.getElementById('boote-container').innerHTML =
                '<div class="alert alert-info"><i class="bi bi-info-circle"></i> ' +
                'Bootsverwaltung wird eingerichtet. Bitte versuche es später erneut.</div>';
        });
}
loadBoote(); // Initial laden

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function openBoatEdit(boatId) {
    // Boot-Daten via AJAX laden und in Modal anzeigen (kein window.open, Kiosk-kompatibel)
    fetch('/api/boats/update-own.php?boat_id=' + boatId)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.boat) {
                alert(data.message || 'Fehler beim Laden des Bootes');
                return;
            }
            const b = data.boat;
            // Modal-HTML erstellen
            let modalEl = document.getElementById('boatEditModal');
            if (modalEl) modalEl.remove();

            const html = `
            <div class="modal fade" id="boatEditModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header" style="background:#fd7e14;color:#fff;">
                            <h6 class="modal-title mb-0"><i class="bi bi-life-preserver"></i> Boot bearbeiten</h6>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3 p-2 bg-light rounded border small">
                                <span class="text-muted">Typ:</span> <strong>${escHtml(b.boat_type)}</strong> &nbsp;|&nbsp;
                                <span class="text-muted">Sitze:</span> <strong>${escHtml(String(b.seats))}</strong>
                            </div>
                            <form id="boatEditForm">
                                <input type="hidden" name="boat_id" value="${b.id}">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Bootsname <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="boat_name" value="${escHtml(b.boat_name)}" required>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Hersteller</label>
                                        <input type="text" class="form-control" name="manufacturer" placeholder="z.B. Tiderace, Prijon..." value="${escHtml(b.manufacturer || '')}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Modell</label>
                                        <input type="text" class="form-control" name="model" placeholder="z.B. Xplore-S" value="${escHtml(b.model || '')}">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Seriennummer</label>
                                    <input type="text" class="form-control" name="serial_number" placeholder="Seriennummer / Chassisnummer" value="${escHtml(b.serial_number || '')}">
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Kaufdatum</label>
                                        <input type="date" class="form-control" name="purchase_date" value="${escHtml(b.purchase_date || '')}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Kaufpreis (€)</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="purchase_price" step="0.01" min="0" value="${escHtml(b.purchase_price || '')}">
                                            <span class="input-group-text">€</span>
                                        </div>
                                    </div>
                                </div>
                                <div id="boatEditMsg" class="mt-3"></div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="button" class="btn btn-warning" id="saveBoatEditBtn">
                                <i class="bi bi-floppy"></i> Speichern
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;

            document.body.insertAdjacentHTML('beforeend', html);
            const modal = new bootstrap.Modal(document.getElementById('boatEditModal'));
            modal.show();

            // Speichern-Handler
            document.getElementById('saveBoatEditBtn').addEventListener('click', function() {
                const form = document.getElementById('boatEditForm');
                const formData = new FormData(form);
                const msgEl = document.getElementById('boatEditMsg');

                fetch('/api/boats/update-own.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        msgEl.innerHTML = '<div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> Gespeichert!</div>';
                        setTimeout(() => { modal.hide(); loadBoote(); }, 800);
                    } else {
                        msgEl.innerHTML = '<div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> ' + escHtml(res.message) + '</div>';
                    }
                })
                .catch(err => {
                    msgEl.innerHTML = '<div class="alert alert-danger py-2">Fehler beim Speichern</div>';
                });
            });

            // Cleanup bei Modal-Schließen
            document.getElementById('boatEditModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        })
        .catch(err => {
            console.error('[KCS] Boot laden:', err);
            alert('Fehler beim Laden des Bootes');
        });
}

// ── Statistik-Charts ────────────────────────────────────────────────────────
(function initCharts() {
    if (typeof Chart === 'undefined') return;

    const monthLabels = ['Okt','Nov','Dez','Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep'];
    const chartColors = [
        '#0d6efd','#198754','#fd7e14','#dc3545','#6f42c1',
        '#20c997','#0dcaf0','#ffc107','#e91e8c'
    ];
    const chartOpts = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 11 } } },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(1).replace('.', ',') + ' km';
                    }
                }
            }
        },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'km', font: { size: 11 } } }
        }
    };

    <?php if ($loggedIn && !empty($dashboardData) && empty($dashboardData['error'])): ?>
    const chartMonthly = <?= json_encode($d['chartMonthly'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const chartClub    = <?= json_encode($d['chartClubMonthly'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const selectedSaison = <?= json_encode($d['selectedSaison']) ?>;

    // Graph 1: Aktuelle Saison vs Vorsaison (Mitglied)
    const chart1El = document.getElementById('chartSaisonVergleich');
    if (chart1El) {
        const seasons = Object.keys(chartMonthly);
        const curIdx = seasons.indexOf(selectedSaison);
        const curData = chartMonthly[selectedSaison] || [];
        // Vorsaison berechnen
        const parts = selectedSaison.split('-');
        const prevSaison = (parseInt(parts[0])-1) + '-' + (parseInt(parts[1])-1);
        const prevData = chartMonthly[prevSaison] || [];

        const datasets1 = [];
        if (curData.length) {
            datasets1.push({
                label: selectedSaison,
                data: curData,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                fill: true,
                tension: 0.3,
                borderWidth: 2.5,
                pointRadius: 4
            });
        }
        if (prevData.length && prevData.some(v => v > 0)) {
            datasets1.push({
                label: prevSaison,
                data: prevData,
                borderColor: '#6c757d',
                backgroundColor: 'rgba(108,117,125,0.05)',
                fill: true,
                tension: 0.3,
                borderWidth: 1.5,
                borderDash: [5, 3],
                pointRadius: 3
            });
        }
        if (datasets1.length) {
            new Chart(chart1El, { type: 'line', data: { labels: monthLabels, datasets: datasets1 }, options: chartOpts });
        } else {
            chart1El.parentNode.innerHTML = '<p class="text-muted text-center py-3"><i class="bi bi-info-circle"></i> Noch keine Daten.</p>';
        }
    }

    // Graph 2: Alle Saisonen (Mitglied)
    const chart2El = document.getElementById('chartAlleSaisonen');
    if (chart2El) {
        const allSeasons = Object.keys(chartMonthly).sort();
        const datasets2 = [];
        allSeasons.forEach((s, i) => {
            if (!chartMonthly[s].some(v => v > 0)) return;
            datasets2.push({
                label: s,
                data: chartMonthly[s],
                borderColor: chartColors[i % chartColors.length],
                backgroundColor: 'transparent',
                tension: 0.3,
                borderWidth: s === selectedSaison ? 3 : 1.5,
                borderDash: s === selectedSaison ? [] : [4,2],
                pointRadius: s === selectedSaison ? 4 : 2
            });
        });
        if (datasets2.length) {
            new Chart(chart2El, { type: 'line', data: { labels: monthLabels, datasets: datasets2 }, options: chartOpts });
        }
    }

    // Graph 3: Verein aktuell vs Vorsaison vs Vor-Vorsaison
    const chart3El = document.getElementById('chartClubVergleich');
    if (chart3El) {
        const clubSeasons = Object.keys(chartClub).sort().reverse();
        const clubColors = ['#198754', '#6c757d', '#adb5bd'];
        const clubBg     = ['rgba(25,135,84,0.1)', 'rgba(108,117,125,0.05)', 'rgba(173,181,189,0.03)'];
        const datasets3 = [];
        clubSeasons.forEach((s, i) => {
            if (!chartClub[s].some(v => v > 0)) return;
            datasets3.push({
                label: 'Verein ' + s,
                data: chartClub[s],
                borderColor: clubColors[i] || '#adb5bd',
                backgroundColor: clubBg[i] || 'transparent',
                fill: i === 0,
                tension: 0.3,
                borderWidth: i === 0 ? 2.5 : (i === 1 ? 1.5 : 1),
                borderDash: i === 0 ? [] : (i === 1 ? [5, 3] : [3, 3]),
                pointRadius: i === 0 ? 4 : (i === 1 ? 3 : 2)
            });
        });
        if (datasets3.length) {
            new Chart(chart3El, { type: 'line', data: { labels: monthLabels, datasets: datasets3 }, options: chartOpts });
        } else {
            chart3El.parentNode.innerHTML = '<p class="text-muted text-center py-3"><i class="bi bi-info-circle"></i> Noch keine Daten.</p>';
        }
    }
    <?php endif; ?>
})();

// ── E-Mail Benachrichtigung speichern ─────────────────────────────────────
(function() {
    var btn = document.getElementById('saveNotifyBtn');
    if (!btn) return;
    btn.addEventListener('click', function() {
        btn.disabled = true;
        var fd = new FormData();
        fd.append('member_id',         '<?= (int)$memberId ?>');
        fd.append('notify_after_trip', document.getElementById('notify_after_trip').checked ? '1' : '0');
        fd.append('notify_weekly',     document.getElementById('notify_weekly').checked ? '1' : '0');
        fetch('/api/statistics/notification-settings.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                if (!d.success) { alert('Fehler: ' + d.message); return; }
                var s = document.getElementById('notifySaveStatus');
                s.style.display = '';
                setTimeout(function() { s.style.display = 'none'; }, 2500);
            })
            .catch(function() { btn.disabled = false; alert('Verbindungsfehler'); });
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/modals/trip-track-modal.php'; ?>

</body>
</html>
