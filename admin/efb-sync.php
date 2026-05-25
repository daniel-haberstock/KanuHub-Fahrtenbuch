<?php
/**
 * eFB Sync Cronjob - Überträgt Fahrten ans DKV elektronisches Fahrtenbuch
 *
 * Aufruf:
 *   CLI:            php /pfad/zu/admin/efb-sync.php
 *   HTTP (Token):   /admin/efb-sync.php?token=SYNC_SECRET_TOKEN
 *   HTTP (Admin):   /admin/efb-sync.php  (Browser, eingeloggt)
 *
 * Cronjob (täglich 02:00, nach ycom-sync):
 *   0 2 * * * /usr/bin/php /mnt/.../admin/efb-sync.php >> /mnt/.../logs/efb-sync.log 2>&1
 *   0 2 * * * wget -q -O /dev/null "https://fahrtenbuch.kanuhub.de/admin/efb-sync.php?token=CHANGE_ME_TOKEN"
 */

if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/../config/config.php';
} elseif (
    isset($_GET['token']) &&
    defined('SYNC_SECRET_TOKEN') &&
    SYNC_SECRET_TOKEN !== '' &&
    hash_equals(SYNC_SECRET_TOKEN, $_GET['token'])
) {
    require_once __DIR__ . '/../config/config.php';
} else {
    require_once __DIR__ . '/auth_check.php';
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/EfbSync.php';

$logFile = __DIR__ . '/../logs/efb-sync.log';
$isCli   = php_sapi_name() === 'cli';
$debug   = isset($_GET['debug']) || $isCli;

if ($debug && !$isCli) {
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">'
       . '<title>eFB-Sync – ' . CLUB_NAME . ' Fahrtenbuch</title>'
       . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
       . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">'
       . '</head><body>'
       . '<nav class="navbar navbar-dark bg-dark"><div class="container-fluid">'
       . '<a class="navbar-brand" href="/admin/"><i class="bi bi-cloud-upload"></i> Administration – eFB-Sync</a>'
       . '<a href="/admin/" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>'
       . '</div></nav>'
       . '<div class="p-4">'
       . '<pre class="bg-dark text-light p-3 rounded" style="font-size:.85rem">';
}

function efb_log(string $msg, string $logFile, bool $debug, bool $isCli): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    if ($debug) {
        echo $isCli ? $line : htmlspecialchars($line);
        if (!$isCli) flush();
    }
}

if (!defined('EFB_ENABLED') || !EFB_ENABLED) {
    efb_log('EFB_ENABLED ist false – Sync übersprungen.', $logFile, $debug, $isCli);
    exit(0);
}

efb_log('eFB-Sync gestartet.', $logFile, $debug, $isCli);

try {
    $db  = Database::getInstance()->getConnection();
    $efb = new EfbSync($db);

    if (!$efb->login()) {
        foreach ($efb->getLog() as $entry) {
            efb_log('  ' . $entry, $logFile, $debug, $isCli);
        }
        efb_log('FEHLER: Login fehlgeschlagen.', $logFile, $debug, $isCli);
        exit(1);
    }
    efb_log('Login erfolgreich.', $logFile, $debug, $isCli);

    $trips = $efb->getTripsToSync();
    efb_log(count($trips) . ' Fahrten zum Synchronisieren gefunden.', $logFile, $debug, $isCli);

    if (empty($trips)) {
        $efb->syncDone();
        efb_log('Nichts zu syncen. Sitzung beendet.', $logFile, $debug, $isCli);
        exit(0);
    }

    $result = $efb->syncTrips($trips);
    efb_log(
        "Sync abgeschlossen: {$result['synced']} übertragen, {$result['errors']} Fehler.",
        $logFile,
        $debug,
        $isCli
    );

    if (!empty($result['syncedIds'])) {
        $now          = time();
        $placeholders = implode(',', array_fill(0, count($result['syncedIds']), '?'));
        $stmt = $db->prepare("UPDATE trips SET efb_sync_at = ? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$now], $result['syncedIds']));
        efb_log(count($result['syncedIds']) . ' Fahrten als gesynct markiert.', $logFile, $debug, $isCli);
    }

    $efb->syncDone();
    efb_log('eFB-Sitzung beendet.', $logFile, $debug, $isCli);

    foreach ($efb->getLog() as $entry) {
        efb_log('  ' . $entry, $logFile, $debug, $isCli);
    }

    exit($result['errors'] > 0 ? 1 : 0);

} catch (\Exception $e) {
    efb_log('EXCEPTION: ' . $e->getMessage(), $logFile, $debug, $isCli);
    if ($debug && !$isCli) echo '</pre><div class="mt-3 d-flex gap-2"><a href="/admin/" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a></div></div></body></html>';
    exit(1);
}

if ($debug && !$isCli) {
    echo '</pre>'
       . '<div class="mt-3 d-flex gap-2">'
       . '<a href="/admin/" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>'
       . '<a href="/admin/efb-sync.php?debug=1" class="btn btn-outline-success"><i class="bi bi-arrow-repeat"></i> Nochmal</a>'
       . '</div></div></body></html>';
}
