<?php
/**
 * Fahrtenbuch - Lokale Events verwalten
 *
 * CRUD für wiederkehrende Trainings und Veranstaltungen.
 * Events können wöchentlich oder monatlich wiederkehren
 * und werden im Frontend als "Gemeinsame Ausfahrt" angezeigt.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// CRUD-Operationen
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $startTime = $_POST['start_time'] ?? '';
        $color = $_POST['color'] ?? '#28a745';
        $icon = $_POST['icon'] ?? 'bi-calendar-event';
        $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
        $recurrenceType = $isRecurring ? ($_POST['recurrence_type'] ?? null) : null;
        $recurrenceDay = $isRecurring ? (int)($_POST['recurrence_day'] ?? 0) : null;
        $validFrom = ($isRecurring && !empty($_POST['valid_from'])) ? $_POST['valid_from'] : null;
        $validTo = ($isRecurring && !empty($_POST['valid_to'])) ? $_POST['valid_to'] : null;
        $oneTimeDate = (!$isRecurring && !empty($_POST['one_time_date'])) ? $_POST['one_time_date'] : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Validierung
        if (empty($name) || empty($startTime)) {
            $errorMessage = "Name und Uhrzeit sind Pflichtfelder.";
        } elseif ($isRecurring && empty($recurrenceType)) {
            $errorMessage = "Bitte Wiederholungsart wählen.";
        } elseif ($isRecurring && ($recurrenceDay < 1 || ($recurrenceType === 'weekly' && $recurrenceDay > 7) || ($recurrenceType === 'monthly' && $recurrenceDay > 31))) {
            $errorMessage = "Ungültiger Wiederholungstag.";
        } elseif (!$isRecurring && empty($oneTimeDate)) {
            $errorMessage = "Einmalige Events benötigen ein Datum.";
        }

        if (!$errorMessage) {
            if ($action === 'create') {
                $query = "INSERT INTO local_events (name, description, start_time, color, icon, is_recurring, recurrence_type, recurrence_day, valid_from, valid_to, one_time_date, is_active)
                          VALUES (:name, :description, :start_time, :color, :icon, :is_recurring, :recurrence_type, :recurrence_day, :valid_from, :valid_to, :one_time_date, :is_active)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'name' => $name,
                    'description' => $description ?: null,
                    'start_time' => $startTime,
                    'color' => $color,
                    'icon' => $icon,
                    'is_recurring' => $isRecurring,
                    'recurrence_type' => $recurrenceType,
                    'recurrence_day' => $recurrenceDay,
                    'valid_from' => $validFrom,
                    'valid_to' => $validTo,
                    'one_time_date' => $oneTimeDate,
                    'is_active' => $isActive
                ]);
                $successMessage = "Event erfolgreich erstellt!";
            } else {
                $query = "UPDATE local_events SET name = :name, description = :description, start_time = :start_time,
                          color = :color, icon = :icon, is_recurring = :is_recurring, recurrence_type = :recurrence_type,
                          recurrence_day = :recurrence_day, valid_from = :valid_from, valid_to = :valid_to,
                          one_time_date = :one_time_date, is_active = :is_active WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'id' => $id,
                    'name' => $name,
                    'description' => $description ?: null,
                    'start_time' => $startTime,
                    'color' => $color,
                    'icon' => $icon,
                    'is_recurring' => $isRecurring,
                    'recurrence_type' => $recurrenceType,
                    'recurrence_day' => $recurrenceDay,
                    'valid_from' => $validFrom,
                    'valid_to' => $validTo,
                    'one_time_date' => $oneTimeDate,
                    'is_active' => $isActive
                ]);
                $successMessage = "Event erfolgreich aktualisiert!";
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        // Prüfen ob Event in trip_groups referenziert wird
        $checkQuery = "SELECT COUNT(*) as cnt FROM trip_groups WHERE source_type = 'local_event' AND source_id = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute(['id' => $id]);
        $usageCount = $checkStmt->fetch()['cnt'];

        if ($usageCount > 0) {
            $errorMessage = "Dieses Event kann nicht gelöscht werden, da es von $usageCount Fahrtengruppe(n) referenziert wird. Stattdessen deaktivieren.";
        } else {
            $query = "DELETE FROM local_events WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute(['id' => $id]);
            $successMessage = "Event erfolgreich gelöscht!";
        }
    } elseif ($action === 'toggle') {
        $id = $_POST['id'] ?? null;
        $query = "UPDATE local_events SET is_active = NOT is_active WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $id]);
        $successMessage = "Status geändert!";
    }
}

// Events laden
$query = "SELECT * FROM local_events ORDER BY is_active DESC, is_recurring DESC, name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$events = $stmt->fetchAll();

// Bearbeitungsmodus
$editMode = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editEvent = null;
if ($editMode) {
    $query = "SELECT * FROM local_events WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $editMode]);
    $editEvent = $stmt->fetch();
}

// Verfügbare Icons
$icons = [
    'bi-calendar-event' => 'Kalender-Event',
    'bi-water' => 'Wasser',
    'bi-trophy' => 'Pokal',
    'bi-lightning' => 'Blitz',
    'bi-people' => 'Personen',
    'bi-flag' => 'Flagge',
    'bi-star' => 'Stern',
    'bi-heart' => 'Herz',
    'bi-sun' => 'Sonne',
    'bi-moon' => 'Mond',
    'bi-bicycle' => 'Fahrrad',
    'bi-compass' => 'Kompass',
    'bi-mortarboard' => 'Ausbildung',
    'bi-stopwatch' => 'Stoppuhr',
    'bi-life-preserver' => 'Rettungsring',
];

// Wochentage
$weekdays = [
    1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag',
    5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lokale Events - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .color-preview {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 4px;
            vertical-align: middle;
            border: 1px solid #dee2e6;
        }
        .event-inactive {
            opacity: 0.5;
        }
        .icon-preview {
            font-size: 1.2rem;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - Lokale Events
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= e($successMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= e($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formular links -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><?= $editMode ? 'Event bearbeiten' : 'Neues Event' ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?= $editMode ? 'update' : 'create' ?>">
                            <?php if ($editMode): ?>
                                <input type="hidden" name="id" value="<?= $editEvent['id'] ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" class="form-control" name="name"
                                       value="<?= $editEvent ? e($editEvent['name']) : '' ?>"
                                       placeholder="z.B. Donnerstags-Paddeltreff" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Beschreibung</label>
                                <textarea class="form-control" name="description" rows="2"
                                          placeholder="Optionale Infos zum Event"><?= $editEvent ? e($editEvent['description']) : '' ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Uhrzeit *</label>
                                <input type="time" class="form-control" name="start_time"
                                       value="<?= $editEvent ? substr($editEvent['start_time'], 0, 5) : '18:00' ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Farbe</label>
                                    <input type="color" class="form-control form-control-color w-100" name="color"
                                           value="<?= $editEvent ? e($editEvent['color']) : '#28a745' ?>">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Icon</label>
                                    <select class="form-select" name="icon" id="iconSelect">
                                        <?php foreach ($icons as $class => $label): ?>
                                            <option value="<?= $class ?>" <?= ($editEvent && $editEvent['icon'] === $class) ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_recurring" id="isRecurring"
                                           value="1" <?= (!$editEvent || $editEvent['is_recurring']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="isRecurring">Wiederkehrendes Event</label>
                                </div>
                            </div>

                            <!-- Wiederkehrend: Typ + Tag + Gültigkeitszeitraum -->
                            <div id="recurringFields">
                                <div class="mb-3">
                                    <label class="form-label">Wiederholung</label>
                                    <select class="form-select" name="recurrence_type" id="recurrenceType">
                                        <option value="weekly" <?= ($editEvent && $editEvent['recurrence_type'] === 'weekly') ? 'selected' : '' ?>>Wöchentlich</option>
                                        <option value="monthly" <?= ($editEvent && $editEvent['recurrence_type'] === 'monthly') ? 'selected' : '' ?>>Monatlich</option>
                                    </select>
                                </div>

                                <div class="mb-3" id="weekdayField">
                                    <label class="form-label">Wochentag</label>
                                    <select class="form-select" name="recurrence_day_weekly">
                                        <?php foreach ($weekdays as $num => $dayName): ?>
                                            <option value="<?= $num ?>" <?= ($editEvent && $editEvent['recurrence_type'] === 'weekly' && (int)$editEvent['recurrence_day'] === $num) ? 'selected' : '' ?>>
                                                <?= $dayName ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3 d-none" id="monthdayField">
                                    <label class="form-label">Tag im Monat</label>
                                    <input type="number" class="form-control" name="recurrence_day_monthly"
                                           min="1" max="31"
                                           value="<?= ($editEvent && $editEvent['recurrence_type'] === 'monthly') ? (int)$editEvent['recurrence_day'] : '1' ?>">
                                </div>

                                <!-- Hidden field für den tatsächlichen recurrence_day -->
                                <input type="hidden" name="recurrence_day" id="recurrenceDayHidden"
                                       value="<?= $editEvent ? (int)$editEvent['recurrence_day'] : '4' ?>">

                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Gültig ab</label>
                                        <input type="date" class="form-control" name="valid_from"
                                               value="<?= $editEvent ? ($editEvent['valid_from'] ?? '') : '' ?>">
                                        <small class="form-text text-muted">Leer = sofort</small>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Gültig bis</label>
                                        <input type="date" class="form-control" name="valid_to"
                                               value="<?= $editEvent ? ($editEvent['valid_to'] ?? '') : '' ?>">
                                        <small class="form-text text-muted">Leer = unbegrenzt</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Einmalig: Datum -->
                            <div id="oneTimeFields" class="d-none">
                                <div class="mb-3">
                                    <label class="form-label">Datum *</label>
                                    <input type="date" class="form-control" name="one_time_date"
                                           value="<?= $editEvent ? ($editEvent['one_time_date'] ?? '') : '' ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                           value="1" <?= (!$editEvent || $editEvent['is_active']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="isActive">Aktiv</label>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Speichern
                                </button>
                                <?php if ($editMode): ?>
                                    <a href="/admin/local-events.php" class="btn btn-secondary">Abbrechen</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Liste rechts -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Lokale Events (<?= count($events) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;"></th>
                                        <th>Name</th>
                                        <th>Uhrzeit</th>
                                        <th>Wiederholung</th>
                                        <th>Status</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr class="<?= !$event['is_active'] ? 'event-inactive' : '' ?>">
                                            <td>
                                                <span class="color-preview" style="background-color: <?= e($event['color']) ?>;"></span>
                                            </td>
                                            <td>
                                                <i class="<?= e($event['icon']) ?> icon-preview"></i>
                                                <strong><?= e($event['name']) ?></strong>
                                                <?php if ($event['description']): ?>
                                                    <br><small class="text-muted"><?= e(mb_substr($event['description'], 0, 60)) ?><?= mb_strlen($event['description']) > 60 ? '...' : '' ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= substr($event['start_time'], 0, 5) ?> Uhr</td>
                                            <td>
                                                <?php if ($event['is_recurring']): ?>
                                                    <?php if ($event['recurrence_type'] === 'weekly'): ?>
                                                        <span class="badge bg-primary">
                                                            Jeden <?= $weekdays[(int)$event['recurrence_day']] ?? '?' ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">
                                                            Jeden <?= (int)$event['recurrence_day'] ?>. im Monat
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($event['valid_from'] || $event['valid_to']): ?>
                                                        <br><small class="text-muted">
                                                            <?= $event['valid_from'] ? formatDateGerman($event['valid_from']) : '...' ?>
                                                            –
                                                            <?= $event['valid_to'] ? formatDateGerman($event['valid_to']) : '...' ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        Einmalig: <?= $event['one_time_date'] ? formatDateGerman($event['one_time_date']) : '–' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($event['is_active']): ?>
                                                    <span class="badge bg-success">Aktiv</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inaktiv</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?edit=<?= $event['id'] ?>" class="btn btn-warning" title="Bearbeiten">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="id" value="<?= $event['id'] ?>">
                                                        <button type="submit" class="btn btn-<?= $event['is_active'] ? 'secondary' : 'success' ?>" title="<?= $event['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                                            <i class="bi bi-<?= $event['is_active'] ? 'pause' : 'play' ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Event wirklich löschen?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= $event['id'] ?>">
                                                        <button type="submit" class="btn btn-danger" title="Löschen">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($events)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="bi bi-calendar-x display-4"></i>
                                                <p class="mt-2">Noch keine Events angelegt.<br>Erstelle z.B. einen wöchentlichen Paddeltreff!</p>
                                            </td>
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
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const isRecurring = document.getElementById('isRecurring');
        const recurringFields = document.getElementById('recurringFields');
        const oneTimeFields = document.getElementById('oneTimeFields');
        const recurrenceType = document.getElementById('recurrenceType');
        const weekdayField = document.getElementById('weekdayField');
        const monthdayField = document.getElementById('monthdayField');
        const recurrenceDayHidden = document.getElementById('recurrenceDayHidden');

        function toggleRecurring() {
            if (isRecurring.checked) {
                recurringFields.classList.remove('d-none');
                oneTimeFields.classList.add('d-none');
            } else {
                recurringFields.classList.add('d-none');
                oneTimeFields.classList.remove('d-none');
            }
        }

        function toggleRecurrenceType() {
            if (recurrenceType.value === 'weekly') {
                weekdayField.classList.remove('d-none');
                monthdayField.classList.add('d-none');
            } else {
                weekdayField.classList.add('d-none');
                monthdayField.classList.remove('d-none');
            }
            syncRecurrenceDay();
        }

        function syncRecurrenceDay() {
            if (recurrenceType.value === 'weekly') {
                recurrenceDayHidden.value = document.querySelector('[name="recurrence_day_weekly"]').value;
            } else {
                recurrenceDayHidden.value = document.querySelector('[name="recurrence_day_monthly"]').value;
            }
        }

        // Event-Listener
        isRecurring.addEventListener('change', toggleRecurring);
        recurrenceType.addEventListener('change', toggleRecurrenceType);
        document.querySelector('[name="recurrence_day_weekly"]').addEventListener('change', syncRecurrenceDay);
        document.querySelector('[name="recurrence_day_monthly"]').addEventListener('input', syncRecurrenceDay);

        // Initial
        toggleRecurring();
        toggleRecurrenceType();

        // Icon-Vorschau
        const iconSelect = document.getElementById('iconSelect');
        iconSelect.addEventListener('change', function() {
            // Optional: Live-Vorschau könnte hier ergänzt werden
        });
    });
    </script>
</body>
</html>
