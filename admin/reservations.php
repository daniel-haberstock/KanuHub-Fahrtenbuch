<?php
/**
 * Fahrtenbuch - Reservierungsverwaltung
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// Delete-Operation mit E-Mail-Benachrichtigung
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = $_POST['id'] ?? null;
    $reason = $_POST['delete_reason'] ?? '';

    // Reservierung laden für E-Mail
    $query = "SELECT br.*, b.boat_name, m.first_name, m.last_name, m.email
              FROM boat_reservations br
              JOIN boats b ON br.boat_id = b.id
              LEFT JOIN members m ON br.member_id = m.id
              WHERE br.id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $id]);
    $reservation = $stmt->fetch();

    if ($reservation) {
        // E-Mail senden, falls E-Mail-Adresse vorhanden
        $emailSent = false;
        if (!empty($reservation['email'])) {
            try {
                // PHPMailer einbinden
                require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
                require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
                require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

                $mail = new PHPMailer\PHPMailer\PHPMailer(true);

                // SMTP-Konfiguration (anpassen nach Bedarf)
                //$mail->isSMTP();
                //$mail->Host = 'smtp.example.com';
                //$mail->SMTPAuth = true;
                //$mail->Username = 'your-email@example.com';
                //$mail->Password = 'your-password';
                //$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                //$mail->Port = 587;

                // Alternativ: Standard PHP mail() verwenden
                $mail->isMail();

                // E-Mail-Inhalt
                $mail->setFrom(CLUB_EMAIL, CLUB_NAME . ' Fahrtenbuch');
                $mail->addAddress($reservation['email'], $reservation['first_name'] . ' ' . $reservation['last_name']);
                $mail->CharSet = 'UTF-8';

                $mail->Subject = 'Ihre Bootsreservierung wurde storniert';

                $startDate = date('d.m.Y H:i', strtotime($reservation['reservation_start']));
                $endDate = date('d.m.Y H:i', strtotime($reservation['reservation_end']));

                $mail->Body = "Hallo " . $reservation['first_name'] . " " . $reservation['last_name'] . ",\n\n";
                $mail->Body .= "Ihre Reservierung wurde storniert:\n\n";
                $mail->Body .= "Boot: " . $reservation['boat_name'] . "\n";
                $mail->Body .= "Von: " . $startDate . "\n";
                $mail->Body .= "Bis: " . $endDate . "\n\n";
                $mail->Body .= "Grund der Stornierung:\n" . $reason . "\n\n";
                $mail->Body .= "Bei Fragen wenden Sie sich bitte an die Verwaltung.\n\n";
                $mail->Body .= "Mit freundlichen Grüßen\n";
                $mail->Body .= "Ihre " . CLUB_NAME . " Verwaltung";

                $mail->send();
                $emailSent = true;
            } catch (Exception $e) {
                $emailError = "E-Mail konnte nicht gesendet werden: " . $mail->ErrorInfo;
            }
        }

        // Reservierung löschen
        $deleteQuery = "DELETE FROM boat_reservations WHERE id = :id";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->execute(['id' => $id]);

        if ($emailSent) {
            $successMessage = "Reservierung erfolgreich gelöscht und Mitglied per E-Mail benachrichtigt!";
        } else {
            $successMessage = "Reservierung erfolgreich gelöscht!";
            if (isset($emailError)) {
                $warningMessage = $emailError;
            }
        }
    }
}

// Neue Reservierung erstellen (Admin ohne 14-Tage-Limit, bis zu 10 Boote gleichzeitig)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $boatIds = $_POST['boat_ids'] ?? [];
    $memberId = $_POST['member_id'] ?? null;
    $memberName = $_POST['member_name'] ?? null;
    $startDate = $_POST['start_date'] ?? null;
    $startTime = $_POST['start_time'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $endTime = $_POST['end_time'] ?? null;
    $reason = $_POST['reason'] ?? null;

    if (empty($boatIds)) {
        $errorMessage = "Bitte mindestens ein Boot auswählen!";
    } elseif (count($boatIds) > 10) {
        $errorMessage = "Maximal 10 Boote gleichzeitig auswählen!";
    } elseif (!($memberId || $memberName) || !$startDate || !$startTime || !$endDate || !$endTime || !$reason) {
        $errorMessage = "Bitte alle Pflichtfelder ausfüllen!";
    } else {
        try {
            $db->beginTransaction();
            $query = "INSERT INTO boat_reservations (boat_id, member_id, member_name, reservation_start, reservation_end, reason)
                      VALUES (:boat_id, :member_id, :member_name, :reservation_start, :reservation_end, :reason)";
            $stmt = $db->prepare($query);
            foreach ($boatIds as $boatId) {
                $stmt->execute([
                    'boat_id' => (int)$boatId,
                    'member_id' => $memberId ?: null,
                    'member_name' => $memberId ? null : $memberName,
                    'reservation_start' => $startDate . ' ' . $startTime,
                    'reservation_end' => $endDate . ' ' . $endTime,
                    'reason' => $reason
                ]);
            }
            $db->commit();
            $count = count($boatIds);
            $successMessage = $count === 1
                ? "Reservierung erfolgreich erstellt!"
                : "$count Reservierungen erfolgreich erstellt!";
        } catch (Exception $e) {
            $db->rollBack();
            $errorMessage = "Fehler beim Erstellen: " . $e->getMessage();
        }
    }
}

// Alle Boote laden
$boatsQuery = "SELECT id, boat_name FROM boats WHERE boat_name LIKE :prefix ORDER BY boat_name";
$boatsStmt = $db->prepare($boatsQuery);
$boatsStmt->execute([':prefix' => CLUB_BOAT_PREFIX . '%']);
$boats = $boatsStmt->fetchAll();

// Alle Mitglieder laden
$membersQuery = "SELECT id, first_name, last_name, membership_no FROM members WHERE valid_until IS NULL OR valid_until >= CURDATE() ORDER BY first_name, last_name";
$membersStmt = $db->prepare($membersQuery);
$membersStmt->execute();
$members = $membersStmt->fetchAll();

// Alle Reservierungen laden (inkl. vergangene)
$query = "SELECT
          br.*,
          b.boat_name,
          m.first_name, m.last_name, m.email,
          DATE_FORMAT(br.reservation_start, '%d.%m.%Y %H:%i') as start_formatted,
          DATE_FORMAT(br.reservation_end, '%d.%m.%Y %H:%i') as end_formatted,
          CASE
            WHEN NOW() < br.reservation_start THEN 'future'
            WHEN NOW() BETWEEN br.reservation_start AND br.reservation_end THEN 'active'
            ELSE 'past'
          END as status
          FROM boat_reservations br
          JOIN boats b ON br.boat_id = b.id
          LEFT JOIN members m ON br.member_id = m.id
          ORDER BY br.reservation_start DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$reservations = $stmt->fetchAll();

// Delete-Modus
$deleteMode = isset($_GET['delete']) ? (int)$_GET['delete'] : null;
$deleteReservation = null;
if ($deleteMode) {
    foreach ($reservations as $res) {
        if ($res['id'] == $deleteMode) {
            $deleteReservation = $res;
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
    <title>Reservierungsverwaltung - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - Reservierungen
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

        <?php if (isset($warningMessage)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?php echo e($warningMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo e($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Neue Reservierung erstellen -->
        <?php if (!$deleteMode): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="bi bi-plus-circle"></i> Neue Reservierung erstellen</h5>
                        <small>Als Admin können Sie Reservierungen ohne zeitliche Begrenzung erstellen</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="create">

                            <div class="col-md-4">
                                <label class="form-label">Boote <span class="text-danger">*</span></label>
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Maximal 10 Boote gleichzeitig</small>
                                    <span>
                                        <a href="#" id="selectAllBoats" class="small">Alle</a> |
                                        <a href="#" id="selectNoBoats" class="small">Keine</a>
                                    </span>
                                </div>
                                <div class="border rounded p-2" style="max-height: 250px; overflow-y: auto;">
                                    <?php foreach ($boats as $boat): ?>
                                    <div class="form-check">
                                        <input class="form-check-input boat-checkbox" type="checkbox"
                                               name="boat_ids[]" value="<?php echo $boat['id']; ?>" id="boat_<?php echo $boat['id']; ?>">
                                        <label class="form-check-label" for="boat_<?php echo $boat['id']; ?>">
                                            <?php echo e($boat['boat_name']); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Mitglied <span class="text-danger">*</span></label>
                                <select class="form-select" name="member_id" id="member_select">
                                    <option value="">-- Mitglied auswählen --</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>">
                                            <?php echo e($member['first_name'] . ' ' . $member['last_name']); ?>
                                            (<?php echo e($member['membership_no']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="other">-- Oder manuell eingeben --</option>
                                </select>
                            </div>

                            <div class="col-md-4" id="manual_name_field" style="display: none;">
                                <label class="form-label">Manueller Name</label>
                                <input type="text" class="form-control" name="member_name" id="member_name_input" placeholder="Name eingeben">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Startdatum <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Startzeit <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="start_time" value="08:00" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Enddatum <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="end_date" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Endzeit <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="end_time" value="18:00" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Grund <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="reason" rows="2" placeholder="Grund für die Reservierung" required></textarea>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Reservierung erstellen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Lösch-Formular -->
            <?php if ($deleteMode && $deleteReservation): ?>
            <div class="col-md-4">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5>Reservierung löschen</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3">Boot: <?php echo e($deleteReservation['boat_name']); ?></h6>
                        <p>
                            <strong>Reserviert für:</strong><br>
                            <?php if ($deleteReservation['first_name']): ?>
                                <?php echo e($deleteReservation['first_name'] . ' ' . $deleteReservation['last_name']); ?>
                                <?php if ($deleteReservation['email']): ?>
                                    <br><small class="text-muted"><?php echo e($deleteReservation['email']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php echo e($deleteReservation['member_name']); ?>
                            <?php endif; ?>
                        </p>
                        <p>
                            <strong>Zeitraum:</strong><br>
                            <?php echo e($deleteReservation['start_formatted']); ?> -<br>
                            <?php echo e($deleteReservation['end_formatted']); ?>
                        </p>

                        <form method="POST">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $deleteReservation['id']; ?>">

                            <div class="mb-3">
                                <label class="form-label">Grund der Stornierung</label>
                                <textarea class="form-control" name="delete_reason" rows="5"
                                          placeholder="Bitte geben Sie den Grund für die Stornierung an (wird per E-Mail an das Mitglied gesendet)"
                                          required></textarea>
                                <small class="form-text text-muted">
                                    <?php if ($deleteReservation['email']): ?>
                                        <i class="bi bi-envelope"></i> E-Mail-Benachrichtigung wird versendet
                                    <?php else: ?>
                                        <i class="bi bi-exclamation-triangle text-warning"></i> Keine E-Mail-Adresse hinterlegt
                                    <?php endif; ?>
                                </small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-danger"
                                        onclick="return confirm('Reservierung wirklich löschen?');">
                                    <i class="bi bi-trash"></i> Reservierung löschen
                                </button>
                                <a href="/admin/reservations.php" class="btn btn-secondary">Abbrechen</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reservierungsliste -->
            <div class="<?php echo $deleteMode ? 'col-md-8' : 'col-12'; ?>">
                <div class="card">
                    <div class="card-header">
                        <h5>Reservierungen</h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-3" id="reservationsTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="active-tab" data-bs-toggle="tab"
                                        data-bs-target="#active" type="button">
                                    <i class="bi bi-calendar-check text-success"></i> Aktiv
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="future-tab" data-bs-toggle="tab"
                                        data-bs-target="#future" type="button">
                                    <i class="bi bi-calendar-plus text-primary"></i> Zukünftig
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="past-tab" data-bs-toggle="tab"
                                        data-bs-target="#past" type="button">
                                    <i class="bi bi-calendar-x text-secondary"></i> Vergangen
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="reservationsTabContent">
                            <!-- Aktive Reservierungen -->
                            <div class="tab-pane fade show active" id="active" role="tabpanel">
                                <?php
                                $activeReservations = array_filter($reservations, fn($r) => $r['status'] === 'active');
                                ?>
                                <?php if (count($activeReservations) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Boot</th>
                                                <th>Reserviert für</th>
                                                <th>Von</th>
                                                <th>Bis</th>
                                                <th>Grund</th>
                                                <th>Typ</th>
                                                <th>Aktion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activeReservations as $res): ?>
                                            <tr class="table-success">
                                                <td><?php echo e($res['boat_name']); ?></td>
                                                <td>
                                                    <?php if ($res['first_name']): ?>
                                                        <?php echo e($res['first_name'] . ' ' . $res['last_name']); ?>
                                                    <?php else: ?>
                                                        <?php echo e($res['member_name']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo e($res['start_formatted']); ?></td>
                                                <td><?php echo e($res['end_formatted']); ?></td>
                                                <td><?php echo e($res['reason']); ?></td>
                                                <td>
                                                    <?php if ($res['reservation_type'] === 'WEEKLY'): ?>
                                                        <span class="badge bg-info">Wöchentlich</span>
                                                    <?php elseif ($res['reservation_type'] === 'WEEKLY_LIMITED'): ?>
                                                        <span class="badge bg-warning">Befristet wöchentlich</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary">Einmalig</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="?delete=<?php echo $res['id']; ?>" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted text-center py-4">Keine aktiven Reservierungen</p>
                                <?php endif; ?>
                            </div>

                            <!-- Zukünftige Reservierungen -->
                            <div class="tab-pane fade" id="future" role="tabpanel">
                                <?php
                                $futureReservations = array_filter($reservations, fn($r) => $r['status'] === 'future');
                                ?>
                                <?php if (count($futureReservations) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Boot</th>
                                                <th>Reserviert für</th>
                                                <th>Von</th>
                                                <th>Bis</th>
                                                <th>Grund</th>
                                                <th>Typ</th>
                                                <th>Aktion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($futureReservations as $res): ?>
                                            <tr>
                                                <td><?php echo e($res['boat_name']); ?></td>
                                                <td>
                                                    <?php if ($res['first_name']): ?>
                                                        <?php echo e($res['first_name'] . ' ' . $res['last_name']); ?>
                                                    <?php else: ?>
                                                        <?php echo e($res['member_name']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo e($res['start_formatted']); ?></td>
                                                <td><?php echo e($res['end_formatted']); ?></td>
                                                <td><?php echo e($res['reason']); ?></td>
                                                <td>
                                                    <?php if ($res['reservation_type'] === 'WEEKLY'): ?>
                                                        <span class="badge bg-info">Wöchentlich</span>
                                                    <?php elseif ($res['reservation_type'] === 'WEEKLY_LIMITED'): ?>
                                                        <span class="badge bg-warning">Befristet wöchentlich</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary">Einmalig</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="?delete=<?php echo $res['id']; ?>" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted text-center py-4">Keine zukünftigen Reservierungen</p>
                                <?php endif; ?>
                            </div>

                            <!-- Vergangene Reservierungen -->
                            <div class="tab-pane fade" id="past" role="tabpanel">
                                <?php
                                $pastReservations = array_filter($reservations, fn($r) => $r['status'] === 'past');
                                ?>
                                <?php if (count($pastReservations) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Boot</th>
                                                <th>Reserviert für</th>
                                                <th>Von</th>
                                                <th>Bis</th>
                                                <th>Grund</th>
                                                <th>Typ</th>
                                                <th>Aktion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pastReservations as $res): ?>
                                            <tr class="table-secondary">
                                                <td><?php echo e($res['boat_name']); ?></td>
                                                <td>
                                                    <?php if ($res['first_name']): ?>
                                                        <?php echo e($res['first_name'] . ' ' . $res['last_name']); ?>
                                                    <?php else: ?>
                                                        <?php echo e($res['member_name']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo e($res['start_formatted']); ?></td>
                                                <td><?php echo e($res['end_formatted']); ?></td>
                                                <td><?php echo e($res['reason']); ?></td>
                                                <td>
                                                    <?php if ($res['reservation_type'] === 'WEEKLY'): ?>
                                                        <span class="badge bg-info">Wöchentlich</span>
                                                    <?php elseif ($res['reservation_type'] === 'WEEKLY_LIMITED'): ?>
                                                        <span class="badge bg-warning">Befristet wöchentlich</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary">Einmalig</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="?delete=<?php echo $res['id']; ?>" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted text-center py-4">Keine vergangenen Reservierungen</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Boots-Checkbox: Max 10 + Alle/Keine
        document.querySelectorAll('.boat-checkbox').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var checked = document.querySelectorAll('.boat-checkbox:checked');
                if (checked.length > 10) {
                    this.checked = false;
                    alert('Maximal 10 Boote gleichzeitig auswählen!');
                }
            });
        });
        document.getElementById('selectAllBoats')?.addEventListener('click', function(e) {
            e.preventDefault();
            var all = document.querySelectorAll('.boat-checkbox');
            var count = 0;
            all.forEach(function(cb) {
                if (count < 10) { cb.checked = true; count++; }
                else { cb.checked = false; }
            });
        });
        document.getElementById('selectNoBoats')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.boat-checkbox').forEach(function(cb) { cb.checked = false; });
        });

        // Manuelles Namensfeld ein-/ausblenden
        document.getElementById('member_select')?.addEventListener('change', function() {
            const manualField = document.getElementById('manual_name_field');
            const manualInput = document.getElementById('member_name_input');
            if (this.value === 'other') {
                manualField.style.display = 'block';
                manualInput.required = true;
                this.removeAttribute('required');
            } else {
                manualField.style.display = 'none';
                manualInput.required = false;
                manualInput.value = '';
                this.setAttribute('required', 'required');
            }
        });
    </script>
</body>
</html>
