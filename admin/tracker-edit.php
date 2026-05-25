<?php
/**
 * Fahrtenbuch - Tracker bearbeiten
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();
$id = (int)($_GET['id'] ?? 0);

if (!$id) { header('Location: tracker.php'); exit; }

$tracker = $db->prepare("SELECT * FROM tracker WHERE id = :id");
$tracker->execute(['id' => $id]);
$tracker = $tracker->fetch();
if (!$tracker) { header('Location: tracker.php'); exit; }

$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'update') {
        $notes          = trim($_POST['notes'] ?? '');
        $firmware_target = trim($_POST['firmware_target'] ?? '') ?: null;
        $db->prepare("UPDATE tracker SET notes = :notes, firmware_target = :ft WHERE id = :id")
           ->execute(['notes' => $notes, 'ft' => $firmware_target, 'id' => $id]);
        $successMessage = "Gespeichert.";
        $tracker['notes']           = $notes;
        $tracker['firmware_target'] = $firmware_target;
    }

    if ($action === 'recompute-stats') {
        $sid = (int)($_POST['session_id'] ?? 0);
        if ($sid > 0) {
            require_once __DIR__ . '/../api/tracker/analyze.php';
            try {
                $r = TrackAnalyzer::run($db, $sid);
                $successMessage = sprintf("Session %d neu berechnet: %.1f km, %d s, %d Punkte.",
                    $sid, $r['distance_m']/1000, $r['duration_s'], $r['count']);
            } catch (\Throwable $e) {
                $errorMessage = "Analyse fehlgeschlagen: " . $e->getMessage();
            }
        }
    }

    if ($action === 'regenerate-token') {
        $token = bin2hex(random_bytes(32));
        $db->prepare("UPDATE tracker SET api_token = :t WHERE id = :id")->execute(['t' => $token, 'id' => $id]);
        $newToken       = $token;
        $successMessage = "Token neu generiert – einmalig kopieren!";
    }
}

// Letzte 20 Sessions
$sessions = $db->prepare("
    SELECT ts.*, t.start_date, t.end_date,
           m.first_name, m.last_name,
           st.distance_m, st.max_speed_ms, st.avg_speed_ms
    FROM tracker_sessions ts
    JOIN trips t   ON ts.trip_id   = t.id
    JOIN members m ON ts.member_id = m.id
    LEFT JOIN tracker_stats st ON st.session_id = ts.id
    WHERE ts.tracker_id = :tid
    ORDER BY ts.started_at DESC
    LIMIT 20");
$sessions->execute(['tid' => $id]);
$sessions = $sessions->fetchAll();

// Verfügbare Firmware-Versionen aus app_config
$fwVersions = [];
$fwRow = $db->query("SELECT v FROM app_config WHERE k = 'firmware_versions'")->fetch();
if ($fwRow) $fwVersions = json_decode($fwRow['v'], true) ?: [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracker <?= e($tracker['nummer']) ?> – <?= CLUB_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>.token-box { font-family:monospace; font-size:.78rem; word-break:break-all; background:#f8f9fa; padding:8px; border-radius:4px; border:1px solid #dee2e6; }</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin/tracker.php">
            <i class="bi bi-geo-alt-fill"></i> Tracker <?= e($tracker['nummer']) ?>
        </a>
        <a href="/admin/tracker.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
    </div>
</nav>

<div class="container mt-4">

    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $successMessage ?>
            <?php if (isset($newToken)): ?>
                <div class="mt-2 token-box" id="newTokenBox"><?= e($newToken) ?></div>
                <button class="btn btn-sm btn-outline-secondary mt-1" onclick="copyToken()"><i class="bi bi-clipboard"></i> Kopieren</button>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Stammdaten -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Stammdaten</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update">
                        <div class="mb-3">
                            <label class="form-label text-muted small">Gerätenummer</label>
                            <p class="fw-bold mb-0"><?= e($tracker['nummer']) ?></p>
                            <small class="text-muted">Nicht änderbar (in Firmware fest)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notiz</label>
                            <input type="text" class="form-control" name="notes"
                                   value="<?= e($tracker['notes'] ?? '') ?>" placeholder="z.B. Boot 1, Reserve…">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Firmware-Zielversion (OTA)</label>
                            <?php if (!empty($fwVersions)): ?>
                                <select class="form-select" name="firmware_target">
                                    <option value="">– kein Update –</option>
                                    <?php foreach ($fwVersions as $v): ?>
                                        <option value="<?= e($v) ?>" <?= $tracker['firmware_target'] === $v ? 'selected' : '' ?>>
                                            <?= e($v) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control" name="firmware_target"
                                       value="<?= e($tracker['firmware_target'] ?? '') ?>"
                                       placeholder="z.B. 1.3.0">
                                <small class="text-muted">Versions-Liste in tracker-config.php pflegbar.</small>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Speichern</button>
                    </form>

                    <hr>
                    <p class="small text-danger fw-semibold"><i class="bi bi-exclamation-triangle"></i> Token regenerieren</p>
                    <form method="POST" onsubmit="return confirm('Altes Token wird ungültig. Firmware muss neu konfiguriert werden!')">
                        <input type="hidden" name="action" value="regenerate-token">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-arrow-repeat"></i> Neues Token generieren
                        </button>
                    </form>
                </div>
            </div>

            <!-- Aktueller Status -->
            <div class="card mt-3">
                <div class="card-header"><h6 class="mb-0">Aktueller Status</h6></div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>Status</th><td><?= e($tracker['status']) ?></td></tr>
                        <tr><th>Akku</th>
                            <td>
                                <?php if ($tracker['charging']): ?>
                                    <i class="bi bi-plug-fill text-success"></i> lädt
                                <?php elseif ($tracker['battery_pct'] !== null): ?>
                                    <?= $tracker['battery_pct'] ?> %
                                    (<?= number_format($tracker['battery_mv']/1000, 2) ?> V)
                                <?php else: ?>–<?php endif; ?>
                            </td>
                        </tr>
                        <tr><th>Firmware</th><td><?= e($tracker['firmware_version'] ?? '–') ?></td></tr>
                        <tr><th>Letzter Kontakt</th>
                            <td><?= $tracker['last_seen'] ? date('d.m.Y H:i', strtotime($tracker['last_seen'])) : '–' ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Session-Historie -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Letzte Sessions <small class="text-muted">(max. 20)</small></h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Datum</th>
                                    <th>Mitglied</th>
                                    <th>Status</th>
                                    <th>Strecke</th>
                                    <th>v_max</th>
                                    <th>v_avg</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($sessions as $s): ?>
                                <tr>
                                    <td><?= date('d.m.Y', strtotime($s['start_date'])) ?></td>
                                    <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                    <td>
                                        <?php
                                        $sc = ['assigned'=>'secondary','active'=>'primary','finished'=>'success','aborted'=>'danger'];
                                        $sl = ['assigned'=>'Zugeordnet','active'=>'Aktiv','finished'=>'Abgeschlossen','aborted'=>'Abgebrochen'];
                                        ?>
                                        <span class="badge bg-<?= $sc[$s['status']] ?? 'secondary' ?>">
                                            <?= $sl[$s['status']] ?? $s['status'] ?>
                                        </span>
                                    </td>
                                    <td><?= $s['distance_m'] !== null ? number_format($s['distance_m']/1000, 2).' km' : '–' ?></td>
                                    <td><?= $s['max_speed_ms'] !== null ? number_format($s['max_speed_ms']*3.6, 1).' km/h' : '–' ?></td>
                                    <td><?= $s['avg_speed_ms'] !== null ? number_format($s['avg_speed_ms']*3.6, 1).' km/h' : '–' ?></td>
                                    <td>
                                        <a href="/fahrt.php?id=<?= (int)$s['trip_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Karte öffnen">
                                            <i class="bi bi-map"></i>
                                        </a>
                                        <a href="/api/fahrt/track.gpx.php?trip_id=<?= (int)$s['trip_id'] ?>" class="btn btn-sm btn-outline-secondary" title="GPX-Export">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <?php if (in_array($s['status'], ['finished','aborted'], true)): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Statistik neu berechnen?');">
                                            <input type="hidden" name="action" value="recompute-stats">
                                            <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Statistik neu berechnen">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </form>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sessions)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">Noch keine Sessions.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyToken() {
    const text = document.getElementById('newTokenBox')?.innerText;
    if (text) navigator.clipboard.writeText(text).then(() => alert('Token kopiert!'));
}
</script>
</body>
</html>
