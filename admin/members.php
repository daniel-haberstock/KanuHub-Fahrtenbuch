<?php
/**
 * Fahrtenbuch - Mitgliederliste / Mitgliederverwaltung
 *
 * Im REDAXO-Modus (REDAXO_API_TOKEN gesetzt): Nur-Lese-Ansicht
 * Ohne REDAXO: Volles CRUD wie bisher
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// REDAXO-Modus: Wenn Token konfiguriert, ist REDAXO die Quelle → kein lokales Bearbeiten
$isRedaxoMode = defined('REDAXO_API_TOKEN') && !empty(REDAXO_API_TOKEN);

// CRUD-Operationen (nur wenn NICHT im REDAXO-Modus)
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Tracker-Freigabe: immer erlaubt, unabhängig vom REDAXO-Modus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'toggle-tracker')) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $db->prepare("UPDATE members SET tracker_allowed = 1 - tracker_allowed WHERE id = :id")
           ->execute(['id' => $id]);
        $successMessage = "Tracker-Freigabe aktualisiert.";
    }
}

// eFB-ID setzen: immer erlaubt (auch im REDAXO-Modus)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'set-efb-id') {
    $id    = (int)($_POST['id'] ?? 0);
    $efbId = !empty($_POST['efb_id']) ? (int)$_POST['efb_id'] : null;
    if ($id) {
        $db->prepare("UPDATE members SET efb_id = :efb_id WHERE id = :id")
           ->execute(['efb_id' => $efbId, 'id' => $id]);
        $successMessage = "eFB-ID aktualisiert.";
    }
}

if (!$isRedaxoMode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $salutation = $_POST['salutation'] ?? 'Herr';
        $status = $_POST['status'] ?? 'Aktiv';
        $membershipNo = $_POST['membership_no'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
        $trackerAllowed = isset($_POST['tracker_allowed']) ? 1 : 0;
        $efbId = !empty($_POST['efb_id']) ? (int)$_POST['efb_id'] : null;

        if ($action === 'create') {
            $hashedPassword = !empty($password) ? hashPassword($password) : hashPassword('default123');
            $query = "INSERT INTO members (first_name, last_name, salutation, status, membership_no, email, password, valid_until, tracker_allowed, efb_id)
                      VALUES (:first_name, :last_name, :salutation, :status, :membership_no, :email, :password, :valid_until, :tracker_allowed, :efb_id)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'salutation' => $salutation,
                'status' => $status,
                'membership_no' => $membershipNo,
                'email' => $email,
                'password' => $hashedPassword,
                'valid_until' => $validUntil,
                'tracker_allowed' => $trackerAllowed,
                'efb_id' => $efbId,
            ]);
            $successMessage = "Mitglied erfolgreich erstellt!";
        } else {
            if (!empty($password)) {
                $query = "UPDATE members SET first_name = :first_name, last_name = :last_name, salutation = :salutation,
                          status = :status, membership_no = :membership_no, email = :email, password = :password,
                          valid_until = :valid_until, tracker_allowed = :tracker_allowed, efb_id = :efb_id
                          WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'id' => $id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'salutation' => $salutation,
                    'status' => $status,
                    'membership_no' => $membershipNo,
                    'email' => $email,
                    'password' => hashPassword($password),
                    'valid_until' => $validUntil,
                    'tracker_allowed' => $trackerAllowed,
                    'efb_id' => $efbId,
                ]);
            } else {
                $query = "UPDATE members SET first_name = :first_name, last_name = :last_name, salutation = :salutation,
                          status = :status, membership_no = :membership_no, email = :email,
                          valid_until = :valid_until, tracker_allowed = :tracker_allowed, efb_id = :efb_id WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'id' => $id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'salutation' => $salutation,
                    'status' => $status,
                    'membership_no' => $membershipNo,
                    'email' => $email,
                    'valid_until' => $validUntil,
                    'tracker_allowed' => $trackerAllowed,
                    'efb_id' => $efbId,
                ]);
            }
            $successMessage = "Mitglied erfolgreich aktualisiert!";
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        $query = "DELETE FROM members WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $id]);
        $successMessage = "Mitglied erfolgreich gelöscht!";
    }
}

// 1) Mitglieder laden
$members = $db->query("SELECT * FROM members ORDER BY last_name ASC, first_name ASC")->fetchAll();

// 2) Zugewiesene Boote (via default_crew_1/2) mit letzter Fahrt pro Boot
//    JOIN über boat_id ODER boat_name (importierte Fahrten haben nur boat_name, kein boat_id)
$boatsQuery = "SELECT b.id, b.boat_name, b.boat_type, b.default_crew_1, b.default_crew_2,
        b.storage_location,
        COUNT(t.id) as trip_count, MAX(t.start_date) as last_trip_date
    FROM boats b
    LEFT JOIN trips t ON (t.boat_id = b.id OR (t.boat_id IS NULL AND t.boat_name = b.boat_name))
        AND t.end_date IS NOT NULL
    WHERE (b.default_crew_1 IS NOT NULL OR b.default_crew_2 IS NOT NULL)
        AND (b.valid_until IS NULL OR b.valid_until >= CURDATE())
    GROUP BY b.id
    ORDER BY b.boat_name ASC";
$boatsData = $db->query($boatsQuery)->fetchAll();

// In PHP zuordnen: memberBoats + ownedBoatCount
$memberBoats = [];
$ownedBoatCount = [];
foreach ($boatsData as $boat) {
    // "Ungenutzt seit" berechnen
    $unusedDays = null;
    if ($boat['last_trip_date']) {
        $unusedDays = (int)((time() - strtotime($boat['last_trip_date'])) / 86400);
    }
    $info = [
        'name' => $boat['boat_name'],
        'type' => $boat['boat_type'],
        'trips' => (int)$boat['trip_count'],
        'last_trip' => $boat['last_trip_date'] ? date('d.m.Y', strtotime($boat['last_trip_date'])) : null,
        'unused_days' => $unusedDays,
        'storage' => $boat['storage_location'] ?? null
    ];
    foreach (['default_crew_1', 'default_crew_2'] as $col) {
        if (!empty($boat[$col])) {
            $mid = $boat[$col];
            $memberBoats[$mid][] = $info;
            $ownedBoatCount[$mid] = ($ownedBoatCount[$mid] ?? 0) + 1;
        }
    }
}

// 3) Letzte Fahrt pro Mitglied (wann war das Mitglied selbst zuletzt auf dem Wasser)
//    Per member_id (normale Fahrten)
$lastTripQuery = "SELECT tc.member_id, MAX(t.start_date) as last_trip_date
    FROM trip_crew tc
    JOIN trips t ON tc.trip_id = t.id
    WHERE tc.member_id IS NOT NULL
    GROUP BY tc.member_id";
$lastTripData = [];
foreach ($db->query($lastTripQuery)->fetchAll() as $row) {
    $lastTripData[$row['member_id']] = $row['last_trip_date'];
}
//    Per Name-Matching (importierte Fahrten ohne member_id)
$lastTripNameQuery = "SELECT mem.id as member_id, MAX(t.start_date) as last_trip_date
    FROM trip_crew tc
    JOIN trips t ON tc.trip_id = t.id
    JOIN members mem ON tc.member_id IS NULL
        AND tc.member_name LIKE CONCAT(mem.first_name, ' ', mem.last_name, '%')
    GROUP BY mem.id";
foreach ($db->query($lastTripNameQuery)->fetchAll() as $row) {
    $mid = $row['member_id'];
    if (!isset($lastTripData[$mid]) || $row['last_trip_date'] > $lastTripData[$mid]) {
        $lastTripData[$mid] = $row['last_trip_date'];
    }
}

// Ergebnisse in Mitglieder-Array einfügen
foreach ($members as &$m) {
    $m['last_trip_date'] = $lastTripData[$m['id']] ?? null;
    $m['owned_boat_count'] = $ownedBoatCount[$m['id']] ?? 0;
}
unset($m);

// Bearbeitungsmodus (nur wenn NICHT im REDAXO-Modus)
$editMode = null;
$editMember = null;
if (!$isRedaxoMode && isset($_GET['edit'])) {
    $editMode = (int)$_GET['edit'];
    $query = "SELECT * FROM members WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $editMode]);
    $editMember = $stmt->fetch();
}

$pageTitle = $isRedaxoMode ? 'Mitgliederliste' : 'Mitgliederverwaltung';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - <?php echo $pageTitle; ?>
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </nav>

    <div class="container-fluid mt-4 px-4">
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo e($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <?php if (!$isRedaxoMode && $editMode): ?>
            <!-- Bearbeiten-Formular (nur ohne REDAXO) -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Mitglied bearbeiten</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'create'; ?>">
                            <?php if ($editMode): ?>
                                <input type="hidden" name="id" value="<?php echo $editMember['id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Anrede</label>
                                <select class="form-select" name="salutation" required>
                                    <option value="Herr" <?php echo ($editMember && $editMember['salutation'] === 'Herr') ? 'selected' : ''; ?>>Herr</option>
                                    <option value="Frau" <?php echo ($editMember && $editMember['salutation'] === 'Frau') ? 'selected' : ''; ?>>Frau</option>
                                    <option value="Divers" <?php echo ($editMember && $editMember['salutation'] === 'Divers') ? 'selected' : ''; ?>>Divers</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Vorname</label>
                                <input type="text" class="form-control" name="first_name"
                                       value="<?php echo $editMember ? e($editMember['first_name']) : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nachname</label>
                                <input type="text" class="form-control" name="last_name"
                                       value="<?php echo $editMember ? e($editMember['last_name']) : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="Aktiv" <?php echo ($editMember && $editMember['status'] === 'Aktiv') ? 'selected' : ''; ?>>Aktiv</option>
                                    <option value="Gastmitglied" <?php echo ($editMember && $editMember['status'] === 'Gastmitglied') ? 'selected' : ''; ?>>Gastmitglied</option>
                                    <option value="Jugendmitglied" <?php echo ($editMember && $editMember['status'] === 'Jugendmitglied') ? 'selected' : ''; ?>>Jugendmitglied</option>
                                    <option value="Passiv" <?php echo ($editMember && $editMember['status'] === 'Passiv') ? 'selected' : ''; ?>>Passiv</option>
                                    <option value="Ehrenmitglied" <?php echo ($editMember && $editMember['status'] === 'Ehrenmitglied') ? 'selected' : ''; ?>>Ehrenmitglied</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Mitgliedsnummer</label>
                                <input type="text" class="form-control" name="membership_no"
                                       value="<?php echo $editMember ? e($editMember['membership_no']) : ''; ?>"
                                       placeholder="<?= CLUB_BOAT_PREFIX ?>-XXX">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">E-Mail</label>
                                <input type="email" class="form-control" name="email"
                                       value="<?php echo $editMember ? e($editMember['email']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Mitglied bis</label>
                                <input type="date" class="form-control" name="valid_until"
                                       value="<?php echo $editMember ? e($editMember['valid_until']) : ''; ?>">
                                <small class="form-text text-muted">Leer lassen für unbegrenzt</small>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="tracker_allowed"
                                           id="tracker_allowed" value="1"
                                           <?php echo ($editMember && $editMember['tracker_allowed']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="tracker_allowed">
                                        <i class="bi bi-geo-alt-fill text-success"></i> Darf GPS-Tracker verwenden
                                    </label>
                                </div>
                                <small class="text-muted">Freigegebene Mitglieder können beim Fahrtstart einen verfügbaren Tracker wählen.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Passwort <?php echo $editMode ? '(leer lassen für keine Änderung)' : ''; ?></label>
                                <input type="password" class="form-control" name="password"
                                       placeholder="<?php echo $editMode ? 'Nur bei Änderung eingeben' : 'Passwort'; ?>" autocomplete="off">
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-person-badge"></i> eFB-ID</label>
                                <input type="number" class="form-control" name="efb_id"
                                       value="<?php echo $editMember ? e($editMember['efb_id'] ?? '') : ''; ?>"
                                       placeholder="z.B. 12345" min="1">
                                <div class="form-text">DKV eFB Nutzer-ID – zu finden im eFB-Portal unter Mitgliederverwaltung → Mitglied bearbeiten (letzte Zeile).</div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Speichern
                                </button>
                                <?php if ($editMode): ?>
                                    <a href="/admin/members.php" class="btn btn-secondary">Abbrechen</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Info-Box / Sync-Hinweis -->
            <div class="col-md-3">
                <div class="card border-info mb-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Hinweis</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($isRedaxoMode): ?>
                            <p><strong>Mitglieder werden automatisch aus REDAXO YCom synchronisiert.</strong></p>
                            <p class="text-muted">
                                Die Synchronisation läuft täglich automatisch per Cronjob. Die Bearbeitung erfolgt im REDAXO YCom.
                            </p>
                        <?php else: ?>
                            <p><strong>Neue Mitglieder werden über YCom importiert.</strong></p>
                            <p class="text-muted">
                                Die Synchronisation läuft täglich automatisch per Cronjob.
                                Neue Mitglieder bitte im REDAXO YCom anlegen.
                            </p>
                        <?php endif; ?>
                        <hr class="my-2">
                        <button type="button" id="syncNowBtn" onclick="runYcomSync()"
                                class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-arrow-repeat"></i> Jetzt synchronisieren
                        </button>
                        <div id="syncResult" class="mt-2 small" style="display:none;"></div>
                    </div>
                </div>

                <?php if (defined('EFB_ENABLED')):
                    $efbCount    = $db->query("SELECT COUNT(*) FROM members WHERE efb_id IS NOT NULL")->fetchColumn();
                    $lastEfbSync = $db->query("SELECT MAX(efb_sync_at) FROM trips WHERE efb_sync_at IS NOT NULL")->fetchColumn();
                    $lastEfbFmt  = $lastEfbSync ? date('d.m.Y H:i', (int)$lastEfbSync) . ' Uhr' : 'Noch nie';
                ?>
                <div class="card border-<?= EFB_ENABLED ? 'success' : 'secondary' ?>">
                    <div class="card-header bg-<?= EFB_ENABLED ? 'success' : 'secondary' ?> text-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-person-badge"></i> DKV eFB-Sync</span>
                        <span class="badge bg-white text-<?= EFB_ENABLED ? 'success' : 'secondary' ?> small">
                            <?= EFB_ENABLED ? 'Aktiv' : 'Inaktiv' ?>
                        </span>
                    </div>
                    <div class="card-body small">
                        <p class="mb-1"><strong><?= (int)$efbCount ?></strong> Mitglieder mit eFB-ID</p>
                        <p class="mb-2 text-muted">Letzter Sync: <?= e($lastEfbFmt) ?></p>
                        <?php if (EFB_ENABLED): ?>
                        <button class="btn btn-sm btn-outline-success w-100" id="btnEfbSync">
                            <i class="bi bi-cloud-upload"></i> Jetzt syncen
                        </button>
                        <div id="efbSyncStatus" class="mt-2" style="display:none;"></div>
                        <?php else: ?>
                        <p class="text-muted mb-0">EFB_ENABLED = false in config.php</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="<?php echo (!$isRedaxoMode && $editMode) ? 'col-md-8' : 'col-md-9'; ?>">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo $pageTitle; ?></h5>
                        <span class="badge bg-secondary"><?php echo count($members); ?> Mitglieder</span>
                    </div>
                    <div class="card-body">
                        <!-- Filter -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <select id="statusFilter" class="form-select form-select-sm">
                                    <option value="">Alle Status</option>
                                    <option value="Aktiv" selected>Aktiv</option>
                                    <option value="Gastmitglied">Gastmitglied</option>
                                    <option value="Jugendmitglied">Jugendmitglied</option>
                                    <option value="Passiv">Passiv</option>
                                    <option value="Ehrenmitglied">Ehrenmitglied</option>
                                    <option value="Ausgeschieden">Ausgeschieden</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="nameSearch" class="form-control form-control-sm" placeholder="Name suchen...">
                            </div>
                            <div class="col-md-4 text-end">
                                <span id="filterCount" class="text-muted small"></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm" id="membersTable">
                                <thead>
                                    <tr>
                                        <th class="sortable" data-sort="string" data-col="0" role="button">Mitgliedsnr. <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="string" data-col="1" role="button">Name <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="string" data-col="2" role="button">Anrede <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="string" data-col="3" role="button">Status <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="string" data-col="4" role="button">E-Mail <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="date" data-col="5" role="button">Letzte Fahrt <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="date" data-col="6" role="button" title="Letzter Login im Mitglieder-Bereich">Letzter Login <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="number" data-col="7" role="button" title="Zugewiesene Boote (via Standard-Crew)">Boote <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="number" data-col="8" role="button" title="Tage seit letzter Nutzung des Boots">Ungenutzt (Tage) <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th class="sortable" data-sort="string" data-col="9" role="button">Lagerplatz <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                        <th title="GPS-Tracker erlaubt"><i class="bi bi-geo-alt-fill text-success"></i></th>
                                        <th title="DKV eFB Nutzer-ID"><i class="bi bi-person-badge"></i> eFB</th>
                                        <?php if (!$isRedaxoMode): ?>
                                            <th>Aktionen</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member):
                                        $isExpired = $member['valid_until'] && strtotime($member['valid_until']) < time();
                                        $displayStatus = $isExpired ? 'Ausgeschieden' : $member['status'];
                                        $boats = $memberBoats[$member['id']] ?? [];
                                    ?>
                                        <tr class="<?php echo $isExpired ? 'table-secondary' : ''; ?>"
                                            data-status="<?php echo e($displayStatus); ?>"
                                            data-name="<?php echo e(strtolower($member['first_name'] . ' ' . $member['last_name'])); ?>">
                                            <td><?php echo e($member['membership_no']); ?></td>
                                            <td>
                                                <?php echo e($member['first_name'] . ' ' . $member['last_name']); ?>
                                                <?php if ($isExpired): ?>
                                                    <span class="badge bg-danger">Ausgeschieden</span>
                                                <?php endif; ?>
                                                <?php if (!empty($member['tracker_allowed'])): ?>
                                                    <i class="bi bi-geo-alt-fill text-success" title="GPS-Tracker erlaubt"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo e($member['salutation']); ?></td>
                                            <td>
                                                <?php
                                                $statusColor = 'secondary';
                                                switch ($member['status']) {
                                                    case 'Aktiv': $statusColor = 'success'; break;
                                                    case 'Jugendmitglied': $statusColor = 'info'; break;
                                                    case 'Ehrenmitglied': $statusColor = 'warning'; break;
                                                    case 'Passiv': $statusColor = 'secondary'; break;
                                                    case 'Gastmitglied': $statusColor = 'primary'; break;
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $statusColor; ?>">
                                                    <?php echo e($member['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo e($member['email']); ?></td>
                                            <td>
                                                <?php if (!empty($member['last_trip_date'])): ?>
                                                    <?php echo date('d.m.Y', strtotime($member['last_trip_date'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">–</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($member['last_login_stats'])): ?>
                                                    <span title="<?php echo date('d.m.Y H:i', strtotime($member['last_login_stats'])); ?>">
                                                        <?php echo date('d.m.Y', strtotime($member['last_login_stats'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">–</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($boats)):
                                                    // Prüfe ob mindestens ein Boot keine Fahrten hat
                                                    $hasUnused = false;
                                                    foreach ($boats as $b) { if ($b['trips'] === 0) { $hasUnused = true; break; } }
                                                    $badgeClass = $hasUnused ? 'bg-warning text-dark' : 'bg-primary';
                                                ?>
                                                    <a href="#" class="badge <?php echo $badgeClass; ?> text-decoration-none show-boats"
                                                       data-boats="<?php echo e(json_encode($boats)); ?>"
                                                       data-member="<?php echo e($member['first_name'] . ' ' . $member['last_name']); ?>"
                                                       title="<?php echo $hasUnused ? 'Ungenutzte Boote vorhanden!' : 'Zugewiesene Boote'; ?>">
                                                        <?php echo (int)$member['owned_boat_count']; ?>
                                                        <?php if ($hasUnused): ?><i class="bi bi-exclamation-triangle-fill ms-1"></i><?php endif; ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">–</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($boats)):
                                                    // Maximale ungenutzte Tage über alle Boote des Mitglieds
                                                    $maxUnused = null;
                                                    foreach ($boats as $b) {
                                                        if ($b['trips'] === 0) {
                                                            $maxUnused = '∞'; break;
                                                        } elseif ($b['unused_days'] !== null) {
                                                            $maxUnused = $maxUnused === null ? $b['unused_days'] : max($maxUnused, $b['unused_days']);
                                                        }
                                                    }
                                                    if ($maxUnused === '∞'): ?>
                                                        <span class="badge bg-danger" title="Mindestens ein Boot wurde nie genutzt">nie genutzt</span>
                                                    <?php elseif ($maxUnused !== null):
                                                        $unusedColor = $maxUnused > 365 ? 'danger' : ($maxUnused > 180 ? 'warning text-dark' : 'success');
                                                    ?>
                                                        <span class="badge bg-<?php echo $unusedColor; ?>"><?php echo $maxUnused; ?> Tage</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">–</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">–</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($boats)):
                                                    $storages = array_filter(array_unique(array_column($boats, 'storage')));
                                                    echo !empty($storages) ? e(implode(', ', $storages)) : '<span class="text-muted">–</span>';
                                                else: ?>
                                                    <span class="text-muted">–</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle-tracker">
                                                    <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                                    <button type="submit"
                                                            class="btn btn-sm <?= $member['tracker_allowed'] ? 'btn-success' : 'btn-outline-secondary' ?>"
                                                            title="<?= $member['tracker_allowed'] ? 'Tracker-Nutzung aktiv – klicken zum Entziehen' : 'Tracker-Nutzung deaktiviert – klicken zum Freigeben' ?>">
                                                        <i class="bi bi-geo-alt<?= $member['tracker_allowed'] ? '-fill' : '' ?>"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <?php if (!empty($member['efb_id'])): ?>
                                                    <a href="#" class="badge bg-success text-decoration-none efb-id-edit"
                                                       data-id="<?= $member['id'] ?>"
                                                       data-efb-id="<?= (int)$member['efb_id'] ?>"
                                                       data-name="<?= e($member['first_name'] . ' ' . $member['last_name']) ?>"
                                                       title="eFB-ID bearbeiten"><?= (int)$member['efb_id'] ?></a>
                                                <?php else: ?>
                                                    <a href="#" class="text-muted efb-id-edit"
                                                       data-id="<?= $member['id'] ?>"
                                                       data-efb-id=""
                                                       data-name="<?= e($member['first_name'] . ' ' . $member['last_name']) ?>"
                                                       title="eFB-ID setzen">–</a>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (!$isRedaxoMode): ?>
                                            <td>
                                                <a href="?edit=<?php echo $member['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Wirklich löschen?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $member['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <?php endif; ?>
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

    <!-- Modal: eFB-ID setzen -->
    <div class="modal fade" id="efbIdModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h6 class="modal-title"><i class="bi bi-person-badge"></i> eFB-ID: <span id="efbIdModalName"></span></h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="set-efb-id">
                    <input type="hidden" name="id" id="efbIdModalMemberId">
                    <div class="modal-body">
                        <label class="form-label small">DKV eFB Nutzer-ID</label>
                        <input type="number" class="form-control" name="efb_id" id="efbIdModalInput"
                               placeholder="z.B. 12345" min="1">
                        <div class="form-text">Leer lassen zum Entfernen.</div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="submit" class="btn btn-success btn-sm">Speichern</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Boote eines Mitglieds -->
    <div class="modal fade" id="boatsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-water"></i> Boote von <span id="boatsModalMember"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="boatsModalWarning" class="alert alert-warning py-2 mb-3" style="display:none;">
                        <i class="bi bi-exclamation-triangle-fill"></i> <strong>Achtung:</strong> <span id="boatsModalWarningText"></span>
                    </div>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Boot</th>
                                <th>Typ</th>
                                <th>Fahrten</th>
                                <th>Letzte Fahrt</th>
                                <th>Ungenutzt</th>
                                <th>Lagerplatz</th>
                            </tr>
                        </thead>
                        <tbody id="boatsModalBody"></tbody>
                    </table>
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
        // Status-Filter + Namenssuche
        var statusFilter = document.getElementById('statusFilter');
        var nameSearch = document.getElementById('nameSearch');
        var filterCount = document.getElementById('filterCount');

        function applyFilters() {
            var status = statusFilter.value.toLowerCase();
            var search = nameSearch.value.toLowerCase();
            var rows = document.querySelectorAll('#membersTable tbody tr');
            var visible = 0;

            rows.forEach(function(row) {
                var rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
                var rowName = (row.getAttribute('data-name') || '').toLowerCase();
                var show = true;

                if (status && rowStatus !== status) show = false;
                if (search && rowName.indexOf(search) === -1) show = false;

                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            filterCount.textContent = visible + ' von ' + rows.length + ' angezeigt';
        }

        statusFilter.addEventListener('change', applyFilters);
        nameSearch.addEventListener('input', applyFilters);
        applyFilters(); // Initial filtern (default: Aktiv)

        // Tabelle sortieren
        var currentSortCol = -1;
        var currentSortDir = 'asc';

        function parseDateDE(str) {
            // "dd.mm.yyyy" oder "–" → Timestamp für Vergleich
            if (!str || str.trim() === '–' || str.trim() === '-') return 0;
            var parts = str.trim().split('.');
            if (parts.length === 3) return new Date(parts[2], parts[1] - 1, parts[0]).getTime();
            return 0;
        }

        document.querySelectorAll('#membersTable th.sortable').forEach(function(th) {
            th.addEventListener('click', function() {
                var col = parseInt(this.getAttribute('data-col'));
                var sortType = this.getAttribute('data-sort');
                var tbody = document.querySelector('#membersTable tbody');
                var rows = Array.from(tbody.querySelectorAll('tr'));

                // Sortierrichtung bestimmen
                if (currentSortCol === col) {
                    currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSortDir = 'asc';
                    currentSortCol = col;
                }

                rows.sort(function(a, b) {
                    var cellA = a.cells[col] ? a.cells[col].textContent.trim() : '';
                    var cellB = b.cells[col] ? b.cells[col].textContent.trim() : '';
                    var result = 0;

                    if (sortType === 'number') {
                        var numA = parseInt(cellA) || 0;
                        var numB = parseInt(cellB) || 0;
                        result = numA - numB;
                    } else if (sortType === 'date') {
                        result = parseDateDE(cellA) - parseDateDE(cellB);
                    } else {
                        result = cellA.localeCompare(cellB, 'de');
                    }

                    return currentSortDir === 'asc' ? result : -result;
                });

                // Zeilen neu einfügen
                rows.forEach(function(row) { tbody.appendChild(row); });

                // Icons aktualisieren
                document.querySelectorAll('#membersTable th.sortable i').forEach(function(icon) {
                    icon.className = 'bi bi-arrow-down-up text-muted small';
                });
                var activeIcon = this.querySelector('i');
                activeIcon.className = currentSortDir === 'asc'
                    ? 'bi bi-sort-up text-primary small'
                    : 'bi bi-sort-down text-primary small';
            });
        });

        // Boote-Modal
        var boatsModal = new bootstrap.Modal(document.getElementById('boatsModal'));
        document.querySelectorAll('.show-boats').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                var boats = JSON.parse(this.getAttribute('data-boats'));
                var member = this.getAttribute('data-member');
                document.getElementById('boatsModalMember').textContent = member;
                var tbody = document.getElementById('boatsModalBody');
                var warning = document.getElementById('boatsModalWarning');
                var warningText = document.getElementById('boatsModalWarningText');
                tbody.innerHTML = '';

                var unusedBoats = [];
                boats.forEach(function(b) {
                    var isUnused = b.trips === 0;
                    if (isUnused) unusedBoats.push(b.name);
                    var lastTrip = b.last_trip
                        ? b.last_trip
                        : '<span class="text-danger fw-bold">Nie genutzt</span>';
                    var unusedCol = '';
                    if (b.trips === 0) {
                        unusedCol = '<span class="badge bg-danger">nie</span>';
                    } else if (b.unused_days !== null) {
                        var cls = b.unused_days > 365 ? 'bg-danger' : (b.unused_days > 180 ? 'bg-warning text-dark' : 'bg-success');
                        unusedCol = '<span class="badge ' + cls + '">' + b.unused_days + ' Tage</span>';
                    } else {
                        unusedCol = '–';
                    }
                    var storageCol = b.storage ? b.storage : '<span class="text-muted">–</span>';
                    var tr = document.createElement('tr');
                    if (isUnused) tr.className = 'table-warning';
                    tr.innerHTML = '<td>' + b.name + '</td><td>' + b.type + '</td><td>' + b.trips + '</td><td>' + lastTrip + '</td><td>' + unusedCol + '</td><td>' + storageCol + '</td>';
                    tbody.appendChild(tr);
                });

                if (unusedBoats.length > 0) {
                    warningText.textContent = unusedBoats.length === 1
                        ? unusedBoats[0] + ' wurde noch nie genutzt!'
                        : unusedBoats.length + ' Boote wurden noch nie genutzt!';
                    warning.style.display = 'block';
                } else {
                    warning.style.display = 'none';
                }

                boatsModal.show();
            });
        });
    </script>
    <script>
        // eFB-ID Modal
        var efbIdModal = new bootstrap.Modal(document.getElementById('efbIdModal'));
        document.querySelectorAll('.efb-id-edit').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('efbIdModalName').textContent      = this.getAttribute('data-name');
                document.getElementById('efbIdModalMemberId').value        = this.getAttribute('data-id');
                document.getElementById('efbIdModalInput').value           = this.getAttribute('data-efb-id') || '';
                efbIdModal.show();
            });
        });

        // eFB Sync Button
        var btnEfbSync = document.getElementById('btnEfbSync');
        if (btnEfbSync) {
            btnEfbSync.addEventListener('click', function() {
                var status = document.getElementById('efbSyncStatus');
                btnEfbSync.disabled = true;
                btnEfbSync.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Läuft…';
                status.style.display = 'none';

                fetch('/admin/efb-sync-ajax.php', { method: 'POST' })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        status.innerHTML = d.success
                            ? '<div class="alert alert-success py-1 mb-0 small"><i class="bi bi-check-circle-fill me-1"></i>' + d.synced + ' Fahrten synchronisiert</div>'
                            : '<div class="alert alert-danger py-1 mb-0 small"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + (d.error || 'Fehler') + '</div>';
                        status.style.display = 'block';
                        btnEfbSync.disabled = false;
                        btnEfbSync.innerHTML = '<i class="bi bi-cloud-upload"></i> Jetzt syncen';
                    })
                    .catch(function() {
                        status.innerHTML = '<div class="alert alert-danger py-1 mb-0 small">Verbindungsfehler</div>';
                        status.style.display = 'block';
                        btnEfbSync.disabled = false;
                        btnEfbSync.innerHTML = '<i class="bi bi-cloud-upload"></i> Jetzt syncen';
                    });
            });
        }
    </script>
    <script>
        function runYcomSync() {
            var btn    = document.getElementById('syncNowBtn');
            var result = document.getElementById('syncResult');

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Läuft…';
            result.style.display = 'none';
            result.innerHTML = '';

            fetch('/admin/ycom-sync-ajax.php', { method: 'POST' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        var s = data.stats;
                        result.innerHTML =
                            '<div class="alert alert-success py-1 mb-0">' +
                            '<i class="bi bi-check-circle-fill me-1"></i><strong>Fertig!</strong><br>' +
                            '<span class="text-success">+' + s.created + ' neu</span> &nbsp;' +
                            '<span class="text-primary">&#8635; ' + s.updated + ' aktualisiert</span><br>' +
                            (s.deactivated > 0 ? '<span class="text-warning">&#8722; ' + s.deactivated + ' deaktiviert</span><br>' : '') +
                            (s.errors      > 0 ? '<span class="text-danger">&#9888; ' + s.errors + ' Fehler</span>'              : '') +
                            '</div>';
                    } else {
                        result.innerHTML =
                            '<div class="alert alert-danger py-1 mb-0">' +
                            '<i class="bi bi-exclamation-triangle-fill me-1"></i>' +
                            (data.message || 'Unbekannter Fehler') +
                            '</div>';
                    }
                    result.style.display = 'block';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Jetzt synchronisieren';
                    if (data.success) location.reload();
                })
                .catch(function() {
                    result.innerHTML =
                        '<div class="alert alert-danger py-1 mb-0">' +
                        '<i class="bi bi-exclamation-triangle-fill me-1"></i>Verbindungsfehler.' +
                        '</div>';
                    result.style.display = 'block';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Jetzt synchronisieren';
                });
        }
    </script>
</body>
</html>
