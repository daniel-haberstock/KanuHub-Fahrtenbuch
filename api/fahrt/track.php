<?php
/**
 * API: Tracking-Daten einer Fahrt als GeoJSON + Stats + beste Segmente.
 * GET ?trip_id=... (oder ?fahrt_id=... als Alias)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$tripId = (int)($_GET['trip_id'] ?? $_GET['fahrt_id'] ?? 0);
if ($tripId <= 0) {
    jsonResponse(['error' => 'bad_param', 'message' => 'trip_id required'], 400);
}

try {
    $db = Database::getInstance()->getConnection();

    $s = $db->prepare("SELECT id, status, started_at, ended_at FROM tracker_sessions WHERE trip_id = :t ORDER BY id DESC LIMIT 1");
    $s->execute(['t' => $tripId]);
    $session = $s->fetch();

    if (!$session) {
        jsonResponse(['error' => 'no_session', 'message' => 'keine Tracking-Session fuer diese Fahrt'], 404);
    }
    $sid = (int)$session['id'];

    $p = $db->prepare("SELECT ts, lat, lon, speed_ms, sats
                         FROM tracker_points
                        WHERE session_id = :s
                        ORDER BY ts ASC, id ASC");
    $p->execute(['s' => $sid]);
    $points = $p->fetchAll(PDO::FETCH_ASSOC);

    $coords = [];
    $speeds = [];
    $times  = [];
    foreach ($points as $pt) {
        $coords[] = [round((float)$pt['lon'], 7), round((float)$pt['lat'], 7)];
        $speeds[] = $pt['speed_ms'] !== null ? (float)$pt['speed_ms'] : null;
        $times[]  = $pt['ts'];
    }

    $st = $db->prepare("SELECT distance_m, duration_s, max_speed_ms, avg_speed_ms,
                              best_250m_s, best_500m_s, best_1000m_s, best_1500m_s, best_2000m_s,
                              computed_at
                         FROM tracker_stats WHERE session_id = :s");
    $st->execute(['s' => $sid]);
    $stats = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    $segments = [];
    if ($stats && count($points) >= 2) {
        require_once __DIR__ . '/../tracker/analyze.php';
        $cum = [0.0];
        $t   = [strtotime($points[0]['ts'])];
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $d = TrackAnalyzer::haversine(
                (float)$points[$i-1]['lat'], (float)$points[$i-1]['lon'],
                (float)$points[$i]['lat'],   (float)$points[$i]['lon']
            );
            $cum[$i] = $cum[$i-1] + $d;
            $t[$i]   = strtotime($points[$i]['ts']);
        }
        foreach ([250, 500, 1000, 1500, 2000] as $D) {
            $seg = findBestSegmentIdx($cum, $t, (float)$D);
            if ($seg) $segments[(string)$D] = $seg;
        }
    }

    jsonResponse([
        'session' => [
            'id'         => $sid,
            'status'     => $session['status'],
            'started_at' => $session['started_at'],
            'ended_at'   => $session['ended_at'],
            'point_count'=> count($coords),
        ],
        'stats' => $stats,
        'track' => [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $coords,
            ],
            'properties' => [
                'speeds'     => $speeds,
                'timestamps' => $times,
            ],
        ],
        'segments' => $segments,
    ]);
} catch (\Throwable $e) {
    error_log('[Fahrtenbuch fahrt/track] ' . $e->getMessage());
    jsonResponse(['error' => 'server', 'message' => $e->getMessage()], 500);
}

/**
 * Liefert das schnellste Segment der Laenge D als {start_idx, end_idx, duration_s}.
 */
function findBestSegmentIdx(array $cum, array $t, float $D): ?array
{
    $n = count($cum);
    if ($n < 2 || end($cum) < $D) return null;
    $best = INF; $bestA = -1; $bestB = -1;
    $j = 0;
    for ($i = 0; $i < $n; $i++) {
        if ($j < $i) $j = $i;
        while ($j < $n && ($cum[$j] - $cum[$i]) < $D) $j++;
        if ($j >= $n) break;
        $dt = $t[$j] - $t[$i];
        if ($dt > 0 && $dt < $best) { $best = $dt; $bestA = $i; $bestB = $j; }
    }
    if ($bestA < 0) return null;
    return ['start_idx' => $bestA, 'end_idx' => $bestB, 'duration_s' => $best];
}
