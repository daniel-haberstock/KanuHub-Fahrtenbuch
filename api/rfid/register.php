<?php
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht autorisiert']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$uid      = strtoupper(trim($data['uid']       ?? ''));
$name     = trim($data['name']      ?? '');
$memberId = (int)($data['member_id'] ?? 0);
$boatId   = !empty($data['boat_id']) ? (int)$data['boat_id'] : null;

if (!$uid || !$name || !$memberId) {
    echo json_encode(['success' => false, 'error' => 'Fehlende Pflichtfelder (uid, name, member_id)']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->prepare("INSERT INTO rfid_cards (uid, name, member_id, boat_id) VALUES (:uid, :name, :mid, :bid)");
    $stmt->execute(['uid' => $uid, 'name' => $name, 'mid' => $memberId, 'bid' => $boatId]);
    echo json_encode(['success' => true, 'card_id' => (int)$db->lastInsertId()]);
} catch (PDOException $e) {
    if ((int)$e->errorInfo[1] === 1062) {
        echo json_encode(['success' => false, 'error' => 'Diese Karte ist bereits registriert']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    }
}
