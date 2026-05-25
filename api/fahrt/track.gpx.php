<?php
/**
 * GPX-Export einer Fahrt (Tracker-Session).
 * GET ?trip_id=...
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$tripId = (int)($_GET['trip_id'] ?? $_GET['fahrt_id'] ?? 0);
if ($tripId <= 0) { http_response_code(400); exit('trip_id required'); }

$db = Database::getInstance()->getConnection();
$s = $db->prepare("SELECT id FROM tracker_sessions WHERE trip_id = :t ORDER BY id DESC LIMIT 1");
$s->execute(['t' => $tripId]);
$sid = (int)($s->fetchColumn() ?: 0);
if (!$sid) { http_response_code(404); exit('no session'); }

$p = $db->prepare("SELECT ts, lat, lon, speed_ms
                     FROM tracker_points WHERE session_id = :s ORDER BY ts ASC, id ASC");
$p->execute(['s' => $sid]);
$points = $p->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/gpx+xml; charset=utf-8');
header('Content-Disposition: attachment; filename="fahrt_' . $tripId . '.gpx"');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<gpx version="1.1" creator="Fahrtenbuch"
     xmlns="http://www.topografix.com/GPX/1/1"
     xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
     xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">
  <metadata><name>Fahrt <?= (int)$tripId ?></name><time><?= gmdate('Y-m-d\TH:i:s\Z') ?></time></metadata>
  <trk>
    <name>Fahrt <?= (int)$tripId ?></name>
    <trkseg>
<?php foreach ($points as $pt): ?>
      <trkpt lat="<?= htmlspecialchars($pt['lat']) ?>" lon="<?= htmlspecialchars($pt['lon']) ?>">
        <time><?= gmdate('Y-m-d\TH:i:s\Z', strtotime($pt['ts'])) ?></time>
<?php if ($pt['speed_ms'] !== null): ?>
        <extensions><speed><?= htmlspecialchars($pt['speed_ms']) ?></speed></extensions>
<?php endif; ?>
      </trkpt>
<?php endforeach; ?>
    </trkseg>
  </trk>
</gpx>
