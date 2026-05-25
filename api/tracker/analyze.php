<?php
/**
 * Tracker-Analyse: Distanz, Dauer, Max-/Avg-Speed, beste 250/500/1000/1500/2000 m.
 *
 * Nutzung:
 *   - Als Klasse: TrackAnalyzer::run($db, $sessionId)
 *   - CLI: php analyze.php --session=42
 *          php analyze.php --all
 */

if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/../../config/database.php';
}

class TrackAnalyzer
{
    // Plausibilitaet Kajak/SUP: 15 m/s = 54 km/h -> alles darueber = GPS-Ausreisser
    const MAX_PLAUSIBLE_MS = 15.0;
    // Zeitsprung der ein Segment unterbricht (z.B. GPS-Luecke beim Reinfallen)
    const MAX_SEGMENT_GAP_S = 30.0;
    const BEST_DISTANCES = [250, 500, 1000, 1500, 2000];

    /**
     * Berechnet Kennzahlen und schreibt sie in tracker_stats.
     * @return array{distance_m:float,duration_s:int,count:int}
     */
    public static function run(PDO $db, int $sessionId): array
    {
        $rows = $db->prepare(
            "SELECT UNIX_TIMESTAMP(ts) + MICROSECOND(ts)/1e6 AS t,
                    lat, lon, speed_ms
               FROM tracker_points
              WHERE session_id = :sid
              ORDER BY ts ASC, id ASC"
        );
        $rows->execute(['sid' => $sessionId]);
        $pts = $rows->fetchAll(PDO::FETCH_ASSOC);

        $n = count($pts);
        if ($n < 2) {
            self::persist($db, $sessionId, 0.0, 0, null, null, array_fill(0, 5, null));
            return ['distance_m' => 0.0, 'duration_s' => 0, 'count' => $n];
        }

        $clean = self::filterOutliers($pts);
        $m = count($clean);
        if ($m < 2) {
            self::persist($db, $sessionId, 0.0, 0, null, null, array_fill(0, 5, null));
            return ['distance_m' => 0.0, 'duration_s' => 0, 'count' => $m];
        }

        // Kumulative Distanz + geglaettete Geschwindigkeit
        $cum = [0.0];
        $t   = [(float)$clean[0]['t']];
        $v   = [0.0];
        for ($i = 1; $i < $m; $i++) {
            $d   = self::haversine(
                (float)$clean[$i-1]['lat'], (float)$clean[$i-1]['lon'],
                (float)$clean[$i]['lat'],   (float)$clean[$i]['lon']
            );
            $dt  = (float)$clean[$i]['t'] - (float)$clean[$i-1]['t'];
            $cum[$i] = $cum[$i-1] + $d;
            $t[$i]   = (float)$clean[$i]['t'];
            $vstep   = ($dt > 0) ? $d / $dt : 0.0;
            $v[$i]   = ($clean[$i]['speed_ms'] !== null) ? (float)$clean[$i]['speed_ms'] : $vstep;
        }
        // Median-3-Glaettung
        $vs = $v;
        for ($i = 1; $i < $m - 1; $i++) {
            $a = [$v[$i-1], $v[$i], $v[$i+1]]; sort($a);
            $vs[$i] = $a[1];
        }

        $distance = end($cum);
        $duration = (int)round($t[$m-1] - $t[0]);
        $avg      = $duration > 0 ? $distance / $duration : 0.0;
        $max      = max($vs);

        $best = [];
        foreach (self::BEST_DISTANCES as $D) {
            $best[] = self::fastestSegment($cum, $t, (float)$D);
        }

        self::persist($db, $sessionId, $distance, $duration, $max, $avg, $best);
        return ['distance_m' => $distance, 'duration_s' => $duration, 'count' => $m];
    }

    private static function filterOutliers(array $pts): array
    {
        $out = [$pts[0]];
        $last = $pts[0];
        for ($i = 1; $i < count($pts); $i++) {
            $dt = (float)$pts[$i]['t'] - (float)$last['t'];
            if ($dt <= 0) continue;
            $d = self::haversine(
                (float)$last['lat'], (float)$last['lon'],
                (float)$pts[$i]['lat'], (float)$pts[$i]['lon']
            );
            if (($d / $dt) > self::MAX_PLAUSIBLE_MS) continue;
            $out[]  = $pts[$i];
            $last   = $pts[$i];
        }
        return $out;
    }

