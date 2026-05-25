<?php
/**
 * Fahrtenbuch - Tracker-Verwaltung
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// Konfiguration laden
$cfg = [];
foreach ($db->query("SELECT k, v FROM app_config WHERE k IN ('tracker_heartbeat_timeout_s','tracker_min_battery_pct')")->fetchAll() as $row) {
    $cfg[$row['k']] = $row['v'];
}
$timeout = (int)($cfg['tracker_heartbeat_timeout_s'] ?? 600);
$minPct  = (int)($cfg['tracker_min_battery_pct']     ?? 30);

// POST-Aktionen
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'rename-activate') {
        $id       = (int)($_POST['id'] ?? 0);
        $nummer   = trim($_POST['nummer'] ?? '');
        $notes    = trim($_POST['notes']  ?? '');
        $activate = !empty($_POST['activate_now']) ? 1 : 0;
        if ($id && $nummer !== '') {
            $stmt = $db->prepare("UPDATE tracker SET nummer = :n, notes = :no, aktiv = GREATEST(aktiv, :a) WHERE id = :id");
            $stmt->execute(['n' => $nummer, 'no' => $notes, 'a' => $activate, 'id' => $id]);
            $successMessage = "Tracker <strong>" . e($nummer) . "</strong> gespeichert" . ($activate ? " und freigeschaltet." : ".");
        }
    }

    if ($action === 'toggle-active') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE tracker SET aktiv = 1 - aktiv WHERE id = :id")->execute(['id' => $id]);
        header('Location: tracker.php');
        exit;
    }

    if ($action === 'regenerate-token') {
        $id    = (int)($_POST['id'] ?? 0);
        $token = bin2hex(random_bytes(32));
        $db->prepare("UPDATE tracker SET api_token = :t WHERE id = :id")->execute(['t' => $token, 'id' => $id]);
        $newToken     = $token;
        $newTrackerId = $id;
        $successMessage = "Token neu generiert. Einmalig kopieren und in Firmware-Config eintragen!";
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Nur löschen wenn keine aktive Session
        $check = $db->prepare("SELECT COUNT(*) FROM tracker_sessions WHERE tracker_id = :id AND status IN ('assigned','active')");
        $check->execute(['id' => $id]);
        if ($check->fetchColumn() == 0) {
            $db->prepare("DELETE FROM tracker WHERE id = :id")->execute(['id' => $id]);
            $successMessage = "Tracker gelöscht.";
        } else {
            $errorMessage = "Tracker kann nicht gelöscht werden – aktive Session vorhanden.";
        }
    }
}

// Tracker laden: neue (nummer IS NULL) zuerst, dann aktive, dann inaktive
$trackers = $db->query("
    SELECT * FROM tracker
    ORDER BY (nummer IS NULL) DESC, aktiv DESC, nummer ASC, id ASC
")->fetchAll();

$pendingCount = 0;
foreach ($trackers as $t) {
    if ($t['nummer'] === null || (int)$t['aktiv'] === 0) $pendingCount++;
}

// Online-Status + lesbarer Status berechnen
function trackerBadge(array $t, int $timeout, int $minPct): array {
    $online = $t['last_seen'] && (time() - strtotime($t['last_seen'])) <= $timeout;
    $status = $online ? $t['status'] : 'offline';
    $colors = [
        'idle'      => 'success',
        'armed'     => 'primary',
        'recording' => 'warning',
        'uploading' => 'info',
        'offline'   => 'secondary',
    ];
    $labels = [
        'idle' => 'Bereit', 'armed' => 'Bereit (aktiv)',
        'recording' => 'Aufnahme', 'uploading' => 'Hochladen', 'offline' => 'Offline',
    ];
    $batColor = 'success';
    if ($t['battery_pct'] !== null) {
        if ($t['battery_pct'] < $minPct) $batColor = 'danger';
        elseif ($t['battery_pct'] < 60)  $batColor = 'warning';
    }
    return compact('online', 'status', 'colors', 'labels', 'batColor');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracker-Verwaltung - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .bat-bar { height: 8px; border-radius: 4px; }
        .token-box { font-family: monospace; font-size: .78rem; word-break: break-all; background:#f8f9fa; padding:8px; border-radius:4px; border:1px solid #dee2e6; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin/">
            <i class="bi bi-geo-alt-fill"></i> Administration – Tracker
        </a>
        <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
    </div>
</nav>

<div class="container-fluid mt-4 px-4">

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
    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= e($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Tracker-Liste -->
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-router"></i> Tracker-Geräte</h5>
                    <div>
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge bg-warning text-dark me-1"><?= $pendingCount ?> wartet auf Freigabe</span>
                        <?php endif; ?>
                        <span class="badge bg-secondary"><?= count($trackers) ?> Geräte</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name / HWID</th>
                                    <th>Status</th>
                                    <th>Akku</th>
                                    <th>Letzter Kontakt</th>
                                    <th>Letzte Position</th>
                                    <th>Firmware</th>
                                    <th>Notiz</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($trackers as $t):
                                $b = trackerBadge($t, $timeout, $minPct);
                                $isPending = ($t['nummer'] === null || (int)$t['aktiv'] === 0);
                                $rowClass = $t['nummer'] === null ? 'table-warning' : (!$t['aktiv'] ? 'table-secondary text-muted' : '');
                            ?>
                                <tr class="<?= $rowClass ?>">
                                    <td>
                                        <?php if ($t['nummer']): ?>
                                            <strong><?= e($t['nummer']) ?></strong>
                                        <?php else: ?>
                                            <em class="text-warning-emphasis">Neues Gerät</em>
                                        <?php endif; ?>
                                        <?php if (!$t['aktiv']): ?>
                                            <span class="badge bg-secondary ms-1">Inaktiv</span>
                                        <?php endif; ?>
                                        <?php if ($t['hwid']): ?>
                                            <br><code class="small text-muted"><?= e($t['hwid']) ?></code>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $b['colors'][$b['status']] ?>">
                                            <?= $b['labels'][$b['status']] ?>
                                        </span>
                                        <?php if ($t['charging']): ?>
                                            <i class="bi bi-plug-fill text-success ms-1" title="Lädt"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td style="min-width:110px;">
                                        <?php if ($t['battery_pct'] !== null): ?>
                                            <?php if ($t['charging']): ?>
                                                <span class="text-success"><i class="bi bi-plug-fill"></i> lädt</span>
                                                <small class="text-muted"><?= number_format($t['battery_mv']/1000, 2) ?> V</small>
                                            <?php else: ?>
                                                <div class="progress bat-bar mb-1" style="width:80px;">
                                                    <div class="progress-bar bg-<?= $b['batColor'] ?>"
                                                         style="width:<?= $t['battery_pct'] ?>%"></div>
                                                </div>
                                                <small class="text-<?= $b['batColor'] ?>">
                                                    <?= $t['battery_pct'] ?> %
                                                    (<?= number_format($t['battery_mv']/1000, 2) ?> V)
                                                    <?php if ($t['battery_pct'] < $minPct): ?>
                                                        <i class="bi bi-exclamation-triangle-fill" title="Zu niedrig für Fahrt"></i>
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">–</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($t['last_seen']): ?>
                                            <?php
                                            $diff = time() - strtotime($t['last_seen']);
                                            if ($diff < 60)       $ago = 'gerade eben';
                                            elseif ($diff < 3600) $ago = round($diff/60).' min';
                                            else                  $ago = date('d.m. H:i', strtotime($t['last_seen']));
                                            ?>
                                            <span title="<?= date('d.m.Y H:i:s', strtotime($t['last_seen'])) ?>"
                                                  class="<?= !$b['online'] ? 'text-danger' : 'text-success' ?>">
                                                <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                                                <?= $ago ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Noch nie gesehen</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="min-width:140px;">
                                        <?php if ($t['last_lat'] !== null && $t['last_lon'] !== null):
                                            $locDiff = $t['last_loc_at'] ? time() - strtotime($t['last_loc_at']) : null;
                                            if ($locDiff === null)       $locAgo = '';
                                            elseif ($locDiff < 60)       $locAgo = 'jetzt';
                                            elseif ($locDiff < 3600)     $locAgo = round($locDiff/60) . ' min';
                                            elseif ($locDiff < 86400)    $locAgo = round($locDiff/3600) . ' h';
                                            else                         $locAgo = date('d.m.', strtotime($t['last_loc_at']));
                                            $lat = number_format((float)$t['last_lat'], 5, '.', '');
                                            $lon = number_format((float)$t['last_lon'], 5, '.', '');
                                        ?>
                                            <a href="#" onclick="openMapModal(<?= $lat ?>, <?= $lon ?>); return false;"
                                               title="<?= e($t['last_loc_at']) ?>"
                                               class="text-decoration-none">
                                                <i class="bi bi-geo-alt-fill text-primary"></i>
                                                <span class="font-monospace small"><?= $lat ?>, <?= $lon ?></span>
                                            </a>
                                            <?php if ($locAgo): ?>
                                                <br><small class="text-muted"><?= $locAgo ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">–</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= e($t['firmware_version'] ?? '–') ?>
                                        <?php if ($t['firmware_target'] && $t['firmware_target'] !== $t['firmware_version']): ?>
                                            <span class="badge bg-info ms-1" title="Update ausstehend">→ <?= e($t['firmware_target']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= e($t['notes'] ?? '') ?></small></td>
                                    <td>
                                        <?php if ($isPending): ?>
                                            <button type="button" class="btn btn-sm btn-success"
                                                    data-bs-toggle="modal" data-bs-target="#nameModal<?= $t['id'] ?>"
                                                    title="Namen vergeben &amp; freischalten">
                                                <i class="bi bi-check-circle"></i> Freischalten
                                            </button>
                                        <?php endif; ?>
                                        <a href="tracker-edit.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning" title="Bearbeiten">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle-active">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $t['aktiv'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                                    title="<?= $t['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                                <i class="bi bi-<?= $t['aktiv'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Tracker wirklich löschen? Alle Sessions und Punkte werden ebenfalls gelöscht.')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Löschen">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($trackers)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">Noch keine Tracker registriert. Geräte melden sich automatisch beim ersten WLAN-Kontakt.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar: Info zum Self-Enrollment -->
        <div class="col-lg-3">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Self-Enrollment</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2">
                        Tracker registrieren sich <strong>automatisch</strong>, sobald sie zum ersten Mal
                        WLAN haben. Sie erscheinen dann hier mit HWID und dem Status
                        <em>„Neues Gerät / Inaktiv"</em>.
                    </p>
                    <p class="small mb-0">
                        Vergib dann einen Namen (z.&nbsp;B. <code>Tracker-01</code>) und klicke
                        <strong>Freischalten</strong>.
                    </p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-sliders"></i> Einstellungen</h6>
                </div>
                <div class="card-body">
                    <a href="tracker-config.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-geo-alt"></i> Geofence &amp; Firmware
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php foreach ($trackers as $t): if (!($t['nummer'] === null || (int)$t['aktiv'] === 0)) continue; ?>
<div class="modal fade" id="nameModal<?= $t['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-circle text-success"></i> Tracker freischalten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-3">
                        HWID: <code><?= e($t['hwid'] ?: '–') ?></code>
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nummer" required
                               value="<?= e($t['nummer'] ?? '') ?>" placeholder="Tracker-01"
                               pattern="[A-Za-z0-9\- ]+" maxlength="32">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notiz</label>
                        <input type="text" class="form-control" name="notes" value="<?= e($t['notes'] ?? '') ?>" placeholder="z. B. Boot 1, Reserve">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="activate_now" value="1" id="act<?= $t['id'] ?>" checked>
                        <label class="form-check-label" for="act<?= $t['id'] ?>">Sofort aktivieren</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="rename-activate">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Übernehmen</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Karten-Modal -->
<div class="modal fade" id="mapModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-geo-alt-fill text-primary"></i> Standort</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="mapFrame" src="" width="100%" height="500" style="border:0; display:block;"></iframe>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openMapModal(lat, lon) {
    document.getElementById('mapFrame').src =
        'https://www.openstreetmap.org/export/embed.html?bbox='
        + (lon - 0.005) + ',' + (lat - 0.003) + ',' + (lon + 0.005) + ',' + (lat + 0.003)
        + '&layer=mapnik&marker=' + lat + ',' + lon;
    new bootstrap.Modal(document.getElementById('mapModal')).show();
}
function copyToken() {
    const text = document.getElementById('newTokenBox')?.innerText;
    if (text) navigator.clipboard.writeText(text).then(() => alert('Token kopiert!'));
}
</script>
</body>
</html>
