<?php
/**
 * API: Alle Boote mit aktuellem Status
 *
 * Gibt alle aktiven Boote zurück mit normalisiertem Status:
 * available | onWater | reserved | damaged
 *
 * Rückgabe: [{id, name, type, seats, is_club_boat, status,
 *             since, until, crew, boat_icon_url, boat_details}]
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Kurzform-Typ aus DB-Typ + Sitze ableiten
function shortBoatType(string $type, int $seats): string {
    if (stripos($type, 'Canadier') !== false) return 'C' . $seats;
    if ($type === 'SUP')        return 'SUP';
    if ($type === 'Anhänger')   return 'Anh';
    if ($type === 'Outrigger')  return 'OC' . $seats;
    if ($type === 'Surf Ski')   return 'SS';
    if ($type === 'Faltboot')   return 'Falt';
    if ($type === 'Packraft')   return 'Pack';
    return 'K' . $seats; // Alle Kajak-Varianten
}

try {
    $db = Database::getInstance()->getConnection();

    // Alle gültigen Boote laden
    $stmt = $db->prepare("
        SELECT b.id, b.boat_name, b.boat_type, b.seats,
               b.note_start, b.note_end, b.video_url,
               b.boat_image, b.boat_details,
               b.default_crew_1, b.default_crew_2,
               CONCAT(m1.first_name, ' ', m1.last_name) AS default_crew_1_name,
               CONCAT(m2.first_name, ' ', m2.last_name) AS default_crew_2_name,
               bi.file_path AS icon_file_path
        FROM boats b
        LEFT JOIN boat_icons bi ON b.boat_icon_id = bi.id
        LEFT JOIN members m1 ON m1.id = b.default_crew_1
        LEFT JOIN members m2 ON m2.id = b.default_crew_2
        WHERE (b.valid_until IS NULL OR b.valid_until >= CURDATE())
        ORDER BY b.boat_type ASC, b.boat_name ASC
    ");
    $stmt->execute();
    $boats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Status-Maps vorab laden (1 Query je Status-Typ = effizient)

    // Auf dem Wasser (aktive Trips)
    $onWater = [];
    $s = $db->query("
        SELECT t.boat_id,
               TIME_FORMAT(t.start_time,'%H:%i') AS since,
               GROUP_CONCAT(COALESCE(CONCAT(m.first_name,' ',m.last_name), tc.member_name)
                            ORDER BY tc.seat_position SEPARATOR ', ') AS crew
        FROM trips t
        LEFT JOIN trip_crew tc ON tc.trip_id = t.id
        LEFT JOIN members   m  ON tc.member_id = m.id
        WHERE t.is_completed = 0 AND t.boat_id IS NOT NULL
        GROUP BY t.boat_id, t.start_time
    ");
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $onWater[$r['boat_id']] = ['since' => $r['since'], 'crew' => $r['crew']];
    }

    // Beschädigt
    $damaged = [];
    $s = $db->query("SELECT DISTINCT boat_id FROM boat_damages WHERE is_fixed = 0");
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $damaged[$id] = true;
    }

    // Reserviert (aktuell aktiv)
    $reserved = [];
    $s = $db->query("
        SELECT boat_id, DATE_FORMAT(reservation_end,'%d.%m. %H:%i') AS until_fmt
        FROM boat_reservations
        WHERE NOW() BETWEEN reservation_start AND reservation_end
    ");
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $reserved[$r['boat_id']] = $r['until_fmt'];
    }

    // Reserviert in den nächsten 10 Stunden (noch nicht aktiv, aber bald)
    $reservedSoon = [];
    $s = $db->query("
        SELECT DISTINCT boat_id FROM boat_reservations
        WHERE reservation_start > NOW()
          AND reservation_start <= DATE_ADD(NOW(), INTERVAL 10 HOUR)
    ");
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $reservedSoon[$id] = true;
    }

    $result = [];
    foreach ($boats as $b) {
        $id    = (int)$b['id'];
        $seats = (int)$b['seats'];

        // Status bestimmen
        if (isset($damaged[$id])) {
            $status = 'damaged'; $extra = [];
        } elseif (isset($reserved[$id])) {
            $status = 'reserved'; $extra = ['until' => $reserved[$id]];
        } elseif (isset($onWater[$id])) {
            $status = 'onWater';  $extra = $onWater[$id];
        } else {
            $status = 'available'; $extra = [];
        }

        $result[] = array_merge([
            'id'          => $id,
            'name'        => $b['boat_name'],
            'type'        => shortBoatType($b['boat_type'], $seats),
            'type_full'   => $b['boat_type'],
            'seats'       => $seats,
            'is_club_boat'  => str_starts_with($b['boat_name'], CLUB_BOAT_PREFIX),
            'status'        => $status,
            'reserved_soon' => isset($reservedSoon[$id]) && $status === 'available',
            'boat_icon_url' => $b['icon_file_path'] ? '/assets/icons/boats/' . $b['icon_file_path'] : null,
            'boat_details'  => $b['boat_details'] ? json_decode($b['boat_details'], true) : null,
            'note_start'         => $b['note_start'],
            'note_end'           => $b['note_end'],
            'video_url'          => $b['video_url'],
            'default_crew_1'     => $b['default_crew_1']      ? (int)$b['default_crew_1']     : null,
            'default_crew_1_name'=> $b['default_crew_1_name'] ?: null,
            'default_crew_2'     => $b['default_crew_2']      ? (int)$b['default_crew_2']     : null,
            'default_crew_2_name'=> $b['default_crew_2_name'] ?: null,
        ], $extra);
    }

    echo json_encode(['success' => true, 'boats' => $result]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch boats/all] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