    /**
     * Schnellstes Segment der Laenge D. Beruecksichtigt Zeitluecken > MAX_SEGMENT_GAP_S
     * indem es den Track virtuell an diesen Stellen teilt.
     */
    private static function fastestSegment(array $cum, array $t, float $D): ?float
    {
        $n = count($cum);
        if ($n < 2 || end($cum) < $D) return null;

        // Subsegmente an Zeitluecken
        $starts = [0];
        for ($i = 1; $i < $n; $i++) {
            if (($t[$i] - $t[$i-1]) > self::MAX_SEGMENT_GAP_S) $starts[] = $i;
        }
        $starts[] = $n;

        $best = INF;
        for ($k = 0; $k < count($starts) - 1; $k++) {
            $a = $starts[$k]; $b = $starts[$k+1]; // Subsegment [a, b)
            if ($b - $a < 2) continue;
            $j = $a;
            for ($i = $a; $i < $b; $i++) {
                if ($j < $i) $j = $i;
                while ($j < $b && ($cum[$j] - $cum[$i]) < $D) $j++;
                if ($j >= $b) break;
                $excess = ($cum[$j] - $cum[$i]) - $D;
                $segDt  = $t[$j] - $t[$j-1];
                $segDs  = $cum[$j] - $cum[$j-1];
                $tj_adj = $t[$j] - ($segDs > 0 ? ($excess / $segDs) * $segDt : 0.0);
                $dt     = $tj_adj - $t[$i];
                if ($dt > 0 && $dt < $best) $best = $dt;
            }
        }
        return $best === INF ? null : $best;
    }

    private static function persist(PDO $db, int $sid, float $dist, int $dur,
                                    ?float $max, ?float $avg, array $best): void
    {
        $stmt = $db->prepare(
            "REPLACE INTO tracker_stats
             (session_id, distance_m, duration_s, max_speed_ms, avg_speed_ms,
              best_250m_s, best_500m_s, best_1000m_s, best_1500m_s, best_2000m_s, computed_at)
             VALUES (:sid, :dist, :dur, :max, :avg,
                     :b250, :b500, :b1000, :b1500, :b2000, NOW())"
        );
        $stmt->execute([
            'sid'   => $sid,
            'dist'  => $dist,
            'dur'   => $dur,
            'max'   => $max,
            'avg'   => $avg,
            'b250'  => $best[0] ?? null,
            'b500'  => $best[1] ?? null,
            'b1000' => $best[2] ?? null,
            'b1500' => $best[3] ?? null,
            'b2000' => $best[4] ?? null,
        ]);
    }

    public static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000.0;
        $phi1 = deg2rad($lat1); $phi2 = deg2rad($lat2);
        $dphi = deg2rad($lat2 - $lat1);
        $dlam = deg2rad($lon2 - $lon1);
        $a = sin($dphi/2) ** 2 + cos($phi1) * cos($phi2) * sin($dlam/2) ** 2;
        return 2 * $R * asin(min(1.0, sqrt($a)));
    }
}

// ===== CLI-Einstieg =====
if (PHP_SAPI === 'cli') {
    $opts = getopt('', ['session::', 'all', 'help']);
    if (isset($opts['help'])) {
        fwrite(STDOUT, "Usage: php analyze.php --session=<id> | --all\n");
        exit(0);
    }
    $db = Database::getInstance()->getConnection();

    if (isset($opts['all'])) {
        $ids = $db->query("SELECT id FROM tracker_sessions WHERE status IN ('finished','aborted') ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $sid) {
            $r = TrackAnalyzer::run($db, (int)$sid);
            printf("session=%d  dist=%.1fm  dur=%ds  n=%d\n",
                $sid, $r['distance_m'], $r['duration_s'], $r['count']);
        }
        exit(0);
    }
    if (isset($opts['session'])) {
        $sid = (int)$opts['session'];
        $r = TrackAnalyzer::run($db, $sid);
        printf("session=%d  dist=%.1fm  dur=%ds  n=%d\n",
            $sid, $r['distance_m'], $r['duration_s'], $r['count']);
        exit(0);
    }
    fwrite(STDERR, "Missing --session or --all\n");
    exit(1);
}
