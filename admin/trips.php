<?php
/**
 * Fahrtenbuch - Admin: Fahrten verwalten
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// Suchparameter
$search = $_GET['search'] ?? '';
$filterBoat = $_GET['boat'] ?? '';
$filterDate = $_GET['date'] ?? '';
$filterCompleted = $_GET['completed'] ?? '';

// Query für Trips
$query = "
    SELECT
        t.*,
        COALESCE(b.boat_name, t.boat_name) as display_boat_name,
        b.boat_type,
        DATE_FORMAT(t.start_date, '%d.%m.%Y') as start_date_formatted,
        TIME_FORMAT(t.start_time, '%H:%i') as start_time_formatted,
        DATE_FORMAT(t.end_date, '%d.%m.%Y') as end_date_formatted,
        TIME_FORMAT(t.end_time, '%H:%i') as end_time_formatted,
        COALESCE(
            CONCAT(m1.first_name, ' ', m1.last_name),
            tc1.member_name
        ) as first_driver
    FROM trips t
    LEFT JOIN boats b ON t.boat_id = b.id
    LEFT JOIN trip_crew tc1 ON tc1.trip_id = t.id AND tc1.seat_position = 1
    LEFT JOIN members m1 ON m1.id = tc1.member_id
    WHERE 1=1
";

$params = [];

if ($search) {
    $query .= " AND (t.boat_name LIKE :search OR b.boat_name LIKE :search OR t.route_custom LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if ($filterBoat) {
    $query .= " AND (t.boat_id = :boat_id OR t.boat_name = :boat_name)";
    $params['boat_id'] = $filterBoat;
    $params['boat_name'] = $filterBoat;
}

if ($filterDate) {
    $query .= " AND t.start_date = :date";
    $params['date'] = $filterDate;
}

if ($filterCompleted !== '') {
    $query .= " AND t.is_completed = :completed";
    $params['completed'] = $filterCompleted;
}

$query .= " ORDER BY t.start_date DESC, t.start_time DESC LIMIT 100";

$stmt = $db->prepare($query);
$stmt->execute($params);
$trips = $stmt->fetchAll();

// Boote für Filter laden
$boatsQuery = "SELECT id, boat_name FROM boats ORDER BY boat_name";
$boatsStmt = $db->query($boatsQuery);
$boats = $boatsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fahrten verwalten - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .trip-row:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }
        .badge-boat-type {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-journal-text"></i> Administration – Fahrten
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="bi bi-journal-text"></i> Fahrten verwalten</h2>
        <p class="text-muted">Fahrtenbuch-Einträge bearbeiten oder löschen</p>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-funnel"></i> Filter
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Suche</label>
                        <input type="text" name="search" class="form-control" placeholder="Boot, Strecke..." value="<?= e($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Boot</label>
                        <select name="boat" class="form-select">
                            <option value="">Alle Boote</option>
                            <?php foreach ($boats as $boat): ?>
                                <option value="<?= $boat['id'] ?>" <?= $filterBoat == $boat['id'] ? 'selected' : '' ?>>
                                    <?= e($boat['boat_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Datum</label>
                        <input type="date" name="date" class="form-control" value="<?= e($filterDate) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="completed" class="form-select">
                            <option value="">Alle</option>
                            <option value="0" <?= $filterCompleted === '0' ? 'selected' : '' ?>>Aktiv</option>
                            <option value="1" <?= $filterCompleted === '1' ? 'selected' : '' ?>>Abgeschlossen</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Filtern
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Trips Liste -->
        <div class="card">
            <div class="card-header">
                Fahrten (<?= count($trips) ?>)
            </div>
            <div class="card-body p-0">
                <?php if (empty($trips)): ?>
                    <div class="alert alert-info m-3">
                        <i class="bi bi-info-circle"></i> Keine Fahrten gefunden.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Datum/Zeit</th>
                                    <th>Boot</th>
                                    <th>Strecke</th>
                                    <th>Status</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trips as $trip): ?>
                                    <tr class="trip-row">
                                        <td><?= $trip['id'] ?></td>
                                        <td>
                                            <?= $trip['start_date_formatted'] ?> <?= $trip['start_time_formatted'] ?>
                                            <?php if ($trip['end_date']): ?>
                                                <br><small class="text-muted">bis <?= $trip['end_date_formatted'] ?> <?= $trip['end_time_formatted'] ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= e($trip['display_boat_name']) ?>
                                            <?php if ($trip['first_driver']): ?>
                                                <br><small class="text-muted"><i class="bi bi-person"></i> <?= e($trip['first_driver']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($trip['route_custom']): ?>
                                                <?= e($trip['route_custom']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                            <?php if ($trip['distance']): ?>
                                                <br><small class="text-muted"><?= $trip['distance'] ?> km</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($trip['is_completed']): ?>
                                                <span class="badge bg-success">Abgeschlossen</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Aktiv</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editTrip(<?= $trip['id'] ?>)">
                                                <i class="bi bi-pencil"></i> Bearbeiten
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteTrip(<?= $trip['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Fahrt bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editForm">
                    <div class="modal-body">
                        <input type="hidden" id="edit_trip_id" name="trip_id">
                        <input type="hidden" id="edit_is_trailer" name="is_trailer">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Boot</label>
                                <input type="text" class="form-control" id="edit_boat_name" name="boat_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Boot ID (optional)</label>
                                <input type="number" class="form-control" id="edit_boat_id" name="boat_id">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start-Datum</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start-Zeit</label>
                                <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End-Datum</label>
                                <input type="date" class="form-control" id="edit_end_date" name="end_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End-Zeit</label>
                                <input type="time" class="form-control" id="edit_end_time" name="end_time">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Strecke</label>
                                <input type="text" class="form-control" id="edit_route_custom" name="route_custom">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Entfernung (km)</label>
                                <input type="number" step="0.01" class="form-control" id="edit_distance" name="distance">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bemerkungen</label>
                            <textarea class="form-control" id="edit_comments" name="comments" rows="3"></textarea>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="edit_is_completed" name="is_completed" value="1">
                            <label class="form-check-label" for="edit_is_completed">Fahrt abgeschlossen</label>
                        </div>

                        <div id="crewFields"></div>
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
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));

        async function editTrip(tripId) {
            try {
                const response = await fetch('/api/trips/get.php?id=' + tripId);
                const data = await response.json();

                if (!data.success) {
                    alert('Fehler beim Laden der Fahrt: ' + data.message);
                    return;
                }

                const trip = data.trip;

                // Formular füllen
                document.getElementById('edit_trip_id').value = trip.id;
                document.getElementById('edit_boat_name').value = trip.boat_name || '';
                document.getElementById('edit_boat_id').value = trip.boat_id || '';
                document.getElementById('edit_start_date').value = trip.start_date || '';
                document.getElementById('edit_start_time').value = trip.start_time || '';
                document.getElementById('edit_end_date').value = trip.end_date || '';
                document.getElementById('edit_end_time').value = trip.end_time || '';
                document.getElementById('edit_route_custom').value = trip.route_custom || '';
                document.getElementById('edit_distance').value = trip.distance || '';
                document.getElementById('edit_comments').value = trip.comments || '';
                document.getElementById('edit_is_completed').checked = trip.is_completed == 1;
                document.getElementById('edit_is_trailer').value = trip.boat_type === 'Anhänger' ? '1' : '0';

                // Crew-Felder erstellen
                const crewFields = document.getElementById('crewFields');
                crewFields.innerHTML = '<h6 class="mt-3">Crew</h6>';

                if (trip.crew && trip.crew.length > 0) {
                    trip.crew.forEach((member, index) => {
                        const position = index + 1;
                        crewFields.innerHTML += `
                            <div class="row mb-2">
                                <div class="col-md-2">
                                    <label class="form-label">Position ${position}</label>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="crew_${position}" value="${member.member_name || member.first_name + ' ' + member.last_name || ''}">
                                </div>
                                <div class="col-md-4">
                                    <input type="number" class="form-control" name="crew_${position}_id" placeholder="Mitglieds-ID" value="${member.member_id || ''}">
                                </div>
                            </div>
                        `;
                    });
                }

                editModal.show();
            } catch (error) {
                alert('Fehler beim Laden der Fahrt: ' + error.message);
            }
        }

        async function deleteTrip(tripId) {
            if (!confirm('Möchten Sie diese Fahrt wirklich löschen?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('trip_id', tripId);
                formData.append('is_trailer', '0'); // TODO: Richtig erkennen

                const response = await fetch('/api/trips/delete.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert('Fahrt erfolgreich gelöscht');
                    location.reload();
                } else {
                    alert('Fehler beim Löschen: ' + data.message);
                }
            } catch (error) {
                alert('Fehler beim Löschen: ' + error.message);
            }
        }

        document.getElementById('editForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch('/api/trips/update.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert('Fahrt erfolgreich aktualisiert');
                    editModal.hide();
                    location.reload();
                } else {
                    alert('Fehler beim Aktualisieren: ' + data.message);
                }
            } catch (error) {
                alert('Fehler beim Aktualisieren: ' + error.message);
            }
        });
    </script>
</body>
</html>
