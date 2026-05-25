<?php
/**
 * API: Wetterdaten client-seitig speichern
 * Empfängt Wind-Messwerte vom Browser und schreibt sie in weather_data.
 * Wind: max. 6× täglich (Sperre: 4 Stunden zwischen Einträgen).
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$type = $_POST['type'] ?? '';
$data = $_POST['data'] ?? '';

if (!in_array($type, ['wind', 'pegel', 'watertemp'], true) || $data === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid type or missing data']);
    exit;
}

$decoded = json_decode($data, true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'data must be valid JSON']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Mindestabstand prüfen
    $minAgeHours = match($type) {
        'wind'      => 4,   // max. 6× täglich
        'pegel'     => 20,  // 1× täglich
        'watertemp' => 20,
        default     => 4,
    };

    $stmt = $db->prepare(
        'SELECT fetched_at FROM weather_data
         WHERE type = ? ORDER BY fetched_at DESC LIMIT 1'
    );
    $stmt->execute([$type]);
    $last = $stmt->fetchColumn();

    if ($last && (time() - strtotime($last)) < $minAgeHours * 3600) {
        echo json_encode(['success' => true, 'skipped' => true, 'last' => $last]);
        exit;
    }

    $stmt = $db->prepare(
        'INSERT INTO weather_data (type, data, fetched_at) VALUES (?, ?, NOW())'
    );
    $stmt->execute([$type, json_encode($decoded, JSON_UNESCAPED_UNICODE)]);

    echo json_encode(['success' => true, 'skipped' => false]);

} catch (\Throwable $e) {
    error_log('[weather/save.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error']);
}
