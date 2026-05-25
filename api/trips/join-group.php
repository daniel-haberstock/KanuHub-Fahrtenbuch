<?php
/**
 * API: Fahrt erstellen und einer Gruppe beitreten
 *
 * POST /api/trips/join-group.php
 *
 * Parameter:
 *   trip_group_id  — Bestehende Gruppe (optional)
 *   join_trip_id   — Solo-Paddler dem man sich anschließt (optional, erstellt neue Gruppe)
 *   source_type    — 'termin', 'local_event', 'manual' (für neue Gruppe)
 *   source_id      — ID des Events (für neue Gruppe)
 *   group_name     — Name der neuen Gruppe (für neue Gruppe)
 *   boat_id, boat_name, crew_*, start_date, start_time — wie bei create.php
 *
 * Logik:
 *   1. Wenn trip_group_id → Fahrt in bestehender Gruppe erstellen
 *   2. Wenn join_trip_id → Neue Gruppe erstellen, Solo-Trip zuordnen, neue Fahrt erstellen
 *   3. Wenn source_type + source_id → Neue Gruppe aus Event erstellen, Fahrt erstellen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $db = Database::getInstance()->getConnection();

    $tripGroupId = !empty($_POST['trip_group_id']) ? (int)$_POST['trip_group_id'] : null;
    $joinTripId  = !empty($_POST['join_trip_id']) ? (int)$_POST['join_trip_id'] : null;
    $sourceType  = $_POST['source_type'] ?? 'manual';
    $sourceId    = !empty($_POST['source_id']) ? (int)$_POST['source_id'] : null;
    $groupName   = $_POST['group_name'] ?? '';

    // Standard-Trip-Parameter
    $boatId    = !empty($_POST['boat_id']) ? $_POST['boat_id'] : null;
    $boatName  = $_POST['boat_name'] ?? null;
    $startDate = $_POST['start_date'] ?? null;
    $startTime = $_POST['start_time'] ?? null;

    if (!$boatName || !$startDate || !$startTime) {
        jsonResponse(['success' => false, 'message' => 'Boot, Start-Datum und Start-Uhrzeit sind erforderlich'], 400);
    }

    $db->beginTransaction();

    // ── 1. Gruppe ermitteln oder erstellen ──────────────────────────
    if ($tripGroupId) {
        // Bestehende Gruppe prüfen
        $checkStmt = $db->prepare("SELECT id FROM trip_groups WHERE id = :id AND is_completed = 0");
        $checkStmt->execute(['id' => $tripGroupId]);
        if (!$checkStmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Gruppe nicht gefunden oder bereits abgeschlossen'], 404);
        }
    } elseif ($joinTripId) {
        // Solo-Paddler: Neue Gruppe erstellen und dessen Trip zuordnen
        $soloStmt = $db->prepare("SELECT id, boat_name, trip_group_id FROM trips WHERE id = :id AND is_completed = 0");
        $soloStmt->execute(['id' => $joinTripId]);
        $soloTrip = $soloStmt->fetch(PDO::FETCH_ASSOC);

        if (!$soloTrip) {
            jsonResponse(['success' => false, 'message' => 'Solo-Fahrt nicht gefunden oder bereits beendet'], 404);
        }

        if ($soloTrip['trip_group_id']) {
            // Solo-Trip hat bereits eine Gruppe → dieser beitreten
            $tripGroupId = (int)$soloTrip['trip_group_id'];
        } else {
            // Neue Gruppe erstellen
            $name = $groupName ?: ('Gemeinsame Ausfahrt ' . date('d.m.', strtotime($startDate)));
            $createGroupStmt = $db->prepare("
                INSERT INTO trip_groups (name, source_type, created_by_trip_id, started_at)
                VALUES (:name, 'manual', :created_by_trip_id, NOW())
            ");
            $createGroupStmt->execute([
                'name' => $name,
                'created_by_trip_id' => $joinTripId,
            ]);
            $tripGroupId = (int)$db->lastInsertId();

            // Solo-Trip der neuen Gruppe zuordnen
            $updateSoloStmt = $db->prepare("UPDATE trips SET trip_group_id = :group_id WHERE id = :id");
            $updateSoloStmt->execute(['group_id' => $tripGroupId, 'id' => $joinTripId]);
        }
    } else {
        // Für Events: erst prüfen ob bereits eine aktive Gruppe für HEUTE existiert
        // (gleiche Logik wie findTripGroup() in events/upcoming.php)
        if ($sourceId && in_array($sourceType, ['termin', 'local_event'])) {
            $existingStmt = $db->prepare("
                SELECT tg.id FROM trip_groups tg
                WHERE tg.source_type = :st
                  AND tg.source_id = :si
                  AND tg.is_completed = 0
                  AND DATE(tg.started_at) = :today
                  AND EXISTS (SELECT 1 FROM trips t WHERE t.trip_group_id = tg.id AND t.is_completed = 0)
                ORDER BY tg.id DESC
                LIMIT 1
            ");
            $existingStmt->execute(['st' => $sourceType, 'si' => $sourceId, 'today' => $startDate]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $tripGroupId = (int)$existing['id'];
            }
        }

        if (!$tripGroupId) {
            // Neue Gruppe erstellen
            $name = $groupName ?: ('Gemeinsame Ausfahrt ' . date('d.m.', strtotime($startDate)));
            $createGroupStmt = $db->prepare("
                INSERT INTO trip_groups (name, source_type, source_id, started_at)
                VALUES (:name, :source_type, :source_id, NOW())
            ");
            $createGroupStmt->execute([
                'name'        => $name,
                'source_type' => in_array($sourceType, ['termin', 'local_event', 'manual']) ? $sourceType : 'manual',
                'source_id'   => $sourceId,
            ]);
            $tripGroupId = (int)$db->lastInsertId();
        }
    }

    // ── 2. Fahrt erstellen (mit trip_group_id) ──────────────────────
    $tripQuery = "
        INSERT INTO trips (boat_id, boat_name, start_date, start_time, trip_group_id, is_completed, is_backlog)
        VALUES (:boat_id, :boat_name, :start_date, :start_time, :trip_group_id, 0, 0)
    ";
    $tripStmt = $db->prepare($tripQuery);
    $tripStmt->execute([
        'boat_id'       => $boatId,
        'boat_name'     => $boatName,
        'start_date'    => $startDate,
        'start_time'    => $startTime,
        'trip_group_id' => $tripGroupId,
    ]);
    $newTripId = (int)$db->lastInsertId();

    // created_by_trip_id setzen falls neue Gruppe ohne Solo
    if (!$joinTripId && !isset($_POST['trip_group_id'])) {
        $db->prepare("UPDATE trip_groups SET created_by_trip_id = :trip_id WHERE id = :id AND created_by_trip_id IS NULL")
           ->execute(['trip_id' => $newTripId, 'id' => $tripGroupId]);
    }

    // ── 3. Crew hinzufügen ──────────────────────────────────────────
    $crewQuery = "
        INSERT INTO trip_crew (trip_id, seat_position, member_id, member_name)
        VALUES (:trip_id, :seat_position, :member_id, :member_name)
    ";
    $crewStmt = $db->prepare($crewQuery);

    $seatPosition = 1;
    while (isset($_POST['crew_' . $seatPosition])) {
        $crewName = $_POST['crew_' . $seatPosition];
        $crewId = $_POST['crew_' . $seatPosition . '_id'] ?? null;

        // member_id auflösen (direkt oder aus Mitgliedsnummer im Namen)
        $resolvedId = resolveCrewMemberId($crewId, $crewName, $db);

        if (!empty($crewName)) {
            $crewStmt->execute([
                'trip_id'       => $newTripId,
                'seat_position' => $seatPosition,
                'member_id'     => $resolvedId,
                'member_name'   => $resolvedId ? null : $crewName,
            ]);
        }
        $seatPosition++;
    }

    $db->commit();

    jsonResponse([
        'success'       => true,
        'trip_id'       => $newTripId,
        'trip_group_id' => $tripGroupId,
        'message'       => 'Fahrt erstellt und Gruppe beigetreten',
    ]);

} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[Fahrtenbuch api/trips/join-group] Fehler: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
