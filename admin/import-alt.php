<?php
/**
 * Fahrtenbuch - Import alter EFA2-Daten
 *
 * Einmaliger Import aller historischen Fahrten aus den EFA2-XML-Exporten.
 * Skript im Browser aufrufen: /admin/import-alt.php
 *
 * Quellen:
 *   - alte_daten/persons.xml       → Mitgliedsnummern-Mapping
 *   - alte_daten/Fahrtenbuch_*.xml → Historische Fahrten (Crew1..Crew10 + Cox)
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// Zeitlimit erhöhen für großen Import
set_time_limit(300);

$db = Database::getInstance()->getConnection();

// ============================================================
// HILFSFUNKTIONEN
// ============================================================

/**
 * Konvertiert EFA2-Datum (DD.MM.YYYY) in MySQL-Format (YYYY-MM-DD)
 */
function convertDate(string $date): ?string {
    if (empty(trim($date))) return null;
    $parts = explode('.', trim($date));
    if (count($parts) !== 3) return null;
    return $parts[2] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
}

/**
 * Extrahiert Kilometer-Zahl aus EFA2-Distanz-String ("8 km" → 8.0)
 */
function parseDistance(string $distance): ?float {
    if (empty(trim($distance))) return null;
    if (preg_match('/(\d+(?:[.,]\d+)?)/', $distance, $m)) {
        return (float) str_replace(',', '.', $m[1]);
    }
    return null;
}

/**
 * Mappt EFA2-SessionType auf neues System
 */
function mapSessionType(string $type): string {
    $type = strtolower(trim($type));
    $map = [
        'normale fahrt'     => 'Normal',
        'normal'            => 'Normal',
        'tour'              => 'Tour',
        'wanderfahrt'       => 'Tour',
        'regatta'           => 'Regatta',
        'trainingslager'    => 'Trainingslager',
        'schulung'          => 'Schulung',
        'kurs'              => 'Schulung',
        'training'          => 'Normal',
        'gruppenfahrt'      => 'Tour',
        'vereinsfahrt'      => 'Tour',
    ];
    foreach ($map as $key => $value) {
        if (strpos($type, $key) !== false) return $value;
    }
    return 'Normal';
}

/**
 * Baut die PersonMap aus persons.xml auf.
 * Schlüssel-Varianten:
 *   1. "Nachname, Vorname"
 *   2. "Vorname Nachname"
 *   3. "Nachname Vorname" (Fallback)
 * Wert: membership_no (String, z.B. "1085")
 */
function buildPersonMap(string $personsFile): array {
    $map = [];
    if (!file_exists($personsFile)) {
        return $map;
    }
    $xml = simplexml_load_file($personsFile);
    if (!$xml) return $map;

    foreach ($xml->Record as $record) {
        $firstName = trim((string)($record->FirstName ?? ''));
        $lastName  = trim((string)($record->LastName ?? ''));
        $memberNo  = trim((string)($record->MembershipNo ?? ''));

        if (empty($firstName) || empty($lastName) || empty($memberNo) || $memberNo === '000') {
            continue;
        }

        // Variante 1: "Nachname, Vorname" (häufigstes Format in XML)
        $key1 = $lastName . ', ' . $firstName;
        // Variante 2: "Vorname Nachname"
        $key2 = $firstName . ' ' . $lastName;
        // Variante 3: "Nachname Vorname" (kein Komma)
        $key3 = $lastName . ' ' . $firstName;

        // Kleinschreibung für case-insensitiven Vergleich
        $map[strtolower($key1)] = $memberNo;
        $map[strtolower($key2)] = $memberNo;
        $map[strtolower($key3)] = $memberNo;
    }
    return $map;
}

/**
 * Sucht Mitgliedsnummer für einen Namen in der PersonMap.
 * Mehrstufige Suche: exakt → ohne Komma → Fallback
 *
 * @return string|null membership_no oder null wenn nicht gefunden
 */
