<?php
/**
 * API: Badge als "gesehen" markieren
 * Verhindert, dass neue Badges bei jedem Seitenaufruf erneut animiert werden.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $input     = json_decode(file_get_contents('php://input'), true) ?? [];
    $memberId  = (int)($input['member_id']  ?? $_POST['member_id']  ?? 0);
    $badgeKey  = trim($input['badge_key']   ?? $_POST['badge_key']  ?? '');

    if (!$memberId || !$badgeKey) {
        jsonResponse(['success' => false, 'message' => 'member_id und badge_key erforderlich'], 400);
    }

    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare(
        "UPDATE member_badges SET seen = 1 WHERE member_id = ? AND badge_key = ?"
    );
    $stmt->execute([$memberId, $badgeKey]);

    jsonResponse(['success' => true]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/badges/mark-seen.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
