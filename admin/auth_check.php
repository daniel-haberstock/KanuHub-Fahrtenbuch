<?php
/**
 * Admin Auth-Guard
 *
 * In jeder Admin-Seite am Anfang einbinden:
 *   require_once __DIR__ . '/auth_check.php';
 *
 * Prüft ob ein Admin eingeloggt ist und ob die Session noch gültig ist.
 * Leitet bei Bedarf auf login.php weiter.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

// Nicht eingeloggt → Login-Seite
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin/login.php');
    exit;
}

// Session-Timeout prüfen
$timeout = defined('ADMIN_SESSION_TIMEOUT') ? ADMIN_SESSION_TIMEOUT : 1800;
if (time() - ($_SESSION['admin_last_activity'] ?? 0) > $timeout) {
    session_destroy();
    header('Location: /admin/login.php?timeout=1');
    exit;
}

// Aktivität aktualisieren
$_SESSION['admin_last_activity'] = time();
