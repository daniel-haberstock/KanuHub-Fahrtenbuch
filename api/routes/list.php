<?php
/**
 * API: Streckenliste abrufen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();

    $query = "
        SELECT
            r.id,
            r.start_point,
            r.end_point,
            r.distance,
            r.description,
            w.name as water_name
        FROM routes r
        LEFT JOIN waters w ON r.water_id = w.id
        ORDER BY COALESCE(NULLIF(r.description, ''), CONCAT(r.start_point, ' - ', r.end_point)) ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $routes = $stmt->fetchAll();

    jsonResponse(['success' => true, 'routes' => $routes]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/routes/list.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
