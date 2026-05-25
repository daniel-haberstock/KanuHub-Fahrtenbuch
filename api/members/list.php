<?php
/**
 * API: Mitgliederliste abrufen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();

    $query = "
        SELECT id, first_name, last_name, membership_no, status
        FROM members
        WHERE (valid_until IS NULL OR valid_until >= CURDATE())
        ORDER BY last_name ASC, first_name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $members = $stmt->fetchAll();

    jsonResponse(['success' => true, 'members' => $members]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/members/list.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
