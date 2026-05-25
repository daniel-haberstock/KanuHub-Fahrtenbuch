<?php
/**
 * API: Badges für ein Mitglied laden – über Mitgliedsnummer (für REDAXO)
 *
 * Authentifizierung: Bearer-Token im Header ODER ?token=... im Query-String.
 * Der Token ist in config.php als REDAXO_API_TOKEN definiert.
 *
 * Parameter:
 *   membership_no  (string, required) – Mitgliedsnummer aus ycom
 *
 * Rückgabe:
 *   {
 *     success: true,
 *     member_id: 42,
 *     earned_count: 5,
 *     total_count: 23,
 *     badge_grid_html: "<div class=\"badges-container\">...</div>",
 *     almost_badges: [...],
 *     badges: [
 *       { key: "1km", label: "1 km", earned: true, awarded_at: "2025-05-01 12:00:00" },
 *       ...
 *     ]
 *   }
 *
 * Verwendung in REDAXO:
 *   $json = file_get_contents(FAHRTENBUCH_BASE_URL . '/api/badges/get-by-membership.php'
 *           . '?token=' . REDAXO_API_TOKEN . '&membership_no=' . urlencode($mitgliedsNr));
 *   $data = json_decode($json, true);
 *   echo $data['badge_grid_html'];
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/BadgeChecker.php';
require_once __DIR__ . '/../../includes/BadgeRenderer.php';

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

// ── Parameter ─────────────────────────────────────────────────────────────
$membershipNo = trim($_GET['membership_no'] ?? '');
if ($membershipNo === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'membership_no erforderlich']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Mitglied über Mitgliedsnummer finden
    $stmt = $db->prepare(
        "SELECT id FROM members WHERE membership_no = ? LIMIT 1"
    );
    $stmt->execute([$membershipNo]);
    $member = $stmt->fetch();

    if (!$member) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Mitglied nicht gefunden']);
        exit;
    }

    $memberId = (int)$member['id'];

    // Verdiente & neue Badges ermitteln
    $badges      = BadgeChecker::getMemberBadges($memberId, $db);
    $earnedKeys  = $badges['earned'];
    $newKeys     = $badges['new'];

    // Fast-Badges (Fortschrittsbalken für "knapp davor")
    $almostBadges = BadgeChecker::getAlmostBadges($memberId, $db);

    // Badge-Grid als fertig gerendertes HTML (Inline-SVG, alle Kategorien)
    $badgeGridHtml = BadgeRenderer::renderGrid($earnedKeys, $newKeys);

    // Flache Badge-Liste mit Metadaten (für eigene Darstellung in REDAXO)
    $allKeys = BadgeRenderer::getAllKeys();
    $earnedSet = array_flip($earnedKeys);

    // awarded_at pro Badge laden
    $stmtDates = $db->prepare(
        "SELECT badge_key, earned_at FROM member_badges WHERE member_id = ?"
    );
    $stmtDates->execute([$memberId]);
    $awardedDates = $stmtDates->fetchAll(PDO::FETCH_KEY_PAIR);

    $badgeList = [];
    foreach ($allKeys as $key) {
        $earned = isset($earnedSet[$key]);
        $badgeList[] = [
            'key'        => $key,
            'label'      => BadgeRenderer::getLabel($key),
            'earned'     => $earned,
            'is_new'     => in_array($key, $newKeys),
            'awarded_at' => $earned ? ($awardedDates[$key] ?? null) : null,
        ];
    }

    echo json_encode([
        'success'         => true,
        'member_id'       => $memberId,
        'earned_count'    => count($earnedKeys),
        'total_count'     => count($allKeys),
        'new_count'       => count($newKeys),
        'badge_grid_html' => $badgeGridHtml,
        'almost_badges'   => $almostBadges,
        'badges'          => $badgeList,
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/badges/get-by-membership.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage() ?: get_class($e)]);
}
