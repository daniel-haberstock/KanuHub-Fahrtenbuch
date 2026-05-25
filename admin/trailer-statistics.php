<?php
/**
 * Fahrtenbuch - Anhänger-Statistik (nur Admin)
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// Filter
$filterBoat = $_GET['boat_id'] ?? null;
$filterYear = $_GET['year'] ?? date('Y');

// Anhänger-Boote laden
$boatsQuery = "SELECT id, boat_name FROM boats WHERE boat_type = 'Anhänger' ORDER BY boat_name ASC";
$boatsStmt = $db->prepare($boatsQuery);
$boatsStmt->execute();
$trailers = $boatsStmt->fetchAll();

// Statistik-Abfrage
$query = "
    SELECT
        t.id,
        t.boat_id,
        COALESCE(b.boat_name, t.boat_name) as boat_name,
        t.start_date,
        t.start_time,
        t.end_date,
        t.end_time,
        t.distance,
        t.route_custom,
        t.is_completed
    FROM trailer_trips t
    LEFT JOIN boats b ON t.boat_id = b.id
    WHERE 1=1
";

$params = [];

if ($filterBoat) {
    $query .= " AND t.boat_id = :boat_id";
    $params['boat_id'] = $filterBoat;
}

if ($filterYear) {
    $query .= " AND YEAR(t.start_date) = :year";
    $params['year'] = $filterYear;
}

$query .= " ORDER BY t.start_date DESC, t.start_time DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$trips = $stmt->fetchAll();

// Crew-Namen für jede Fahrt
foreach ($trips as &$trip) {
    $crewQuery = "
        SELECT
            tc.member_id,
            tc.member_name,
            m.first_name,
            m.last_name
        FROM trailer_trip_crew tc
        LEFT JOIN members m ON tc.member_id = m.id
        WHERE tc.trip_id = :trip_id
        ORDER BY tc.seat_position ASC
    ";

    $crewStmt = $db->prepare($crewQuery);
    $crewStmt->execute(['trip_id' => $trip['id']]);
    $crew = $crewStmt->fetchAll();

    $crewNames = [];
    foreach ($crew as $c) {
        if ($c['member_id']) {
            $crewNames[] = $c['first_name'] . ' ' . $c['last_name'];
        } else {
            $crewNames[] = $c['member_name'];
        }
    }

    $trip['crew_names'] = implode(', ', $crewNames);
}

// Gesamt-Statistik
$totalKm = array_sum(array_column($trips, 'distance'));
$totalTrips = count(array_filter($trips, function($t) { return $t['is_completed']; }));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anhänger-Statistik - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - Anhänger-Statistik
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Statistik-Übersicht -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Gesamt-Kilometer</h5>
                        <h2><?php echo number_format($totalKm, 2, ',', '.'); ?> km</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Abgeschlossene Fahrten</h5>
                        <h2><?php echo $totalTrips; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Anhänger</label>
                        <select class="form-select" name="boat_id">
                            <option value="">Alle Anhänger</option>
                            <?php foreach ($trailers as $trailer): ?>
                                <option value="<?php echo $trailer['id']; ?>"
                                        <?php echo ($filterBoat == $trailer['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($trailer['boat_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Jahr</label>
                        <select class="form-select" name="year">
                            <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                <option value="<?php echo $year; ?>"
                                        <?php echo ($filterYear == $year) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filtern
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Fahrten-Tabelle -->
        <div class="card">
            <div class="card-header">
                <h5>Anhänger-Fahrten</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Anhänger</th>
                                <th>Fahrer</th>
                                <th>Strecke</th>
                                <th>Kilometer</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($trips)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Keine Fahrten gefunden</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($trips as $trip): ?>
                                    <tr>
                                        <td>
                                            <?php echo date('d.m.Y', strtotime($trip['start_date'])); ?>
                                            <?php if ($trip['start_time']): ?>
                                                <br><small class="text-muted"><?php echo substr($trip['start_time'], 0, 5); ?> Uhr</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo e($trip['boat_name']); ?></td>
                                        <td><?php echo e($trip['crew_names']); ?></td>
                                        <td>
                                            <?php if ($trip['route_custom']): ?>
                                                <?php echo e($trip['route_custom']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($trip['distance']): ?>
                                                <strong><?php echo number_format($trip['distance'], 2, ',', '.'); ?> km</strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($trip['is_completed']): ?>
                                                <span class="badge bg-success">Abgeschlossen</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Aktiv</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
