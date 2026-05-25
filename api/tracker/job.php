<?php
/**
 * Tracker-API: aktuelle Session + Geofence abrufen
 * GET
 */

require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'method', 'message' => 'GET required'], 405);
}

[$db, $tracker] = trackerAuth();

$s = $db->prepare("
    SELECT id, trip_id, member_id, started_at, status, nonce
      FROM tracker_sessions
     WHERE tracker_id = :tid AND status IN ('assigned','active')
     ORDER BY id DESC LIMIT 1
");
$s->execute(['tid' => $tracker['id']]);
$session = $s->fetch();

$cfg = [];
foreach ($db->query("SELECT k,v FROM app_config WHERE k IN ('geofence_lat','geofence_lon','geofence_radius_m')")->fetchAll() as $r) {
    $cfg[$r['k']] = $r['v'];
}

jsonResponse([
    'server_time' => time(),
    'geofence' => [
        'lat'      => isset($cfg['geofence_lat']) ? (float)$cfg['geofence_lat'] : null,
        'lon'      => isset($cfg['geofence_lon']) ? (float)$cfg['geofence_lon'] : null,
        'radius_m' => isset($cfg['geofence_radius_m']) ? (int)$cfg['geofence_radius_m'] : 50,
    ],
    'session' => $session ? [
        'id'         => (int)$session['id'],
        'trip_id'    => (int)$session['trip_id'],
        'status'     => $session['status'],
        'started_at' => $session['started_at'],
        'nonce'      => $session['nonce'],
    ] : null,
]);
