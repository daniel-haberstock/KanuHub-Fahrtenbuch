<?php
/**
 * API: Session nach erfolgreichem Login setzen
 * Wird vom Frontend nach /api/statistics/login.php aufgerufen.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

$memberId   = !empty($_POST['member_id'])   ? (int)$_POST['member_id']   : null;
$memberName = !empty($_POST['member_name']) ? trim($_POST['member_name']) : null;

if (!$memberId || !$memberName) {
    jsonResponse(['success' => false, 'message' => 'Fehlende Parameter'], 400);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/']);
    session_start();
}

$_SESSION['stats_member_id']     = $memberId;
$_SESSION['stats_member_name']   = $memberName;
$_SESSION['stats_last_activity'] = time();

jsonResponse(['success' => true]);
