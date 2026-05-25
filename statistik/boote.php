<?php
/**
 * Fahrtenbuch – Bootsverwaltung (Mitglieder-Portal)
 *
 * Gibt die Boote des eingeloggten Mitglieds zurück.
 * Mit ?json=1 als JSON-API, ohne als HTML-Seite.
 *
 * Erfordert aktive stats_member_id Session.
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

define('STATS_SESSION_TIMEOUT', 300);

// Session prüfen
if (!isset($_SESSION['stats_member_id'])) {
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    } else {
        header('Location: /statistik/');
    }
    exit;
}
if (isset($_SESSION['stats_last_activity']) && time() - $_SESSION['stats_last_activity'] > STATS_SESSION_TIMEOUT) {
    session_unset();
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sitzung abgelaufen']);
    } else {
        header('Location: /statistik/');
    }
    exit;
}
$_SESSION['stats_last_activity'] = time();

$memberId = (int)$_SESSION['stats_member_id'];
$isJson   = isset($_GET['json']);

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT id, boat_name, boat_type, seats,
               manufacturer, model, serial_number,
               DATE_FORMAT(purchase_date, '%d.%m.%Y') AS purchase_date,
               purchase_price, storage_location
        FROM boats
        WHERE default_crew_1 = :mid
        ORDER BY boat_name ASC
    ");
    $stmt->execute(['mid' => $memberId]);
    $boats = $stmt->fetchAll();

    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'boats' => $boats], JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch statistik/boote.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage() ?: get_class($e)]);
        exit;
    }
    $boats = [];
    $error = $e->getMessage() ?: get_class($e);
}

// HTML-Fallback (falls kein ?json=1)
header('Location: /statistik/#section-boote');
exit;
