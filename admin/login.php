<?php
/**
 * Admin Login – Fahrtenbuch
 *
 * Login-Formular für den Admin-Bereich.
 * Prüft Credentials gegen die Datenbank-Tabelle `admins`.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Bereits eingeloggt → weiter zum Admin
if (!empty($_SESSION['admin_logged_in'])) {
    $timeout = defined('ADMIN_SESSION_TIMEOUT') ? ADMIN_SESSION_TIMEOUT : 1800;
    if (time() - ($_SESSION['admin_last_activity'] ?? 0) < $timeout) {
        header('Location: /admin/');
        exit;
    }
    session_destroy();
    session_start();
}

$error = '';
$info = '';

if (!empty($_GET['timeout'])) {
    $info = 'Sitzung abgelaufen. Bitte erneut anmelden.';
}
if (!empty($_GET['logged_out'])) {
    $info = 'Erfolgreich abgemeldet.';
}

// Login verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, username, password FROM admins WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_last_activity'] = time();
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                header('Location: /admin/');
                exit;
            }
        } catch (\Throwable $e) {
            error_log('[Fahrtenbuch Admin Login] Fehler: ' . $e->getMessage());
        }
    }

    $error = 'Ungültige Anmeldedaten.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin-Login – <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .login-card { max-width: 380px; margin: 0 auto; }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100">
<div class="container">
    <div class="login-card">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h4 class="text-center mb-1">
                    <span style="font-size: 1.5em;">🔒</span>
                </h4>
                <h5 class="text-center mb-3 text-muted">Admin-Login</h5>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($info): ?>
                    <div class="alert alert-info py-2"><?= htmlspecialchars($info) ?></div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label for="username" class="form-label">Benutzername</label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required autofocus autocomplete="nope" readonly
                               onfocus="this.removeAttribute('readonly');" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Passwort</label>
                        <input type="password" class="form-control" id="password" name="password"
                               onfocus="this.removeAttribute('readonly');" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Anmelden</button>
                </form>
                <div class="text-center mt-3">
                    <a href="/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Zurück zum Fahrtenbuch</a>
                </div>
            </div>
        </div>
        <p class="text-center text-muted mt-3 small">
            <?= CLUB_NAME ?> Fahrtenbuch &mdash; Administration
        </p>
        <script>
            // Nach 60 Sekunden Inaktivität zurück zum Fahrtenbuch
            let _adminLoginTimer = setTimeout(function(){ window.location.href = '/'; }, 60000);
            document.addEventListener('keydown', function(){ clearTimeout(_adminLoginTimer); _adminLoginTimer = setTimeout(function(){ window.location.href = '/'; }, 60000); });
            document.addEventListener('click', function(){ clearTimeout(_adminLoginTimer); _adminLoginTimer = setTimeout(function(){ window.location.href = '/'; }, 60000); });
        </script>
    </div>
</div>
</body>
</html>
