<?php
/**
 * Fahrtenbuch - Gewässerverwaltung
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
        $description = $_POST['description'] ?? '';

        if ($action === 'create') {
            $query = "INSERT INTO waters (name, description) VALUES (:name, :description)";
            $stmt = $db->prepare($query);
            $stmt->execute(['name' => $name, 'description' => $description]);
            $successMessage = "Gewässer erfolgreich erstellt!";
        } else {
            $query = "UPDATE waters SET name = :name, description = :description WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute(['id' => $id, 'name' => $name, 'description' => $description]);
            $successMessage = "Gewässer erfolgreich aktualisiert!";
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        $query = "DELETE FROM waters WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $id]);
        $successMessage = "Gewässer erfolgreich gelöscht!";
    }
}

// Gewässer laden
$query = "SELECT * FROM waters ORDER BY name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$watersList = $stmt->fetchAll();

// Bearbeitungsmodus
$editMode = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editWater = null;
if ($editMode) {
    $query = "SELECT * FROM waters WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $editMode]);
    $editWater = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gewässerverwaltung - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - Gewässer
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
                        <h5><?php echo $editMode ? 'Gewässer bearbeiten' : 'Neues Gewässer'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'create'; ?>">
                            <?php if ($editMode): ?>
                                <input type="hidden" name="id" value="<?php echo $editWater['id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Gewässername</label>
                                <input type="text" class="form-control" name="name"
                                       value="<?php echo $editWater ? e($editWater['name']) : ''; ?>"
                                       placeholder="z.B. Schlei, Ostsee" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Beschreibung</label>
                                <textarea class="form-control" name="description" rows="3"
                                          placeholder="Optionale Beschreibung des Gewässers"><?php echo $editWater ? e($editWater['description']) : ''; ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Speichern
                                </button>
                                <?php if ($editMode): ?>
                                    <a href="/admin/waters.php" class="btn btn-secondary">Abbrechen</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Gewässerliste</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Beschreibung</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($watersList as $water): ?>
                                        <tr>
                                            <td><?php echo e($water['name']); ?></td>
                                            <td><?php echo $water['description'] ? e($water['description']) : '-'; ?></td>
                                            <td>
                                                <a href="?edit=<?php echo $water['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Wirklich löschen?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $water['id']; ?>">
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
