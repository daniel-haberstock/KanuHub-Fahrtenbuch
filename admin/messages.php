<?php
/**
 * Fahrtenbuch - Systemmeldungen verwalten
 * Bearbeitbare Hinweise/Warnungen für Fahrtbeginn, Rückkehr, etc.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

$successMessage = '';
$errorMessage = '';

// Tabelle anlegen falls nicht vorhanden
$db->exec("
    CREATE TABLE IF NOT EXISTS system_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        msg_key VARCHAR(100) NOT NULL UNIQUE,
        msg_label VARCHAR(255) NOT NULL,
        msg_text TEXT NOT NULL,
        msg_type ENUM('danger','warning','info','secondary') NOT NULL DEFAULT 'info',
        msg_icon VARCHAR(100) NOT NULL DEFAULT 'bi-info-circle',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Standard-Meldungen einfügen, falls leer
$count = $db->query("SELECT COUNT(*) FROM system_messages")->fetchColumn();
if ($count == 0) {
    $db->exec("
        INSERT INTO system_messages (msg_key, msg_label, msg_text, msg_type, msg_icon, sort_order) VALUES
        ('club_boat_external', 'Vereinsboot: Externe/Passive Fahrer', 'Die Benutzung von Vereinsbooten ist für Externe und passive Mitglieder nur möglich, wenn ein aktives Mitglied dabei ist. Das aktive Mitglied ist für Schäden am ausgeliehenen Boot verantwortlich.', 'danger', 'bi-exclamation-triangle', 10),
        ('passive_member', 'Passive Mitglieder Hinweis', 'Passive Mitglieder werden wie Externe behandelt. Sie dürfen die Boote nur mit einem aktiven Mitglied verwenden.', 'warning', 'bi-info-circle', 20),
        ('external_guest', 'Externe Gäste Hinweis', 'Sie haben einen oder mehrere Fahrer eingegeben, die nicht in der Mitgliederliste sind. Diese werden als Gäste erfasst.', 'info', 'bi-info-circle', 30),
        ('club_boat_return_same_day', 'Vereinsboot: Tagesrückkehr', 'Bitte beachte, dass Vereinsboote am Ende des Tages wieder im Bootshaus sein müssen.', 'warning', 'bi-exclamation-triangle', 40),
        ('trailer_warning', 'Anhänger-Warnung', 'Für Schaden am Anhänger ist der Fahrer verantwortlich. Ausleih nur nach voriger Absprache mit dem Vorstand.', 'danger', 'bi-exclamation-triangle-fill', 50),
        ('trip_start_success', 'Fahrt begonnen – Hinweis', 'Beim Zurückkommen bitte gleich das Boot ordnungsgemäß säubern und verräumen. Sei ein gutes Vorbild für andere.', 'info', 'bi-info-circle', 60),
        ('all_boats_back', 'Alle Boote zurück', 'Alle Boote sind zurück! Bitte Bootshaus abschließen.', 'info', 'bi-house-fill', 70),
        ('trip_end_cleanup', 'Fahrt beendet – Aufräumen', 'Bitte Boot sauber machen, trocknen und ordentlich verräumen.', 'secondary', 'bi-droplet', 80),
        ('reservation_external', 'Reservierung: Externe Warnung', 'Externe dürfen die Boote nur mit einem aktiven Mitglied verwenden. Das aktive Mitglied haftet für Schäden.', 'info', 'bi-info-circle', 90),
        ('weather_disclaimer', 'Wetter-Haftungshinweis', 'Wetterdaten sind nur ein Anhaltspunkt – nicht allein darauf verlassen!', 'warning', 'bi-info-circle', 100),
        ('damage_report_info', 'Schadensmeldung Hinweis', 'Bitte geben Sie Ihren Namen für Rückfragen an. Dies dient nicht der Schuldzuweisung, sondern hilft uns, den Schaden korrekt zu erfassen und bei Bedarf Rückfragen zu stellen.', 'info', 'bi-info-circle', 110),
        ('trailer_modal_warning', 'Anhänger-Modal Warnung', 'Für Schäden am Anhänger trägt der Ausleiher Verantwortung!', 'danger', 'bi-exclamation-triangle-fill', 120)
    ");
}

// POST: Meldung aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        try {
            $stmt = $db->prepare("
                UPDATE system_messages
                SET msg_text = :text, msg_type = :type, msg_icon = :icon, is_active = :active
                WHERE id = :id
            ");
            $stmt->execute([
                'text' => $_POST['msg_text'],
                'type' => $_POST['msg_type'],
                'icon' => $_POST['msg_icon'],
                'active' => isset($_POST['is_active']) ? 1 : 0,
                'id' => $_POST['id']
            ]);
            $successMessage = 'Meldung erfolgreich aktualisiert!';
        } catch (Exception $e) {
            $errorMessage = 'Fehler: ' . $e->getMessage();
        }
    }

    if ($action === 'update_all') {
        try {
            $ids = $_POST['msg_id'] ?? [];
            $texts = $_POST['msg_text'] ?? [];
            $types = $_POST['msg_type'] ?? [];
            $icons = $_POST['msg_icon'] ?? [];
            $actives = $_POST['is_active'] ?? [];

            $stmt = $db->prepare("
                UPDATE system_messages
                SET msg_text = :text, msg_type = :type, msg_icon = :icon, is_active = :active
                WHERE id = :id
            ");

            foreach ($ids as $i => $id) {
                $stmt->execute([
                    'text' => $texts[$i] ?? '',
                    'type' => $types[$i] ?? 'info',
                    'icon' => $icons[$i] ?? 'bi-info-circle',
                    'active' => in_array($id, $actives) ? 1 : 0,
                    'id' => $id
                ]);
            }
            $successMessage = 'Alle Meldungen erfolgreich gespeichert!';
        } catch (Exception $e) {
            $errorMessage = 'Fehler: ' . $e->getMessage();
        }
    }
}

// Alle Meldungen laden
$stmt = $db->query("SELECT * FROM system_messages ORDER BY sort_order ASC");
$messages = $stmt->fetchAll();

$typeLabels = [
    'danger' => 'Rot (Gefahr)',
    'warning' => 'Gelb (Warnung)',
    'info' => 'Blau (Info)',
    'secondary' => 'Grau (Neutral)'
];

$iconOptions = [
    'bi-info-circle' => 'ℹ Info-Kreis',
    'bi-exclamation-triangle' => '⚠ Warnung',
    'bi-exclamation-triangle-fill' => '⚠ Warnung (gefüllt)',
    'bi-house-fill' => '🏠 Haus',
    'bi-droplet' => '💧 Tropfen',
    'bi-check-circle' => '✓ Häkchen',
    'bi-shield-exclamation' => '🛡 Schild',
    'bi-megaphone' => '📢 Megafon'
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Systemmeldungen - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .msg-preview {
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .msg-card { border-left: 4px solid; margin-bottom: 1rem; }
        .msg-card.border-danger { border-left-color: #dc3545 !important; }
        .msg-card.border-warning { border-left-color: #ffc107 !important; }
        .msg-card.border-info { border-left-color: #0dcaf0 !important; }
        .msg-card.border-secondary { border-left-color: #6c757d !important; }
        .msg-inactive { opacity: 0.5; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> <?= CLUB_NAME ?> Fahrtenbuch - Administration
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="bi bi-chat-square-text"></i> Systemmeldungen</h2>
        <p class="text-muted">Bearbeiten Sie die Hinweise und Warnungen, die im Fahrtenbuch angezeigt werden.</p>

        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($successMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="post" id="messagesForm">
            <input type="hidden" name="action" value="update_all">

            <?php foreach ($messages as $i => $msg): ?>
                <div class="card msg-card border-<?= e($msg['msg_type']) ?> <?= $msg['is_active'] ? '' : 'msg-inactive' ?>">
                    <div class="card-body">
                        <div class="row align-items-start">
                            <div class="col-md-8">
                                <input type="hidden" name="msg_id[<?= $i ?>]" value="<?= $msg['id'] ?>">

                                <h6 class="card-title mb-1">
                                    <i class="bi <?= e($msg['msg_icon']) ?>"></i>
                                    <?= e($msg['msg_label']) ?>
                                    <small class="text-muted">(<?= e($msg['msg_key']) ?>)</small>
                                </h6>

                                <textarea class="form-control mt-2" name="msg_text[<?= $i ?>]"
                                          rows="2" placeholder="Meldungstext..."><?= e($msg['msg_text']) ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small">Typ</label>
                                        <select class="form-select form-select-sm" name="msg_type[<?= $i ?>]">
                                            <?php foreach ($typeLabels as $val => $label): ?>
                                                <option value="<?= $val ?>" <?= $msg['msg_type'] === $val ? 'selected' : '' ?>>
                                                    <?= $label ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">Icon</label>
                                        <select class="form-select form-select-sm" name="msg_icon[<?= $i ?>]">
                                            <?php foreach ($iconOptions as $val => $label): ?>
                                                <option value="<?= $val ?>" <?= $msg['msg_icon'] === $val ? 'selected' : '' ?>>
                                                    <?= $label ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                   name="is_active[]" value="<?= $msg['id'] ?>"
                                                   id="active_<?= $msg['id'] ?>"
                                                   <?= $msg['is_active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="active_<?= $msg['id'] ?>">Aktiv</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vorschau -->
                                <div class="msg-preview alert alert-<?= e($msg['msg_type']) ?> py-1 px-2 mb-0 mt-2" style="font-size:0.82rem;">
                                    <i class="bi <?= e($msg['msg_icon']) ?>"></i>
                                    <span class="preview-text"><?= e(mb_substr($msg['msg_text'], 0, 80)) ?><?= mb_strlen($msg['msg_text']) > 80 ? '…' : '' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="d-flex justify-content-end mb-5">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg"></i> Alle Meldungen speichern
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
