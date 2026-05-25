<?php
/**
 * API: Tracker einer Fahrt zuordnen
 * POST trip_id, tracker_id, member_id
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $db = Database::getInstance()->getConnection();

    $tripId    = (int)($_POST['trip_id']    ?? 0);
    $trackerId = (int)($_POST['tracker_id'] ?? 0);
    $memberId  = (int)($_POST['member_id']  ?? 0);

    if (!$tripId || !$trackerId || !$memberId) {
        jsonResponse(['success' => false, 'message' => 'Ungültige Parameter'], 400);
    }

    // Freigabe prüfen
    $m = $db->prepare("SELECT tracker_allowed FROM members WHERE id = :id");
    $m->execute(['id' => $memberId]);
    $member = $m->fetch();
    if (!$member || !$member['tracker_allowed']) {
        jsonResponse(['success' => false, 'message' => 'Keine Tracker-Freigabe'], 403);
    }

    // Tracker verfügbar?
    $cfg = [];
    foreach ($db->query("SELECT k,v FROM app_config WHERE k IN ('tracker_heartbeat_timeout_s','tracker_min_battery_pct')")->fetchAll() as $row) {
        $cfg[$row['k']] = $row['v'];
    }
    $timeout = (int)($cfg['tracker_heartbeat_timeout_s'] ?? 600);
    $minPct  = (int)($cfg['tracker_min_battery_pct']     ?? 30);

    $t = $db->prepare("
        SELECT id FROM tracker
        WHERE id = :tid AND aktiv = 1 AND status = 'idle' AND charging = 0
          AND (battery_pct IS NULL OR battery_pct >= :minpct)
          AND last_seen >= (NOW() - INTERVAL :tout SECOND)
          AND NOT EXISTS (
            SELECT 1 FROM tracker_sessions s
            WHERE s.tracker_id = :tid2 AND s.status IN ('assigned','active')
          )
    ");
    $t->execute([':tid' => $trackerId, ':tid2' => $trackerId, ':minpct' => $minPct, ':tout' => $timeout]);
    if (!$t->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Tracker nicht verfügbar'], 409);
    }

    $nonce = bin2hex(random_bytes(16));

    $db->beginTransaction();

    $db->prepare("
        INSERT INTO tracker_sessions (trip_id, tracker_id, member_id, started_at, status, nonce)
        VALUES (:trip_id, :tracker_id, :member_id, NOW(), 'assigned', :nonce)
    ")->execute([
        'trip_id'    => $tripId,
        'tracker_id' => $trackerId,
        'member_id'  => $memberId,
        'nonce'      => $nonce,
    ]);

    // Fast-Poll für diesen Tracker setzen
    $db->prepare("UPDATE tracker SET fast_poll_until = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = :id")
       ->execute(['id' => $trackerId]);

    $db->commit();

    jsonResponse(['success' => true, 'message' => 'Tracker zugeordnet']);

} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    // Unique-Constraint verletzt = Doppel-Submit
    if ($e->getCode() == 23000) {
        jsonResponse(['success' => true, 'message' => 'Bereits zugeordnet']);
    }
    error_log('[Fahrtenbuch tracker/assign.php] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Serverfehler'], 500);
}
