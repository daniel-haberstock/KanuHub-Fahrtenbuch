<?php
/**
 * Fahrtenbuch – RFID-Kartenverwaltung
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

$stmt = $db->query("
    SELECT c.id, c.uid, c.name AS card_name, c.created_at,
           m.id AS member_id, m.first_name, m.last_name, m.status,
           b.id AS boat_id, b.boat_name
    FROM   rfid_cards c
    JOIN   members m ON m.id = c.member_id
    LEFT JOIN boats b ON b.id = c.boat_id
    ORDER BY m.last_name ASC, m.first_name ASC, c.name ASC
");
$cards = $stmt->fetchAll();

$stmt = $db->query("
    SELECT id, first_name, last_name
    FROM   members
    WHERE  (valid_until IS NULL OR valid_until >= CURDATE())
    ORDER  BY last_name ASC, first_name ASC
");
$members = $stmt->fetchAll();

$stmt = $db->query("
    SELECT id, boat_name
    FROM   boats
    WHERE  (valid_until IS NULL OR valid_until >= CURDATE())
    ORDER  BY boat_name ASC
");
$boats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID-Karten – <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .sortable { cursor: pointer; user-select: none; white-space: nowrap; }
        .sortable:hover { background-color: rgba(0,0,0,.05); }
        #rfidScanInput { position: absolute; opacity: 0; width: 1px; height: 1px; }
        .scan-pulse {
            animation: pulse 1.4s ease-in-out infinite;
            font-size: 4rem;
            color: #0d6efd;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: .4; transform: scale(.88); }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-credit-card-2-front"></i> RFID-Kartenverwaltung
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </nav>

    <div class="container mt-4">

        <div id="alertBox"></div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Registrierte Karten <span class="badge bg-secondary"><?= count($cards) ?></span></h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCardModal">
                    <i class="bi bi-plus-circle"></i> Neue Karte hinzufügen
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($cards)): ?>
                    <p class="text-muted text-center py-3">
                        Noch keine Karten registriert. Klicke auf „Neue Karte hinzufügen".
                    </p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="rfidTable">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="string" data-col="0">Kartenname <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                <th class="sortable" data-sort="string" data-col="1">Mitglied <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                <th class="sortable" data-sort="string" data-col="2">Standard-Boot <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                <th>UID</th>
                                <th class="sortable" data-sort="date" data-col="4">Registriert <i class="bi bi-arrow-down-up text-muted small"></i></th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cards as $c): ?>
                            <tr id="row-<?= $c['id'] ?>">
                                <td><?= e($c['card_name']) ?></td>
                                <td>
                                    <?= e($c['last_name']) ?>, <?= e($c['first_name']) ?>
                                    <?php if ($c['status'] !== 'Aktiv'): ?>
                                        <span class="badge bg-warning text-dark">inaktiv</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $c['boat_name'] ? e($c['boat_name']) : '<span class="text-muted">–</span>' ?></td>
                                <td><code><?= e($c['uid']) ?></code></td>
                                <td><?= date('d.m.Y', strtotime($c['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning btn-edit"
                                        data-id="<?= $c['id'] ?>"
                                        data-name="<?= e($c['card_name']) ?>"
                                        data-boat="<?= (int)$c['boat_id'] ?>"
                                        data-bs-toggle="modal" data-bs-target="#editCardModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-delete"
                                        data-id="<?= $c['id'] ?>"
                                        data-name="<?= e($c['card_name']) ?>">
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

    <!-- ===== MODAL: Neue Karte ===== -->
    <div class="modal fade" id="addCardModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-credit-card-2-front"></i> Neue Karte hinzufügen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Phase 1: Scan wartet -->
                    <div id="scanWaiting" class="text-center py-4">
                        <div class="scan-pulse"><i class="bi bi-wifi"></i></div>
                        <p class="mt-3 text-muted">Karte jetzt vor den RFID-Leser halten&hellip;</p>
                        <input type="text" id="rfidScanInput" autocomplete="off" tabindex="-1">
                    </div>
                    <!-- Phase 2: Karte erkannt, Formular -->
                    <div id="scanForm" style="display:none">
                        <div class="alert alert-success py-2 mb-3">
                            <i class="bi bi-check-circle"></i> Karte erkannt: <code id="scanUidValue"></code>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Kartenname <span class="text-danger">*</span></label>
                            <input type="text" id="newCardName" class="form-control"
                                   placeholder="z.B. Max – Kajak 01" required>
                            <div class="form-text">Frei wählbare Bezeichnung, z.B. Name + Boot</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mitglied <span class="text-danger">*</span></label>
                            <select id="newCardMember" class="form-select" required>
                                <option value="">Mitglied wählen…</option>
                                <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= e($m['last_name']) ?>, <?= e($m['first_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-1">
                            <label class="form-label fw-semibold">Standard-Boot <span class="text-muted fw-normal">(optional)</span></label>
                            <select id="newCardBoat" class="form-select">
                                <option value="">Kein Standard-Boot</option>
                                <?php foreach ($boats as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= e($b['boat_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Wenn gesetzt, wird dieses Boot beim Quick-Start vorausgewählt.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" id="btnSaveCard" class="btn btn-primary" style="display:none">
                        <i class="bi bi-check-lg"></i> Karte speichern
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Karte bearbeiten ===== -->
    <div class="modal fade" id="editCardModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Karte bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editCardId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kartenname <span class="text-danger">*</span></label>
                        <input type="text" id="editCardName" class="form-control" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Standard-Boot <span class="text-muted fw-normal">(optional)</span></label>
                        <select id="editCardBoat" class="form-select">
                            <option value="">Kein Standard-Boot</option>
                            <?php foreach ($boats as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= e($b['boat_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" id="btnUpdateCard" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Speichern
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        'use strict';

        // ── Tabellen-Sortierung ──────────────────────────────────────────
        var table = document.getElementById('rfidTable');
        if (table) {
            var headers = table.querySelectorAll('th.sortable');
            var sortState = {};
            headers.forEach(function (th) {
                th.addEventListener('click', function () {
                    var col  = parseInt(th.dataset.col);
                    var type = th.dataset.sort;
                    var asc  = sortState[col] !== 'asc';
                    sortState = {};
                    sortState[col] = asc ? 'asc' : 'desc';
                    var tbody = table.querySelector('tbody');
                    var rows  = Array.from(tbody.querySelectorAll('tr'));
                    rows.sort(function (a, b) {
                        var at = (a.children[col] || {}).textContent.trim();
                        var bt = (b.children[col] || {}).textContent.trim();
                        if (type === 'date') {
                            var parse = function (s) { var m = s.match(/(\d{2})\.(\d{2})\.(\d{4})/); return m ? m[3]+m[2]+m[1] : ''; };
                            return asc ? parse(at).localeCompare(parse(bt)) : parse(bt).localeCompare(parse(at));
                        }
                        return asc ? at.localeCompare(bt, 'de') : bt.localeCompare(at, 'de');
                    });
                    rows.forEach(function (r) { tbody.appendChild(r); });
                    headers.forEach(function (h) {
                        var i = h.querySelector('i');
                        i.className = (h === th) ? (asc ? 'bi bi-sort-up text-primary small' : 'bi bi-sort-down text-primary small') : 'bi bi-arrow-down-up text-muted small';
                    });
                });
            });
        }

        // ── Alert-Helper ─────────────────────────────────────────────────
        function showAlert(msg, type) {
            var box = document.getElementById('alertBox');
            box.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show">' + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }

        // ── Add-Modal: RFID-Scan ──────────────────────────────────────────
        var scannedUid = '';
        var rfidBuffer = '';
        var scanInput  = document.getElementById('rfidScanInput');
        var addModal   = document.getElementById('addCardModal');

        addModal.addEventListener('shown.bs.modal', function () {
            resetScanModal();
            scanInput.focus();
        });

        addModal.addEventListener('hidden.bs.modal', function () {
            rfidBuffer = '';
            scannedUid = '';
        });

        addModal.addEventListener('click', function () {
            if (document.getElementById('scanWaiting').style.display !== 'none') {
                scanInput.focus();
            }
        });

        scanInput.addEventListener('input', function () {
            rfidBuffer = this.value;
        });

        scanInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                var val = rfidBuffer.trim();
                rfidBuffer = '';
                this.value = '';
                if (val.toUpperCase().startsWith('RFID:')) {
                    var uid = val.slice(5).toUpperCase();
                    if (uid) cardDetected(uid);
                }
            }
        });

        function resetScanModal() {
            scannedUid = '';
            rfidBuffer = '';
            scanInput.value = '';
            document.getElementById('scanWaiting').style.display = '';
            document.getElementById('scanForm').style.display    = 'none';
            document.getElementById('btnSaveCard').style.display = 'none';
            document.getElementById('newCardName').value   = '';
            document.getElementById('newCardMember').value = '';
            document.getElementById('newCardBoat').value   = '';
        }

        function cardDetected(uid) {
            scannedUid = uid;
            document.getElementById('scanUidValue').textContent = uid;
            document.getElementById('scanWaiting').style.display = 'none';
            document.getElementById('scanForm').style.display    = '';
            document.getElementById('btnSaveCard').style.display = '';
            document.getElementById('newCardName').focus();
        }

        document.getElementById('btnSaveCard').addEventListener('click', function () {
            var name     = document.getElementById('newCardName').value.trim();
            var memberId = document.getElementById('newCardMember').value;
            var boatId   = document.getElementById('newCardBoat').value;

            if (!name)     { document.getElementById('newCardName').focus();   return; }
            if (!memberId) { document.getElementById('newCardMember').focus(); return; }

            this.disabled = true;
            fetch('/api/rfid/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    uid:       scannedUid,
                    name:      name,
                    member_id: parseInt(memberId),
                    boat_id:   boatId ? parseInt(boatId) : null
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    bootstrap.Modal.getInstance(addModal).hide();
                    showAlert('<i class="bi bi-check-circle"></i> Karte <strong>' + name + '</strong> erfolgreich registriert. Seite wird aktualisiert…', 'success');
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    showAlert('<i class="bi bi-exclamation-triangle"></i> ' + (data.error || 'Fehler beim Speichern'), 'danger');
                    document.getElementById('btnSaveCard').disabled = false;
                }
            })
            .catch(function () {
                showAlert('Netzwerkfehler', 'danger');
                document.getElementById('btnSaveCard').disabled = false;
            });
        });

        // ── Edit-Modal ────────────────────────────────────────────────────
        document.querySelectorAll('.btn-edit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('editCardId').value   = btn.dataset.id;
                document.getElementById('editCardName').value = btn.dataset.name;
                document.getElementById('editCardBoat').value = btn.dataset.boat || '';
            });
        });

        document.getElementById('btnUpdateCard').addEventListener('click', function () {
            var id   = document.getElementById('editCardId').value;
            var name = document.getElementById('editCardName').value.trim();
            var boat = document.getElementById('editCardBoat').value;

            if (!name) { document.getElementById('editCardName').focus(); return; }

            this.disabled = true;
            fetch('/api/rfid/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ card_id: parseInt(id), name: name, boat_id: boat ? parseInt(boat) : null })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editCardModal')).hide();
                    showAlert('<i class="bi bi-check-circle"></i> Karte aktualisiert.', 'success');
                    setTimeout(function () { location.reload(); }, 900);
                } else {
                    showAlert(data.error || 'Fehler', 'danger');
                    document.getElementById('btnUpdateCard').disabled = false;
                }
            });
        });

        // ── Löschen ───────────────────────────────────────────────────────
        document.querySelectorAll('.btn-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id   = btn.dataset.id;
                var name = btn.dataset.name;
                if (!confirm('Karte „' + name + '" wirklich löschen?')) return;

                fetch('/api/rfid/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ card_id: parseInt(id) })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        var row = document.getElementById('row-' + id);
                        if (row) row.remove();
                        showAlert('<i class="bi bi-check-circle"></i> Karte gelöscht.', 'success');
                    } else {
                        showAlert(data.error || 'Fehler', 'danger');
                    }
                });
            });
        });

    })();
    </script>
</body>
</html>
