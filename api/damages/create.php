<?php
/**
 * API: Schaden melden
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $db = Database::getInstance()->getConnection();

    $boatId = $_POST['boat'] ?? null;
    $damageDescription = $_POST['damage_description'] ?? null;
    $damageType = $_POST['damage_type'] ?? null;
    $reporterName = $_POST['reporter_name'] ?? null;
    $reporterId = $_POST['reporter_id'] ?? null;

    if (!$boatId || !$damageDescription || !$damageType) {
        jsonResponse(['success' => false, 'message' => 'Boot, Schadensbeschreibung und Schadensart sind erforderlich'], 400);
    }

    $query = "
        INSERT INTO boat_damages (boat_id, damage_description, damage_type, reporter_member_id, reporter_name)
        VALUES (:boat_id, :damage_description, :damage_type, :reporter_id, :reporter_name)
    ";

    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        'boat_id' => $boatId,
        'damage_description' => $damageDescription,
        'damage_type' => $damageType,
        'reporter_id' => $reporterId ?: null,
        'reporter_name' => $reporterId ? null : $reporterName
    ]);

    if ($result) {
        jsonResponse(['success' => true, 'message' => 'Schaden erfolgreich gemeldet']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Fehler beim Melden des Schadens'], 500);
    }

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/damages/create.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