function findMemberNo(string $name, array $personMap): ?string {
    $name = trim($name);
    if (empty($name)) return null;

    // Direkte Suche (case-insensitiv)
    $lower = strtolower($name);
    if (isset($personMap[$lower])) {
        return $personMap[$lower];
    }

    // Format "Nachname, Vorname" → "Vorname Nachname" umdrehen und nochmal suchen
    if (strpos($name, ', ') !== false) {
        $parts = explode(', ', $name, 2);
        $flipped = trim($parts[1]) . ' ' . trim($parts[0]);
        if (isset($personMap[strtolower($flipped)])) {
            return $personMap[strtolower($flipped)];
        }
    }

    // Format "Vorname Nachname" → "Nachname, Vorname" umdrehen
    if (strpos($name, ', ') === false && strpos($name, ' ') !== false) {
        $parts = explode(' ', $name, 2);
        $flipped = trim($parts[1]) . ', ' . trim($parts[0]);
        if (isset($personMap[strtolower($flipped)])) {
            return $personMap[strtolower($flipped)];
        }
    }

    return null;
}

/**
 * Sucht member.id anhand der membership_no in der Datenbank.
 * Nutzt Cache um DB-Abfragen zu minimieren.
 */
function findMemberId(string $memberNo, PDO $db, array &$memberIdCache): ?int {
    if (isset($memberIdCache[$memberNo])) {
        return $memberIdCache[$memberNo];
    }
    $stmt = $db->prepare("SELECT id FROM members WHERE membership_no = ? LIMIT 1");
    $stmt->execute([$memberNo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $id = $row ? (int)$row['id'] : null;
    $memberIdCache[$memberNo] = $id;
    return $id;
}

// ============================================================
// HAUPTPROGRAMM
// ============================================================

$alteDataDir = __DIR__ . '/../alte_daten/';
$personsFile = $alteDataDir . 'persons.xml';

// PersonMap aufbauen
$personMap = buildPersonMap($personsFile);

// Cache für Mitglieds-IDs (DB-Lookup nur einmal pro Mitgliedsnummer)
$memberIdCache = [];

// Alle Saison-XML-Dateien sammeln
$xmlFiles = glob($alteDataDir . 'Fahrtenbuch_*.xml');
sort($xmlFiles); // Chronologisch sortieren

// Statistik-Sammlung
$globalStats = [
    'imported'   => 0,
    'skipped'    => 0,
    'errors'     => 0,
    'unmatched'  => [],  // Namen die nicht gemappt werden konnten
];
$seasonStats = [];

// ============================================================
// HTML-AUSGABE STARTEN
// ============================================================
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Import historischer Fahrten – <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .danger  { color: #dc3545; }
        .info    { color: #17a2b8; }
        .summary-box { display: flex; gap: 15px; flex-wrap: wrap; }
        .stat-card { background: #fff; border-left: 4px solid #007bff; padding: 10px 15px; border-radius: 4px; min-width: 120px; }
        .stat-card.green { border-color: #28a745; }
        .stat-card.orange { border-color: #fd7e14; }
        .stat-card.red { border-color: #dc3545; }
        .stat-num { font-size: 24px; font-weight: bold; }
        .unmatched-list { max-height: 200px; overflow-y: auto; font-size: 12px; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin/">
            <i class="bi bi-clock-history"></i> Administration – Import historischer Fahrten
        </a>
        <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
    </div>
</nav>
<div class="container-fluid px-4 py-3">

<?php
if (!file_exists($personsFile)) {
    echo '<div class="card"><p class="danger">❌ persons.xml nicht gefunden: ' . htmlspecialchars($personsFile) . '</p></div>';
    echo '</div></body></html>';
    exit;
}
?>

<div class="card">
    <p class="success">✓ persons.xml geladen: <strong><?= count($personMap) ?></strong> Namens-Einträge (3 Varianten pro Person)</p>
    <p class="info">📁 Saisondateien gefunden: <strong><?= count($xmlFiles) ?></strong></p>
</div>

<?php
if (empty($xmlFiles)) {
    echo '<div class="card"><p class="warning">⚠️ Keine Fahrtenbuch_*.xml-Dateien in ' . htmlspecialchars($alteDataDir) . ' gefunden.</p></div>';
    echo '</div></body></html>';
    exit;
}

// ============================================================
// IMPORT PRO SAISON
// ============================================================
foreach ($xmlFiles as $xmlFile) {
    $seasonName = basename($xmlFile, '.xml'); // z.B. "Fahrtenbuch_2023-2024"
    $displayName = str_replace('Fahrtenbuch_', '', $seasonName); // z.B. "2023-2024"

    $seasonStats[$displayName] = ['imported' => 0, 'skipped' => 0, 'errors' => 0];

    echo "<h2>Saison {$displayName}</h2><div class='card'>";
    flush();

    $xml = simplexml_load_file($xmlFile);
    if (!$xml) {
        echo "<p class='danger'>❌ Datei konnte nicht gelesen werden: " . htmlspecialchars($xmlFile) . "</p></div>";
        $globalStats['errors']++;
        continue;
    }

    $records = $xml->Record;
    $count = count($records);
    echo "<p class='info'>📋 Einträge in XML: <strong>{$count}</strong></p>";

    if ($count === 0) {
        echo "<p class='warning'>⚠️ Keine Einträge vorhanden.</p></div>";
        continue;
    }

    $db->beginTransaction();
    try {
        foreach ($records as $record) {
            // Datum konvertieren
            $rawDate   = trim((string)($record->Date ?? ''));
            $startDate = convertDate($rawDate);
            if (!$startDate) {
                $seasonStats[$displayName]['errors']++;
                $globalStats['errors']++;
                continue;
            }

            $startTime = trim((string)($record->StartTime ?? '00:00:00'));
            $endTime   = trim((string)($record->EndTime ?? ''));
            $boatName  = trim((string)($record->Boat ?? ''));
            $dest      = trim((string)($record->Destination ?? ''));
            $rawDist   = trim((string)($record->Distance ?? ''));
            $distance  = parseDistance($rawDist);
            $rawType   = trim((string)($record->SessionType ?? 'normale Fahrt'));
            $sessionType = mapSessionType($rawType);
            $comments  = trim((string)($record->Comments ?? ''));

            // Duplikat-Prüfung
            $dupCheck = $db->prepare(
                "SELECT id FROM trips WHERE start_date = ? AND start_time = ? AND boat_name = ? AND is_backlog = 1 LIMIT 1"
            );
            $dupCheck->execute([$startDate, $startTime, $boatName]);
            if ($dupCheck->fetch()) {
                $seasonStats[$displayName]['skipped']++;
                $globalStats['skipped']++;
                continue;
            }

            // Trip INSERT
            $tripStmt = $db->prepare("
                INSERT INTO trips
                    (boat_name, start_date, start_time, end_date, end_time,
                     route_custom, distance, session_type, comments,
                     is_completed, is_backlog)
                VALUES
                    (?, ?, ?, ?, ?,
                     ?, ?, ?, ?,
                     1, 1)
            ");
            $tripStmt->execute([
                $boatName ?: null,
                $startDate,
                $startTime,
                $startDate, // end_date = start_date wenn nicht vorhanden
                $endTime ?: null,
                $dest ?: null,
                $distance,
                $sessionType,
                $comments ?: null,
            ]);
            $tripId = (int)$db->lastInsertId();

            // --------------------------------------------------------
            // Besatzung: Crew1..Crew10 + Cox
            // --------------------------------------------------------
            $seatPosition = 1;

            // Cox zuerst auf Platz 1 wenn kein Crew1 vorhanden
            $crew1 = trim((string)($record->Crew1 ?? ''));
            $cox   = trim((string)($record->Cox ?? ''));

            // Crew1..Crew10
            for ($i = 1; $i <= 10; $i++) {
                $crewField = 'Crew' . $i;
                $crewName  = trim((string)($record->$crewField ?? ''));
                if (empty($crewName)) continue;

                $memberNo = findMemberNo($crewName, $personMap);
                $memberId = null;
                if ($memberNo) {
                    $memberId = findMemberId($memberNo, $db, $memberIdCache);
                }
                if (!$memberId && !empty($crewName)) {
                    $globalStats['unmatched'][$crewName] = ($globalStats['unmatched'][$crewName] ?? 0) + 1;
                }

                $crewStmt = $db->prepare("
                    INSERT INTO trip_crew (trip_id, seat_position, member_id, member_name)
                    VALUES (?, ?, ?, ?)
                ");
                $crewStmt->execute([
                    $tripId,
                    $seatPosition,
                    $memberId,
                    $memberId ? null : $crewName,
                ]);
                $seatPosition++;
            }

            // Cox (nach Crew-Plätzen)
            if (!empty($cox)) {
                $memberNo = findMemberNo($cox, $personMap);
                $memberId = null;
                if ($memberNo) {
                    $memberId = findMemberId($memberNo, $db, $memberIdCache);
                }
                if (!$memberId) {
                    $globalStats['unmatched'][$cox] = ($globalStats['unmatched'][$cox] ?? 0) + 1;
                }

                $crewStmt = $db->prepare("
                    INSERT INTO trip_crew (trip_id, seat_position, member_id, member_name)
                    VALUES (?, ?, ?, ?)
                ");
                $crewStmt->execute([
                    $tripId,
                    $seatPosition,
                    $memberId,
                    $memberId ? null : $cox,
                ]);
            }

            $seasonStats[$displayName]['imported']++;
            $globalStats['imported']++;
        }

        $db->commit();

    } catch (Exception $e) {
        $db->rollBack();
        echo "<p class='danger'>❌ Fehler in Saison {$displayName}: " . htmlspecialchars($e->getMessage()) . "</p>";
        $seasonStats[$displayName]['errors']++;
        $globalStats['errors']++;
    }

    // Saison-Statistik ausgeben
    $s = $seasonStats[$displayName];
    echo "<div class='summary-box'>";
    echo "<div class='stat-card green'><div class='stat-num'>{$s['imported']}</div><small>Importiert</small></div>";
    echo "<div class='stat-card orange'><div class='stat-num'>{$s['skipped']}</div><small>Übersprungen</small></div>";
    echo "<div class='stat-card red'><div class='stat-num'>{$s['errors']}</div><small>Fehler</small></div>";
    echo "</div></div>";
    flush();
}

// ============================================================
// GESAMTSTATISTIK
// ============================================================
echo "<h2>Gesamtergebnis</h2><div class='card'>";
echo "<div class='summary-box'>";
echo "<div class='stat-card green'><div class='stat-num'>{$globalStats['imported']}</div><small>Importiert gesamt</small></div>";
echo "<div class='stat-card orange'><div class='stat-num'>{$globalStats['skipped']}</div><small>Übersprungen (Duplikate)</small></div>";
echo "<div class='stat-card red'><div class='stat-num'>{$globalStats['errors']}</div><small>Fehler</small></div>";
echo "<div class='stat-card'><div class='stat-num'>" . count($globalStats['unmatched']) . "</div><small>Unbekannte Namen</small></div>";
echo "</div></div>";

// Nicht-gematchte Namen in import_unmatched Tabelle persistieren
if (!empty($globalStats['unmatched'])) {
    arsort($globalStats['unmatched']); // Häufigste zuerst

    // Tabelle erstellen falls noch nicht vorhanden
    $db->exec("
        CREATE TABLE IF NOT EXISTS import_unmatched (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_name VARCHAR(255) NOT NULL,
            trip_count INT NOT NULL DEFAULT 0,
            status ENUM('pending', 'ignored', 'matched') NOT NULL DEFAULT 'pending',
            matched_member_id INT NULL,
            matched_at DATETIME NULL,
            matched_by VARCHAR(100) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (original_name),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $upsertStmt = $db->prepare("
        INSERT INTO import_unmatched (original_name, trip_count)
        VALUES (:name, :count)
        ON DUPLICATE KEY UPDATE trip_count = :count2
    ");

    $persistedCount = 0;
    foreach ($globalStats['unmatched'] as $name => $count) {
        $upsertStmt->execute([
            'name'   => $name,
            'count'  => $count,
            'count2' => $count,
        ]);
        $persistedCount++;
    }

    echo "<h2>Nicht erkannte Namen (als Freitext gespeichert)</h2><div class='card'>";
    echo "<p class='warning'>Diese " . count($globalStats['unmatched']) . " Personen konnten keinem Mitglied zugeordnet werden.</p>";
    echo "<p class='info'>✓ {$persistedCount} Einträge in <code>import_unmatched</code>-Tabelle gespeichert.</p>";
    echo "<p><a href='/admin/unmatched-persons.php' style='font-weight:bold;'>→ Nicht zugeordnete Personen verwalten</a></p>";
    echo "<div class='unmatched-list'><table><tr><th>Name (aus XML)</th><th>Anzahl Fahrten</th></tr>";
    foreach ($globalStats['unmatched'] as $name => $count) {
        echo "<tr><td>" . htmlspecialchars($name) . "</td><td>{$count}</td></tr>";
    }
    echo "</table></div></div>";
}

echo "<p style='margin-top:20px;color:#666;'>✅ Import abgeschlossen.</p>";
?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
