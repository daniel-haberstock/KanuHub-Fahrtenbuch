<?php
/**
 * API: Reservierung erstellen
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $db = Database::getInstance()->getConnection();

    $boatId = $_POST['boat'] ?? null;
    $memberName = $_POST['member_name'] ?? null;
    $memberId = $_POST['member_id'] ?? null;
    $startDate = $_POST['start_date'] ?? null;
    $startTime = $_POST['start_time'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $endTime = $_POST['end_time'] ?? null;
    $reason = $_POST['reason'] ?? null;
    $phone = $_POST['phone'] ?? null;
    $waterId = $_POST['water_id'] ?? null;
    $adminOverride = isset($_POST['admin_override']) && $_POST['admin_override'] === 'true';

    if (!$boatId || !$memberName || !$startDate || !$startTime || !$endDate || !$endTime || !$reason) {
        jsonResponse(['success' => false, 'message' => 'Alle Pflichtfelder müssen ausgefüllt sein'], 400);
    }

    // Reservierungslimit: Max. 14 Tage im Voraus (außer für Admins)
    if (!$adminOverride) {
        $reservationStartDt = new DateTime($startDate . ' ' . $startTime);
        $now = new DateTime();
        $maxDate = (clone $now)->modify('+14 days');

        if ($reservationStartDt > $maxDate) {
            jsonResponse(['success' => false, 'message' => 'Reservierungen können nur bis zu 14 Tage im Voraus erstellt werden'], 400);
        }
    }

    $query = "
        INSERT INTO boat_reservations (boat_id, member_id, member_name, reservation_start, reservation_end, reason, phone, water_id)
        VALUES (:boat_id, :member_id, :member_name, :reservation_start, :reservation_end, :reason, :phone, :water_id)
    ";

    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        'boat_id' => $boatId,
        'member_id' => $memberId ?: null,
        'member_name' => $memberName,
        'reservation_start' => $startDate . ' ' . $startTime,
        'reservation_end' => $endDate . ' ' . $endTime,
        'reason' => $reason,
        'phone' => $phone ?: null,
        'water_id' => $waterId ?: null
    ]);

    if ($result) {
        jsonResponse(['success' => true, 'message' => 'Reservierung erfolgreich erstellt']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Fehler beim Erstellen der Reservierung'], 500);
    }

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/reservations/create.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
