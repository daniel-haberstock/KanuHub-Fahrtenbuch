<?php
/**
 * API: Statistik-Sichtbarkeit umschalten
 *
 * POST-Parameter:
 *   member_id  INT    – Mitglieds-ID (aus Login-Response)
 *   action     STRING – "show" (sichtbar machen) oder "hide" (unsichtbar machen)
 *
 * Zustandsmaschine (Geschäftsregeln):
 *   NULL → 0   : Erstentscheidung "sichtbar"
 *   NULL → 1   : Erstentscheidung "unsichtbar"
 *   0    → 1   : Unsichtbar (warnung nötig; returned_visible=0 → noch 1 freier Rückwechsel)
 *   1    → 0   : Sichtbar (nur wenn !locked); setzt returned_visible=1
 *   0    → 1   (2. Mal, returned_visible=1) : LOCKED, dauerhaft unsichtbar
 *   locked=1   : Kein Self-Toggle mehr möglich → Fehlermeldung
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

$memberId = (int)($_POST['member_id'] ?? 0);
$action   = $_POST['action'] ?? '';

if (!$memberId || !in_array($action, ['show', 'hide', 'decide_show', 'decide_hide'], true)) {
    jsonResponse(['success' => false, 'message' => 'member_id und action (show|hide|decide_show|decide_hide) erforderlich'], 400);
}

try {
    $db = Database::getInstance()->getConnection();

    // Aktuellen Zustand laden
    $stmt = $db->prepare(
        "SELECT hide_statistics, hide_statistics_returned_visible, hide_statistics_locked
         FROM members WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();

    if (!$member) {
        jsonResponse(['success' => false, 'message' => 'Mitglied nicht gefunden'], 404);
    }

    $current       = $member['hide_statistics'];           // NULL, 0 oder 1
    $returned      = (int)$member['hide_statistics_returned_visible'];
    $locked        = (int)$member['hide_statistics_locked'];

    // ----------------------------------------------------------------
    // Erstentscheidung (aus NULL heraus)
    // ----------------------------------------------------------------
    if ($action === 'decide_show' || $action === 'decide_hide') {
        if ($current !== null) {
            jsonResponse(['success' => false, 'message' => 'Entscheidung wurde bereits getroffen'], 409);
        }
        $newValue = ($action === 'decide_hide') ? 1 : 0;
        $db->prepare("UPDATE members SET hide_statistics = ? WHERE id = ?")
           ->execute([$newValue, $memberId]);
        jsonResponse([
            'success'            => true,
            'hide_statistics'    => $newValue,
            'locked'             => false,
            'returned_visible'   => false,
            'message'            => $newValue === 1
                ? 'Du bist jetzt unsichtbar in der Statistik. Du kannst einmalig selbst wieder sichtbar werden.'
                : 'Du bist in der Statistik sichtbar.',
        ]);
    }

    // ----------------------------------------------------------------
    // Gesperrt → kein Self-Toggle
    // ----------------------------------------------------------------
    if ($locked) {
        jsonResponse([
            'success' => false,
            'locked'  => true,
            'message' => 'Deine Sichtbarkeit ist dauerhaft deaktiviert. Bitte wende dich an die Vorstandschaft.',
        ], 403);
    }

    // ----------------------------------------------------------------
    // SHOW: unsichtbar → sichtbar (nur wenn noch nicht returned)
    // ----------------------------------------------------------------
    if ($action === 'show') {
        if ($current != 1) {
            jsonResponse(['success' => false, 'message' => 'Du bist bereits sichtbar'], 409);
        }
        // Rückwechsel ist einmalig
        $db->prepare(
            "UPDATE members SET hide_statistics = 0, hide_statistics_returned_visible = 1 WHERE id = ?"
        )->execute([$memberId]);

        jsonResponse([
            'success'          => true,
            'hide_statistics'  => 0,
            'locked'           => false,
            'returned_visible' => true,
            'message'          => 'Du bist jetzt wieder sichtbar. Achtung: Ein erneutes Ausblenden ist dauerhaft – du kannst dich dann nur noch über die Vorstandschaft wieder einblenden lassen.',
        ]);
    }

    // ----------------------------------------------------------------
    // HIDE: sichtbar → unsichtbar
    // ----------------------------------------------------------------
    if ($action === 'hide') {
        if ($current == 1) {
            jsonResponse(['success' => false, 'message' => 'Du bist bereits unsichtbar'], 409);
        }

        if ($returned === 1) {
            // 2. Unsichtbar-Wahl → LOCK
            $db->prepare(
                "UPDATE members SET hide_statistics = 1, hide_statistics_locked = 1 WHERE id = ?"
            )->execute([$memberId]);
            jsonResponse([
                'success'          => true,
                'hide_statistics'  => 1,
                'locked'           => true,
                'returned_visible' => true,
                'message'          => 'Du bist dauerhaft unsichtbar. Eine erneute Änderung ist nur noch über die Vorstandschaft möglich.',
            ]);
        } else {
            // 1. Unsichtbar-Wahl
            $db->prepare("UPDATE members SET hide_statistics = 1 WHERE id = ?")
               ->execute([$memberId]);
            jsonResponse([
                'success'          => true,
                'hide_statistics'  => 1,
                'locked'           => false,
                'returned_visible' => false,
                'message'          => 'Du bist jetzt unsichtbar. Du kannst einmalig selbst wieder sichtbar werden.',
            ]);
        }
    }

    jsonResponse(['success' => false, 'message' => 'Unbekannte Aktion'], 400);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/statistics/visibility.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
