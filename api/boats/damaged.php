<?php
/**
 * API: Beschädigte Boote abrufen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();

    $query = "
        SELECT
            bd.id,
            bd.boat_id,
            b.boat_name,
            bd.damage_description,
            bd.damage_type,
            bd.created_at
        FROM boat_damages bd
        JOIN boats b ON bd.boat_id = b.id
        WHERE bd.is_fixed = 0
        AND (b.valid_until IS NULL OR b.valid_until >= CURDATE())
        ORDER BY bd.created_at DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $damages = $stmt->fetchAll();

    jsonResponse(['success' => true, 'damages' => $damages]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/boats/damaged.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
