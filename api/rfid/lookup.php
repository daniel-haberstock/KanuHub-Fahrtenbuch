<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$uid = strtoupper(trim($_GET['uid'] ?? ''));
if (!$uid) {
    echo json_encode(['success' => false, 'error' => 'missing_uid']);
    exit;
}

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT c.id AS card_id, c.name AS card_name,
           m.id AS member_id, m.first_name, m.last_name, m.status,
           b.id AS boat_id, b.boat_name
    FROM   rfid_cards c
    JOIN   members m ON m.id = c.member_id
    LEFT JOIN boats b ON b.id = c.boat_id
    WHERE  c.uid = :uid
      AND  (m.valid_until IS NULL OR m.valid_until >= CURDATE())
");
$stmt->execute(['uid' => $uid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'not_found']);
    exit;
}

if ($row['status'] !== 'Aktiv') {
    echo json_encode(['success' => false, 'error' => 'member_inactive',
        'member' => $row['first_name'] . ' ' . $row['last_name']]);
    exit;
}

echo json_encode([
    'success' => true,
    'card' => [
        'id'     => (int)$row['card_id'],
        'name'   => $row['card_name'],
        'member' => ['id' => (int)$row['member_id'], 'first_name' => $row['first_name'], 'last_name' => $row['last_name']],
        'boat'   => $row['boat_id'] ? ['id' => (int)$row['boat_id'], 'name' => $row['boat_name']] : null,
    ],
]);
