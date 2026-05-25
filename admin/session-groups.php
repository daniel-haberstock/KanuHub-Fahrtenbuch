<?php
/**
 * Fahrtenbuch - Fahrtgruppen-Verwaltung (Wanderfahrten, Regatten, etc.)
 *
 * Ermöglicht die Verwaltung von Fahrtgruppen, um zusammengehörige Fahrten
 * (z.B. Wanderfahrten, Regatten, Trainingslager) zu gruppieren.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// CRUD-Operationen
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $name = $_POST['name'] ?? '';
        $route = $_POST['route'] ?? '';
        $organizer = $_POST['organizer'] ?? '';
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $description = $_POST['description'] ?? '';

        if ($action === 'create') {
            $query = "INSERT INTO session_groups (name, route, organizer, start_date, end_date, description)
                      VALUES (:name, :route, :organizer, :start_date, :end_date, :description)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'name' => $name,
                'route' => $route,
                'organizer' => $organizer,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'description' => $description
            ]);
            $successMessage = "Fahrtgruppe erfolgreich erstellt!";
        } else {
            $query = "UPDATE session_groups SET name = :name, route = :route, organizer = :organizer,
                      start_date = :start_date, end_date = :end_date, description = :description WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'route' => $route,
                'organizer' => $organizer,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'description' => $description
            ]);
            $successMessage = "Fahrtgruppe erfolgreich aktualisiert!";
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        $query = "DELETE FROM session_groups WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $id]);
        $successMessage = "Fahrtgruppe erfolgreich gelöscht!";
    }
}

// Fahrtgruppen laden
$query = "SELECT * FROM session_groups ORDER BY start_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$sessionGroups = $stmt->fetchAll();

// Bearbeitungsmodus
$editMode = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editGroup = null;
if ($editMode) {
    $query = "SELECT * FROM session_groups WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $editMode]);
    $editGroup = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fahrtgruppen-Verwaltung - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - Fahrtgruppen
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo e($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><?php echo $editMode ? 'Fahrtgruppe bearbeiten' : 'Neue Fahrtgruppe'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'create'; ?>">
                            <?php if ($editMode): ?>
                                <input type="hidden" name="id" value="<?php echo $editGroup['id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Name der Fahrtgruppe</label>
                                <input type="text" class="form-control" name="name"
                                       value="<?php echo $editGroup ? e($editGroup['name']) : ''; ?>"
                                       placeholder="z.B. Schlei-Wanderfahrt 2024" required>
                                <small class="form-text text-muted">Eindeutiger Name für die Fahrtgruppe</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Route/Ziel</label>
                                <input type="text" class="form-control" name="route"
                                       value="<?php echo $editGroup ? e($editGroup['route']) : ''; ?>"
                                       placeholder="z.B. Schleswig - Maasholm">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Veranstalter/Tourenleiter</label>
                                <input type="text" class="form-control" name="organizer"
                                       value="<?php echo $editGroup ? e($editGroup['organizer']) : ''; ?>"
                                       placeholder="z.B. Max Mustermann">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Startdatum</label>
                                    <input type="date" class="form-control" name="start_date"
                                           value="<?php echo $editGroup ? $editGroup['start_date'] : ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Enddatum</label>
                                    <input type="date" class="form-control" name="end_date"
                                           value="<?php echo $editGroup ? $editGroup['end_date'] : ''; ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Beschreibung</label>
                                <textarea class="form-control" name="description" rows="4"
                                          placeholder="Beschreibung der Fahrtgruppe"><?php echo $editGroup ? e($editGroup['description']) : ''; ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Speichern
                                </button>
                                <?php if ($editMode): ?>
                                    <a href="/admin/session-groups.php" class="btn btn-secondary">Abbrechen</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Fahrtgruppen-Liste</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Route</th>
                                        <th>Veranstalter</th>
                                        <th>Zeitraum</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessionGroups as $group): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo e($group['name']); ?></strong>
                                                <?php if ($group['description']): ?>
                                                    <br><small class="text-muted"><?php echo e(substr($group['description'], 0, 50)); ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $group['route'] ? e($group['route']) : '-'; ?></td>
                                            <td><?php echo $group['organizer'] ? e($group['organizer']) : '-'; ?></td>
                                            <td>
                                                <?php echo formatDateGerman($group['start_date']); ?>
                                                -
                                                <?php echo formatDateGerman($group['end_date']); ?>
                                            </td>
                                            <td>
                                                <a href="?edit=<?php echo $group['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Wirklich löschen?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $group['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($sessionGroups)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">Keine Fahrtgruppen vorhanden</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
