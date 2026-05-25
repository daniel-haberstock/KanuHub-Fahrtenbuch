<?php
/**
 * Fahrtenbuch Beta — member.php
 * Legacy-Wrapper: Der Mitgliederbereich liegt unter /statistik/ (Original-Logik).
 * Diese Datei leitet alle Anfragen (inkl. Login-POST, Logout, Heartbeat) dorthin um.
 */

require_once __DIR__ . '/config/config.php';

$basePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';
$target   = $basePath . '/statistik/';

// Query-String (logout, heartbeat) durchreichen
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}

// Hash (#statistik etc.) kann der Server nicht sehen; Browser hängt ihn nach Redirect selbst wieder an.
// Bei POST: 307 erhält Methode + Body, damit ein evtl. Modal-Submit nicht verloren geht.
$status = ($_SERVER['REQUEST_METHOD'] === 'POST') ? 307 : 302;

header('Location: ' . $target, true, $status);
exit;
