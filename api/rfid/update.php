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
$name   = trim($data['name']    ?? '');
$boatId = !empty($data['boat_id']) ? (int)$data['boat_id'] : null;

if (!$cardId || !$name) {
    echo json_encode(['success' => false, 'error' => 'Fehlende Pflichtfelder']);
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("UPDATE rfid_cards SET name = :name, boat_id = :bid WHERE id = :id");
$stmt->execute(['name' => $name, 'bid' => $boatId, 'id' => $cardId]);

echo json_encode(['success' => true]);
