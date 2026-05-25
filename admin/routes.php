<?php
/**
 * Fahrtenbuch - Streckenverwaltung
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// Gewässer für Dropdown laden
$watersQuery = "SELECT id, name FROM waters ORDER BY name ASC";
$watersStmt = $db->prepare($watersQuery);
$watersStmt->execute();
$waters = $watersStmt->fetchAll();

// CRUD-Operationen
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $startPoint = $_POST['start_point'] ?? '';
        $endPoint = $_POST['end_point'] ?? '';
        $waterId = $_POST['water_id'] ?? null;
        $distance = $_POST['distance'] ?? 0;
        $description = $_POST['description'] ?? '';

        if ($action === 'create') {
            $query = "INSERT INTO routes (start_point, end_point, water_id, distance, description)
                      VALUES (:start_point, :end_point, :water_id, :distance, :description)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'start_point' => $startPoint,
                'end_point' => $endPoint,
                'water_id' => $waterId ?: null,
                'distance' => $distance,
                'description' => $description
            ]);
            $successMessage = "Strecke erfolgreich erstellt!";
        } else {
            $query = "UPDATE routes SET start_point = :start_point, end_point = :end_point, water_id = :water_id,
                      distance = :distance, description = :description WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'id' => $id,
                'start_point' => $startPoint,
                'end_point' => $endPoint,
                'water_id' => $waterId ?: null,
                'distance' => $distance,
                'description' => $description
            ]);
            $successMessage = "Strecke erfolgreich aktualisiert!";
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        $query = "DELETE FROM routes WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $id]);
        $successMessage = "Strecke erfolgreich gelöscht!";
    }
}

// Strecken laden
$query = "SELECT r.*, w.name as water_name
          FROM routes r
          LEFT JOIN waters w ON r.water_id = w.id
          ORDER BY r.start_point ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$routes = $stmt->fetchAll();

// Bearbeitungsmodus
$editMode = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editRoute = null;
if ($editMode) {
    $query = "SELECT * FROM routes WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $editMode]);
    $editRoute = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Streckenverwaltung - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - Strecken
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
                        <h5><?php echo $editMode ? 'Strecke bearbeiten' : 'Neue Strecke'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'create'; ?>">
                            <?php if ($editMode): ?>
                                <input type="hidden" name="id" value="<?php echo $editRoute['id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Startpunkt</label>
                                <input type="text" class="form-control" name="start_point"
                                       value="<?php echo $editRoute ? e($editRoute['start_point']) : CLUB_BOAT_PREFIX; ?>"
                                       placeholder="z.B. <?= CLUB_BOAT_PREFIX ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Zielpunkt</label>
                                <input type="text" class="form-control" name="end_point"
                                       value="<?php echo $editRoute ? e($editRoute['end_point']) : CLUB_BOAT_PREFIX; ?>"
                                       placeholder="z.B. <?= CLUB_BOAT_PREFIX ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Gewässer</label>
                                <select class="form-select" name="water_id">
                                    <option value="">-- Gewässer auswählen --</option>
                                    <?php foreach ($waters as $water): ?>
                                        <option value="<?php echo $water['id']; ?>"
                                                <?php
                                                if ($editRoute) {
                                                    echo ($editRoute['water_id'] == $water['id']) ? 'selected' : '';
                                                } else {
                                                    // Standardmäßig "Bodensee" auswählen
                                                    echo ($water['name'] === 'Bodensee') ? 'selected' : '';
                                                }
                                                ?>>
                                            <?php echo e($water['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Entfernung (km)</label>
                                <input type="number" step="0.1" min="0" class="form-control" name="distance"
                                       value="<?php echo $editRoute ? $editRoute['distance'] : ''; ?>"
                                       placeholder="0.0" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Beschreibung</label>
                                <textarea class="form-control" name="description" rows="3"
                                          placeholder="Optionale Beschreibung der Strecke"><?php echo $editRoute ? e($editRoute['description']) : ''; ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Speichern
                                </button>
                                <?php if ($editMode): ?>
                                    <a href="/admin/routes.php" class="btn btn-secondary">Abbrechen</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Streckenliste</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Start</th>
                                        <th>Ziel</th>
                                        <th>Gewässer</th>
                                        <th>km</th>
                                        <th>Beschreibung</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($routes as $route): ?>
                                        <tr>
                                            <td><?php echo e($route['start_point']); ?></td>
                                            <td><?php echo e($route['end_point']); ?></td>
                                            <td><?php echo $route['water_name'] ? e($route['water_name']) : '-'; ?></td>
                                            <td><?php echo number_format($route['distance'], 1, ',', '.'); ?></td>
                                            <td><?php echo $route['description'] ? e(substr($route['description'], 0, 50)) : '-'; ?></td>
                                            <td>
                                                <a href="?edit=<?php echo $route['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Wirklich löschen?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $route['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
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
