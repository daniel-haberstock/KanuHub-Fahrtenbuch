<?php
/**
 * Fahrtenbuch - Schadenverwaltung
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// Mitglieder für Dropdown laden (nur aktive)
$membersQuery = "SELECT id, first_name, last_name FROM members
                 WHERE (valid_until IS NULL OR valid_until >= CURDATE())
                 ORDER BY last_name ASC";
$membersStmt = $db->prepare($membersQuery);
$membersStmt->execute();
$members = $membersStmt->fetchAll();

// CRUD-Operationen
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'mark_fixed') {
        $id = $_POST['id'] ?? null;
        $fixedByMemberId = $_POST['fixed_by_member_id'] ?? null;
        $fixedByName = $_POST['fixed_by_name'] ?? null;
        $repairCost = $_POST['repair_cost'] ?? null;
        $workHours = $_POST['work_hours'] ?? null;
        $isInsuranceClaim = isset($_POST['is_insurance_claim']) ? 1 : 0;
        $repairNotes = $_POST['repair_notes'] ?? '';

        $query = "UPDATE boat_damages SET
                  is_fixed = 1,
                  fixed_at = NOW(),
                  fixed_by_member_id = :fixed_by_member_id,
                  fixed_by_name = :fixed_by_name,
                  repair_cost = :repair_cost,
                  work_hours = :work_hours,
                  is_insurance_claim = :is_insurance_claim,
                  repair_notes = :repair_notes
                  WHERE id = :id";

        $stmt = $db->prepare($query);
        $stmt->execute([
            'id' => $id,
            'fixed_by_member_id' => $fixedByMemberId ?: null,
            'fixed_by_name' => $fixedByName,
            'repair_cost' => $repairCost ?: null,
            'work_hours' => $workHours ?: null,
            'is_insurance_claim' => $isInsuranceClaim,
            'repair_notes' => $repairNotes
        ]);

        $successMessage = "Schaden erfolgreich als repariert markiert!";
    }
}

// Schäden laden
$query = "SELECT
          bd.*,
          b.boat_name,
          m1.first_name as reporter_first, m1.last_name as reporter_last,
          m2.first_name as fixer_first, m2.last_name as fixer_last
          FROM boat_damages bd
          JOIN boats b ON bd.boat_id = b.id
          LEFT JOIN members m1 ON bd.reporter_member_id = m1.id
          LEFT JOIN members m2 ON bd.fixed_by_member_id = m2.id
          ORDER BY bd.is_fixed ASC, bd.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$damages = $stmt->fetchAll();

// Bearbeitungsmodus für Reparatur
$repairMode = isset($_GET['repair']) ? (int)$_GET['repair'] : null;
$repairDamage = null;
if ($repairMode) {
    foreach ($damages as $damage) {
        if ($damage['id'] == $repairMode) {
            $repairDamage = $damage;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schadenverwaltung - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - Schäden
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo e($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Reparatur-Formular -->
            <?php if ($repairMode && $repairDamage): ?>
            <div class="col-md-4">
                <div class="card border-warning">
                    <div class="card-header bg-warning">
                        <h5>Schaden reparieren</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3">Boot: <?php echo e($repairDamage['boat_name']); ?></h6>
                        <p class="mb-3"><strong>Beschreibung:</strong><br><?php echo nl2br(e($repairDamage['damage_description'])); ?></p>

                        <form method="POST">
                            <input type="hidden" name="action" value="mark_fixed">
                            <input type="hidden" name="id" value="<?php echo $repairDamage['id']; ?>">

                            <div class="mb-3">
                                <label class="form-label">Repariert von (Mitglied)</label>
                                <select class="form-select" name="fixed_by_member_id">
                                    <option value="">-- Aus Liste wählen --</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>">
                                            <?php echo e($member['first_name'] . ' ' . $member['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Oder Name eingeben</label>
                                <input type="text" class="form-control" name="fixed_by_name"
                                       placeholder="Name (falls nicht in Liste)">
                                <small class="form-text text-muted">Nur ausfüllen, wenn nicht aus Liste</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Arbeitszeit (Stunden)</label>
                                <input type="number" step="0.25" min="0" class="form-control" name="work_hours"
                                       placeholder="0.00">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Materialkosten (€)</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="repair_cost"
                                       placeholder="0.00">
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="insurance_claim" name="is_insurance_claim">
                                <label class="form-check-label" for="insurance_claim">
                                    Versicherungsfall
                                </label>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Bemerkungen zur Reparatur</label>
                                <textarea class="form-control" name="repair_notes" rows="4"
                                          placeholder="Details zur durchgeführten Reparatur"></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Als repariert markieren
                                </button>
                                <a href="/admin/damages.php" class="btn btn-secondary">Abbrechen</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Schäden-Liste -->
            <div class="<?php echo $repairMode ? 'col-md-8' : 'col-12'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h5>Schadenliste</h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-3" id="damagesTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="unfixed-tab" data-bs-toggle="tab"
                                        data-bs-target="#unfixed" type="button">
                                    <i class="bi bi-exclamation-triangle text-danger"></i> Offen
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="fixed-tab" data-bs-toggle="tab"
                                        data-bs-target="#fixed" type="button">
                                    <i class="bi bi-check-circle text-success"></i> Repariert
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="damagesTabContent">
                            <!-- Offene Schäden -->
                            <div class="tab-pane fade show active" id="unfixed" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Boot</th>
                                                <th>Schadentyp</th>
                                                <th>Beschreibung</th>
                                                <th>Gemeldet von</th>
                                                <th>Datum</th>
                                                <th>Aktion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($damages as $damage): ?>
                                                <?php if (!$damage['is_fixed']): ?>
                                                <tr>
                                                    <td><?php echo e($damage['boat_name']); ?></td>
                                                    <td>
                                                        <?php
                                                        $badgeColor = 'secondary';
                                                        if ($damage['damage_type'] === 'Boot nicht nutzbar') {
                                                            $badgeColor = 'danger';
                                                        } elseif ($damage['damage_type'] === 'Boot eingeschränkt nutzbar') {
                                                            $badgeColor = 'warning';
                                                        } elseif ($damage['damage_type'] === 'Boot voll nutzbar') {
                                                            $badgeColor = 'info';
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?php echo $badgeColor; ?>">
                                                            <?php echo e($damage['damage_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo e(substr($damage['damage_description'], 0, 100)) . (strlen($damage['damage_description']) > 100 ? '...' : ''); ?></td>
                                                    <td>
                                                        <?php if ($damage['reporter_first']): ?>
                                                            <?php echo e($damage['reporter_first'] . ' ' . $damage['reporter_last']); ?>
                                                        <?php else: ?>
                                                            <?php echo e($damage['reporter_name']); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('d.m.Y H:i', strtotime($damage['created_at'])); ?></td>
                                                    <td>
                                                        <a href="?repair=<?php echo $damage['id']; ?>" class="btn btn-sm btn-success">
                                                            <i class="bi bi-tools"></i> Reparieren
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Reparierte Schäden -->
                            <div class="tab-pane fade" id="fixed" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Boot</th>
                                                <th>Schadentyp</th>
                                                <th>Repariert von</th>
                                                <th>Repariert am</th>
                                                <th>Arbeitszeit</th>
                                                <th>Kosten</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($damages as $damage): ?>
                                                <?php if ($damage['is_fixed']): ?>
                                                <tr>
                                                    <td><?php echo e($damage['boat_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?php echo e($damage['damage_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($damage['fixer_first']): ?>
                                                            <?php echo e($damage['fixer_first'] . ' ' . $damage['fixer_last']); ?>
                                                        <?php else: ?>
                                                            <?php echo e($damage['fixed_by_name'] ?: '-'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $damage['fixed_at'] ? date('d.m.Y H:i', strtotime($damage['fixed_at'])) : '-'; ?>
                                                    </td>
                                                    <td><?php echo $damage['work_hours'] ? $damage['work_hours'] . ' h' : '-'; ?></td>
                                                    <td>
                                                        <?php if ($damage['repair_cost']): ?>
                                                            <?php echo number_format($damage['repair_cost'], 2, ',', '.') . ' €'; ?>
                                                            <?php if ($damage['is_insurance_claim']): ?>
                                                                <span class="badge bg-warning">Versicherung</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-info"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#detailsModal<?php echo $damage['id']; ?>">
                                                            <i class="bi bi-info-circle"></i>
                                                        </button>

                                                        <!-- Details Modal -->
                                                        <div class="modal fade" id="detailsModal<?php echo $damage['id']; ?>" tabindex="-1">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Schadendetails</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <h6>Boot:</h6>
                                                                        <p><?php echo e($damage['boat_name']); ?></p>

                                                                        <h6>Schadensbeschreibung:</h6>
                                                                        <p><?php echo nl2br(e($damage['damage_description'])); ?></p>

                                                                        <?php if ($damage['repair_notes']): ?>
                                                                        <h6>Reparaturbemerkungen:</h6>
                                                                        <p><?php echo nl2br(e($damage['repair_notes'])); ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
