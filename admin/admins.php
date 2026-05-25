<?php
/**
 * Fahrtenbuch - Administratoren verwalten
 *
 * Ermöglicht das Anlegen, Bearbeiten und Löschen von Admin-Zugängen.
 * Jeder Vorstand erhält einen eigenen Login.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();
$message = '';
$messageType = '';

// ── Aktionen verarbeiten ─────────────────────────────────────────────────

// Admin löschen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    // Sich selbst nicht löschen
    if ($deleteId === ($_SESSION['admin_id'] ?? 0)) {
        $message = 'Sie können Ihren eigenen Account nicht löschen.';
        $messageType = 'danger';
    } else {
        // Mindestens ein Admin muss übrig bleiben
        $countStmt = $db->query("SELECT COUNT(*) FROM admins");
        if ((int)$countStmt->fetchColumn() <= 1) {
            $message = 'Der letzte Administrator kann nicht gelöscht werden.';
            $messageType = 'danger';
        } else {
            $stmt = $db->prepare("DELETE FROM admins WHERE id = :id");
            $stmt->execute(['id' => $deleteId]);
            if ($stmt->rowCount() > 0) {
                $message = 'Administrator erfolgreich gelöscht.';
                $messageType = 'success';
            }
        }
    }
}

// Admin anlegen / bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $adminId = (int)($_POST['admin_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (empty($username)) {
        $message = 'Benutzername darf nicht leer sein.';
        $messageType = 'danger';
    } elseif ($action === 'create') {
        // Neuen Admin anlegen
        if (empty($password)) {
            $message = 'Passwort darf nicht leer sein.';
            $messageType = 'danger';
        } elseif (strlen($password) < 6) {
            $message = 'Passwort muss mindestens 6 Zeichen lang sein.';
            $messageType = 'danger';
        } elseif ($password !== $passwordConfirm) {
            $message = 'Passwörter stimmen nicht überein.';
            $messageType = 'danger';
        } else {
            // Prüfe ob Benutzername schon existiert
            $checkStmt = $db->prepare("SELECT id FROM admins WHERE username = :username");
            $checkStmt->execute(['username' => $username]);
            if ($checkStmt->fetch()) {
                $message = 'Benutzername "' . htmlspecialchars($username) . '" ist bereits vergeben.';
                $messageType = 'danger';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO admins (username, password) VALUES (:username, :password)");
                $stmt->execute(['username' => $username, 'password' => $hash]);
                $message = 'Administrator "' . htmlspecialchars($username) . '" erfolgreich angelegt.';
                $messageType = 'success';
            }
        }
    } elseif ($action === 'update' && $adminId > 0) {
        // Benutzername aktualisieren
        $checkStmt = $db->prepare("SELECT id FROM admins WHERE username = :username AND id != :id");
        $checkStmt->execute(['username' => $username, 'id' => $adminId]);
        if ($checkStmt->fetch()) {
            $message = 'Benutzername "' . htmlspecialchars($username) . '" ist bereits vergeben.';
            $messageType = 'danger';
        } else {
            if (!empty($password)) {
                // Passwort ändern
                if (strlen($password) < 6) {
                    $message = 'Passwort muss mindestens 6 Zeichen lang sein.';
                    $messageType = 'danger';
                } elseif ($password !== $passwordConfirm) {
                    $message = 'Passwörter stimmen nicht überein.';
                    $messageType = 'danger';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE admins SET username = :username, password = :password WHERE id = :id");
                    $stmt->execute(['username' => $username, 'password' => $hash, 'id' => $adminId]);
                    $message = 'Administrator aktualisiert (inkl. Passwort).';
                    $messageType = 'success';
                }
            } else {
                // Nur Benutzername ändern
                $stmt = $db->prepare("UPDATE admins SET username = :username WHERE id = :id");
                $stmt->execute(['username' => $username, 'id' => $adminId]);
                $message = 'Benutzername aktualisiert.';
                $messageType = 'success';
            }
        }
    }
}

// ── Admins laden ─────────────────────────────────────────────────────────
$admins = $db->query("SELECT id, username, created_at, updated_at FROM admins ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administratoren – <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-person-gear"></i> Administration – Administratoren
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-person-gear"></i> Administratoren</h2>
                <p class="text-muted mb-0">Admin-Zugänge für Vorstände verwalten</p>
            </div>
            <a href="/admin/" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Bestehende Admins -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people"></i> Aktuelle Administratoren</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Benutzername</th>
                            <th>Erstellt am</th>
                            <th>Letzte Änderung</th>
                            <th class="text-end">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-person-fill"></i>
                                    <strong><?= htmlspecialchars($admin['username']) ?></strong>
                                    <?php if ($admin['id'] === ($_SESSION['admin_id'] ?? 0)): ?>
                                        <span class="badge bg-primary ms-1">Sie</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= date('d.m.Y H:i', strtotime($admin['created_at'])) ?></td>
                                <td class="text-muted"><?= date('d.m.Y H:i', strtotime($admin['updated_at'])) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                            data-bs-target="#editModal"
                                            data-id="<?= $admin['id'] ?>"
                                            data-username="<?= htmlspecialchars($admin['username']) ?>">
                                        <i class="bi bi-pencil"></i> Bearbeiten
                                    </button>
                                    <?php if ($admin['id'] !== ($_SESSION['admin_id'] ?? 0)): ?>
                                        <a href="?delete=<?= $admin['id'] ?>" class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Administrator &quot;<?= htmlspecialchars($admin['username']) ?>&quot; wirklich löschen?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Neuen Admin anlegen -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-plus"></i> Neuen Administrator anlegen</h5>
            </div>
            <div class="card-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="new_username" class="form-label">Benutzername</label>
                            <input type="text" class="form-control" id="new_username" name="username"
                                   required placeholder="z.B. vorname.nachname" autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label for="new_password" class="form-label">Passwort</label>
                            <input type="password" class="form-control" id="new_password" name="password"
                                   required minlength="6" placeholder="Min. 6 Zeichen" autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label for="new_password_confirm" class="form-label">Passwort bestätigen</label>
                            <input type="password" class="form-control" id="new_password_confirm" name="password_confirm"
                                   required minlength="6" placeholder="Wiederholen" autocomplete="off">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-plus-lg"></i> Anlegen
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bearbeiten-Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Administrator bearbeiten</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Benutzername</label>
                            <input type="text" class="form-control" id="edit_username" name="username" autocomplete="off" required>
                        </div>
                        <hr>
                        <p class="text-muted small mb-2">Passwort nur ausfüllen wenn es geändert werden soll:</p>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Neues Passwort</label>
                            <input type="password" class="form-control" id="edit_password" name="password"
                                   minlength="6" placeholder="Leer lassen = nicht ändern" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="edit_password_confirm" class="form-label">Passwort bestätigen</label>
                            <input type="password" class="form-control" id="edit_password_confirm" name="password_confirm"
                                   minlength="6" placeholder="Wiederholen" autocomplete="off">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal mit Daten befüllen
        document.getElementById('editModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_admin_id').value = button.dataset.id;
            document.getElementById('edit_username').value = button.dataset.username;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_password_confirm').value = '';
        });
    </script>
</body>
</html>
