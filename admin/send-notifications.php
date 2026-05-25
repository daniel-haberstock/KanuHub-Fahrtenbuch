<?php
/**
 * E-Mail Benachrichtigungen versenden (Cron-Skript)
 *
 * Versendet ausstehende Fahrten- und Wochen-Statistik-E-Mails aus der Queue.
 * Mitglieder die noch auf Tour sind werden übersprungen (bleibt 'pending').
 *
 * Aufruf:
 *   Browser (Cronjob-URL): /admin/send-notifications.php?token=SYNC_SECRET_TOKEN
 *   CLI:                   php /pfad/zu/admin/send-notifications.php
 *
 * Cronjob (täglich 20:00 Uhr):
 *   0 20 * * * wget -q -O /dev/null "https://fahrtenbuch.kanuhub.de/admin/send-notifications.php?token=CHANGE_ME_TOKEN"
 */

require_once __DIR__ . '/../config/config.php';

if (php_sapi_name() !== 'cli') {
    $validToken = defined('SYNC_SECRET_TOKEN') &&
                  SYNC_SECRET_TOKEN !== '' &&
                  isset($_GET['token']) &&
                  hash_equals(SYNC_SECRET_TOKEN, $_GET['token']);
    if (!$validToken) {
        require_once __DIR__ . '/auth_check.php';
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TripEmailHelper.php';

$debug   = isset($_GET['debug']) || php_sapi_name() === 'cli';
$logFile = __DIR__ . '/send-notifications.log';

function notifyLog(string $msg, bool $debug, string $logFile): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    if ($debug) {
        echo $line;
    }
}

if ($debug) {
    echo "=== E-Mail Benachrichtigungen ===\n";
    echo "Zeit: " . date('Y-m-d H:i:s') . "\n\n";
    flush();
}

try {
    $db = Database::getInstance()->getConnection();

    // Alle ausstehenden Benachrichtigungen laden
    $pending = $db->query(
        "SELECT id, member_id, type, reference_key FROM email_notifications WHERE status = 'pending' ORDER BY created_at ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    notifyLog("Ausstehend: " . count($pending) . " Benachrichtigungen.", $debug, $logFile);

    $sent    = 0;
    $skipped = 0;
    $failed  = 0;

    foreach ($pending as $row) {
        $memberId = (int)$row['member_id'];

        // Prüfen ob Mitglied noch auf Tour (aktive, nicht abgeschlossene Fahrt)
        $activeStmt = $db->prepare(
            "SELECT COUNT(*) FROM trips t
             JOIN trip_crew tc ON t.id = tc.trip_id
             WHERE tc.member_id = :mid AND t.is_completed = 0 AND t.is_backlog = 0"
        );
        $activeStmt->execute(['mid' => $memberId]);
        if ((int)$activeStmt->fetchColumn() > 0) {
            notifyLog("Übersprungen (noch auf Tour): member_id=$memberId, type={$row['type']}, ref={$row['reference_key']}", $debug, $logFile);
            $skipped++;
            continue;
        }

        try {
            TripEmailHelper::send(
                (int)$row['id'],
                $memberId,
                $row['type'],
                $row['reference_key'],
                $db
            );
            notifyLog("Gesendet: member_id=$memberId, type={$row['type']}, ref={$row['reference_key']}", $debug, $logFile);
            $sent++;
        } catch (\Throwable $e) {
            // Status auf 'failed' setzen
            $db->prepare("UPDATE email_notifications SET status='failed', sent_at=NOW() WHERE id=:id")
               ->execute(['id' => $row['id']]);
            notifyLog("Fehler: member_id=$memberId, type={$row['type']}, ref={$row['reference_key']} → " . $e->getMessage(), $debug, $logFile);
            $failed++;
        }
    }

    notifyLog("Fertig. Gesendet: $sent, Übersprungen: $skipped, Fehler: $failed", $debug, $logFile);

    if ($debug) {
        echo "\n=== Fertig ===\n";
    }

} catch (\Throwable $e) {
    notifyLog("KRITISCHER FEHLER: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), $debug, $logFile);
    if ($debug) {
        echo "FEHLER: " . $e->getMessage() . "\n";
    }
    exit(1);
}
