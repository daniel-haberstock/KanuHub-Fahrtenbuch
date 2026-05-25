<?php
/**
 * Fahrtenbuch – Sichtbarkeits-Umschaltung (Mitglieder-Portal)
 *
 * Nimmt POST-Anfragen aus dem Einstellungs-Bereich entgegen,
 * verarbeitet die Sichtbarkeits-Änderung und leitet zurück zu /statistik/.
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

define('STATS_SESSION_TIMEOUT', 300);

if (!isset($_SESSION['stats_member_id'])) {
    header('Location: /statistik/');
    exit;
}
if (isset($_SESSION['stats_last_activity']) && time() - $_SESSION['stats_last_activity'] > STATS_SESSION_TIMEOUT) {
    session_unset();
    header('Location: /statistik/');
    exit;
}
$_SESSION['stats_last_activity'] = time();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /statistik/#section-einstellungen');
    exit;
}

$memberId = (int)$_SESSION['stats_member_id'];
$action   = $_POST['action'] ?? '';

if (!in_array($action, ['show', 'hide'], true)) {
    header('Location: /statistik/#section-einstellungen');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare(
        "SELECT hide_statistics, hide_statistics_returned_visible, hide_statistics_locked
         FROM members WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();

    if (!$member || $member['hide_statistics_locked']) {
        header('Location: /statistik/#section-einstellungen');
        exit;
    }

    $current  = $member['hide_statistics'];
    $returned = (int)$member['hide_statistics_returned_visible'];

    if ($action === 'show' && $current == 1) {
        // Unsichtbar → Sichtbar (einmalig)
        $db->prepare(
            "UPDATE members SET hide_statistics = 0, hide_statistics_returned_visible = 1 WHERE id = ?"
        )->execute([$memberId]);
    } elseif ($action === 'hide' && $current != 1) {
        if ($returned === 1) {
            // 2. Unsichtbar → dauerhaft gesperrt
            $db->prepare(
                "UPDATE members SET hide_statistics = 1, hide_statistics_locked = 1 WHERE id = ?"
            )->execute([$memberId]);
        } else {
            // 1. Unsichtbar (reversibel)
            $db->prepare("UPDATE members SET hide_statistics = 1 WHERE id = ?")
               ->execute([$memberId]);
        }
    }

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch statistik/toggle-visibility.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

header('Location: /statistik/#section-einstellungen');
exit;
