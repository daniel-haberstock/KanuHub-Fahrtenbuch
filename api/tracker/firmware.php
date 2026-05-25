<?php
/**
 * Tracker-API: Firmware-Download (OTA)
 * GET ?version=...
 * Liefert firmware/<version>.bin mit SHA-256 im Header (X-Firmware-SHA256).
 */

require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'method', 'message' => 'GET required'], 405);
}

[$db, $tracker] = trackerAuth();

$version = (string)($_GET['version'] ?? '');
if ($version === '' || !preg_match('/^[A-Za-z0-9._-]{1,32}$/', $version)) {
    jsonResponse(['error' => 'bad_version', 'message' => 'invalid version'], 400);
}

$base = realpath(__DIR__ . '/../../firmware');
if ($base === false) {
    jsonResponse(['error' => 'no_firmware_dir', 'message' => 'firmware directory missing'], 500);
}
$file = $base . DIRECTORY_SEPARATOR . $version . '.bin';
$real = realpath($file);
if ($real === false || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) {
    jsonResponse(['error' => 'not_found', 'message' => 'firmware not found'], 404);
}

$sha = hash_file('sha256', $real);
$size = filesize($real);

// JSON-Header raus, Binary rein
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/octet-stream');
header('Content-Length: ' . $size);
header('X-Firmware-Version: ' . $version);
header('X-Firmware-SHA256: ' . $sha);
header('Cache-Control: no-store');
readfile($real);
exit;
