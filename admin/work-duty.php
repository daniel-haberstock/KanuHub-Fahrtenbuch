<?php
/**
 * Admin - Arbeitsdienst Sammelerfassung
 * Erfassung von Arbeitsstunden für mehrere Mitglieder gleichzeitig
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

$success = null;
$error = null;

// Sammelerfassung verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_submit'])) {
    $workDate = $_POST['work_date'] ?? '';
    $hours = $_POST['hours'] ?? 0;
    $description = $_POST['description'] ?? '';
    $workLeader = $_POST['work_leader'] ?? '';

    // Mitglieder aus den 10 Feldern sammeln
    $memberIds = [];
    for ($i = 1; $i <= 10; $i++) {
        if (!empty($_POST["member_$i"])) {
            $memberIds[] = $_POST["member_$i"];
        }
    }

    // Validierung
    if (empty($workDate)) {
        $error = 'Bitte ein Datum eingeben';
    } elseif ($hours <= 0) {
        $error = 'Bitte Stunden auswählen';
    } elseif (empty($description)) {
        $error = 'Bitte Beschreibung eingeben';
    } elseif (empty($memberIds)) {
        $error = 'Bitte mindestens ein Mitglied auswählen';
    } else {
        try {
            $db->beginTransaction();

            $query = "INSERT INTO work_hours (member_id, work_date, hours, description, work_leader, created_by)
                      VALUES (:member_id, :work_date, :hours, :description, :work_leader, 'admin')";
            $stmt = $db->prepare($query);

            $successCount = 0;
            foreach ($memberIds as $memberId) {
                $stmt->execute([
                    'member_id' => $memberId,
                    'work_date' => $workDate,
                    'hours' => $hours,
                    'description' => $description,
                    'work_leader' => $workLeader
                ]);
                $successCount++;
            }

            $db->commit();
            $success = "Arbeitseinsatz für $successCount Mitglied(er) erfolgreich erfasst!";

            // Formular zurücksetzen
            $_POST = [];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }
}

// Aktive Mitglieder laden
$query = "SELECT id, membership_no, first_name, last_name
          FROM members
          WHERE (valid_until IS NULL OR valid_until >= CURDATE())
          ORDER BY last_name ASC, first_name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arbeitsdienst Sammelerfassung - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .member-select-row {
            margin-bottom: 0.75rem;
        }
        .member-select-row:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - Arbeitsdienst
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="bi bi-tools"></i> Arbeitsdienst Sammelerfassung</h2>
        <p class="text-muted">Erfassen Sie Arbeitsstunden für mehrere Mitglieder gleichzeitig</p>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo e($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> <?php echo e($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Sammelerfassung</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Datum</label>
                                    <input type="date" class="form-control" name="work_date"
                                           value="<?php echo $_POST['work_date'] ?? date('Y-m-d'); ?>"
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">Stunden pro Person</label>
                                    <select class="form-select" name="hours" required>
                                        <option value="">-- Auswählen --</option>
                                        <?php
                                        for ($h = 0.5; $h <= 8; $h += 0.5) {
                                            $selected = (isset($_POST['hours']) && $_POST['hours'] == $h) ? 'selected' : '';
                                            echo "<option value=\"$h\" $selected>" . number_format($h, 1, ',', '') . " h</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Beschreibung der Tätigkeit</label>
                                <textarea class="form-control" name="description" rows="3"
                                          placeholder="z.B. Bootshalle aufgeräumt, Vereinsgelände gepflegt..." required><?php echo $_POST['description'] ?? ''; ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Arbeitsdienst-Leiter</label>
                                <input type="text" class="form-control" name="work_leader"
                                       value="<?php echo $_POST['work_leader'] ?? ''; ?>"
                                       placeholder="Name des Arbeitsdienst-Leiters (optional)">
                                <small class="form-text text-muted">Wird bei den Einträgen angezeigt</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Teilnehmende Mitglieder (bis zu 10)</label>
                                <small class="form-text text-muted d-block mb-2">
                                    <i class="bi bi-info-circle"></i>
                                    Wählen Sie bis zu 10 Mitglieder aus den Dropdown-Listen aus
                                </small>

                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <div class="member-select-row">
                                        <label class="form-label small">Mitglied <?php echo $i; ?></label>
                                        <select class="form-select form-select-sm member-select" name="member_<?php echo $i; ?>">
                                            <option value="">-- Mitglied auswählen --</option>
                                            <?php foreach ($members as $member): ?>
                                                <?php
                                                    $selected = (isset($_POST["member_$i"]) && $_POST["member_$i"] == $member['id']) ? 'selected' : '';
                                                ?>
                                                <option value="<?php echo $member['id']; ?>" <?php echo $selected; ?>>
                                                    <?php echo e($member['first_name'] . ' ' . $member['last_name']); ?>
                                                    (<?php echo e($member['membership_no']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="batch_submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-save"></i> Arbeitseinsatz erfassen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Hinweise</h6>
                    </div>
                    <div class="card-body">
                        <ul class="small mb-0">
                            <li>Wählen Sie bis zu 10 Mitglieder aus den Dropdown-Listen</li>
                            <li>Alle erhalten die gleiche Stundenzahl</li>
                            <li>Einträge werden als "Admin" markiert</li>
                            <li>Optional: Name des Arbeitsdienst-Leiters angeben</li>
                            <li>Mitglieder können ihre Stunden in ihrem Login sehen</li>
                            <li>Leere Felder werden ignoriert</li>
                        </ul>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bi bi-clock"></i> Schnellzugriff</h6>
                    </div>
                    <div class="card-body">
                        <a href="/arbeitsdienst/" class="btn btn-success btn-sm w-100 mb-2">
                            <i class="bi bi-box-arrow-in-right"></i> Zum Mitglieder-Login
                        </a>
                        <a href="/statistics/login.php" class="btn btn-info btn-sm w-100">
                            <i class="bi bi-graph-up"></i> Zur Statistik
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
