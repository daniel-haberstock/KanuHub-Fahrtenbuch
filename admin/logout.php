<?php
/**
 * Admin Logout – Fahrtenbuch
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Ziel: Fahrtenbuch-Hauptseite oder angegebene URL
$redirect = $_GET['redirect'] ?? '/';
// Sicherheit: Nur relative Pfade erlauben
if (strpos($redirect, '//') !== false || strpos($redirect, ':') !== false) {
    $redirect = '/';
}
header('Location: ' . $redirect);
exit;
