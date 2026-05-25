<?php
/**
 * Fahrtenbuch - SQL Generator für alte EFA-Daten
 *
 * Dieses Script liest die XML-Dateien aus /alte_daten/ und generiert
 * SQL INSERT-Statements zum einmaligen Import der Daten.
 */

require_once __DIR__ . '/auth_check.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseDir = __DIR__ . '/../alte_daten/';
$outputFile = __DIR__ . '/../database/import_generated.sql';

// Aktueller Timestamp in Millisekunden
$nowMs = time() * 1000;

$sql = "-- ============================================================\n";
$sql .= "-- Fahrtenbuch - Generierter Import aus alten EFA-Daten\n";
$sql .= "-- Generiert am: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- ============================================================\n\n";
$sql .= "USE fahrtenbuch;\n\n";

// ============================================================
// 1. GEWÄSSER IMPORTIEREN
// ============================================================
$sql .= "-- ============================================================\n";
$sql .= "-- 1. GEWÄSSER (WATERS)\n";
$sql .= "-- ============================================================\n\n";

$watersXml = simplexml_load_file($baseDir . 'waters.xml');
$watersList = [];

foreach ($watersXml->Record as $record) {
    $name = (string)$record->Name;
    $details = isset($record->Details) ? (string)$record->Details : null;

    if (empty($name)) continue;

    $watersList[] = $name; // Für späteren Lookup

    $nameSql = addslashes($name);
    $detailsSql = $details ? "'" . addslashes($details) . "'" : "NULL";

    $sql .= "INSERT INTO waters (name, description) VALUES ('$nameSql', $detailsSql)\n";
    $sql .= "ON DUPLICATE KEY UPDATE description = VALUES(description);\n";
}

$sql .= "\n";

// ============================================================
// 2. STRECKEN IMPORTIEREN
// ============================================================
$sql .= "-- ============================================================\n";
$sql .= "-- 2. STRECKEN (ROUTES)\n";
$sql .= "-- ============================================================\n\n";

$destinationsXml = simplexml_load_file($baseDir . 'destinations.xml');

foreach ($destinationsXml->Record as $record) {
    $name = (string)$record->Name;
    $start = isset($record->Start) ? (string)$record->Start : 'Verein';
    $end = isset($record->End) ? (string)$record->End : 'Verein';
    $distance = isset($record->Distance) ? (string)$record->Distance : '';
    $roundtrip = isset($record->Roundtrip) && (string)$record->Roundtrip === 'true' ? 1 : 0;
    $startIsBoathouse = isset($record->StartIsBoathouse) && (string)$record->StartIsBoathouse === 'true' ? 1 : 0;
    $watersIdList = isset($record->WatersIdList) ? (string)$record->WatersIdList : '';

    if (empty($name)) continue;

    // Distance parsen (z.B. "12 km" -> 12.00)
    $distanceNum = 0;
    if (preg_match('/(\d+\.?\d*)/', $distance, $matches)) {
        $distanceNum = floatval($matches[1]);
    }

    // Erstes Gewässer aus Liste nehmen
    $waterName = 'Bodensee'; // Default
    if (!empty($watersIdList)) {
        $waters = explode(',', $watersIdList);
        $waterName = trim($waters[0]);
    }

    $nameSql = addslashes($name);
    $startSql = addslashes($start);
    $endSql = addslashes($end);
    $waterNameSql = addslashes($waterName);

    $sql .= "INSERT INTO routes (start_point, end_point, distance, is_roundtrip, start_is_boathouse, water_id, description)\n";
    $sql .= "SELECT '$startSql', '$endSql', $distanceNum, $roundtrip, $startIsBoathouse, w.id, '$nameSql'\n";
    $sql .= "FROM waters w WHERE w.name = '$waterNameSql'\n";
    $sql .= "AND NOT EXISTS (SELECT 1 FROM routes r2 WHERE r2.description = '$nameSql')\n";
    $sql .= "LIMIT 1;\n\n";
}

// ============================================================
// 3. PERSONEN IMPORTIEREN
// ============================================================
$sql .= "-- ============================================================\n";
$sql .= "-- 3. PERSONEN (MEMBERS)\n";
$sql .= "-- Nur gültige Personen (InvalidFrom > jetzt)\n";
$sql .= "-- ============================================================\n\n";

$personsXml = simplexml_load_file($baseDir . 'persons.xml');
$membersList = []; // Für Boot-Besitzer-Lookup

foreach ($personsXml->Record as $record) {
    $firstName = (string)$record->FirstName;
    $lastName = (string)$record->LastName;
    $gender = (string)$record->Gender;
    $statusId = (string)$record->StatusId;
    $membershipNo = isset($record->MembershipNo) ? (string)$record->MembershipNo : null;
    $invalidFrom = isset($record->InvalidFrom) ? (string)$record->InvalidFrom : '9223372036854775807';

    // Nur gültige Personen importieren
    if ($invalidFrom <= $nowMs) continue;

    // Gender Mapping
    $salutation = 'Herr';
    if ($gender === 'weiblich') {
        $salutation = 'Frau';
    }

    // Status Mapping
    $status = 'Aktiv';
    if ($statusId === 'Junior(in)') {
        $status = 'Jugendmitglied';
    } elseif ($statusId === 'Gastmitglied') {
        $status = 'Gastmitglied';
    }

    $membersList[$firstName . ' ' . $lastName] = true; // Für Lookup speichern

    $firstNameSql = addslashes($firstName);
    $lastNameSql = addslashes($lastName);
    $membershipNoSql = $membershipNo ? "'" . addslashes($membershipNo) . "'" : 'NULL';

    $sql .= "INSERT INTO members (first_name, last_name, salutation, status, membership_no)\n";
    $sql .= "VALUES ('$firstNameSql', '$lastNameSql', '$salutation', '$status', $membershipNoSql)\n";
    $sql .= "ON DUPLICATE KEY UPDATE\n";
    $sql .= "  first_name = VALUES(first_name),\n";
    $sql .= "  last_name = VALUES(last_name),\n";
    $sql .= "  salutation = VALUES(salutation),\n";
    $sql .= "  status = VALUES(status);\n\n";
}

