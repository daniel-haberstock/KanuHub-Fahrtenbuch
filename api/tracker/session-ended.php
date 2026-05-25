<?php
/**
 * Tracker-API: Session beendet (Tracker ist zurück im Geofence, Upload fertig)
 * POST JSON { session_id }
 */

require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'method', 'message' => 'POST required'], 405);
}

[$db, $tracker, $raw] = trackerAuth();

$in = $raw !== '' ? json_decode($raw, true) : null;
$sessionId = (int)($in['session_id'] ?? 0);
if ($sessionId <= 0) {
    jsonResponse(['error' => 'bad_body', 'message' => 'session_id required'], 400);
}

$s = $db->prepare("SELECT id, tracker_id, status FROM tracker_sessions WHERE id = :id LIMIT 1");
$s->execute(['id' => $sessionId]);
$session = $s->fetch();
if (!$session || (int)$session['tracker_id'] !== (int)$tracker['id']) {
    jsonResponse(['error' => 'session_mismatch', 'message' => 'session does not belong to tracker'], 403);
}

$db->beginTransaction();
try {
    if ($session['status'] !== 'finished' && $session['status'] !== 'aborted') {
        $db->prepare("UPDATE tracker_sessions SET status = 'finished', ended_at = COALESCE(ended_at, NOW()) WHERE id = :id")
           ->execute(['id' => $sessionId]);
    }
    $db->prepare("UPDATE tracker SET status = 'idle' WHERE id = :id")
       ->execute(['id' => $tracker['id']]);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    error_log('[Fahrtenbuch tracker session-ended] ' . $e->getMessage());
    jsonResponse(['error' => 'db_error', 'message' => 'update failed'], 500);
}

// Step 07: Analyse synchron - tracker_points liegen jetzt vollstaendig vor
require_once __DIR__ . '/analyze.php';
try {
    $res = TrackAnalyzer::run($db, $sessionId);
    jsonResponse(['ok' => true, 'stats' => $res]);
} catch (\Throwable $e) {
    trackerLog('analyze-fail', ['sid' => $sessionId, 'err' => $e->getMessage()]);
    jsonResponse(['ok' => true, 'stats' => null]); // Session-Ende trotzdem OK
}
