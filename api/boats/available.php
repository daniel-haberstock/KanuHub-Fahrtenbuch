<?php
/**
 * API: Verfügbare Boote abrufen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();

    // Verfügbare Boote: Nicht auf Fahrt, nicht beschädigt, nicht reserviert, im Bestand
    $query = "
        SELECT DISTINCT b.id, b.boat_name, b.boat_type, b.seats, b.default_crew_1, b.default_crew_2,
               b.video_url, b.note_start, b.note_end, b.boat_image, b.boat_icon_id, b.boat_details,
               bi.file_path as icon_file_path,
               m1.first_name as crew1_first, m1.last_name as crew1_last,
               m2.first_name as crew2_first, m2.last_name as crew2_last
        FROM boats b
        LEFT JOIN members m1 ON b.default_crew_1 = m1.id AND (m1.valid_until IS NULL OR m1.valid_until >= CURDATE())
        LEFT JOIN members m2 ON b.default_crew_2 = m2.id AND (m2.valid_until IS NULL OR m2.valid_until >= CURDATE())
        LEFT JOIN boat_icons bi ON b.boat_icon_id = bi.id
        WHERE (b.valid_until IS NULL OR b.valid_until >= CURDATE())
        AND b.id NOT IN (
            -- Normale Boote auf Fahrt (nicht abgeschlossen)
            SELECT boat_id FROM trips WHERE is_completed = 0 AND boat_id IS NOT NULL
            UNION
            -- Anhänger auf Fahrt (nicht abgeschlossen)
            SELECT boat_id FROM trailer_trips WHERE is_completed = 0 AND boat_id IS NOT NULL
        )
        AND b.id NOT IN (
            -- Beschädigte Boote (nicht repariert)
            SELECT boat_id FROM boat_damages WHERE is_fixed = 0
        )
        AND b.id NOT IN (
            -- Reservierte Boote (aktuell aktiv)
            SELECT boat_id FROM boat_reservations
            WHERE NOW() BETWEEN reservation_start AND reservation_end
        )
        ORDER BY b.boat_name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $boats = $stmt->fetchAll();

    // Formatiere default crew Namen + boat_details JSON dekodieren
    foreach ($boats as &$boat) {
        if ($boat['crew1_first']) {
            $boat['default_crew_1_name'] = $boat['crew1_first'] . ' ' . $boat['crew1_last'];
        }
        if ($boat['crew2_first']) {
            $boat['default_crew_2_name'] = $boat['crew2_first'] . ' ' . $boat['crew2_last'];
        }
        // boat_details als Objekt durchreichen
        $boat['boat_details'] = $boat['boat_details'] ? json_decode($boat['boat_details'], true) : null;
        // Icon-Pfad zusammensetzen
        if ($boat['icon_file_path']) {
            $boat['boat_icon_url'] = '/assets/icons/boats/' . $boat['icon_file_path'];
        }
    }

    jsonResponse(['success' => true, 'boats' => $boats]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/boats/available.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
