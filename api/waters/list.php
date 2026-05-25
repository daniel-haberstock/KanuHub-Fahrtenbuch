<?php
/**
 * Gewässer-Liste abrufen
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();

    // Gewässer abrufen
    $query = "
        SELECT id, name, description
        FROM waters
        ORDER BY name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $waters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'waters' => $waters
    ]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/waters/list.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fehler beim Laden der Gewässer: ' . ($e->getMessage() ?: get_class($e))
    ]);
}
