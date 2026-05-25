<?php
/**
 * Admin: Layout-Auswahl
 * Schaltet zwischen v2 (neues Design) und classic (altes Layout) um.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// ── CSRF-Token ─────────────────────────────────────────────────
if (empty($_SESSION['csrf_layout'])) {
    $_SESSION['csrf_layout'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_layout'];

$saved  = false;
$errors = [];

$layoutFile = __DIR__ . '/../config/layout.php';

// ── POST: Layout speichern ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ungültiger CSRF-Token. Bitte Seite neu laden.';
    } else {
        $newLayout = $_POST['ui_layout'] ?? '';
        if (!in_array($newLayout, ['v2', 'classic'], true)) {
            $errors[] = 'Ungültiger Layout-Wert.';
        } else {
            $oldLayout = defined('ACTIVE_LAYOUT') ? ACTIVE_LAYOUT : 'v2';
            $content   = "<?php\n// Aktives Frontend-Layout. Werte: 'v2' oder 'classic'.\n// Diese Datei wird von admin/layout-settings.php automatisch überschrieben.\ndefine('ACTIVE_LAYOUT', '" . $newLayout . "');\n";

            // Atomisches Schreiben: temp-Datei + rename (verhindert Korruption)
            $tmp = $layoutFile . '.tmp';
            if (file_put_contents($tmp, $content, LOCK_EX) !== false && rename($tmp, $layoutFile)) {
                // Audit-Log
                $logLine = sprintf(
                    "[%s] admin=%s from=%s to=%s\n",
                    date('Y-m-d H:i:s'),
                    $_SESSION['admin_username'] ?? 'unknown',
                    $oldLayout,
                    $newLayout
                );
                @file_put_contents(
                    __DIR__ . '/../logs/layout_changes.log',
                    $logLine,
                    FILE_APPEND | LOCK_EX
                );
                $saved = true;
            } else {
                $errors[] = 'Fehler beim Schreiben von config/layout.php. Bitte Schreibrechte prüfen.';
                @unlink($tmp);
            }
        }
    }
}

// Aktuellen Wert direkt aus Datei lesen (Konstante ACTIVE_LAYOUT ist bereits definiert)
$currentLayout = defined('ACTIVE_LAYOUT') ? ACTIVE_LAYOUT : 'v2';
// Nach dem Speichern neu einlesen damit die Anzeige stimmt
if ($saved) {
    @include_once $layoutFile; // Konstante bereits defined → kein Effekt auf define(), daher manuell:
    preg_match("/define\('ACTIVE_LAYOUT',\s*'(\w+)'\)/", file_get_contents($layoutFile), $m);
    $currentLayout = $m[1] ?? 'v2';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= CLUB_NAME ?> Fahrtenbuch - Layout-Auswahl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= APP_BASE_PATH ?>/admin/">
            <i class="bi bi-gear"></i> <?= CLUB_NAME ?> Fahrtenbuch - Administration
        </a>
        <div>
            <a href="<?= APP_BASE_PATH ?>/admin/" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
            <span class="text-light me-2 small">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
            </span>
        </div>
    </div>
</nav>

<div class="container mt-4" style="max-width: 700px;">
    <h2><i class="bi bi-palette"></i> Layout-Auswahl</h2>
    <p class="text-muted">Legt fest, welches Interface alle Kiosk-Nutzer sehen.</p>

    <?php if ($saved): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            Layout erfolgreich auf <strong><?= htmlspecialchars($currentLayout === 'v2' ? 'Neu (v2)' : 'Klassisch') ?></strong> gesetzt.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <!-- Aktueller Status -->
    <div class="card mb-4">
        <div class="card-body d-flex align-items-center gap-3">
            <i class="bi bi-display display-6 text-primary"></i>
            <div>
                <div class="fw-semibold">Aktives Layout</div>
                <div class="fs-5">
                    <?php if ($currentLayout === 'v2'): ?>
                        <span class="badge bg-primary fs-6">Neu (v2)</span>
                    <?php else: ?>
                        <span class="badge bg-secondary fs-6">Klassisch</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="<?= APP_BASE_PATH ?>/?ui=v2" target="_blank"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right"></i> Vorschau Neu
                </a>
                <a href="<?= APP_BASE_PATH ?>/?ui=classic" target="_blank"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right"></i> Vorschau Klassisch
                </a>
            </div>
        </div>
    </div>

    <!-- Formular -->
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="row g-3 mb-4">

            <!-- Option: Neu (v2) -->
            <div class="col-md-6">
                <label class="card h-100 <?= $currentLayout === 'v2' ? 'border-primary border-2' : '' ?>"
                       style="cursor:pointer;">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-layout-wtf display-4 text-primary mb-2"></i>
                        <div class="form-check d-flex justify-content-center align-items-center gap-2 mt-2">
                            <input class="form-check-input" type="radio" name="ui_layout" value="v2"
                                   id="opt-v2" <?= $currentLayout === 'v2' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold fs-5" for="opt-v2">
                                Neu (v2)
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">
                            3-Spalten-Design, dunkler Header,<br>Wetter-Strip, modernes Styling
                        </small>
                    </div>
                </label>
            </div>

            <!-- Option: Klassisch -->
            <div class="col-md-6">
                <label class="card h-100 <?= $currentLayout === 'classic' ? 'border-secondary border-2' : '' ?>"
                       style="cursor:pointer;">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-layout-three-columns display-4 text-secondary mb-2"></i>
                        <div class="form-check d-flex justify-content-center align-items-center gap-2 mt-2">
                            <input class="form-check-input" type="radio" name="ui_layout" value="classic"
                                   id="opt-classic" <?= $currentLayout === 'classic' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold fs-5" for="opt-classic">
                                Klassisch
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Bootstrap-Layout, blauer Header,<br>klassische Buttons und Cards
                        </small>
                    </div>
                </label>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Layout speichern
            </button>
            <a href="<?= APP_BASE_PATH ?>/admin/" class="btn btn-outline-secondary">Abbrechen</a>
        </div>
    </form>

    <!-- Vorschau-Hinweis -->
    <div class="alert alert-info mt-4">
        <i class="bi bi-info-circle-fill"></i>
        Die Vorschau-Links öffnen das jeweilige Layout in einem neuen Tab.
        Der Override gilt nur für diesen Tab (Cookie, 1 Stunde).
        Das gespeicherte Layout gilt für alle Nutzer.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
