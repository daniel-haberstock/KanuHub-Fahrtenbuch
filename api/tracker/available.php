<?php
/**
 * API: Verfügbare Tracker für ein Mitglied abfragen
 * GET ?member_id=X
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Nur GET erlaubt'], 405);
}

try {
    $db = Database::getInstance()->getConnection();

    $memberId = (int)($_GET['member_id'] ?? 0);
    if (!$memberId) {
        jsonResponse(['allowed' => false, 'trackers' => []]);
    }

    // Tracker-Freigabe prüfen
    $m = $db->prepare("SELECT tracker_allowed FROM members WHERE id = :id");
    $m->execute(['id' => $memberId]);
    $member = $m->fetch();

    if (!$member || !$member['tracker_allowed']) {
        jsonResponse(['allowed' => false, 'trackers' => []]);
    }

    // Konfiguration
    $cfg = [];
    foreach ($db->query("SELECT k,v FROM app_config WHERE k IN ('tracker_heartbeat_timeout_s','tracker_min_battery_pct')")->fetchAll() as $row) {
        $cfg[$row['k']] = $row['v'];
    }
    $timeout = (int)($cfg['tracker_heartbeat_timeout_s'] ?? 600);
    $minPct  = (int)($cfg['tracker_min_battery_pct']     ?? 30);

    // Verfügbare Tracker
    $stmt = $db->prepare("
        SELECT t.id, t.nummer, t.battery_pct, t.battery_mv, t.charging, t.last_seen
        FROM tracker t
        WHERE t.aktiv = 1
          AND t.status = 'idle'
          AND (t.battery_pct IS NULL OR t.battery_pct >= :minpct)
          AND t.charging = 0
          AND t.last_seen >= (NOW() - INTERVAL :tout SECOND)
          AND NOT EXISTS (
            SELECT 1 FROM tracker_sessions s
            WHERE s.tracker_id = t.id AND s.status IN ('assigned','active')
          )
        ORDER BY t.battery_pct DESC, t.nummer ASC
    ");
    $stmt->execute([':minpct' => $minPct, ':tout' => $timeout]);
    $trackers = $stmt->fetchAll();

    // Trigger fast-poll für alle idle Tracker
    $db->query("UPDATE tracker SET fast_poll_until = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE aktiv = 1 AND status = 'idle'");

    jsonResponse([
        'allowed'  => true,
        'trackers' => array_map(function($t) {
            return [
                'id'          => (int)$t['id'],
                'nummer'      => $t['nummer'],
                'battery_pct' => $t['battery_pct'] !== null ? (int)$t['battery_pct'] : null,
                'battery_mv'  => $t['battery_mv']  !== null ? (int)$t['battery_mv']  : null,
            ];
        }, $trackers),
    ]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch tracker/available.php] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Serverfehler'], 500);
}
