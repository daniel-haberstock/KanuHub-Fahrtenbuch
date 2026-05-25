<?php
/**
 * Fahrt-Track-Viewer: Karte + Kennzahlen + GPX-Export.
 * URL: /fahrt.php?id=<trip_id>
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$tripId = (int)($_GET['id'] ?? 0);
if ($tripId <= 0) { header('Location: /'); exit; }

$db = Database::getInstance()->getConnection();
$trip = $db->prepare("SELECT t.id, t.start_date, t.end_date, t.distance,
                             COALESCE(b.boat_name, t.boat_name) AS boat_name
                        FROM trips t LEFT JOIN boats b ON b.id = t.boat_id
                       WHERE t.id = :id LIMIT 1");
$trip->execute(['id' => $tripId]);
$trip = $trip->fetch();
if (!$trip) { http_response_code(404); exit('Fahrt nicht gefunden'); }

$hasSession = (int)$db->query("SELECT COUNT(*) FROM tracker_sessions WHERE trip_id = " . $tripId)->fetchColumn() > 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fahrt <?= (int)$trip['id'] ?> – Tracking</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/assets/css/fahrt_track.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid">
    <span class="navbar-brand"><i class="bi bi-geo-alt-fill"></i> Fahrt <?= (int)$trip['id'] ?>
      <small class="text-white-50 ms-2"><?= htmlspecialchars($trip['boat_name'] ?? '') ?> · <?= htmlspecialchars($trip['start_date'] ?? '') ?></small>
    </span>
    <a href="javascript:history.back()" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
  </div>
</nav>

<?php if (!$hasSession): ?>
<div class="container py-5">
  <div class="alert alert-info">Diese Fahrt wurde ohne GPS-Tracker aufgezeichnet — keine Track-Daten vorhanden.</div>
</div>
<?php else: ?>
<div class="container-fluid py-3">
  <div class="row g-3">
    <div class="col-lg-8">
      <div id="track-map"></div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body" id="track-stats">
          <div class="text-muted">Lade Daten…</div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>window.FAHRT_ID = <?= (int)$trip['id'] ?>;</script>
<script src="/assets/js/fahrt_track.js"></script>
<?php endif; ?>
</body>
</html>
