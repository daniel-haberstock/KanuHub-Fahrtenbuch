<?php
/**
 * XML-Datenimport aus alten EFA-Daten (Web-Oberfläche)
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

$importStarted = false;
$stats = [
    'waters' => 0,
    'routes' => 0,
    'boats' => 0,
    'errors' => 0
];
$messages = [];

// Import starten wenn Button gedrückt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_import'])) {
    $importStarted = true;

    // Aktueller Timestamp in Millisekunden
    $now = time() * 1000;

    // ============================================================
    // 1. GEWÄSSER IMPORTIEREN
    // ============================================================
    $messages[] = ['type' => 'info', 'text' => '<strong>1. Gewässer importieren...</strong>'];
    $watersFile = __DIR__ . '/../alte_daten/waters.xml';

    if (file_exists($watersFile)) {
        $xml = simplexml_load_file($watersFile);

        foreach ($xml->Record as $record) {
            $name = (string)$record->Name;
            $details = isset($record->Details) ? (string)$record->Details : null;

            if (empty($name)) continue;

            try {
                $stmt = $db->prepare("INSERT INTO waters (name, description) VALUES (:name, :description)
                                      ON DUPLICATE KEY UPDATE description = VALUES(description)");
                $stmt->execute([
                    'name' => $name,
                    'description' => $details
                ]);
                $stats['waters']++;
                $messages[] = ['type' => 'success', 'text' => "✓ Gewässer: $name"];
            } catch (Exception $e) {
                $messages[] = ['type' => 'danger', 'text' => "✗ Fehler bei $name: " . $e->getMessage()];
                $stats['errors']++;
            }
        }
    } else {
        $messages[] = ['type' => 'warning', 'text' => 'waters.xml nicht gefunden'];
    }

    // ============================================================
    // 2. STRECKEN IMPORTIEREN
    // ============================================================
    $messages[] = ['type' => 'info', 'text' => '<strong>2. Strecken importieren...</strong>'];
    $destinationsFile = __DIR__ . '/../alte_daten/destinations.xml';

    if (file_exists($destinationsFile)) {
        $xml = simplexml_load_file($destinationsFile);

        foreach ($xml->Record as $record) {
            $name = (string)$record->Name;
            $start = isset($record->Start) ? (string)$record->Start : '';
            $end = isset($record->End) ? (string)$record->End : '';
            $distance = isset($record->Distance) ? (string)$record->Distance : '0';
            $roundtrip = isset($record->Roundtrip) && (string)$record->Roundtrip === 'true' ? 1 : 0;
            $startIsBoathouse = isset($record->StartIsBoathouse) && (string)$record->StartIsBoathouse === 'true' ? 1 : 0;
            $watersIdList = isset($record->WatersIdList) ? (string)$record->WatersIdList : '';

            // WICHTIG: Name ist aussagekräftig (z.B. "Seezeichen 6 (Fahrtenbuch6 -)")
            // Start/End sind oft nur "Verein", daher Name verwenden
            if (empty($name)) continue;

            // Distanz extrahieren (z.B. "12 km" -> 12.00)
            if (preg_match('/(\d+(?:\.\d+)?)/', $distance, $matches)) {
                $distance = floatval($matches[1]);
            } else {
                $distance = null;
            }

            // Erstes Gewässer aus Liste nehmen
            $waterName = explode(',', $watersIdList)[0];
            $waterName = trim($waterName);

            // Gewässer-ID ermitteln
            $waterId = null;
            if (!empty($waterName)) {
                $stmt = $db->prepare("SELECT id FROM waters WHERE name = :name LIMIT 1");
                $stmt->execute(['name' => $waterName]);
                $water = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($water) {
                    $waterId = $water['id'];
                }
            }

            // Start/End aus Name ableiten, wenn generisch
            // Beispiel: "Seezeichen 6 (Fahrtenbuch6 -)" -> Start: "Verein", End: "SZ6"
            $startPoint = $start;
            $endPoint = $end;

            // Wenn Start/End beide "Verein" sind oder leer, dann Name als Strecke verwenden
            if ((empty($startPoint) && empty($endPoint)) ||
                ($startPoint === 'Verein' && $endPoint === 'Verein') ||
                ($startPoint === $endPoint && !empty($startPoint))) {

                // Versuche Ziel aus Name zu extrahieren: "Text (Start - Ziel - Ende)"
                if (preg_match('/^(.+?)\s*\(.*?-\s*(.+?)\s*-/', $name, $matches)) {
                    // z.B. "Seezeichen 6 (Fahrtenbuch6 -)" -> "Seezeichen 6", "SZ6"
                    $startPoint = $start ?: 'Verein';
                    $endPoint = trim($matches[2]); // Mittlerer Teil = Ziel
                } else {
                    // Sonst kompletter Name als Strecke
                    $startPoint = $start ?: 'Verein';
                    $endPoint = $name;
                }
            }

            if (empty($startPoint)) $startPoint = 'Verein';
            if (empty($endPoint)) $endPoint = $roundtrip ? 'Rundfahrt' : $startPoint;

            try {
                $stmt = $db->prepare("INSERT IGNORE INTO routes (start_point, end_point, distance, is_roundtrip, start_is_boathouse, water_id, description)
                                      VALUES (:start, :end, :distance, :roundtrip, :start_is_boathouse, :water_id, :description)");
                $stmt->execute([
                    'start' => $startPoint,
                    'end' => $endPoint,
                    'distance' => $distance,
                    'roundtrip' => $roundtrip,
                    'start_is_boathouse' => $startIsBoathouse,
                    'water_id' => $waterId,
                    'description' => $name  // Aussagekräftiger Name (z.B. "Seezeichen 6 (Fahrtenbuch6 -)")
                ]);
                $stats['routes']++;
                $messages[] = ['type' => 'success', 'text' => "✓ Strecke: $name"];
            } catch (Exception $e) {
                $messages[] = ['type' => 'danger', 'text' => "✗ Fehler bei $name: " . $e->getMessage()];
                $stats['errors']++;
            }
        }
    } else {
        $messages[] = ['type' => 'warning', 'text' => 'destinations.xml nicht gefunden'];
    }

    // ============================================================
    // 3. BOOTE IMPORTIEREN
    // ============================================================
    $messages[] = ['type' => 'info', 'text' => '<strong>3. Boote importieren...</strong>'];
    $boatsFile = __DIR__ . '/../alte_daten/boats.xml';

    if (file_exists($boatsFile)) {
        $xml = simplexml_load_file($boatsFile);

        foreach ($xml->Record as $record) {
            // Invisible Boote überspringen
            if (isset($record->Invisible) && (string)$record->Invisible === 'true') {
                continue;
            }

            // InvalidFrom prüfen
            $invalidFrom = isset($record->InvalidFrom) ? (int)$record->InvalidFrom : PHP_INT_MAX;
            if ($invalidFrom <= $now) {
                continue; // Boot nicht mehr gültig
            }

            $name = (string)$record->Name;
            $nameAffix = isset($record->NameAffix) ? (string)$record->NameAffix : '';
            $typeType = isset($record->TypeType) ? (string)$record->TypeType : 'andere';
            $typeSeats = isset($record->TypeSeats) ? (string)$record->TypeSeats : 'andere';
            $defaultCrewId = isset($record->DefaultCrewId) ? (string)$record->DefaultCrewId : null;

            // Boot-Name zusammensetzen
            $boatName = $name;
            if (!empty($nameAffix)) {
                $boatName .= " ($nameAffix)";
            }

            // Bootstyp mappen auf ENUM-Werte
            $boatType = 'Seekajak'; // Default
            if (stripos($typeType, 'Rennkajak') !== false) {
                $boatType = 'Rennkajak';
            } elseif (stripos($typeType, 'Wildwasserkajak') !== false || stripos($typeType, 'Wildwasser Kajak') !== false) {
                $boatType = 'Wildwasser Kajak';
            } elseif (stripos($typeType, 'Kanadier') !== false || stripos($typeType, 'Canadier') !== false) {
                $boatType = 'Canadier';
            } elseif (stripos($typeType, 'Wildwasser Canadier') !== false || stripos($typeType, 'Wildwasser-Canadier') !== false) {
                $boatType = 'Wildwasser-Canadier';
            } elseif (stripos($typeType, 'SUP') !== false) {
                $boatType = 'SUP';
            } elseif (stripos($typeType, 'Faltboot') !== false) {
                $boatType = 'Faltboot';
            } elseif (stripos($typeType, 'Freizeitkajak') !== false) {
                $boatType = 'Freizeitkajak';
            } elseif (stripos($typeType, 'Tourenkajak') !== false) {
                $boatType = 'Tourenkajak';
            } elseif (stripos($typeType, 'Surf Ski') !== false || stripos($typeType, 'Surfski') !== false) {
                $boatType = 'Surf Ski';
            } elseif (stripos($typeType, 'Outrigger') !== false) {
                $boatType = 'Outrigger';
            } elseif (stripos($typeType, 'Packraft') !== false) {
                $boatType = 'Packraft';
            } elseif (stripos($typeType, 'Modul') !== false) {
                $boatType = 'Modul-Kajak';
            } elseif (stripos($typeType, 'Seekajak') !== false) {
                $boatType = 'Seekajak';
            }

            // Sitzplätze mappen
            $seats = null;

            // Versuche aus TypeSeats zu mappen
            if (stripos($typeSeats, 'Einer') !== false || stripos($typeSeats, '1er') !== false) {
                $seats = 1;
            } elseif (stripos($typeSeats, 'Zweier') !== false || stripos($typeSeats, '2er') !== false) {
                $seats = 2;
            } elseif (stripos($typeSeats, 'Dreier') !== false || stripos($typeSeats, '3er') !== false) {
                $seats = 3;
            } elseif (stripos($typeSeats, 'Vierer') !== false || stripos($typeSeats, '4er') !== false) {
                $seats = 4;
            } elseif (stripos($typeSeats, 'Fünfer') !== false || stripos($typeSeats, '5er') !== false) {
                $seats = 5;
            } elseif (stripos($typeSeats, 'Sechser') !== false || stripos($typeSeats, '6er') !== false) {
                $seats = 6;
            } elseif (stripos($typeSeats, 'Siebener') !== false || stripos($typeSeats, '7er') !== false) {
                $seats = 7;
            } elseif (stripos($typeSeats, 'Achter') !== false || stripos($typeSeats, '8er') !== false) {
                $seats = 8;
            } elseif (stripos($typeSeats, 'Neuner') !== false || stripos($typeSeats, '9er') !== false) {
                $seats = 9;
            } elseif (stripos($typeSeats, 'Zehner') !== false || stripos($typeSeats, '10er') !== false) {
                $seats = 10;
            }

            // Falls noch nicht gefunden: Versuche Zahlen aus Name/NameAffix zu extrahieren
            // z.B. "Verein 01 (Hohentwiel 10er Kanadier Holz)" → 10
            if ($seats === null) {
                $fullName = $name . ' ' . $nameAffix;
                if (preg_match('/(\d+)(?:er|plätzer|sitzer)/i', $fullName, $matches)) {
                    $extractedSeats = (int)$matches[1];
                    if ($extractedSeats >= 1 && $extractedSeats <= 10) {
                        $seats = $extractedSeats;
                    }
                }
            }

            // Falls immer noch nicht gefunden: Default 1
            if ($seats === null) {
                $seats = 1;
            }

            // Default Crew 1 ermitteln
            $defaultCrew1 = null;
            if (!empty($defaultCrewId)) {
                $stmt = $db->prepare("SELECT id FROM members WHERE CONCAT(first_name, ' ', last_name) = :name LIMIT 1");
                $stmt->execute(['name' => $defaultCrewId]);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($member) {
                    $defaultCrew1 = $member['id'];
                }
            }

            try {
                $stmt = $db->prepare("INSERT INTO boats (boat_name, boat_type, seats, default_crew_1, valid_until)
                                      VALUES (:boat_name, :boat_type, :seats, :default_crew_1, NULL)
                                      ON DUPLICATE KEY UPDATE
                                        boat_type = VALUES(boat_type),
                                        seats = VALUES(seats),
                                        default_crew_1 = VALUES(default_crew_1)");
                $stmt->execute([
                    'boat_name' => $boatName,
                    'boat_type' => $boatType,
                    'seats' => $seats,
                    'default_crew_1' => $defaultCrew1
                ]);
                $stats['boats']++;
                $messages[] = ['type' => 'success', 'text' => "✓ Boot: $boatName"];
            } catch (Exception $e) {
                $messages[] = ['type' => 'danger', 'text' => "✗ Fehler bei $boatName: " . $e->getMessage()];
                $stats['errors']++;
            }
        }
    } else {
        $messages[] = ['type' => 'warning', 'text' => 'boats.xml nicht gefunden'];
    }

    $messages[] = ['type' => 'info', 'text' => '<strong>Import abgeschlossen!</strong>'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XML-Datenimport - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - XML Datenimport
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="bi bi-download"></i> XML-Datenimport</h2>
        <p class="text-muted">Importiert alte Daten aus den EFA XML-Dateien (einmalige Aktion)</p>

        <?php if (!$importStarted): ?>
            <div class="row mt-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Was wird importiert?</h5>
                        </div>
                        <div class="card-body">
                            <ul>
                                <li><strong>Gewässer</strong> aus <code>alte_daten/waters.xml</code></li>
                                <li><strong>Strecken</strong> aus <code>alte_daten/destinations.xml</code></li>
                                <li><strong>Boote</strong> aus <code>alte_daten/boats.xml</code> (nur gültige Boote)</li>
                            </ul>
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-lightbulb"></i>
                                <strong>Hinweis:</strong> Personen werden NICHT importiert. Neue Mitglieder sollten über
                                <a href="/admin/ycom-sync.php?debug=1" target="_blank">YCom-Sync</a> importiert werden.
                            </div>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Wichtig:</strong> Der Import kann je nach Datenmenge einige Minuten dauern.
                                Bitte schließen Sie dieses Fenster während des Imports nicht.
                            </div>
                        </div>
                    </div>

                    <form method="POST" class="mt-4">
                        <button type="submit" name="start_import" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-play-circle"></i> Import jetzt starten
                        </button>
                    </form>
                </div>

                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-gear"></i> Technische Details</h6>
                        </div>
                        <div class="card-body">
                            <small>
                                <ul class="mb-0">
                                    <li>Filterung: Nur gültige Datensätze (InvalidFrom > heute)</li>
                                    <li>Bootstyp-Mapping: Seekajak, Wildwasserkajak → Kajak</li>
                                    <li>Sitzplatz-Mapping: Einer=1, Zweier=2, etc.</li>
                                    <li>Gewässer-Zuordnung für Strecken</li>
                                    <li>Duplikate werden aktualisiert (ON DUPLICATE KEY)</li>
                                </ul>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header <?php echo $stats['errors'] > 0 ? 'bg-warning' : 'bg-success'; ?> text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-check-circle"></i>
                                Import-Ergebnis
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-4">
                                <div class="col-md-3">
                                    <h2 class="text-primary"><?php echo $stats['waters']; ?></h2>
                                    <small class="text-muted">Gewässer</small>
                                </div>
                                <div class="col-md-3">
                                    <h2 class="text-success"><?php echo $stats['routes']; ?></h2>
                                    <small class="text-muted">Strecken</small>
                                </div>
                                <div class="col-md-3">
                                    <h2 class="text-info"><?php echo $stats['boats']; ?></h2>
                                    <small class="text-muted">Boote</small>
                                </div>
                                <div class="col-md-3">
                                    <h2 class="<?php echo $stats['errors'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo $stats['errors']; ?>
                                    </h2>
                                    <small class="text-muted">Fehler</small>
                                </div>
                            </div>

                            <hr>

                            <h6>Import-Log:</h6>
                            <div style="max-height: 500px; overflow-y: auto; background-color: #f8f9fa; padding: 15px; border-radius: 4px;">
                                <?php foreach ($messages as $msg): ?>
                                    <div class="alert alert-<?php echo $msg['type']; ?> py-1 mb-1">
                                        <?php echo $msg['text']; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($stats['errors'] > 0): ?>
                                <div class="alert alert-warning mt-3">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Achtung:</strong> Es gab <?php echo $stats['errors']; ?> Fehler beim Import.
                                    Bitte prüfen Sie das Log oben.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mt-3">
                                    <i class="bi bi-check-circle"></i>
                                    <strong>Erfolg!</strong> Alle Daten wurden erfolgreich importiert.
                                </div>
                            <?php endif; ?>

                            <a href="/admin/" class="btn btn-primary mt-3">
                                <i class="bi bi-arrow-left"></i> Zurück zur Administration
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
