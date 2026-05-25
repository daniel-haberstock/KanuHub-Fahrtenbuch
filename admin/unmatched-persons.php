<?php
/**
 * Fahrtenbuch - Nicht zugeordnete Personen verwalten
 *
 * Zeigt alle Personen aus dem EFA2-Import, die keinem Mitglied
 * zugeordnet werden konnten.
 *
 * Aktionen:
 *   - Ignorieren: Person wird ausgeblendet (z.B. einmalige Gäste)
 *   - Manuelles Matching: Person einem Mitglied zuordnen
 *     → Alle trip_crew-Einträge mit dem alten Freitext-Namen
 *       werden auf die member_id des gewählten Mitglieds umgeschrieben.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// Tabelle erstellen falls noch nicht vorhanden (Sicherheitsnetz)
$db->exec("
    CREATE TABLE IF NOT EXISTS import_unmatched (
        id INT AUTO_INCREMENT PRIMARY KEY,
        original_name VARCHAR(255) NOT NULL,
        trip_count INT NOT NULL DEFAULT 0,
        status ENUM('pending', 'ignored', 'matched') NOT NULL DEFAULT 'pending',
        matched_member_id INT NULL,
        matched_at DATETIME NULL,
        matched_by VARCHAR(100) NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_name (original_name),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Automatisch aus trip_crew befüllen falls Tabelle leer ist
$countCheck = $db->query("SELECT COUNT(*) FROM import_unmatched")->fetchColumn();
if ((int)$countCheck === 0) {
    $db->exec("
        INSERT INTO import_unmatched (original_name, trip_count)
        SELECT member_name, COUNT(*) as cnt
        FROM trip_crew
        WHERE member_id IS NULL
          AND member_name IS NOT NULL
          AND TRIM(member_name) != ''
        GROUP BY member_name
        ORDER BY cnt DESC
    ");
}

$message = '';
$messageType = '';

// ── Aktionen verarbeiten ─────────────────────────────────────────────────

// Aktion: Ignorieren
if (isset($_POST['action']) && $_POST['action'] === 'ignore') {
    $id = (int)($_POST['unmatched_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE import_unmatched SET status = 'ignored', notes = :notes WHERE id = :id");
        $stmt->execute(['notes' => $notes ?: null, 'id' => $id]);
        $message = 'Person wurde ignoriert (ausgeblendet).';
        $messageType = 'success';
    }
}

// Aktion: Wieder auf pending setzen (rückgängig)
if (isset($_POST['action']) && $_POST['action'] === 'revert') {
    $id = (int)($_POST['unmatched_id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE import_unmatched SET status = 'pending', matched_member_id = NULL, matched_at = NULL, matched_by = NULL WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $message = 'Status zurückgesetzt auf "Offen".';
        $messageType = 'info';
    }
}

// Aktion: Manuelles Matching
if (isset($_POST['action']) && $_POST['action'] === 'match') {
    $id = (int)($_POST['unmatched_id'] ?? 0);
    $memberId = (int)($_POST['member_id'] ?? 0);

    if ($id > 0 && $memberId > 0) {
        // 1. Originalnamen holen
        $stmt = $db->prepare("SELECT original_name FROM import_unmatched WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $unmatched = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($unmatched) {
            $originalName = $unmatched['original_name'];

            // 2. Mitglied prüfen
            $stmt = $db->prepare("SELECT id, first_name, last_name FROM members WHERE id = :id");
            $stmt->execute(['id' => $memberId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($member) {
                $db->beginTransaction();
                try {
                    // 3. Alle trip_crew-Einträge mit diesem Freitext-Namen
                    //    auf die member_id umschreiben
                    $updateCrew = $db->prepare("
                        UPDATE trip_crew
                        SET member_id = :member_id,
                            member_name = NULL
                        WHERE member_id IS NULL
                          AND member_name = :original_name
                    ");
                    $updateCrew->execute([
                        'member_id' => $memberId,
                        'original_name' => $originalName,
                    ]);
                    $updatedRows = $updateCrew->rowCount();

                    // 4. import_unmatched auf 'matched' setzen
                    $updateUnmatched = $db->prepare("
                        UPDATE import_unmatched
                        SET status = 'matched',
                            matched_member_id = :member_id,
                            matched_at = NOW(),
                            matched_by = :admin
                        WHERE id = :id
                    ");
                    $updateUnmatched->execute([
                        'member_id' => $memberId,
                        'admin' => $_SESSION['admin_username'] ?? 'admin',
                        'id' => $id,
                    ]);

                    $db->commit();

                    $memberDisplay = htmlspecialchars($member['first_name'] . ' ' . $member['last_name']);
                    $message = "\"" . htmlspecialchars($originalName) . "\" wurde \"{$memberDisplay}\" zugeordnet. {$updatedRows} Fahrten aktualisiert.";
                    $messageType = 'success';
                } catch (\Throwable $e) {
                    $db->rollBack();
                    $message = 'Fehler beim Matching: ' . htmlspecialchars($e->getMessage());
                    $messageType = 'danger';
                }
            } else {
                $message = 'Mitglied nicht gefunden.';
                $messageType = 'danger';
            }
        }
    }
}

// ── Daten laden ──────────────────────────────────────────────────────────

// Filter
$statusFilter = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'ignored', 'matched', 'all'];
if (!in_array($statusFilter, $validStatuses)) $statusFilter = 'pending';

// Ungematchte Personen laden (inkl. letzte Fahrt)
$where = $statusFilter === 'all' ? '' : "WHERE iu.status = :status";
$query = "
    SELECT iu.*,
           m.first_name AS matched_first_name,
           m.last_name AS matched_last_name,
           m.membership_no AS matched_membership_no,
           lt.last_trip_date
    FROM import_unmatched iu
    LEFT JOIN members m ON iu.matched_member_id = m.id
    LEFT JOIN (
        SELECT tc.member_name, MAX(t.start_date) as last_trip_date
        FROM trip_crew tc
        JOIN trips t ON tc.trip_id = t.id
        WHERE tc.member_id IS NULL AND tc.member_name IS NOT NULL
        GROUP BY tc.member_name
    ) lt ON iu.original_name = lt.member_name
    {$where}
    ORDER BY
        CASE iu.status
            WHEN 'pending' THEN 0
            WHEN 'ignored' THEN 1
            WHEN 'matched' THEN 2
        END,
        iu.trip_count DESC
";
$stmt = $db->prepare($query);
if ($statusFilter !== 'all') {
    $stmt->execute(['status' => $statusFilter]);
} else {
    $stmt->execute();
}
$unmatchedPersons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistik-Zähler
$countStmt = $db->query("
    SELECT status, COUNT(*) as cnt, SUM(trip_count) as trips
    FROM import_unmatched
    GROUP BY status
");
$statusCounts = [];
$totalTrips = 0;
while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    $statusCounts[$row['status']] = ['count' => (int)$row['cnt'], 'trips' => (int)$row['trips']];
    $totalTrips += (int)$row['trips'];
}

// Alle Mitglieder laden für Dropdown (nur aktive)
$membersStmt = $db->query("
    SELECT id, first_name, last_name, membership_no, status
    FROM members
    WHERE status IN ('Aktiv', 'Gastmitglied', 'Jugendmitglied', 'Ehrenmitglied')
    ORDER BY last_name, first_name
");
$allMembers = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nicht zugeordnete Personen – <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .status-badge { font-size: 0.8em; }
        .match-form { display: none; }
        .match-form.active { display: block; }
        .member-search-results { max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; }
        .member-search-results .list-group-item { cursor: pointer; font-size: 0.9em; }
        .member-search-results .list-group-item:hover { background: #e9ecef; }
        .stat-card { text-align: center; padding: 15px; border-radius: 8px; }
        .stat-card .num { font-size: 2em; font-weight: bold; }
        th.sortable { cursor: pointer; user-select: none; white-space: nowrap; }
        th.sortable:hover { background: #e9ecef; }
        th.sortable .sort-icon { opacity: 0.3; font-size: 0.8em; }
        th.sortable.asc .sort-icon, th.sortable.desc .sort-icon { opacity: 1; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-person-x"></i> Administration – Nicht zugeordnet
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-person-x"></i> Nicht zugeordnete Personen</h2>
                <p class="text-muted mb-0">Personen aus dem EFA2-Import, die keinem Mitglied zugeordnet werden konnten</p>
            </div>
            <a href="/admin/" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistik-Karten -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card bg-warning bg-opacity-10 border border-warning">
                    <div class="num text-warning"><?= $statusCounts['pending']['count'] ?? 0 ?></div>
                    <small class="text-muted">Offen</small>
                    <div class="text-muted small"><?= $statusCounts['pending']['trips'] ?? 0 ?> Fahrten</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-secondary bg-opacity-10 border border-secondary">
                    <div class="num text-secondary"><?= $statusCounts['ignored']['count'] ?? 0 ?></div>
                    <small class="text-muted">Ignoriert</small>
                    <div class="text-muted small"><?= $statusCounts['ignored']['trips'] ?? 0 ?> Fahrten</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-success bg-opacity-10 border border-success">
                    <div class="num text-success"><?= $statusCounts['matched']['count'] ?? 0 ?></div>
                    <small class="text-muted">Zugeordnet</small>
                    <div class="text-muted small"><?= $statusCounts['matched']['trips'] ?? 0 ?> Fahrten</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card bg-primary bg-opacity-10 border border-primary">
                    <div class="num text-primary"><?= array_sum(array_column($statusCounts, 'count')) ?></div>
                    <small class="text-muted">Gesamt</small>
                    <div class="text-muted small"><?= $totalTrips ?> Fahrten total</div>
                </div>
            </div>
        </div>

        <!-- Filter-Tabs -->
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?status=pending">
                    <i class="bi bi-clock"></i> Offen
                    <span class="badge bg-warning text-dark"><?= $statusCounts['pending']['count'] ?? 0 ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $statusFilter === 'ignored' ? 'active' : '' ?>" href="?status=ignored">
                    <i class="bi bi-eye-slash"></i> Ignoriert
                    <span class="badge bg-secondary"><?= $statusCounts['ignored']['count'] ?? 0 ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $statusFilter === 'matched' ? 'active' : '' ?>" href="?status=matched">
                    <i class="bi bi-check-circle"></i> Zugeordnet
                    <span class="badge bg-success"><?= $statusCounts['matched']['count'] ?? 0 ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $statusFilter === 'all' ? 'active' : '' ?>" href="?status=all">
                    Alle
                </a>
            </li>
        </ul>

        <?php if (empty($unmatchedPersons)): ?>
            <div class="alert alert-info">
                <?php if ($statusFilter === 'pending'): ?>
                    Keine offenen Personen vorhanden. Alle wurden zugeordnet oder ignoriert.
                <?php else: ?>
                    Keine Einträge mit Status "<?= htmlspecialchars($statusFilter) ?>" gefunden.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="unmatchedTable">
                    <thead class="table-light">
                        <tr>
                            <th class="sortable" data-col="0" data-type="string" onclick="sortTable(this)">Name (aus Import) <i class="bi bi-arrow-down-up sort-icon"></i></th>
                            <th class="sortable text-center" data-col="1" data-type="number" onclick="sortTable(this)">Fahrten <i class="bi bi-arrow-down-up sort-icon"></i></th>
                            <th class="sortable" data-col="2" data-type="string" onclick="sortTable(this)">Letzte Fahrt <i class="bi bi-arrow-down-up sort-icon"></i></th>
                            <th>Status</th>
                            <th>Zuordnung</th>
                            <th class="text-end">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unmatchedPersons as $person): ?>
                            <tr id="row-<?= $person['id'] ?>" class="data-row"
                                data-sort-0="<?= htmlspecialchars(strtolower($person['original_name'])) ?>"
                                data-sort-1="<?= (int)$person['trip_count'] ?>"
                                data-sort-2="<?= $person['last_trip_date'] ?? '0000-00-00' ?>">
                                <td>
                                    <strong><?= htmlspecialchars($person['original_name']) ?></strong>
                                    <?php if ($person['notes']): ?>
                                        <br><small class="text-muted"><i class="bi bi-chat-text"></i> <?= htmlspecialchars($person['notes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary rounded-pill"><?= $person['trip_count'] ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($person['last_trip_date'])): ?>
                                        <?= date('d.m.Y', strtotime($person['last_trip_date'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">–</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($person['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark status-badge">Offen</span>
                                    <?php elseif ($person['status'] === 'ignored'): ?>
                                        <span class="badge bg-secondary status-badge">Ignoriert</span>
                                    <?php elseif ($person['status'] === 'matched'): ?>
                                        <span class="badge bg-success status-badge">Zugeordnet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($person['status'] === 'matched' && $person['matched_first_name']): ?>
                                        <i class="bi bi-arrow-right text-success"></i>
                                        <strong><?= htmlspecialchars($person['matched_first_name'] . ' ' . $person['matched_last_name']) ?></strong>
                                        <?php if ($person['matched_membership_no']): ?>
                                            <small class="text-muted">(#<?= htmlspecialchars($person['matched_membership_no']) ?>)</small>
                                        <?php endif; ?>
                                        <?php if ($person['matched_at']): ?>
                                            <br><small class="text-muted">am <?= date('d.m.Y H:i', strtotime($person['matched_at'])) ?> von <?= htmlspecialchars($person['matched_by'] ?? '-') ?></small>
                                        <?php endif; ?>
                                    <?php elseif ($person['status'] === 'ignored'): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <span class="text-muted">Nicht zugeordnet</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($person['status'] === 'pending'): ?>
                                        <!-- Zuordnen-Button -->
                                        <button class="btn btn-sm btn-outline-success"
                                                onclick="showMatchForm(<?= $person['id'] ?>, '<?= htmlspecialchars(addslashes($person['original_name']), ENT_QUOTES) ?>')">
                                            <i class="bi bi-person-check"></i> Zuordnen
                                        </button>
                                        <!-- Ignorieren-Button -->
                                        <button class="btn btn-sm btn-outline-secondary"
                                                onclick="showIgnoreForm(<?= $person['id'] ?>)">
                                            <i class="bi bi-eye-slash"></i> Ignorieren
                                        </button>
                                    <?php else: ?>
                                        <!-- Rückgängig -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="revert">
                                            <input type="hidden" name="unmatched_id" value="<?= $person['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning"
                                                    onclick="return confirm('Status zurücksetzen auf Offen?')">
                                                <i class="bi bi-arrow-counterclockwise"></i> Rückgängig
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <?php if ($person['status'] === 'pending'): ?>
                            <!-- Inline Match-Formular -->
                            <tr id="match-row-<?= $person['id'] ?>" style="display:none;">
                                <td colspan="6" class="bg-light">
                                    <div class="p-2">
                                        <h6><i class="bi bi-person-check"></i> "<?= htmlspecialchars($person['original_name']) ?>" zuordnen</h6>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control form-control-sm"
                                                       id="search-<?= $person['id'] ?>"
                                                       placeholder="Mitglied suchen (Name eingeben)..."
                                                       onkeyup="filterMembers(<?= $person['id'] ?>, this.value)">
                                                <div class="member-search-results mt-1" id="results-<?= $person['id'] ?>">
                                                    <div class="list-group list-group-flush">
                                                        <?php foreach ($allMembers as $m): ?>
                                                            <div class="list-group-item list-group-item-action member-option"
                                                                 data-name="<?= htmlspecialchars(strtolower($m['last_name'] . ' ' . $m['first_name'])) ?>"
                                                                 onclick="selectMember(<?= $person['id'] ?>, <?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['first_name'] . ' ' . $m['last_name']), ENT_QUOTES) ?>')">
                                                                <strong><?= htmlspecialchars($m['last_name']) ?></strong>, <?= htmlspecialchars($m['first_name']) ?>
                                                                <?php if ($m['membership_no']): ?>
                                                                    <small class="text-muted">(#<?= htmlspecialchars($m['membership_no']) ?>)</small>
                                                                <?php endif; ?>
                                                                <small class="text-muted float-end"><?= htmlspecialchars($m['status']) ?></small>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <form method="POST" id="match-form-<?= $person['id'] ?>">
                                                    <input type="hidden" name="action" value="match">
                                                    <input type="hidden" name="unmatched_id" value="<?= $person['id'] ?>">
                                                    <input type="hidden" name="member_id" id="member-id-<?= $person['id'] ?>" value="">
                                                    <div class="alert alert-info py-2 small" id="match-preview-<?= $person['id'] ?>">
                                                        Bitte ein Mitglied aus der Liste auswählen.
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button type="submit" class="btn btn-sm btn-success" id="match-btn-<?= $person['id'] ?>" disabled>
                                                            <i class="bi bi-check-lg"></i> Zuordnen & Fahrten übertragen
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                                onclick="hideMatchForm(<?= $person['id'] ?>)">
                                                            Abbrechen
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- Inline Ignorieren-Formular -->
                            <tr id="ignore-row-<?= $person['id'] ?>" style="display:none;">
                                <td colspan="6" class="bg-light">
                                    <div class="p-2">
                                        <h6><i class="bi bi-eye-slash"></i> "<?= htmlspecialchars($person['original_name']) ?>" ignorieren</h6>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="ignore">
                                            <input type="hidden" name="unmatched_id" value="<?= $person['id'] ?>">
                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-8">
                                                    <label class="form-label small">Grund (optional)</label>
                                                    <input type="text" class="form-control form-control-sm" name="notes"
                                                           placeholder="z.B. Einmaliger Gast, nicht mehr im Verein...">
                                                </div>
                                                <div class="col-md-4 d-flex gap-2">
                                                    <button type="submit" class="btn btn-sm btn-secondary">
                                                        <i class="bi bi-eye-slash"></i> Ignorieren
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                                            onclick="hideIgnoreForm(<?= $person['id'] ?>)">
                                                        Abbrechen
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showMatchForm(id, name) {
            hideAllForms();
            document.getElementById('match-row-' + id).style.display = '';
            const searchInput = document.getElementById('search-' + id);
            if (searchInput) {
                // Versuche den Namen aus dem Import als Suchbegriff vorzubelegen
                const nameParts = name.split(',');
                if (nameParts.length > 1) {
                    searchInput.value = nameParts[0].trim();
                } else {
                    searchInput.value = name.split(' ').pop(); // Nachname als Suchbegriff
                }
                filterMembers(id, searchInput.value);
                searchInput.focus();
            }
        }

        function hideMatchForm(id) {
            document.getElementById('match-row-' + id).style.display = 'none';
        }

        function showIgnoreForm(id) {
            hideAllForms();
            document.getElementById('ignore-row-' + id).style.display = '';
        }

        function hideIgnoreForm(id) {
            document.getElementById('ignore-row-' + id).style.display = 'none';
        }

        function hideAllForms() {
            document.querySelectorAll('[id^="match-row-"], [id^="ignore-row-"]').forEach(function(el) {
                el.style.display = 'none';
            });
        }

        function filterMembers(id, query) {
            const container = document.getElementById('results-' + id);
            const items = container.querySelectorAll('.member-option');
            const q = query.toLowerCase().trim();

            items.forEach(function(item) {
                const name = item.getAttribute('data-name') || '';
                const text = item.textContent.toLowerCase();
                if (q === '' || name.includes(q) || text.includes(q)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // ── Tabellen-Sortierung ──────────────────────────────────────────
        function sortTable(th) {
            var table = document.getElementById('unmatchedTable');
            if (!table) return;
            var tbody = table.querySelector('tbody');
            var col = parseInt(th.getAttribute('data-col'));
            var type = th.getAttribute('data-type') || 'string';

            // Richtung umschalten
            var isAsc = th.classList.contains('asc');
            table.querySelectorAll('th.sortable').forEach(function(h) {
                h.classList.remove('asc', 'desc');
            });
            th.classList.add(isAsc ? 'desc' : 'asc');
            var dir = isAsc ? -1 : 1;

            // Nur Datenzeilen sammeln (mit ihren Folge-Formularen)
            var groups = [];
            var rows = Array.from(tbody.querySelectorAll('tr'));
            var i = 0;
            while (i < rows.length) {
                var row = rows[i];
                if (row.classList.contains('data-row')) {
                    var group = [row];
                    // Folgezeilen (match-row, ignore-row) sammeln
                    var j = i + 1;
                    while (j < rows.length && !rows[j].classList.contains('data-row')) {
                        group.push(rows[j]);
                        j++;
                    }
                    groups.push(group);
                    i = j;
                } else {
                    i++;
                }
            }

            groups.sort(function(a, b) {
                var aVal = a[0].getAttribute('data-sort-' + col) || '';
                var bVal = b[0].getAttribute('data-sort-' + col) || '';
                if (type === 'number') {
                    return (parseFloat(aVal) - parseFloat(bVal)) * dir;
                }
                return aVal.localeCompare(bVal, 'de') * dir;
            });

            // DOM neu anordnen
            groups.forEach(function(group) {
                group.forEach(function(row) {
                    tbody.appendChild(row);
                });
            });
        }

        function selectMember(unmatchedId, memberId, memberName) {
            document.getElementById('member-id-' + unmatchedId).value = memberId;
            document.getElementById('match-btn-' + unmatchedId).disabled = false;
            document.getElementById('match-preview-' + unmatchedId).innerHTML =
                '<i class="bi bi-arrow-right text-success"></i> Wird zugeordnet zu: <strong>' + memberName + '</strong>' +
                '<br><small class="text-muted">Alle Fahrten mit dem alten Namen werden auf dieses Mitglied umgeschrieben.</small>';
            document.getElementById('match-preview-' + unmatchedId).className = 'alert alert-success py-2 small';
        }
    </script>
</body>
</html>