// ============================================================
// 4. BOOTE IMPORTIEREN
// ============================================================
$sql .= "-- ============================================================\n";
$sql .= "-- 4. BOOTE (BOATS)\n";
$sql .= "-- Nur gültige Boote (InvalidFrom > jetzt) und nicht Invisible\n";
$sql .= "-- ============================================================\n\n";

$boatsXml = simplexml_load_file($baseDir . 'boats.xml');

foreach ($boatsXml->Record as $record) {
    $name = (string)$record->Name;
    $nameAffix = isset($record->NameAffix) ? (string)$record->NameAffix : '';
    $typeType = (string)$record->TypeType;
    $typeSeats = (string)$record->TypeSeats;
    $defaultCrewId = isset($record->DefaultCrewId) ? (string)$record->DefaultCrewId : '';
    $invalidFrom = isset($record->InvalidFrom) ? (string)$record->InvalidFrom : '9223372036854775807';
    $invisible = isset($record->Invisible) && (string)$record->Invisible === 'true';

    // Nur gültige und sichtbare Boote importieren
    if ($invalidFrom <= $nowMs || $invisible) continue;

    // Bootsnamen zusammensetzen: Name (NameAffix)
    $boatName = $name;
    if (!empty($nameAffix)) {
        $boatName .= ' (' . $nameAffix . ')';
    }

    // TypeType Mapping
    $boatType = 'Seekajak'; // Default

    // Semicolon-separated values - nehme ersten
    $types = explode(';', $typeType);
    $firstType = trim($types[0]);

    if ($firstType === 'Wildwasserkajak') {
        $boatType = 'Wildwasser Kajak';
    } elseif ($firstType === 'Tourenkanadier') {
        $boatType = 'Canadier';
    } elseif ($firstType === 'Rennkajak') {
        $boatType = 'Rennkajak';
    } elseif ($firstType === 'SUP' || $firstType === 'andere') {
        $boatType = 'SUP';
    } elseif ($firstType === 'Wildwasser Canadier') {
        $boatType = 'Wildwasser Canadier';
    }

    // TypeSeats Mapping
    $seats = 1; // Default

    // Semicolon-separated values - nehme ersten
    $seatsList = explode(';', $typeSeats);
    $firstSeats = trim($seatsList[0]);

    if ($firstSeats === 'Zweier') {
        $seats = 2;
    } elseif ($firstSeats === 'Dreier') {
        $seats = 3;
    } elseif ($firstSeats === 'Vierer') {
        $seats = 4;
    }

    $boatNameSql = addslashes($boatName);

    // DefaultCrewId: Lookup in members
    if (!empty($defaultCrewId) && isset($membersList[$defaultCrewId])) {
        $defaultCrewSql = addslashes($defaultCrewId);
        $parts = explode(' ', $defaultCrewId);
        if (count($parts) >= 2) {
            $lastName = array_pop($parts);
            $firstName = implode(' ', $parts);
            $firstNameSql = addslashes($firstName);
            $lastNameSql = addslashes($lastName);

            $sql .= "SET @crew_id = (SELECT id FROM members WHERE first_name = '$firstNameSql' AND last_name = '$lastNameSql' LIMIT 1);\n";
            $sql .= "INSERT INTO boats (boat_name, boat_type, seats, default_crew_1)\n";
            $sql .= "VALUES ('$boatNameSql', '$boatType', $seats, @crew_id)\n";
            $sql .= "ON DUPLICATE KEY UPDATE\n";
            $sql .= "  boat_type = VALUES(boat_type),\n";
            $sql .= "  seats = VALUES(seats),\n";
            $sql .= "  default_crew_1 = VALUES(default_crew_1);\n\n";
        } else {
            // Kein Besitzer gefunden
            $sql .= "INSERT INTO boats (boat_name, boat_type, seats)\n";
            $sql .= "VALUES ('$boatNameSql', '$boatType', $seats)\n";
            $sql .= "ON DUPLICATE KEY UPDATE\n";
            $sql .= "  boat_type = VALUES(boat_type),\n";
            $sql .= "  seats = VALUES(seats);\n\n";
        }
    } else {
        // Kein Besitzer
        $sql .= "INSERT INTO boats (boat_name, boat_type, seats)\n";
        $sql .= "VALUES ('$boatNameSql', '$boatType', $seats)\n";
        $sql .= "ON DUPLICATE KEY UPDATE\n";
        $sql .= "  boat_type = VALUES(boat_type),\n";
        $sql .= "  seats = VALUES(seats);\n\n";
    }
}

$sql .= "-- ============================================================\n";
$sql .= "-- IMPORT ABGESCHLOSSEN\n";
$sql .= "-- ============================================================\n";

// SQL in Datei schreiben
file_put_contents($outputFile, $sql);

echo "SQL-Import-Datei erfolgreich generiert!\n\n";
echo "Datei: $outputFile\n\n";
echo "Statistiken:\n";
echo "- Gewässer: " . count($watersXml->Record) . "\n";
echo "- Strecken: " . count($destinationsXml->Record) . "\n";
echo "- Personen: " . count($membersList) . " (gültig)\n";
echo "- Boote: analysiert\n\n";
echo "Nächster Schritt:\n";
echo "mysql -u username -p fahrtenbuch < database/import_generated.sql\n";
