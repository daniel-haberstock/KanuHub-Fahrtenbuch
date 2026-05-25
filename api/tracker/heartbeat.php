<?php
/**
 * Tracker-API: Heartbeat
 * POST JSON { battery_mv, battery_pct, charging, firmware_version, status }
 * Response: { poll_mode, firmware_target, firmware_url?, session? }
 */

require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'method', 'message' => 'POST required'], 405);
}

[$db, $tracker, $raw] = trackerAuth();

$in = $raw !== '' ? json_decode($raw, true) : [];
if (!is_array($in)) { $in = []; }

$batteryMv      = isset($in['battery_mv'])   ? (int)$in['battery_mv']   : null;
$batteryPct     = isset($in['battery_pct'])  ? max(0, min(100, (int)$in['battery_pct'])) : null;
$charging       = !empty($in['charging']) ? 1 : 0;
$firmwareVer    = isset($in['firmware_version']) ? substr((string)$in['firmware_version'], 0, 32) : null;
$reportedStatus = (string)($in['status'] ?? '');

$allowedStatus = ['idle','armed','recording','uploading','offline'];
if (!in_array($reportedStatus, $allowedStatus, true)) {
    $reportedStatus = $tracker['status'];
}

// Optional: letzte bekannte Position (wenn ESP gerade Fix hat)
$lat = isset($in['lat']) && is_numeric($in['lat']) ? (float)$in['lat'] : null;
$lon = isset($in['lon']) && is_numeric($in['lon']) ? (float)$in['lon'] : null;
if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180) {
    $lat = $lon = null;
}

try {
    $upd = $db->prepare("
        UPDATE tracker
           SET battery_mv       = :mv,
               battery_pct      = :pct,
               charging         = :ch,
               firmware_version = COALESCE(:fw, firmware_version),
               status           = :st,
               last_lat         = COALESCE(:lat, last_lat),
               last_lon         = COALESCE(:lon, last_lon),
               last_loc_at      = CASE WHEN :lat2 IS NOT NULL THEN NOW() ELSE last_loc_at END
         WHERE id = :id
    ");
    $upd->execute([
        'mv'   => $batteryMv,
        'pct'  => $batteryPct,
        'ch'   => $charging,
        'fw'   => $firmwareVer,
        'st'   => $reportedStatus,
        'lat'  => $lat,
        'lon'  => $lon,
        'lat2' => $lat,
        'id'   => $tracker['id'],
    ]);
} catch (\Throwable $e) {
    trackerLog('heartbeat-update-fail', [
        'tid' => $tracker['id'],
        'err' => $e->getMessage(),
    ]);
    jsonResponse(['error' => 'db_update', 'message' => $e->getMessage()], 500);
}

try {

// Offene Session?
$s = $db->prepare("
    SELECT id, trip_id, started_at, status, nonce
      FROM tracker_sessions
     WHERE tracker_id = :tid AND status IN ('assigned','active')
     ORDER BY id DESC LIMIT 1
");
$s->execute(['tid' => $tracker['id']]);
$session = $s->fetch();

// Geofence aus app_config
$cfg = [];
foreach ($db->query("SELECT k,v FROM app_config WHERE k IN ('geofence_lat','geofence_lon','geofence_radius_m','tracker_heartbeat_timeout_s')")->fetchAll() as $r) {
    $cfg[$r['k']] = $r['v'];
}

// Poll-Mode bestimmen
$fast = (bool)$db->query("SELECT fast_poll_until > NOW() FROM tracker WHERE id = " . (int)$tracker['id'])->fetchColumn();
if ($session) {
    $pollMode = 'fast';
} elseif ($fast) {
    $pollMode = 'fast';
} elseif ($charging === 1) {
    $pollMode = 'save';
} else {
    $pollMode = 'normal';
}

$resp = [
    'poll_mode'       => $pollMode,
    'server_time'     => time(),
    'firmware_target' => $tracker['firmware_target'] ?: null,
    'geofence' => [
        'lat'      => isset($cfg['geofence_lat']) ? (float)$cfg['geofence_lat'] : null,
        'lon'      => isset($cfg['geofence_lon']) ? (float)$cfg['geofence_lon'] : null,
        'radius_m' => isset($cfg['geofence_radius_m']) ? (int)$cfg['geofence_radius_m'] : 50,
    ],
];

if ($tracker['firmware_target'] && $tracker['firmware_target'] !== $firmwareVer) {
    $resp['firmware_url'] = '/api/tracker/firmware.php?version=' . rawurlencode($tracker['firmware_target']);
}

if ($session) {
    $resp['session'] = [
        'id'         => (int)$session['id'],
        'trip_id'    => (int)$session['trip_id'],
        'status'     => $session['status'],
        'started_at' => $session['started_at'],
        'nonce'      => $session['nonce'],
    ];
} else {
    $resp['session'] = null;
}

jsonResponse($resp);

} catch (\Throwable $e) {
    trackerLog('heartbeat-fail', [
        'tid' => $tracker['id'] ?? null,
        'err' => $e->getMessage(),
        'line'=> $e->getLine(),
        'file'=> basename($e->getFile()),
    ]);
    jsonResponse(['error' => 'server', 'message' => $e->getMessage(), 'line' => $e->getLine()], 500);
}
