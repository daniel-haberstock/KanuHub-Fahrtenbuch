<?php
/**
 * Admin Logout - Setzt die HTTP Basic Auth Session zurück
 */

// Force 401 Unauthorized um Browser-Credentials zurückzusetzen
if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== 'force-logout-now') {
    header('HTTP/1.1 401 Unauthorized');
    require_once __DIR__ . '/config/config.php';
    $clubName = defined('CLUB_NAME') ? CLUB_NAME : 'Fahrtenbuch';
    header('WWW-Authenticate: Basic realm="' . $clubName . ' Fahrtenbuch Admin - Abgemeldet"');
    echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abgemeldet - ' . htmlspecialchars($clubName) . ' Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="card shadow-lg" style="max-width: 500px;">
        <div class="card-header bg-success text-white text-center py-4">
            <h3 class="mb-0">
                <i class="bi bi-check-circle"></i> Erfolgreich abgemeldet
            </h3>
        </div>
        <div class="card-body p-4 text-center">
            <p class="lead">Sie wurden erfolgreich vom Admin-Bereich abgemeldet.</p>
            <p class="text-muted">Schließen Sie alle Browser-Fenster für eine vollständige Abmeldung.</p>
        </div>
        <div class="card-footer text-center">
            <a href="/admin/" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right"></i> Erneut anmelden
            </a>
            <a href="/" class="btn btn-secondary">
                <i class="bi bi-house"></i> Zurück zum Fahrtenbuch
            </a>
        </div>
    </div>
</body>
</html>';
    exit;
}
?>
