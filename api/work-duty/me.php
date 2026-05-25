<?php
/**
 * API: Eigene Arbeitsstunden
 * GET ?member_id=X&year=YYYY
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$memberId = !empty($_SESSION['stats_member_id']) ? (int)$_SESSION['stats_member_id'] : (int)($_GET['member_id'] ?? 0);
$year = (int)($_GET['year'] ?? date('Y'));

if (!$memberId) {
    jsonResponse(['success' => false, 'message' => 'Nicht eingeloggt'], 401);
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT id, work_date, hours, description, work_leader,
               CASE WHEN work_leader IS NOT NULL THEN 1 ELSE 0 END AS is_confirmed
        FROM work_hours
        WHERE member_id = :mid AND YEAR(work_date) = :yr
        ORDER BY work_date DESC
    ");
    $stmt->execute([':mid' => $memberId, ':yr' => $year]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalHours     = array_sum(array_column($entries, 'hours'));
    $confirmedHours = array_sum(array_map(fn($e) => $e['is_confirmed'] ? $e['hours'] : 0, $entries));

    // Target: 40h per year (could be configurable)
    $targetHours = defined('WORK_DUTY_HOURS') ? WORK_DUTY_HOURS : 40;

    jsonResponse([
        'success'         => true,
        'entries'         => $entries,
        'total_hours'     => (float)$totalHours,
        'confirmed_hours' => (float)$confirmedHours,
        'target_hours'    => $targetHours,
        'year'            => $year,
    ]);
} catch (\Throwable $e) {
    error_log('[Fahrtenbuch work-duty/me.php] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Fehler'], 500);
}
