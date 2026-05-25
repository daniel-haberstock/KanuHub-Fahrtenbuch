<?php
/**
 * API: System-Meldungen abrufen
 * Gibt alle aktiven Meldungen als Key→Object zurück
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT msg_key, msg_text, msg_type, msg_icon
        FROM system_messages
        WHERE is_active = 1
        ORDER BY sort_order ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $messages = [];
    foreach ($rows as $row) {
        $messages[$row['msg_key']] = [
            'text' => $row['msg_text'],
            'type' => $row['msg_type'],
            'icon' => $row['msg_icon']
        ];
    }

    jsonResponse(['success' => true, 'messages' => $messages]);

} catch (\Throwable $e) {
    error_log('[api/messages/list.php] Fehler: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
