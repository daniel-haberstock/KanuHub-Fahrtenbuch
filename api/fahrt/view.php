<?php
/**
 * Oeffentlicher Fahrt-Track-Viewer (fuer Redaxo-Einbettung).
 * URL: /api/fahrt/view.php?id=<trip_id>
 * Liegt ausserhalb des htpasswd-Schutzes (siehe api/.htaccess).
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$tripId = (int)($_GET['id'] ?? $_GET['trip_id'] ?? 0);
if ($tripId <= 0) { http_response_code(400); exit('trip_id required'); }

$db = Database::getInstance()->getConnection();
$trip = $db->prepare("SELECT t.id, t.start_date, t.distance,
                             COALESCE(b.boat_name, t.boat_name) AS boat_name
                        FROM trips t LEFT JOIN boats b ON b.id = t.boat_id
                       WHERE t.id = :id LIMIT 1");
$trip->execute(['id' => $tripId]);
$trip = $trip->fetch();
if (!$trip) { http_response_code(404); exit('Fahrt nicht gefunden'); }

$hasSession = (int)$db->query("SELECT COUNT(*) FROM tracker_sessions WHERE trip_id = " . $tripId)->fetchColumn() > 0;
// Allow iframe-embedding (default X-Frame-Options blocks it)
header_remove('X-Frame-Options');
header('Content-Security-Policy: frame-ancestors *');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fahrt <?= (int)$trip['id'] ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  body { margin:0; background:#fff; font-family:system-ui,sans-serif; }
  #track-map { height: 60vh; min-height: 360px; }
  #track-stats .stat-row {
    display:flex; justify-content:space-between;
    padding:.4rem .75rem; border-bottom:1px solid #f1f1f1;
  }
  #track-stats .label { color:#6c757d; font-size:.9rem; }
  #track-stats .value { font-weight:600; font-variant-numeric:tabular-nums; }
  .seg-btn { width:100%; text-align:left; margin-bottom:.25rem; font-variant-numeric:tabular-nums; }
  .header-bar { background:#f8f9fa; border-bottom:1px solid #dee2e6; padding:.5rem 1rem; display:flex; justify-content:space-between; align-items:center; }
</style>
</head>
<body>

<div class="header-bar">
  <div>
    <strong><i class="bi bi-geo-alt-fill"></i> Fahrt <?= (int)$trip['id'] ?></strong>
    <span class="text-muted ms-2"><?= htmlspecialchars($trip['boat_name'] ?? '') ?> · <?= htmlspecialchars($trip['start_date'] ?? '') ?></span>
  </div>
  <?php if ($hasSession): ?>
  <a href="/api/fahrt/track.gpx.php?trip_id=<?= (int)$trip['id'] ?>" class="btn btn-sm btn-outline-primary">
    <i class="bi bi-download"></i> GPX
  </a>
  <?php endif; ?>
</div>

<?php if (!$hasSession): ?>
<div class="container py-5">
  <div class="alert alert-info">Diese Fahrt wurde ohne GPS-Tracker aufgezeichnet.</div>
</div>
<?php else: ?>
<div class="row g-0">
  <div class="col-lg-8"><div id="track-map"></div></div>
  <div class="col-lg-4 border-start">
    <div id="track-stats" class="py-2"><div class="text-muted px-3">Lade…</div></div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  const TRIP_ID = <?= (int)$trip['id'] ?>;
  const map = L.map('track-map');
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    { maxZoom:19, attribution:'© OpenStreetMap' }).addTo(map);
  map.setView([47.715, 8.963], 14);

  const fmtKm  = m => (m/1000).toFixed(2)+' km';
  const fmtKmh = ms => (ms*3.6).toFixed(1)+' km/h';
  const fmtDur = s => {
    if (s==null) return '–';
    s = Math.round(s);
    const h=Math.floor(s/3600), m=Math.floor((s%3600)/60), r=s%60;
    return h>0 ? `${h}:${String(m).padStart(2,'0')}:${String(r).padStart(2,'0')}` : `${m}:${String(r).padStart(2,'0')}`;
  };

  let segLayer = null;

  fetch('/api/fahrt/track.php?trip_id='+TRIP_ID)
    .then(r => r.ok ? r.json() : r.json().then(j=>Promise.reject(j)))
    .then(data => {
      const coords = data.track.geometry.coordinates.map(([lon,lat])=>[lat,lon]);
      if (coords.length < 2) {
        document.getElementById('track-stats').innerHTML =
          '<div class="alert alert-info mx-3">Zu wenige GPS-Punkte.</div>';
        return;
      }
      const line = L.polyline(coords, { color:'#0d6efd', weight:4 }).addTo(map);
      map.fitBounds(line.getBounds(), { padding:[20,20] });
      L.marker(coords[0]).addTo(map).bindTooltip('Start');
      L.marker(coords.at(-1)).addTo(map).bindTooltip('Ziel');
      setTimeout(()=>map.invalidateSize(), 100);

      const s = data.stats || {}, segs = data.segments || {};
      const segBtn = (D, seg) => {
        if (!seg) return `<div class="stat-row"><span class="label">${D} m</span><span class="value">–</span></div>`;
        return `<div class="px-3 py-1"><button class="btn btn-sm btn-outline-primary seg-btn" data-d="${D}">
          <i class="bi bi-speedometer2"></i> ${D} m: ${fmtDur(seg.duration_s)}
          <span class="text-muted small">(⌀ ${fmtKmh(D/seg.duration_s)})</span>
        </button></div>`;
      };

      document.getElementById('track-stats').innerHTML = `
        <div class="px-3 pt-2"><h6><i class="bi bi-graph-up"></i> Kennzahlen</h6></div>
        <div class="stat-row"><span class="label">Distanz</span><span class="value">${s.distance_m!=null?fmtKm(s.distance_m):'–'}</span></div>
        <div class="stat-row"><span class="label">Dauer</span><span class="value">${fmtDur(s.duration_s)}</span></div>
        <div class="stat-row"><span class="label">⌀ Speed</span><span class="value">${s.avg_speed_ms!=null?fmtKmh(s.avg_speed_ms):'–'}</span></div>
        <div class="stat-row"><span class="label">v_max</span><span class="value">${s.max_speed_ms!=null?fmtKmh(s.max_speed_ms):'–'}</span></div>
        <div class="stat-row"><span class="label">Punkte</span><span class="value">${data.session.point_count}</span></div>
        <div class="px-3 pt-3 pb-1"><h6 class="mb-1">Schnellste Abschnitte</h6></div>
        ${segBtn(250, segs['250'])}${segBtn(500, segs['500'])}${segBtn(1000, segs['1000'])}${segBtn(1500, segs['1500'])}${segBtn(2000, segs['2000'])}
      `;

      document.querySelectorAll('#track-stats .seg-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const seg = segs[btn.dataset.d];
          if (!seg) return;
          if (segLayer) map.removeLayer(segLayer);
          segLayer = L.polyline(coords.slice(seg.start_idx, seg.end_idx+1),
            { color:'#dc3545', weight:6 }).addTo(map);
          map.fitBounds(segLayer.getBounds(), { padding:[30,30] });
        });
      });
    })
    .catch(err => {
      document.getElementById('track-stats').innerHTML =
        '<div class="alert alert-warning mx-3">'+(err.message||'Fehler beim Laden')+'</div>';
    });
})();
</script>
<?php endif; ?>
</body>
</html>
