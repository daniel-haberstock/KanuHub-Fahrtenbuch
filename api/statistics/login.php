<?php
/**
 * API: Statistik Login (E-Mail + Passwort)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $email = $_POST['email'] ?? null;
    $rawPassword = $_POST['password'] ?? null;

    if (!$email || !$rawPassword) {
        jsonResponse(['success' => false, 'message' => 'E-Mail und Passwort erforderlich'], 400);
    }

    // Authentifizierung via REDAXO-API (Single Source of Truth)
    $apiResult = authenticateViaApi($email, $rawPassword);

    if ($apiResult === false) {
        jsonResponse(['success' => false, 'message' => 'Ungültige E-Mail oder Passwort'], 401);
    }

    // API-Auth erfolgreich → Member lokal per membership_no oder email suchen
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, membership_no,
               hide_statistics, hide_statistics_returned_visible, hide_statistics_locked
        FROM members
        WHERE ((membership_no = :membership_no AND membership_no != '')
           OR email = :email)
        AND (valid_until IS NULL OR valid_until >= CURDATE())
        LIMIT 1
    ");
    $stmt->execute([
        'membership_no' => $apiResult['membership_no'] ?? '',
        'email'         => $email,
    ]);
    $member = $stmt->fetch();

    if (!$member) {
        jsonResponse(['success' => false, 'message' => 'Dein Account wurde noch nicht synchronisiert. Bitte wende dich an den Vorstand.'], 404);
    }

    // hide_statistics: null = unentschieden, 0 = sichtbar, 1 = unsichtbar
    $hideValue = $member['hide_statistics'];
    jsonResponse([
        'success'                       => true,
        'member_id'                     => $member['id'],
        'member_name'                   => $member['first_name'] . ' ' . $member['last_name'],
        'hide_statistics'               => ($hideValue === null) ? null : (int)$hideValue,
        'hide_statistics_locked'        => (bool)$member['hide_statistics_locked'],
        'hide_statistics_returned_visible' => (bool)$member['hide_statistics_returned_visible'],
    ]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/statistics/login.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => 'Anmeldung derzeit nicht möglich.'], 500);
}
