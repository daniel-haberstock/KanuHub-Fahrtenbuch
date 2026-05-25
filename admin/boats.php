<?php
/**
 * Fahrtenbuch - Bootsverwaltung
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// CRUD-Operationen (nur Löschen – Erstellen/Bearbeiten über boat-edit.php)
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = $_POST['id'] ?? null;
    $query = "DELETE FROM boats WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $id]);
    $successMessage = "Boot erfolgreich gelöscht!";
}

// Boote laden (inkl. letzte Fahrt – auch importierte Fahrten per boat_name)
$query = "SELECT b.*,
          m1.first_name as crew1_first, m1.last_name as crew1_last,
          m2.first_name as crew2_first, m2.last_name as crew2_last,
          GREATEST(COALESCE(lt_id.last_trip_date, '1900-01-01'), COALESCE(lt_name.last_trip_date, '1900-01-01')) as last_trip_date
          FROM boats b
          LEFT JOIN members m1 ON b.default_crew_1 = m1.id
          LEFT JOIN members m2 ON b.default_crew_2 = m2.id
          LEFT JOIN (
              SELECT boat_id, MAX(start_date) as last_trip_date
              FROM trips
              WHERE boat_id IS NOT NULL
              GROUP BY boat_id
          ) lt_id ON b.id = lt_id.boat_id
          LEFT JOIN (
              SELECT boat_name, MAX(start_date) as last_trip_date
              FROM trips
              WHERE boat_id IS NULL AND boat_name IS NOT NULL
              GROUP BY boat_name
          ) lt_name ON b.boat_name = lt_name.boat_name
          ORDER BY b.boat_name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$boats = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bootsverwaltung - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - Boote
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
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Bootsliste</h5>
                        <a href="/admin/boat-edit.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle"></i> Neues Boot
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="boatsTable">
                                <thead>
                                    <tr>
                                        <th class="sortable" data-sort="string" data-col="0" role="button">Bootsname <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="string" data-col="1" role="button">Typ <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="number" data-col="2" role="button">Sitzplätze <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="string" data-col="3" role="button">Bootsplatz <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="date" data-col="4" role="button">Im Bestand bis <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="string" data-col="5" role="button">Standard-Crew <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="date" data-col="6" role="button">Letzte Fahrt <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($boats as $boat):
                                        $isExpired = $boat['valid_until'] && strtotime($boat['valid_until']) < time();
                                    ?>
                                        <tr class="<?php echo $isExpired ? 'table-secondary' : ''; ?>">
                                            <td>
                                                <?php if (stripos($boat['boat_name'], CLUB_BOAT_PREFIX) === 0): ?>
                                                    <span class="badge bg-primary">Verein</span>
                                                <?php endif; ?>
                                                <?php echo e($boat['boat_name']); ?>
                                                <?php if (!empty($boat['video_url'])): ?>
                                                    <i class="bi bi-play-btn-fill text-danger" title="Anleitungsvideo vorhanden"></i>
                                                <?php endif; ?>
                                                <?php if ($isExpired): ?>
                                                    <span class="badge bg-danger">Ausgemustert</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo e($boat['boat_type']); ?></td>
                                            <td><?php echo $boat['seats']; ?></td>
                                            <td>
                                                <?php if ($boat['storage_location']): ?>
                                                    <?php echo e($boat['storage_location']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($boat['valid_until']): ?>
                                                    <?php echo date('d.m.Y', strtotime($boat['valid_until'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $crew = [];
                                                if ($boat['crew1_first']) {
                                                    $crew[] = $boat['crew1_first'] . ' ' . $boat['crew1_last'];
                                                }
                                                if ($boat['crew2_first']) {
                                                    $crew[] = $boat['crew2_first'] . ' ' . $boat['crew2_last'];
                                                }
                                                echo !empty($crew) ? e(implode(', ', $crew)) : '-';
                                                ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($boat['last_trip_date']) && $boat['last_trip_date'] > '1900-01-01'): ?>
                                                    <?php echo date('d.m.Y', strtotime($boat['last_trip_date'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">–</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="/admin/boat-edit.php?id=<?php echo $boat['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Wirklich löschen?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $boat['id']; ?>">
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

    <style>
        .sortable { cursor: pointer; user-select: none; white-space: nowrap; }
        .sortable:hover { background-color: rgba(0,0,0,.05); }
        .sortable .bi-arrow-down-up,
        .sortable .bi-sort-down,
        .sortable .bi-sort-up { transition: opacity .15s; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tabellen-Sortierung
        (function() {
            var table = document.getElementById('boatsTable');
            if (!table) return;
            var headers = table.querySelectorAll('th.sortable');
            var sortState = {};

            function parseDate(str) {
                // DD.MM.YYYY → YYYYMMDD
                var m = str.match(/(\d{2})\.(\d{2})\.(\d{4})/);
                return m ? m[3] + m[2] + m[1] : '';
            }

            headers.forEach(function(th) {
                th.addEventListener('click', function() {
                    var col = parseInt(th.getAttribute('data-col'));
                    var type = th.getAttribute('data-sort');
                    var asc = sortState[col] === 'asc' ? false : true;
                    sortState = {};
                    sortState[col] = asc ? 'asc' : 'desc';

                    var tbody = table.querySelector('tbody');
                    var rows = Array.from(tbody.querySelectorAll('tr'));

                    rows.sort(function(a, b) {
                        var aText = (a.children[col] ? a.children[col].textContent.trim() : '');
                        var bText = (b.children[col] ? b.children[col].textContent.trim() : '');

                        if (type === 'number') {
                            var aNum = parseFloat(aText.replace(/[^\d.,\-]/g, '').replace(',', '.')) || 0;
                            var bNum = parseFloat(bText.replace(/[^\d.,\-]/g, '').replace(',', '.')) || 0;
                            return asc ? aNum - bNum : bNum - aNum;
                        } else if (type === 'date') {
                            var aDate = parseDate(aText);
                            var bDate = parseDate(bText);
                            return asc ? aDate.localeCompare(bDate) : bDate.localeCompare(aDate);
                        } else {
                            return asc ? aText.localeCompare(bText, 'de') : bText.localeCompare(aText, 'de');
                        }
                    });

                    rows.forEach(function(r) { tbody.appendChild(r); });

                    // Icons aktualisieren
                    headers.forEach(function(h) {
                        var icon = h.querySelector('i');
                        icon.className = (h === th)
                            ? (asc ? 'bi bi-sort-up text-primary small' : 'bi bi-sort-down text-primary small')
                            : 'bi bi-arrow-down-up text-muted small';
                    });
                });
            });
        })();

    </script>
</body>
</html>
