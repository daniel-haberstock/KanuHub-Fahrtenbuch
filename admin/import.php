<?php
/**
 * Datenimport aus /alte_daten/
 *
 * Importiert historische Daten einmalig ins Fahrtenbuch
 * WICHTIG: Nur Boote/Mitglieder ohne valid_until in der Vergangenheit
 * Gewässer: Nur ID 1 (Bodensee) und ID 2 (Rhein)
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

$importExecuted = false;
$results = [];

// POST-Request = Import ausführen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    try {
        $db->beginTransaction();

        // === 1. Gewässer importieren/sicherstellen ===
        // Nur Bodensee (ID 1) und Rhein (ID 2)
        $waters = [
            ['id' => 1, 'name' => 'Bodensee'],
            ['id' => 2, 'name' => 'Rhein']
        ];

        foreach ($waters as $water) {
            $stmt = $db->prepare("INSERT IGNORE INTO waters (id, name) VALUES (:id, :name)");
            $stmt->execute($water);
        }
        $results['waters'] = "Gewässer gesetzt (Bodensee, Rhein)";

        // === 2. Mitglieder importieren ===
        // TODO: Pfad und Format anpassen
        $membersFile = __DIR__ . '/../alte_daten/mitglieder.csv';

        if (file_exists($membersFile)) {
            $membersImported = 0;
            $membersSkipped = 0;

            if (($handle = fopen($membersFile, 'r')) !== false) {
                // Erste Zeile überspringen (Header)
                fgetcsv($handle, 1000, ';');

                while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                    // Format: membership_no, first_name, last_name, email, status, valid_until
                    // Zeilen mit valid_until in der Vergangenheit überspringen
                    if (!empty($data[5])) {
                        $validUntil = new DateTime($data[5]);
                        $now = new DateTime();
                        if ($validUntil < $now) {
                            $membersSkipped++;
                            continue; // Übersprungen
                        }
                    }

                    $stmt = $db->prepare("
                        INSERT INTO members (membership_no, first_name, last_name, email, status, valid_until, password)
                        VALUES (:membership_no, :first_name, :last_name, :email, :status, :valid_until, :password)
                        ON DUPLICATE KEY UPDATE
                            first_name = VALUES(first_name),
                            last_name = VALUES(last_name),
                            email = VALUES(email)
                    ");

                    $stmt->execute([
                        'membership_no' => $data[0] ?? '',
                        'first_name' => $data[1] ?? '',
                        'last_name' => $data[2] ?? '',
                        'email' => $data[3] ?? '',
                        'status' => $data[4] ?? 'Aktiv',
                        'valid_until' => $data[5] ?? null,
                        'password' => hashPassword('changeme')
                    ]);

                    $membersImported++;
                }
                fclose($handle);
            }

            $results['members'] = "Mitglieder: $membersImported importiert, $membersSkipped übersprungen";
        } else {
            $results['members'] = "Datei nicht gefunden: $membersFile";
        }

        // === 3. Boote importieren ===
        $boatsFile = __DIR__ . '/../alte_daten/boote.csv';

        if (file_exists($boatsFile)) {
            $boatsImported = 0;
            $boatsSkipped = 0;

            if (($handle = fopen($boatsFile, 'r')) !== false) {
                fgetcsv($handle, 1000, ';'); // Header überspringen

                while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                    // Format: boat_name, boat_type, seats, valid_until
                    // Boote mit valid_until in der Vergangenheit überspringen
                    if (!empty($data[3])) {
                        $validUntil = new DateTime($data[3]);
                        $now = new DateTime();
                        if ($validUntil < $now) {
                            $boatsSkipped++;
                            continue;
                        }
                    }

                    $stmt = $db->prepare("
                        INSERT INTO boats (boat_name, boat_type, seats, valid_until)
                        VALUES (:boat_name, :boat_type, :seats, :valid_until)
                        ON DUPLICATE KEY UPDATE
                            boat_type = VALUES(boat_type),
                            seats = VALUES(seats)
                    ");

                    $stmt->execute([
                        'boat_name' => $data[0] ?? '',
                        'boat_type' => $data[1] ?? 'Seekajak',
                        'seats' => $data[2] ?? 1,
                        'valid_until' => $data[3] ?? null
                    ]);

                    $boatsImported++;
                }
                fclose($handle);
            }

            $results['boats'] = "Boote: $boatsImported importiert, $boatsSkipped übersprungen";
        } else {
            $results['boats'] = "Datei nicht gefunden: $boatsFile";
        }

        // === 4. Strecken importieren ===
        $routesFile = __DIR__ . '/../alte_daten/strecken.csv';

        if (file_exists($routesFile)) {
            $routesImported = 0;

            if (($handle = fopen($routesFile, 'r')) !== false) {
                fgetcsv($handle, 1000, ';'); // Header

                while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                    // Format: start_point, end_point, water_id, distance, description
                    $stmt = $db->prepare("
                        INSERT IGNORE INTO routes (start_point, end_point, water_id, distance, description)
                        VALUES (:start_point, :end_point, :water_id, :distance, :description)
                    ");

                    $stmt->execute([
                        'start_point' => $data[0] ?? '',
                        'end_point' => $data[1] ?? '',
                        'water_id' => $data[2] ?? 1, // Default: Bodensee
                        'distance' => $data[3] ?? 0,
                        'description' => $data[4] ?? ''
                    ]);

                    $routesImported++;
                }
                fclose($handle);
            }

            $results['routes'] = "Strecken: $routesImported importiert";
        } else {
            $results['routes'] = "Datei nicht gefunden: $routesFile";
        }

        $db->commit();
        $importExecuted = true;
        $success = true;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $results['error'] = $e->getMessage();
        $success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenimport - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - Datenimport
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Datenimport aus /alte_daten/</h2>
        <p class="text-muted">Einmaliger Import historischer Daten</p>

        <?php if ($importExecuted): ?>
            <?php if (isset($success) && $success): ?>
                <div class="alert alert-success">
                    <h5><i class="bi bi-check-circle"></i> Import erfolgreich abgeschlossen!</h5>
                    <hr>
                    <?php foreach ($results as $key => $message): ?>
                        <p class="mb-1"><strong><?php echo ucfirst($key); ?>:</strong> <?php echo e($message); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <h5><i class="bi bi-exclamation-circle"></i> Fehler beim Import!</h5>
                    <p><?php echo e($results['error'] ?? 'Unbekannter Fehler'); ?></p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-warning">
                <h5><i class="bi bi-exclamation-triangle"></i> Wichtige Hinweise:</h5>
                <ul>
                    <li>Dieser Import sollte nur <strong>einmalig</strong> ausgeführt werden</li>
                    <li>Gewässer werden auf <strong>ID 1 (Bodensee)</strong> und <strong>ID 2 (Rhein)</strong> beschränkt</li>
                    <li>Boote und Mitglieder mit <code>valid_until</code> in der Vergangenheit werden <strong>übersprungen</strong></li>
                    <li>Erwartet werden CSV-Dateien (Semikolon-getrennt) im Verzeichnis <code>/alte_daten/</code>:
                        <ul>
                            <li><code>mitglieder.csv</code> - Format: membership_no;first_name;last_name;email;status;valid_until</li>
                            <li><code>boote.csv</code> - Format: boat_name;boat_type;seats;valid_until</li>
                            <li><code>strecken.csv</code> - Format: start_point;end_point;water_id;distance;description</li>
                        </ul>
                    </li>
                </ul>
            </div>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Import starten</h5>
                </div>
                <div class="card-body">
                    <form method="POST" onsubmit="return confirm('Import wirklich jetzt starten? Dieser Vorgang kann nicht rückgängig gemacht werden!');">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="confirm" required>
                            <label class="form-check-label" for="confirm">
                                Ich bestätige, dass ich die Hinweise gelesen habe und den Import starten möchte.
                            </label>
                        </div>
                        <button type="submit" name="confirm_import" value="1" class="btn btn-primary btn-lg">
                            <i class="bi bi-download"></i> Import jetzt starten
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
