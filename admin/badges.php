<?php
/**
 * Fahrtenbuch – Admin: Badge-Verwaltung
 *
 * Funktionen:
 *  - Badge-Neuberechnung für alle oder einzelne Mitglieder
 *  - Übersicht: Wer hat welche Badges
 *  - Saisonal vs. dauerhaft pro Badge-Typ definieren
 *  - Badge-Reset für einzelne Mitglieder
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BadgeChecker.php';
require_once __DIR__ . '/../includes/BadgeRenderer.php';

$db = Database::getInstance()->getConnection();

$message = null;
$msgType = 'success';

// ── Tabelle für Badge-Konfiguration anlegen (idempotent) ──────────────────
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS badge_config (
            badge_key    VARCHAR(50) PRIMARY KEY,
            scope        ENUM('season','lifetime') NOT NULL DEFAULT 'season',
            enabled      TINYINT(1) NOT NULL DEFAULT 1,
            updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (\Throwable $e) {
    // Tabelle existiert bereits oder anderer Fehler
}

// ── POST-Aktionen ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Badges für ALLE Mitglieder neu berechnen
    if ($action === 'recalculate_all') {
        try {
            $memberStmt = $db->query("SELECT id, first_name, last_name FROM members WHERE valid_until IS NULL OR valid_until >= CURDATE() ORDER BY last_name, first_name");
            $members = $memberStmt->fetchAll();
            $totalNew = 0;
            $errors   = [];
            foreach ($members as $m) {
                try {
                    $newBadges = BadgeChecker::checkAndAward((int)$m['id'], $db);
                    $totalNew += count($newBadges);
                } catch (\Throwable $e) {
                    $errors[] = $m['first_name'] . ' ' . $m['last_name'] . ': ' . $e->getMessage();
                }
            }
            $message = count($members) . ' Mitglieder geprüft, ' . $totalNew . ' neue Badges vergeben.';
            if (!empty($errors)) {
                $message .= ' Fehler: ' . implode('; ', array_slice($errors, 0, 3));
                $msgType = 'warning';
            }
        } catch (\Throwable $e) {
            $message = 'Fehler: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }

    // Badges für ein einzelnes Mitglied neu berechnen
    if ($action === 'recalculate_member') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($memberId) {
            try {
                $newBadges = BadgeChecker::checkAndAward($memberId, $db);
                $message = count($newBadges) . ' neue Badge(s) vergeben.';
            } catch (\Throwable $e) {
                $message = 'Fehler: ' . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }

    // Badge eines Mitglieds zurücksetzen (löschen)
    if ($action === 'reset_member_badge') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $badgeKey = $_POST['badge_key'] ?? '';
        if ($memberId && $badgeKey) {
            try {
                $db->prepare("DELETE FROM member_badges WHERE member_id = ? AND badge_key = ?")
                   ->execute([$memberId, $badgeKey]);
                $message = 'Badge "' . htmlspecialchars($badgeKey) . '" wurde zurückgesetzt.';
            } catch (\Throwable $e) {
                $message = 'Fehler: ' . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }

    // Alle Badges eines Mitglieds zurücksetzen
    if ($action === 'reset_all_member_badges') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        if ($memberId) {
            try {
                $db->prepare("DELETE FROM member_badges WHERE member_id = ?")
                   ->execute([$memberId]);
                $message = 'Alle Badges des Mitglieds wurden zurückgesetzt.';
            } catch (\Throwable $e) {
                $message = 'Fehler: ' . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }

    // Badge-Konfiguration speichern (Scope + Aktiviert)
    if ($action === 'save_config') {
        try {
            $allKeys = BadgeRenderer::getAllKeys();
            $stmt = $db->prepare("
                INSERT INTO badge_config (badge_key, scope, enabled)
                VALUES (:key, :scope, :enabled)
                ON DUPLICATE KEY UPDATE scope = :scope2, enabled = :enabled2
            ");
            foreach ($allKeys as $key) {
                $scope   = ($_POST['scope_' . $key] ?? 'season') === 'lifetime' ? 'lifetime' : 'season';
                $enabled = isset($_POST['enabled_' . $key]) ? 1 : 0;
                $stmt->execute([
                    'key'      => $key,
                    'scope'    => $scope,
                    'enabled'  => $enabled,
                    'scope2'   => $scope,
                    'enabled2' => $enabled,
                ]);
            }
            $message = 'Badge-Konfiguration gespeichert.';
        } catch (\Throwable $e) {
            $message = 'Fehler: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }

    // Saison-Badges zurücksetzen (für alle Mitglieder, nur Saison-Badges löschen)
    if ($action === 'reset_season_badges') {
        try {
            // Saison-Badges aus badge_config laden
            $seasonKeys = $db->query("SELECT badge_key FROM badge_config WHERE scope = 'season' AND enabled = 1")
                             ->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($seasonKeys)) {
                $placeholders = implode(',', array_fill(0, count($seasonKeys), '?'));
                $stmt = $db->prepare("DELETE FROM member_badges WHERE badge_key IN ($placeholders)");
                $stmt->execute($seasonKeys);
                $message = count($seasonKeys) . ' Saison-Badge-Typen wurden für alle Mitglieder zurückgesetzt.';
            } else {
                $message = 'Keine Saison-Badges konfiguriert. Bitte zuerst Badge-Konfiguration speichern.';
                $msgType = 'warning';
            }
        } catch (\Throwable $e) {
            $message = 'Fehler: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// ── Daten laden ───────────────────────────────────────────────────────────
$allBadgeKeys = BadgeRenderer::getAllKeys();

// Badge-Konfiguration laden
$badgeConfig = [];
try {
    $configRows = $db->query("SELECT badge_key, scope, enabled FROM badge_config")->fetchAll();
    foreach ($configRows as $row) {
        $badgeConfig[$row['badge_key']] = $row;
    }
} catch (\Throwable $e) {}

// Mitglieder mit Badge-Anzahl
$membersWithBadges = [];
try {
    $stmt = $db->query("
        SELECT m.id, m.first_name, m.last_name, m.membership_no,
               COUNT(mb.id) AS badge_count
        FROM members m
        LEFT JOIN member_badges mb ON m.id = mb.member_id
        WHERE m.valid_until IS NULL OR m.valid_until >= CURDATE()
        GROUP BY m.id
        ORDER BY badge_count DESC, m.last_name, m.first_name
    ");
    $membersWithBadges = $stmt->fetchAll();
} catch (\Throwable $e) {}

// Mitglied-Details (Einzelansicht)
$viewMemberId = (int)($_GET['member_id'] ?? 0);
$memberBadges = [];
$memberInfo   = null;
if ($viewMemberId) {
    try {
        $memberInfo = $db->prepare("SELECT id, first_name, last_name, membership_no FROM members WHERE id = ?")->execute([$viewMemberId]) ? null : null;
        $stmt = $db->prepare("SELECT id, first_name, last_name, membership_no FROM members WHERE id = ?");
        $stmt->execute([$viewMemberId]);
        $memberInfo = $stmt->fetch();
        $stmt2 = $db->prepare("SELECT badge_key, earned_at, seen FROM member_badges WHERE member_id = ? ORDER BY earned_at DESC");
        $stmt2->execute([$viewMemberId]);
        $memberBadges = $stmt2->fetchAll();
    } catch (\Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badge-Verwaltung – <?= CLUB_NAME ?> Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; }
        .badge-scope-season  { background: #fff3cd; color: #664d03; }
        .badge-scope-lifetime { background: #d1e7dd; color: #0a3622; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark px-3 py-2 mb-4">
    <span class="navbar-brand mb-0 h6"><i class="bi bi-award-fill text-warning"></i> Badge-Verwaltung</span>
    <div class="d-flex gap-2">
        <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Admin</a>
    </div>
</nav>

<div class="container-lg">

<?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show">
        <i class="bi bi-<?= $msgType === 'success' ? 'check-circle' : ($msgType === 'danger' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

    <div class="row g-4">

        <!-- LINKE SPALTE: Aktionen + Badge-Konfiguration -->
        <div class="col-lg-5">

            <!-- Schnellaktionen -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-lightning-fill"></i> Schnellaktionen
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <form method="POST">
                        <input type="hidden" name="action" value="recalculate_all">
                        <button type="submit" class="btn btn-warning w-100"
                                onclick="return confirm('Badges für ALLE Mitglieder neu berechnen? Dies kann einen Moment dauern.')">
                            <i class="bi bi-arrow-clockwise"></i> Badges für alle Mitglieder neu berechnen
                        </button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_season_badges">
                        <button type="submit" class="btn btn-outline-danger w-100"
                                onclick="return confirm('ACHTUNG: Alle als \'Saison\' markierten Badges werden für alle Mitglieder gelöscht. Danach müssen sie neu berechnet werden. Fortfahren?')">
                            <i class="bi bi-trash"></i> Saison-Badges zurücksetzen (Saisonwechsel)
                        </button>
                    </form>
                    <div class="alert alert-info py-2 mb-0" style="font-size:0.82rem;">
                        <i class="bi bi-info-circle"></i>
                        <strong>Ablauf Saisonwechsel:</strong> Erst "Saison-Badges zurücksetzen", dann "Alle neu berechnen".
                        Saison-Badges werden nach Neuberechnung wieder vergeben wenn die Bedingungen erfüllt sind.
                    </div>
                </div>
            </div>

            <!-- Badge-Konfiguration (Saison vs. Dauerhaft) -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-sliders"></i> Badge-Konfiguration
                    <small class="text-muted ms-2">Saison = zurücksetzbar, Dauerhaft = bleibt immer</small>
                </div>
                <div class="card-body p-0">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_config">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Badge</th>
                                    <th class="text-center">Aktiv</th>
                                    <th>Gültigkeitsbereich</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $categories = ['Distanz', 'Fahrten', 'Jahreszeit', 'Boot-Treue', 'Spezial'];
                            $catBadges  = [
                                'Distanz'    => ['1km','10km','50km','100km','250km','500km','1000km'],
                                'Fahrten'    => ['erste-ausfahrt','trips_10','trips_50','trips_100'],
                                'Jahreszeit' => ['fruehling','sommer','herbst','winter','jahreszeiten'],
                                'Boot-Treue' => ['stammkunde','bootsliebe'],
                                'Spezial'    => ['fruehaufsteher','nachteule','allrounder','stammgast','veteran'],
                            ];
                            foreach ($catBadges as $cat => $keys):
                            ?>
                                <tr class="table-secondary">
                                    <td colspan="3" class="py-1 px-3">
                                        <small class="fw-bold text-muted text-uppercase"><?= $cat ?></small>
                                    </td>
                                </tr>
                                <?php foreach ($keys as $key):
                                    $cfg     = $badgeConfig[$key] ?? ['scope' => 'season', 'enabled' => 1];
                                    $label   = BadgeRenderer::getLabel($key);
                                ?>
                                <tr>
                                    <td class="py-2 ps-4">
                                        <span class="badge badge-scope-<?= $cfg['scope'] ?> me-1" style="font-size:0.7rem;">
                                            <?= $cfg['scope'] === 'lifetime' ? 'Dauerhaft' : 'Saison' ?>
                                        </span>
                                        <?= htmlspecialchars($label) ?>
                                    </td>
                                    <td class="text-center py-2">
                                        <input type="checkbox" name="enabled_<?= $key ?>"
                                               <?= $cfg['enabled'] ? 'checked' : '' ?>
                                               class="form-check-input">
                                    </td>
                                    <td class="py-2">
                                        <select name="scope_<?= $key ?>" class="form-select form-select-sm">
                                            <option value="season"   <?= $cfg['scope'] === 'season'   ? 'selected' : '' ?>>Saison</option>
                                            <option value="lifetime" <?= $cfg['scope'] === 'lifetime' ? 'selected' : '' ?>>Dauerhaft</option>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="p-3 border-top">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-floppy"></i> Konfiguration speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <!-- RECHTE SPALTE: Mitglieder-Übersicht -->
        <div class="col-lg-7">

            <?php if ($memberInfo): ?>
            <!-- Einzelansicht: Mitglied -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-person-fill"></i>
                        <?= htmlspecialchars($memberInfo['first_name'] . ' ' . $memberInfo['last_name']) ?>
                        <small class="text-muted">(<?= htmlspecialchars($memberInfo['membership_no'] ?? '') ?>)</small>
                    </span>
                    <div class="d-flex gap-2">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="recalculate_member">
                            <input type="hidden" name="member_id" value="<?= $viewMemberId ?>">
                            <button type="submit" class="btn btn-sm btn-warning">
                                <i class="bi bi-arrow-clockwise"></i> Neu berechnen
                            </button>
                        </form>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Alle Badges dieses Mitglieds löschen?')">
                            <input type="hidden" name="action" value="reset_all_member_badges">
                            <input type="hidden" name="member_id" value="<?= $viewMemberId ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i> Alle Badges löschen
                            </button>
                        </form>
                        <a href="/admin/badges.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x"></i>
                        </a>
                    </div>
                </div>
                <?php if (empty($memberBadges)): ?>
                    <div class="card-body text-muted text-center py-4">
                        <i class="bi bi-inbox fs-4 d-block mb-2"></i> Noch keine Badges verdient.
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Badge</th><th>Verdient am</th><th>Gesehen</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($memberBadges as $mb): ?>
                            <tr>
                                <td>
                                    <?php $scope = $badgeConfig[$mb['badge_key']]['scope'] ?? 'season'; ?>
                                    <span class="badge badge-scope-<?= $scope ?> me-1" style="font-size:0.68rem;">
                                        <?= $scope === 'lifetime' ? 'Dauerhaft' : 'Saison' ?>
                                    </span>
                                    <?= htmlspecialchars(BadgeRenderer::getLabel($mb['badge_key'])) ?>
                                    <small class="text-muted">(<?= htmlspecialchars($mb['badge_key']) ?>)</small>
                                </td>
                                <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($mb['earned_at']))) ?></td>
                                <td>
                                    <?php if ($mb['seen']): ?>
                                        <span class="badge bg-secondary">gesehen</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">neu</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="reset_member_badge">
                                        <input type="hidden" name="member_id" value="<?= $viewMemberId ?>">
                                        <input type="hidden" name="badge_key" value="<?= htmlspecialchars($mb['badge_key']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1"
                                                onclick="return confirm('Badge zurücksetzen?')"
                                                title="Badge zurücksetzen">
                                            <i class="bi bi-trash" style="font-size:0.75rem;"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Alle Mitglieder: Badge-Übersicht -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-people-fill"></i> Mitglieder-Übersicht
                    <small class="text-muted ms-2"><?= count($membersWithBadges) ?> Mitglieder</small>
                </div>
                <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Name</th>
                                <th class="text-center">Badges</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($membersWithBadges as $m): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                                    <?php if ($m['membership_no']): ?>
                                        <small class="text-muted">(<?= htmlspecialchars($m['membership_no']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($m['badge_count'] > 0): ?>
                                        <span class="badge bg-warning text-dark"><?= $m['badge_count'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">–</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="?member_id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2"
                                           title="Details">
                                            <i class="bi bi-eye" style="font-size:0.75rem;"></i>
                                        </a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="recalculate_member">
                                            <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning py-0 px-2"
                                                    title="Badges neu berechnen">
                                                <i class="bi bi-arrow-clockwise" style="font-size:0.75rem;"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
