<?php
/**
 * Fahrtenbuch - Boot-Icon-Verwaltung
 *
 * Upload und Verwaltung von Icons für Boote.
 * Icons werden in /assets/icons/boats/ gespeichert.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$successMessage = null;
$errorMessage = null;

$categories = ['Boot', 'Paddel', 'Schwimmweste', 'Helm', 'Spritzdecke', 'Zubehoer'];
$uploadDir = __DIR__ . '/../assets/icons/boats/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'upload') {
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? 'Boot';

        if (empty($name)) {
            $errorMessage = 'Bitte einen Namen eingeben.';
        } elseif (!in_array($category, $categories)) {
            $errorMessage = 'Ungültige Kategorie.';
        } elseif (!isset($_FILES['icon_file']) || $_FILES['icon_file']['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = 'Bitte eine Datei auswählen.';
        } else {
            $file = $_FILES['icon_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'];

            if (!in_array($ext, $allowed)) {
                $errorMessage = 'Nur PNG, JPG, SVG, WebP und GIF erlaubt.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errorMessage = 'Datei zu groß (max. 2 MB).';
            } else {
                // Dateiname: slug aus Name + Zeitstempel
                $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $name)));
                $fileName = $slug . '-' . time() . '.' . $ext;
                $targetPath = $uploadDir . $fileName;

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $stmt = $db->prepare(
                        "INSERT INTO boat_icons (name, category, file_path) VALUES (:name, :category, :file_path)"
                    );
                    $stmt->execute([
                        'name' => $name,
                        'category' => $category,
                        'file_path' => $fileName,
                    ]);
                    $successMessage = "Icon \"{$name}\" erfolgreich hochgeladen!";
                } else {
                    $errorMessage = 'Fehler beim Speichern der Datei.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Prüfen ob Icon bei Booten verwendet wird
        $usageStmt = $db->prepare("SELECT COUNT(*) FROM boats WHERE boat_icon_id = ?");
        $usageStmt->execute([$id]);
        $usageCount = (int)$usageStmt->fetchColumn();

        if ($usageCount > 0) {
            $errorMessage = "Icon wird noch von {$usageCount} Boot(en) verwendet und kann nicht gelöscht werden.";
        } else {
            // Datei löschen
            $stmt = $db->prepare("SELECT file_path FROM boat_icons WHERE id = ?");
            $stmt->execute([$id]);
            $filePath = $stmt->fetchColumn();
            if ($filePath && file_exists($uploadDir . $filePath)) {
                unlink($uploadDir . $filePath);
            }
            $db->prepare("DELETE FROM boat_icons WHERE id = ?")->execute([$id]);
            $successMessage = "Icon gelöscht.";
        }
    }
}

// Filter
$filterCategory = $_GET['category'] ?? '';

// Icons laden
$query = "SELECT bi.*, (SELECT COUNT(*) FROM boats WHERE boat_icon_id = bi.id) as usage_count FROM boat_icons bi";
$params = [];
if ($filterCategory && in_array($filterCategory, $categories)) {
    $query .= " WHERE bi.category = :category";
    $params['category'] = $filterCategory;
}
$query .= " ORDER BY bi.category ASC, bi.name ASC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$icons = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boot-Icons - Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin/">
            <i class="bi bi-image"></i> Administration – Boot-Icons
        </a>
        <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
    </div>
</nav>
    <div class="container-fluid py-4">

        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo e($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo e($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Upload-Formular -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-cloud-upload"></i> Icon hochladen</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload">

                            <div class="mb-3">
                                <label class="form-label required">Name</label>
                                <input type="text" class="form-control" name="name" required
                                       placeholder="z.B. Seekajak blau">
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Kategorie</label>
                                <select class="form-select" name="category" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat; ?>"><?php echo $cat === 'Zubehoer' ? 'Zubehör' : $cat; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Datei</label>
                                <input type="file" class="form-control" name="icon_file" required
                                       accept=".png,.jpg,.jpeg,.svg,.webp,.gif">
                                <small class="form-text text-muted">PNG, JPG, SVG, WebP oder GIF. Max. 2 MB.</small>
                            </div>

                            <!-- Vorschau -->
                            <div id="iconPreview" class="text-center mb-3 p-3 bg-light rounded" style="display: none;">
                                <img id="iconPreviewImg" src="" alt="Vorschau" style="max-height: 80px; max-width: 100%;">
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload"></i> Hochladen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Icon-Liste -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-grid-3x3-gap"></i> Vorhandene Icons (<?php echo count($icons); ?>)</h5>
                        <div>
                            <select class="form-select form-select-sm d-inline-block" style="width: auto;"
                                    onchange="window.location='?category='+this.value">
                                <option value="">Alle Kategorien</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>>
                                        <?php echo $cat === 'Zubehoer' ? 'Zubehör' : $cat; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($icons)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p>Noch keine Icons hochgeladen.</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($icons as $icon): ?>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="card h-100 text-center border <?php echo $icon['usage_count'] > 0 ? 'border-success' : ''; ?>">
                                            <div class="card-body py-2 px-2">
                                                <div class="bg-light rounded p-2 mb-2" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                                                    <img src="/assets/icons/boats/<?php echo e($icon['file_path']); ?>"
                                                         alt="<?php echo e($icon['name']); ?>"
                                                         style="max-height: 70px; max-width: 100%; object-fit: contain;">
                                                </div>
                                                <div class="small fw-bold text-truncate" title="<?php echo e($icon['name']); ?>">
                                                    <?php echo e($icon['name']); ?>
                                                </div>
                                                <span class="badge bg-secondary"><?php echo $icon['category'] === 'Zubehoer' ? 'Zubehör' : e($icon['category']); ?></span>
                                                <?php if ($icon['usage_count'] > 0): ?>
                                                    <span class="badge bg-success"><?php echo $icon['usage_count']; ?>× verwendet</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer bg-white border-top-0 py-1">
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Icon wirklich löschen?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $icon['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                            <?php echo $icon['usage_count'] > 0 ? 'disabled title="Wird noch verwendet"' : ''; ?>>
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Datei-Vorschau
        document.querySelector('input[name="icon_file"]').addEventListener('change', function(e) {
            var file = e.target.files[0];
            var preview = document.getElementById('iconPreview');
            var img = document.getElementById('iconPreviewImg');
            if (file && file.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = function(ev) {
                    img.src = ev.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    </script>
</body>
</html>
