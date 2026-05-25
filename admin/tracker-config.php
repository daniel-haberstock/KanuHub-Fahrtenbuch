<?php
/**
 * Fahrtenbuch - Tracker-Konfiguration (Geofence, Schwellen, Firmware)
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

$keys = ['geofence_lat','geofence_lon','geofence_radius_m','tracker_min_battery_pct','tracker_heartbeat_timeout_s'];
$errorMessage   = null;
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save-config') {
        $values = [
            'geofence_lat'                => (string)(float)($_POST['geofence_lat']            ?? 0),
            'geofence_lon'                => (string)(float)($_POST['geofence_lon']            ?? 0),
            'geofence_radius_m'           => (string)max(10,(int)($_POST['geofence_radius_m']  ?? 50)),
            'tracker_min_battery_pct'     => (string)max(0,min(100,(int)($_POST['tracker_min_battery_pct'] ?? 30))),
            'tracker_heartbeat_timeout_s' => (string)max(60,(int)($_POST['tracker_heartbeat_timeout_s']     ?? 600)),
        ];
        $stmt = $db->prepare("INSERT INTO app_config (k,v) VALUES (:k,:v) ON DUPLICATE KEY UPDATE v=VALUES(v)");
        foreach ($values as $k => $v) {
            $stmt->execute(['k'=>$k,'v'=>$v]);
        }
        $successMessage = "Einstellungen gespeichert.";
    }

    if ($action === 'rotate-enrollment-secret') {
        $newSecret = bin2hex(random_bytes(32));
        $db->prepare("INSERT INTO app_config (k,v) VALUES ('enrollment_secret', :v) ON DUPLICATE KEY UPDATE v=VALUES(v)")
           ->execute(['v' => $newSecret]);
        $successMessage = "Enrollment-Secret neu generiert. Bestehende Tracker funktionieren weiter; nur neue Enrollments brauchen das neue Secret.";
    }

    if ($action === 'upload-firmware') {
        $upload = $_FILES['firmware_bin'] ?? null;
        if (!$upload || $upload['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = "Upload fehlgeschlagen.";
        } else {
            $version = trim($_POST['firmware_version'] ?? '');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                $errorMessage = "Ungültiges Versionsformat (erwartet: x.y.z).";
            } else {
                $dir = __DIR__ . '/../firmware/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $dest = $dir . $version . '.bin';
                if (move_uploaded_file($upload['tmp_name'], $dest)) {
                    // Versions-Liste in app_config aktualisieren
                    $existing = $db->query("SELECT v FROM app_config WHERE k='firmware_versions'")->fetchColumn();
                    $list = $existing ? json_decode($existing, true) : [];
                    if (!in_array($version, $list)) {
                        $list[] = $version;
                        usort($list, 'version_compare');
                    }
                    $db->prepare("INSERT INTO app_config (k,v) VALUES ('firmware_versions',:v) ON DUPLICATE KEY UPDATE v=VALUES(v)")
                       ->execute(['v' => json_encode($list)]);
                    $successMessage = "Firmware $version erfolgreich hochgeladen.";
                } else {
                    $errorMessage = "Datei konnte nicht gespeichert werden.";
                }
            }
        }
    }
}

// Aktuelle Werte laden
$cfg = [];
foreach ($db->query("SELECT k,v FROM app_config WHERE k IN ('" . implode("','", $keys) . "','firmware_versions','enrollment_secret')")->fetchAll() as $row) {
    $cfg[$row['k']] = $row['v'];
}
$fwVersions        = isset($cfg['firmware_versions']) ? json_decode($cfg['firmware_versions'], true) : [];
$enrollmentSecret  = $cfg['enrollment_secret'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracker-Konfiguration – <?= CLUB_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>#geofenceMap { height: 320px; border-radius: 8px; }</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin/tracker.php">
            <i class="bi bi-sliders"></i> Tracker-Konfiguration
        </a>
        <a href="/admin/tracker.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
    </div>
</nav>

<div class="container mt-4">

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= e($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= e($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning bg-opacity-25">
            <i class="bi bi-key-fill"></i> <strong>Enrollment-Secret</strong>
            <span class="text-muted small ms-2">für die Firmware-Config aller Tracker</span>
        </div>
        <div class="card-body">
            <p class="small mb-2">
                Dieses Shared Secret kommt in <code>firmware/src/secrets.h</code> als <code>ENROLLMENT_SECRET</code>.
                Tracker nutzen es <strong>nur einmal</strong> beim ersten WLAN-Kontakt, um sich zu registrieren.
                Danach arbeiten sie mit ihrem persönlichen API-Token (automatisch in NVS gespeichert).
            </p>
            <?php if ($enrollmentSecret === ''): ?>
                <div class="alert alert-danger small mb-2">
                    <i class="bi bi-exclamation-triangle"></i>
                    Noch kein Enrollment-Secret in der Datenbank vorhanden.
                    Entweder die Migration <code>2026-04_tracker_hwid.sql</code> einspielen
                    oder hier direkt <strong>jetzt erzeugen</strong>:
                </div>
            <?php else: ?>
                <div class="input-group mb-2">
                    <input type="text" readonly class="form-control font-monospace small" id="enrollSecret"
                           value="<?= e($enrollmentSecret) ?>">
                    <button class="btn btn-outline-secondary" type="button" onclick="copyEnroll()">
                        <i class="bi bi-clipboard"></i> Kopieren
                    </button>
                </div>
            <?php endif; ?>
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('Enrollment-Secret wirklich neu generieren?\nBestehende Tracker funktionieren weiter (nutzen ihre per-Device-Token).\nAber: Jede Firmware, die noch mit dem alten Secret kompiliert ist, kann sich nicht neu registrieren.');">
                <input type="hidden" name="action" value="rotate-enrollment-secret">
                <button type="submit" class="btn btn-sm <?= $enrollmentSecret === '' ? 'btn-danger' : 'btn-outline-warning' ?>">
                    <i class="bi bi-arrow-clockwise"></i>
                    <?= $enrollmentSecret === '' ? 'Jetzt Secret erzeugen' : 'Neu generieren' ?>
                </button>
            </form>
        </div>
    </div>

    <div class="row g-4">

        <!-- Geofence + Schwellen -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-geo-alt"></i> Geofence &amp; Schwellen</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save-config">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bootshaus-Position (Geofence-Mittelpunkt)</label>
                            <div id="geofenceMap" class="mb-2"></div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small">Breitengrad</label>
                                    <input type="number" class="form-control" name="geofence_lat" id="cfgLat" step="0.000001"
                                           value="<?= e($cfg['geofence_lat'] ?? '47.715694') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Längengrad</label>
                                    <input type="number" class="form-control" name="geofence_lon" id="cfgLon" step="0.000001"
                                           value="<?= e($cfg['geofence_lon'] ?? '8.963472') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Radius (m)</label>
                                    <input type="number" class="form-control" name="geofence_radius_m" id="cfgRadius" min="10" max="1000"
                                           value="<?= e($cfg['geofence_radius_m'] ?? '50') ?>" required>
                                </div>
                            </div>
                            <small class="text-muted">Tipp: Marker auf der Karte verschieben um Koordinaten zu setzen.</small>
                        </div>

                        <hr>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Mindest-Akku für Tracker-Auswahl (%)</label>
                                <input type="number" class="form-control" name="tracker_min_battery_pct" min="0" max="100"
                                       value="<?= e($cfg['tracker_min_battery_pct'] ?? '30') ?>">
                                <small class="text-muted">Darunter wird der Tracker nicht angeboten.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Heartbeat-Timeout (s)</label>
                                <input type="number" class="form-control" name="tracker_heartbeat_timeout_s" min="60"
                                       value="<?= e($cfg['tracker_heartbeat_timeout_s'] ?? '600') ?>">
                                <small class="text-muted">Danach gilt Tracker als offline.</small>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="bi bi-save"></i> Einstellungen speichern
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Firmware-Upload -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-cpu"></i> OTA-Firmware</h6></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload-firmware">
                        <div class="mb-3">
                            <label class="form-label">Version <small class="text-muted">(Format: x.y.z)</small></label>
                            <input type="text" class="form-control" name="firmware_version" placeholder="1.3.0"
                                   pattern="\d+\.\d+\.\d+" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Firmware-Datei (.bin)</label>
                            <input type="file" class="form-control" name="firmware_bin" accept=".bin" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-upload"></i> Hochladen
                        </button>
                    </form>

                    <?php if (!empty($fwVersions)): ?>
                        <hr>
                        <p class="small fw-semibold mb-2">Verfügbare Versionen:</p>
                        <ul class="list-group list-group-flush">
                            <?php foreach (array_reverse($fwVersions) as $v): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                                    <span><i class="bi bi-file-binary"></i> <?= e($v) ?></span>
                                    <a href="/firmware/<?= urlencode($v) ?>.bin" class="btn btn-xs btn-outline-secondary btn-sm" download>
                                        <i class="bi bi-download"></i>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3 border-info">
                <div class="card-body">
                    <p class="small mb-1 fw-semibold"><i class="bi bi-info-circle text-info"></i> Tracker-Freigabe pro Mitglied</p>
                    <p class="small text-muted mb-2">
                        In der Mitgliederliste bei jedem Mitglied auf <i class="bi bi-pencil"></i> klicken
                        → Checkbox <strong>„Darf GPS-Tracker verwenden"</strong>.
                    </p>
                    <a href="/admin/members.php" class="btn btn-sm btn-outline-info w-100">
                        <i class="bi bi-people"></i> Zur Mitgliederliste
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function copyEnroll() {
    var inp = document.getElementById('enrollSecret');
    if (!inp) return;
    inp.select();
    inp.setSelectionRange(0, 99999);
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(inp.value);
    } else {
        document.execCommand('copy');
    }
    var btn = event.currentTarget;
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2"></i> Kopiert!';
    setTimeout(function() { btn.innerHTML = orig; }, 1500);
}
(function() {
    var lat = parseFloat(document.getElementById('cfgLat').value) || 47.715694;
    var lon = parseFloat(document.getElementById('cfgLon').value) || 8.963472;
    var rad = parseInt(document.getElementById('cfgRadius').value)  || 50;

    var map = L.map('geofenceMap').setView([lat, lon], 17);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);

    var marker = L.marker([lat, lon], { draggable: true }).addTo(map);
    var circle = L.circle([lat, lon], { radius: rad, color: '#198754', fillOpacity: 0.1 }).addTo(map);

    marker.on('dragend', function(e) {
        var ll = e.target.getLatLng();
        document.getElementById('cfgLat').value = ll.lat.toFixed(6);
        document.getElementById('cfgLon').value = ll.lng.toFixed(6);
        circle.setLatLng(ll);
    });

    document.getElementById('cfgRadius').addEventListener('input', function() {
        circle.setRadius(parseInt(this.value) || 50);
    });
    document.getElementById('cfgLat').addEventListener('change', updateFromInputs);
    document.getElementById('cfgLon').addEventListener('change', updateFromInputs);

    function updateFromInputs() {
        var la = parseFloat(document.getElementById('cfgLat').value);
        var lo = parseFloat(document.getElementById('cfgLon').value);
        if (!isNaN(la) && !isNaN(lo)) {
            marker.setLatLng([la, lo]);
            circle.setLatLng([la, lo]);
            map.setView([la, lo]);
        }
    }
})();
</script>
</body>
</html>
