<?php
/**
 * API: Reservierte Boote abrufen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();

    // Alte Reservierungen aufräumen (abgelaufene)
    $cleanupQuery = "
        DELETE FROM boat_reservations
        WHERE reservation_end < NOW()
    ";
    $db->exec($cleanupQuery);

    $query = "
        SELECT
            br.id,
            br.boat_id,
            b.boat_name,
            b.seats,
            br.member_id,
            br.member_name,
            m.first_name,
            m.last_name,
            DATE_FORMAT(br.reservation_start, '%d.%m.%Y %H:%i') as start_datetime,
            DATE_FORMAT(br.reservation_end, '%d.%m.%Y %H:%i') as end_datetime,
            DATE_FORMAT(br.reservation_start, '%Y-%m-%d %H:%i:%s') as start_iso,
            DATE_FORMAT(br.reservation_end,   '%Y-%m-%d %H:%i:%s') as end_iso,
            br.reason
        FROM boat_reservations br
        JOIN boats b ON br.boat_id = b.id
        LEFT JOIN members m ON br.member_id = m.id
        WHERE br.reservation_end >= NOW()
        AND (b.valid_until IS NULL OR b.valid_until >= CURDATE())
        AND (m.id IS NULL OR m.valid_until IS NULL OR m.valid_until >= CURDATE())
        ORDER BY br.reservation_start ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $reservations = $stmt->fetchAll();

    // Mitglied-Anzeige formatieren
    foreach ($reservations as &$res) {
        if ($res['member_id']) {
            $res['member_display'] = $res['first_name'] . ' ' . $res['last_name'];
        } else {
            $res['member_display'] = $res['member_name'];
        }
    }

    jsonResponse(['success' => true, 'reservations' => $reservations]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/boats/reserved.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
