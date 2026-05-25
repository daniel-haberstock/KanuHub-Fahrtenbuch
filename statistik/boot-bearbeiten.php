<?php
/**
 * Fahrtenbuch – Boot bearbeiten (Mitglieder-Portal)
 *
 * Öffnet als separates Fenster (window.open).
 * Nur Boote, bei denen default_crew_1 = eingeloggtes Mitglied.
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
$boatId   = (int)($_GET['boat_id'] ?? 0);

$error   = null;
$success = false;
$boat    = null;

try {
    $db = Database::getInstance()->getConnection();

    if (!$boatId) {
        throw new RuntimeException('Keine Boot-ID angegeben.');
    }

    // Boot laden + Eigentümer-Prüfung
    $stmt = $db->prepare("
        SELECT id, boat_name, boat_type, seats,
               manufacturer, model, serial_number,
               purchase_date, purchase_price, storage_location
        FROM boats
        WHERE id = :bid AND default_crew_1 = :mid
        LIMIT 1
    ");
    $stmt->execute(['bid' => $boatId, 'mid' => $memberId]);
    $boat = $stmt->fetch();

    if (!$boat) {
        throw new RuntimeException('Boot nicht gefunden oder du bist nicht der Eigentümer.');
    }

    // POST: Speichern
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
        $boatName     = trim($_POST['boat_name']     ?? '');
        $manufacturer = trim($_POST['manufacturer']  ?? '');
        $model        = trim($_POST['model']         ?? '');
        $serialNumber = trim($_POST['serial_number'] ?? '');
        $purchaseDate = trim($_POST['purchase_date'] ?? '');
        $purchasePrice= trim($_POST['purchase_price']?? '');

        if (!$boatName) {
            $error = 'Der Bootsname ist ein Pflichtfeld.';
        } else {
            $upd = $db->prepare("
                UPDATE boats
                SET boat_name      = :boat_name,
                    manufacturer   = :manufacturer,
                    model          = :model,
                    serial_number  = :serial_number,
                    purchase_date  = :purchase_date,
                    purchase_price = :purchase_price
                WHERE id = :id AND default_crew_1 = :mid
            ");
            $upd->execute([
                'boat_name'      => $boatName,
                'manufacturer'   => $manufacturer ?: null,
                'model'          => $model ?: null,
                'serial_number'  => $serialNumber ?: null,
                'purchase_date'  => $purchaseDate ?: null,
                'purchase_price' => $purchasePrice !== '' && is_numeric($purchasePrice)
                                    ? (float)$purchasePrice : null,
                'id'             => $boatId,
                'mid'            => $memberId,
            ]);
            $success = true;
            // Aktualisiertes Boot laden
            $stmt->execute(['bid' => $boatId, 'mid' => $memberId]);
            $boat = $stmt->fetch();
        }
    }

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch statistik/boot-bearbeiten.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $error = $e->getMessage() ?: get_class($e);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boot bearbeiten –</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; padding: 20px; font-size: 0.95rem; }
        .window-header { background: #fd7e14; color: #fff; border-radius: 8px 8px 0 0; padding: 12px 16px; }
    </style>
</head>
<body>
<div class="card shadow" style="max-width:580px;margin:0 auto;">
    <div class="window-header">
        <h6 class="mb-0"><i class="bi bi-life-preserver"></i> Boot bearbeiten</h6>
    </div>
    <div class="card-body">

    <?php if ($error && !$boat): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <div class="text-center">
            <button onclick="closeEditor()" class="btn btn-secondary btn-sm">Schließen</button>
        </div>

    <?php elseif ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <strong>Gespeichert!</strong> Die Boot-Daten wurden erfolgreich aktualisiert.
        </div>
        <dl class="row small">
            <dt class="col-5">Bootsname</dt>
            <dd class="col-7"><?= htmlspecialchars($boat['boat_name']) ?></dd>
            <?php if ($boat['manufacturer']): ?>
            <dt class="col-5">Hersteller</dt>
            <dd class="col-7"><?= htmlspecialchars($boat['manufacturer']) ?></dd>
            <?php endif; ?>
            <?php if ($boat['model']): ?>
            <dt class="col-5">Modell</dt>
            <dd class="col-7"><?= htmlspecialchars($boat['model']) ?></dd>
            <?php endif; ?>
            <?php if ($boat['serial_number']): ?>
            <dt class="col-5">Seriennummer</dt>
            <dd class="col-7"><?= htmlspecialchars($boat['serial_number']) ?></dd>
            <?php endif; ?>
            <?php if ($boat['purchase_date']): ?>
            <dt class="col-5">Kaufdatum</dt>
            <dd class="col-7"><?= htmlspecialchars($boat['purchase_date']) ?></dd>
            <?php endif; ?>
            <?php if ($boat['purchase_price']): ?>
            <dt class="col-5">Kaufpreis</dt>
            <dd class="col-7"><?= number_format((float)$boat['purchase_price'], 2, ',', '.') ?> €</dd>
            <?php endif; ?>
        </dl>
        <div class="d-flex gap-2 justify-content-end">
            <button onclick="closeEditor()" class="btn btn-success">
                <i class="bi bi-x-lg"></i> Schließen
            </button>
        </div>

    <?php elseif ($boat): ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Boot-Info (unveränderlich) -->
        <div class="mb-3 p-3 bg-light rounded border">
            <div class="row small g-1">
                <div class="col-5 text-muted">Typ</div>
                <div class="col-7 fw-semibold"><?= htmlspecialchars($boat['boat_type']) ?></div>
                <div class="col-5 text-muted">Sitzplätze</div>
                <div class="col-7"><?= htmlspecialchars($boat['seats']) ?></div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="save" value="1">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Bootsname <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="boat_name"
                           value="<?= htmlspecialchars($boat['boat_name']) ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Hersteller</label>
                    <input type="text" class="form-control" name="manufacturer"
                           placeholder="z.B. Tiderace, Prijon..."
                           value="<?= htmlspecialchars($boat['manufacturer'] ?? '') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Modell</label>
                    <input type="text" class="form-control" name="model"
                           placeholder="z.B. Xplore-S"
                           value="<?= htmlspecialchars($boat['model'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Seriennummer</label>
                    <input type="text" class="form-control" name="serial_number"
                           placeholder="Seriennummer / Chassisnummer"
                           value="<?= htmlspecialchars($boat['serial_number'] ?? '') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Kaufdatum</label>
                    <input type="date" class="form-control" name="purchase_date"
                           value="<?= htmlspecialchars($boat['purchase_date'] ?? '') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Kaufpreis (€)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="purchase_price"
                               step="0.01" min="0"
                               value="<?= htmlspecialchars($boat['purchase_price'] ?? '') ?>">
                        <span class="input-group-text">€</span>
                    </div>
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
        var modalEl = p.document.querySelector('.modal.show');
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
