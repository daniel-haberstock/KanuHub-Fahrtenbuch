<?php
/**
 * API: Eigenes Boot bearbeiten (Mitglieder-Portal)
 *
 * GET  ?boat_id=X  → Boot-Daten laden (nur wenn default_crew_1 = Session-Mitglied)
 * POST             → Boot-Daten speichern
 *
 * Erfordert aktive stats_member_id Session.
 */

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

define('STATS_SESSION_TIMEOUT', 300);

// Session prüfen
if (!isset($_SESSION['stats_member_id'])) {
    jsonResponse(['success' => false, 'message' => 'Nicht eingeloggt'], 401);
}
if (isset($_SESSION['stats_last_activity']) && time() - $_SESSION['stats_last_activity'] > STATS_SESSION_TIMEOUT) {
    session_unset();
    jsonResponse(['success' => false, 'message' => 'Sitzung abgelaufen'], 401);
}
$_SESSION['stats_last_activity'] = time();

$memberId = (int)$_SESSION['stats_member_id'];

try {
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Boot laden
        $boatId = (int)($_GET['boat_id'] ?? 0);
        if (!$boatId) {
            jsonResponse(['success' => false, 'message' => 'Keine Boot-ID angegeben'], 400);
        }

        $stmt = $db->prepare("
            SELECT id, boat_name, boat_type, seats,
                   manufacturer, model, serial_number,
                   purchase_date, purchase_price, storage_location
            FROM boats
            WHERE id = :bid AND default_crew_1 = :mid
            LIMIT 1
        ");
        $stmt->execute(['bid' => $boatId, 'mid' => $memberId]);
        $boat = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$boat) {
            jsonResponse(['success' => false, 'message' => 'Boot nicht gefunden oder du bist nicht der Eigentümer.'], 404);
        }

        jsonResponse(['success' => true, 'boat' => $boat]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Boot speichern
        $boatId       = (int)($_POST['boat_id'] ?? 0);
        $boatName     = trim($_POST['boat_name'] ?? '');
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $modelVal     = trim($_POST['model'] ?? '');
        $serialNumber = trim($_POST['serial_number'] ?? '');
        $purchaseDate = trim($_POST['purchase_date'] ?? '');
        $purchasePrice= trim($_POST['purchase_price'] ?? '');

        if (!$boatId) {
            jsonResponse(['success' => false, 'message' => 'Keine Boot-ID angegeben'], 400);
        }
        if (!$boatName) {
            jsonResponse(['success' => false, 'message' => 'Der Bootsname ist ein Pflichtfeld.'], 400);
        }

        // Eigentümer-Prüfung
        $check = $db->prepare("SELECT id FROM boats WHERE id = :bid AND default_crew_1 = :mid LIMIT 1");
        $check->execute(['bid' => $boatId, 'mid' => $memberId]);
        if (!$check->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Boot nicht gefunden oder du bist nicht der Eigentümer.'], 403);
        }

        $upd = $db->prepare("
            UPDATE boats
            SET boat_name      = :boat_name,
                manufacturer   = :manufacturer,
                model          = :model,
                serial_number  = :serial_number,
                purchase_date  = :purchase_date,
                purchase_price = :purchase_price
            WHERE id = :id AND default_crew_1 = :mid
        ");
        $upd->execute([
            'boat_name'      => $boatName,
            'manufacturer'   => $manufacturer ?: null,
            'model'          => $modelVal ?: null,
            'serial_number'  => $serialNumber ?: null,
            'purchase_date'  => $purchaseDate ?: null,
            'purchase_price' => $purchasePrice !== '' && is_numeric($purchasePrice)
                                ? (float)$purchasePrice : null,
            'id'             => $boatId,
            'mid'            => $memberId,
        ]);

        jsonResponse(['success' => true, 'message' => 'Boot-Daten wurden gespeichert.']);

    } else {
        jsonResponse(['success' => false, 'message' => 'Nur GET/POST erlaubt'], 405);
    }

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/boats/update-own.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
