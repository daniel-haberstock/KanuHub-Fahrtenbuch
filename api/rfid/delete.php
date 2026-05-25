<?php
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht autorisiert']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$cardId = (int)($data['card_id'] ?? 0);

if (!$cardId) {
    echo json_encode(['success' => false, 'error' => 'Fehlende card_id']);
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("DELETE FROM rfid_cards WHERE id = :id");
$stmt->execute(['id' => $cardId]);

echo json_encode(['success' => true]);
