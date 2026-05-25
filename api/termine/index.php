<?php
/**
 * API: Termine aus REDAXO abrufen
 *
 * GET /api/termine/
 * GET /api/termine/?limit=10&months=3&kategorie=1
 *
 * Query-Parameter:
 *   limit     — Max. Anzahl Termine (default: 20, max: 100)
 *   months    — Monate im Voraus (default: 6, max: 12)
 *   kategorie — Kategorie-Filter ('1' oder '2'), optional
 *
 * Antwort: JSON mit 'success', 'count', 'termine'
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Nur GET erlaubt'], 405);
}

try {
    $limit     = min(max((int)($_GET['limit'] ?? 20), 1), 100);
    $months    = min(max((int)($_GET['months'] ?? 6), 1), 12);
    $kategorie = isset($_GET['kategorie']) && in_array($_GET['kategorie'], ['1', '2'])
                 ? $_GET['kategorie']
                 : null;

    $result = fetchTermine($limit, $months, $kategorie);

    if ($result === false) {
        jsonResponse([
            'success' => false,
            'message' => 'Termine konnten nicht geladen werden. Bitte später erneut versuchen.',
        ], 502);
    }

    jsonResponse($result);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/termine] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse([
        'success' => false,
        'message' => 'Termine konnten nicht geladen werden.',
    ], 500);
}
