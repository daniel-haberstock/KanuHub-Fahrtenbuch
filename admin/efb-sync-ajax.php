<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/EfbSync.php';

header('Content-Type: application/json');

if (!defined('EFB_ENABLED') || !EFB_ENABLED) {
    echo json_encode(['success' => false, 'error' => 'EFB_ENABLED ist false']);
    exit;
}

try {
    $db  = Database::getInstance()->getConnection();
    $efb = new EfbSync($db);

    if (!$efb->login()) {
        echo json_encode(['success' => false, 'error' => 'Login fehlgeschlagen']);
        exit;
    }

    $trips  = $efb->getTripsToSync();
    $result = empty($trips)
        ? ['synced' => 0, 'errors' => 0, 'syncedIds' => []]
        : $efb->syncTrips($trips);

    if (!empty($result['syncedIds'])) {
        $now = time();
        $ph  = implode(',', array_fill(0, count($result['syncedIds']), '?'));
        $db->prepare("UPDATE trips SET efb_sync_at = ? WHERE id IN ($ph)")
           ->execute(array_merge([$now], $result['syncedIds']));
    }

    $efb->syncDone();

    echo json_encode([
        'success' => $result['errors'] === 0,
        'synced'  => $result['synced'],
    ]);

} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
