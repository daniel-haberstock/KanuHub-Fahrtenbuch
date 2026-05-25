<?php
/**
 * API: Tracker-Session beim Fahrt-Ende freigeben
 * POST trip_id
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $db = Database::getInstance()->getConnection();
    $tripId = (int)($_POST['trip_id'] ?? 0);
    if (!$tripId) {
        jsonResponse(['success' => false, 'message' => 'trip_id fehlt'], 400);
    }

    $sess = $db->prepare("SELECT id, tracker_id, status FROM tracker_sessions WHERE trip_id = :tid AND status IN ('assigned','active')");
    $sess->execute(['tid' => $tripId]);
    $session = $sess->fetch();

    if (!$session) {
        jsonResponse(['success' => true, 'message' => 'Keine aktive Session']);
    }

    $db->beginTransaction();

    $db->prepare("UPDATE tracker_sessions SET status = 'finished', ended_at = NOW() WHERE id = :id")
       ->execute(['id' => $session['id']]);

    // Tracker erst auf idle wenn Daten hochgeladen – bis dahin Status beibehalten.
    // Falls Session noch 'assigned' (nie ARMED geworden): direkt idle.
    if ($session['status'] === 'assigned') {
        $db->prepare("UPDATE tracker SET status = 'idle', fast_poll_until = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = :id")
           ->execute(['id' => $session['tracker_id']]);
    } else {
        // Tracker macht Upload und setzt sich dann selbst auf idle via session-ended.
        // Wir setzen fast_poll_until damit er schnell reagiert.
        $db->prepare("UPDATE tracker SET fast_poll_until = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = :id")
           ->execute(['id' => $session['tracker_id']]);
    }

    $db->commit();

    jsonResponse(['success' => true]);

} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('[Fahrtenbuch tracker/finish.php] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Serverfehler'], 500);
}
