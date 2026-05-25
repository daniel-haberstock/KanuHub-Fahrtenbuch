<?php
/**
 * API: Vereinsboote abrufen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();
    $prefix = defined('CLUB_BOAT_PREFIX') ? CLUB_BOAT_PREFIX : 'Verein';

    $query = "
        SELECT id, boat_name, boat_type, seats
        FROM boats
        WHERE boat_name LIKE :prefix
        AND (valid_until IS NULL OR valid_until >= CURDATE())
        ORDER BY boat_name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':prefix' => $prefix . '%']);
    $boats = $stmt->fetchAll();

    jsonResponse(['success' => true, 'boats' => $boats]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/boats/club.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
