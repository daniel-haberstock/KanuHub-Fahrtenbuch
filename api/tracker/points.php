<?php
/**
 * Tracker-API: Messpunkte hochladen
 * POST JSON { session_id, points: [{ts, lat, lon, speed_ms?, hdop?, sats?}, ...] }
 * Content-Encoding: gzip wird akzeptiert.
 */

require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'method', 'message' => 'POST required'], 405);
}

[$db, $tracker, $raw] = trackerAuth();

$in = $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($in) || !isset($in['session_id'], $in['points']) || !is_array($in['points'])) {
    jsonResponse(['error' => 'bad_body', 'message' => 'session_id + points[] required'], 400);
}

$sessionId = (int)$in['session_id'];

// Session verifizieren: muss zu diesem Tracker gehören und offen sein
$s = $db->prepare("SELECT id, tracker_id, status FROM tracker_sessions WHERE id = :id LIMIT 1");
$s->execute(['id' => $sessionId]);
$session = $s->fetch();
if (!$session || (int)$session['tracker_id'] !== (int)$tracker['id']) {
    jsonResponse(['error' => 'session_mismatch', 'message' => 'session does not belong to tracker'], 403);
}
if (!in_array($session['status'], ['assigned','active','finished'], true)) {
    jsonResponse(['error' => 'bad_status', 'message' => 'session not open'], 409);
}

$insert = $db->prepare("
    INSERT IGNORE INTO tracker_points (session_id, ts, lat, lon, speed_ms, hdop, sats)
    VALUES (:sid, :ts, :lat, :lon, :sp, :hd, :sa)
");

$accepted = 0; $rejected = 0;
$db->beginTransaction();
try {
    foreach ($in['points'] as $p) {
        if (!is_array($p) || !isset($p['ts'], $p['lat'], $p['lon'])) { $rejected++; continue; }
        $lat = (float)$p['lat']; $lon = (float)$p['lon'];
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) { $rejected++; continue; }

        // ts darf Unix-Sek/ms oder 'Y-m-d H:i:s(.v)' sein
        $ts = $p['ts'];
        if (is_numeric($ts)) {
            $tsI = (int)$ts;
            if ($tsI > 1e12) { // ms
                $tsStr = date('Y-m-d H:i:s', (int)($tsI / 1000)) . sprintf('.%03d', $tsI % 1000);
            } else {
                $tsStr = date('Y-m-d H:i:s', $tsI) . '.000';
            }
        } else {
            $tsStr = (string)$ts;
        }

        try {
            $insert->execute([
                'sid' => $sessionId,
                'ts'  => $tsStr,
                'lat' => $lat,
                'lon' => $lon,
                'sp'  => isset($p['speed_ms']) ? (float)$p['speed_ms'] : null,
                'hd'  => isset($p['hdop']) ? (float)$p['hdop'] : null,
                'sa'  => isset($p['sats']) ? (int)$p['sats'] : null,
            ]);
            $accepted += $insert->rowCount();
        } catch (\PDOException $e) {
            $rejected++;
        }
    }

    // Session „assigned" -> „active" beim ersten Punkt
    if ($session['status'] === 'assigned' && $accepted > 0) {
        $db->prepare("UPDATE tracker_sessions SET status = 'active' WHERE id = :id")
           ->execute(['id' => $sessionId]);
    }
    if ($tracker['status'] !== 'recording' && $accepted > 0) {
        $db->prepare("UPDATE tracker SET status = 'recording' WHERE id = :id")
           ->execute(['id' => $tracker['id']]);
    }

    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    error_log('[Fahrtenbuch tracker points] ' . $e->getMessage());
    jsonResponse(['error' => 'db_error', 'message' => 'insert failed'], 500);
}

jsonResponse([
    'accepted' => $accepted,
    'rejected' => $rejected,
]);
