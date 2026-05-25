<?php
/**
 * YCom Sync – AJAX-Endpoint für den Admin-Bereich
 * Gibt JSON zurück statt Text-Output.
 * Erfordert aktiven Admin-Login.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $db = Database::getInstance()->getConnection();

    $ycomDb = new PDO(
        'mysql:host=' . YCOM_DB_HOST . ';dbname=' . YCOM_DB_NAME . ';charset=utf8mb4',
        YCOM_DB_USER,
        YCOM_DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $ycomStmt = $ycomDb->prepare("
        SELECT u.id, u.mitglied_nr AS membership_no,
               u.firstname AS first_name, u.name AS last_name,
               u.email, u.anrede, u.ycom_groups
        FROM rex_ycom_user u
        WHERE u.status = 1
          AND u.mitglied_nr IS NOT NULL
          AND u.mitglied_nr != ''
    ");
    $ycomStmt->execute();
    $ycomMembers = $ycomStmt->fetchAll();

    function _mapSalutation($anrede) {
        return $anrede == 2 ? 'Frau' : 'Herr';
    }
    function _mapStatus($ycomGroups) {
        $g = trim(explode(',', $ycomGroups)[0]);
        return match((int)$g) {
            2 => 'Gastmitglied',
            3 => 'Jugendmitglied',
            4 => 'Ehrenmitglied',
            5 => 'Passiv',
            default => 'Aktiv',
        };
    }

    $stats = ['created' => 0, 'updated' => 0, 'deactivated' => 0, 'errors' => 0];
    $ycomNos = [];

    $db->beginTransaction();

    foreach ($ycomMembers as $m) {
        try {
            $ycomNos[] = $m['membership_no'];

            $check = $db->prepare("SELECT id FROM members WHERE membership_no = :no");
            $check->execute(['no' => $m['membership_no']]);

            if ($check->fetch()) {
                $db->prepare("
                    UPDATE members
                    SET first_name = :fn, last_name = :ln, email = :em,
                        salutation = :sa, status = :st, valid_until = NULL, updated_at = NOW()
                    WHERE membership_no = :no
                ")->execute([
                    'fn' => $m['first_name'], 'ln' => $m['last_name'],
                    'em' => $m['email'],      'sa' => _mapSalutation($m['anrede']),
                    'st' => _mapStatus($m['ycom_groups']), 'no' => $m['membership_no'],
                ]);
                $stats['updated']++;
            } else {
                $db->prepare("
                    INSERT INTO members (membership_no, first_name, last_name, email, salutation, status, valid_until)
                    VALUES (:no, :fn, :ln, :em, :sa, :st, NULL)
                ")->execute([
                    'no' => $m['membership_no'], 'fn' => $m['first_name'],
                    'ln' => $m['last_name'],     'em' => $m['email'],
                    'sa' => _mapSalutation($m['anrede']),
                    'st' => _mapStatus($m['ycom_groups']),
                ]);
                $stats['created']++;
            }
        } catch (\Throwable $e) {
            $stats['errors']++;
        }
    }

    if (!empty($ycomNos)) {
        $placeholders = implode(',', array_fill(0, count($ycomNos), '?'));
        $deact = $db->prepare("
            UPDATE members SET valid_until = CURDATE()
            WHERE membership_no NOT IN ($placeholders)
              AND (valid_until IS NULL OR valid_until > CURDATE())
              AND membership_no IS NOT NULL AND membership_no != ''
        ");
        $deact->execute($ycomNos);
        $stats['deactivated'] = $deact->rowCount();
    }

    $db->commit();

    // Ins Log schreiben
    $logEntry = date('Y-m-d H:i:s') . " [AJAX] Sync: {$stats['created']} neu, {$stats['updated']} aktualisiert, {$stats['deactivated']} deaktiviert, {$stats['errors']} Fehler\n";
    file_put_contents(__DIR__ . '/ycom-sync.log', $logEntry, FILE_APPEND);

    jsonResponse(['success' => true, 'stats' => $stats]);

} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('[Fahrtenbuch ycom-sync-ajax] ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
