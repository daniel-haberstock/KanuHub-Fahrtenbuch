<?php
/**
 * API: Badges für ein Mitglied laden
 * Gibt verdienten Badges als gerenderten HTML-String zurück + "Fast-Badges"
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/BadgeChecker.php';
require_once __DIR__ . '/../../includes/BadgeRenderer.php';

try {
    $memberId = (int)($_GET['member_id'] ?? 0);
    if (!$memberId) {
        jsonResponse(['success' => false, 'message' => 'member_id erforderlich'], 400);
    }

    $db = Database::getInstance()->getConnection();

    // Earned & new badges
    $badges      = BadgeChecker::getMemberBadges($memberId, $db);
    $earnedKeys  = $badges['earned'];
    $newKeys     = $badges['new'];

    // "Fast-Badges" (70–99% des Schwellenwerts)
    $almostBadges = BadgeChecker::getAlmostBadges($memberId, $db);

    // Gesamtanzahl aller Badges
    $totalBadges  = count(BadgeRenderer::getAllKeys());
    $earnedCount  = count($earnedKeys);

    // Badge-Grid als HTML rendern (PHP-seitig, mit SVG inline)
    $badgeGridHtml = BadgeRenderer::renderGrid($earnedKeys, $newKeys);

    jsonResponse([
        'success'         => true,
        'earned_count'    => $earnedCount,
        'total_count'     => $totalBadges,
        'new_count'       => count($newKeys),
        'badge_grid_html' => $badgeGridHtml,
        'almost_badges'   => $almostBadges,
    ]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/badges/get.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
