<?php
/**
 * API: Arbeitseinsatz erfassen (Selbsteintrag durch Mitglied)
 * POST: work_date, hours, description
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST'], 405);
}

$memberId = !empty($_SESSION['stats_member_id']) ? (int)$_SESSION['stats_member_id'] : null;
if (!$memberId) {
    jsonResponse(['success' => false, 'message' => 'Nicht eingeloggt'], 401);
}

$workDate    = $_POST['work_date']    ?? null;
$hours       = str_replace(',', '.', $_POST['hours'] ?? '');
$description = trim($_POST['description'] ?? '');

if (!$workDate || !$hours || !$description) {
    jsonResponse(['success' => false, 'message' => 'Alle Felder erforderlich'], 400);
}

$hours = (float)$hours;
if ($hours <= 0 || $hours > 24) {
    jsonResponse(['success' => false, 'message' => 'Ungültige Stundenzahl'], 400);
}

try {
    $db = Database::getInstance()->getConnection();
    $db->prepare("
        INSERT INTO work_hours (member_id, work_date, hours, description, created_by)
        VALUES (:mid, :wd, :h, :desc, 'member')
    ")->execute([':mid' => $memberId, ':wd' => $workDate, ':h' => $hours, ':desc' => $description]);

    $newId = (int)$db->lastInsertId();

    jsonResponse(['success' => true, 'id' => $newId, 'message' => 'Eintrag gespeichert']);
} catch (\Throwable $e) {
    error_log('[Fahrtenbuch work-duty/create.php] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Fehler beim Speichern'], 500);
}
