<?php
/**
 * Fahrtenbuch – Fahrt nachträglich bearbeiten
 *
 * Wird als separates Fenster geöffnet (window.open).
 * Erfordert aktive Session aus /statistik/.
 * Nur Fahrten, bei denen der Nutzer Crew-Mitglied ist, sind editierbar.
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

define('STATS_SESSION_TIMEOUT', 300);

// Session prüfen
if (!isset($_SESSION['stats_member_id'])) {
    echo '<p style="font-family:sans-serif;padding:20px;">Sitzung abgelaufen. <a href="/statistik/">Bitte erneut anmelden.</a></p>';
    exit;
}
if (isset($_SESSION['stats_last_activity']) && time() - $_SESSION['stats_last_activity'] > STATS_SESSION_TIMEOUT) {
    session_unset();
    echo '<p style="font-family:sans-serif;padding:20px;">Sitzung abgelaufen. <a href="/statistik/">Bitte erneut anmelden.</a></p>';
    exit;
}
$_SESSION['stats_last_activity'] = time();

$memberId = (int)$_SESSION['stats_member_id'];
$tripId   = (int)($_GET['trip_id'] ?? 0);

$error   = null;
$success = false;
$trip    = null;

try {
    $db = Database::getInstance()->getConnection();

    if (!$tripId) {
        throw new RuntimeException('Keine Fahrt-ID angegeben.');
    }

    // Fahrt laden + Crew-Zugehörigkeit prüfen
    $stmt = $db->prepare("
        SELECT t.id, t.start_date, t.start_time, t.end_date, t.end_time,
               t.route_custom, t.distance, t.comments, t.is_completed,
               t.boat_id, COALESCE(t.boat_name, b.boat_name, '–') AS boot_name
        FROM trips t
        JOIN trip_crew tc ON t.id = tc.trip_id
        LEFT JOIN boats b ON t.boat_id = b.id
        WHERE t.id = :tid AND tc.member_id = :mid AND t.is_completed = 1
        LIMIT 1
    ");
    $stmt->execute(['tid' => $tripId, 'mid' => $memberId]);
    $trip = $stmt->fetch();

    if (!$trip) {
        throw new RuntimeException('Fahrt nicht gefunden oder du bist kein Crew-Mitglied dieser Fahrt.');
    }

    // POST: Speichern
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
        $endDate     = trim($_POST['end_date']     ?? '');
        $endTime     = trim($_POST['end_time']     ?? '');
        $routeCustom = trim($_POST['route_custom'] ?? '');
        $distance    = str_replace(',', '.', trim($_POST['distance'] ?? ''));
        $comments    = trim($_POST['comments']     ?? '');

        if (!$endDate || !$endTime) {
            $error = 'Enddatum und Endzeit sind Pflichtfelder.';
        } elseif (!is_numeric($distance) || (float)$distance <= 0) {
            $error = 'Bitte eine gültige Distanz eingeben (> 0 km).';
        } else {
            $upd = $db->prepare("
                UPDATE trips
                SET end_date     = :end_date,
                    end_time     = :end_time,
                    route_custom = :route_custom,
                    distance     = :distance,
                    comments     = :comments
                WHERE id = :id
            ");
            $upd->execute([
                'end_date'     => $endDate,
                'end_time'     => $endTime,
                'route_custom' => $routeCustom ?: null,
                'distance'     => (float)$distance,
                'comments'     => $comments ?: null,
                'id'           => $tripId,
            ]);
            $success = true;
            // Aktualisierten Trip für Anzeige neu laden
            $stmt->execute(['tid' => $tripId, 'mid' => $memberId]);
            $trip = $stmt->fetch();
        }
    }

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch statistik/bearbeiten.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $error = $e->getMessage() ?: get_class($e);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fahrt bearbeiten –</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; padding: 20px; font-size: 0.95rem; }
        .window-header { background: #fd7e14; color: #fff; border-radius: 8px 8px 0 0; padding: 12px 16px; }
    </style>
</head>
<body>
<div class="card shadow" style="max-width:620px;margin:0 auto;">
    <div class="window-header">
        <h6 class="mb-0"><i class="bi bi-pencil-square"></i> Fahrt bearbeiten</h6>
    </div>
    <div class="card-body">

    <?php if ($error && !$trip): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <div class="text-center">
            <button onclick="closeEditor()" class="btn btn-secondary btn-sm">Schließen</button>
        </div>

    <?php elseif ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <strong>Gespeichert!</strong> Die Fahrt wurde erfolgreich aktualisiert.
        </div>
        <dl class="row small">
            <dt class="col-4">Boot</dt>
            <dd class="col-8"><?= htmlspecialchars($trip['boot_name']) ?></dd>
            <dt class="col-4">Start</dt>
            <dd class="col-8"><?= htmlspecialchars($trip['start_date']) ?> <?= htmlspecialchars($trip['start_time']) ?></dd>
            <dt class="col-4">Ende</dt>
            <dd class="col-8"><?= htmlspecialchars($trip['end_date']) ?> <?= htmlspecialchars($trip['end_time']) ?></dd>
            <dt class="col-4">Strecke</dt>
            <dd class="col-8"><?= htmlspecialchars($trip['route_custom'] ?? '–') ?></dd>
            <dt class="col-4">Distanz</dt>
            <dd class="col-8"><?= number_format((float)$trip['distance'], 1, ',', '.') ?> km</dd>
        </dl>
        <p class="text-muted small text-center">Schließt automatisch...</p>
        <script>setTimeout(function(){ closeEditor(); }, 1200);</script>

    <?php elseif ($trip): ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Unveränderliche Info -->
        <div class="mb-3 p-3 bg-light rounded border">
            <div class="row small g-1">
                <div class="col-4 text-muted">Boot</div>
                <div class="col-8 fw-semibold"><?= htmlspecialchars($trip['boot_name']) ?></div>
                <div class="col-4 text-muted">Start</div>
                <div class="col-8"><?= htmlspecialchars($trip['start_date']) ?> um <?= htmlspecialchars(substr($trip['start_time'],0,5)) ?> Uhr</div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="save" value="1">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Enddatum <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="end_date"
                           value="<?= htmlspecialchars($trip['end_date'] ?? $trip['start_date']) ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Endzeit <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" name="end_time"
                           value="<?= htmlspecialchars(substr($trip['end_time'] ?? '',0,5)) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Strecke / Route</label>
                    <input type="text" class="form-control" name="route_custom"
                           placeholder="z.B. Iznang → Höri"
                           value="<?= htmlspecialchars($trip['route_custom'] ?? '') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Distanz (km) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="distance" inputmode="decimal"
                               pattern="[0-9]+([.,][0-9]+)?" placeholder="z.B. 12,5"
                               value="<?= str_replace('.', ',', $trip['distance'] ?? '') ?>" required>
                        <span class="input-group-text">km</span>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Anmerkungen</label>
                    <textarea class="form-control" name="comments" rows="2"
                              placeholder="Optionale Anmerkungen zur Fahrt"><?= htmlspecialchars($trip['comments'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-4">
                <button type="button" onclick="closeEditor()" class="btn btn-outline-secondary">Abbrechen</button>
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-floppy"></i> Speichern
                </button>
            </div>
        </form>

    <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function closeEditor() {
    if (window.parent && window.parent !== window) {
        var p = window.parent;
        var modalEl = p.document.getElementById('editTripModal')
                   || p.document.querySelector('.modal.show');
        if (modalEl && p.bootstrap && p.bootstrap.Modal) {
            var inst = p.bootstrap.Modal.getInstance(modalEl) || new p.bootstrap.Modal(modalEl);
            inst.hide();
            return;
        }
        var btn = p.document.querySelector('.modal.show .btn-close');
        if (btn) { btn.click(); return; }
    }
    window.close();
}
</script>
</body>
</html>
