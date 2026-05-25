<?php
/**
 * YCom Validierung - Überprüft Konsistenz zwischen YCom und Fahrtenbuch
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// Fahrtenbuch-Mitglieder laden
$fbQuery = "SELECT id, membership_no, first_name, last_name, email FROM members
            WHERE (valid_until IS NULL OR valid_until >= CURDATE())
            ORDER BY membership_no";
$fbStmt = $db->prepare($fbQuery);
$fbStmt->execute();
$fbMembers = $fbStmt->fetchAll(PDO::FETCH_ASSOC);

// YCom-Mitglieder laden
try {
    $ycomDb = new PDO(
        'mysql:host=' . YCOM_DB_HOST . ';dbname=' . YCOM_DB_NAME . ';charset=utf8mb4',
        YCOM_DB_USER,
        YCOM_DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $ycomQuery = "
        SELECT
            u.id,
            u.mitglied_nr as membership_no,
            u.firstname as first_name,
            u.name as last_name,
            u.email
        FROM rex_ycom_user u
        WHERE u.status = 1
        AND u.mitglied_nr IS NOT NULL
        AND u.mitglied_nr != ''
        ORDER BY u.mitglied_nr
    ";

    $ycomStmt = $ycomDb->prepare($ycomQuery);
    $ycomStmt->execute();
    $ycomMembers = $ycomStmt->fetchAll();
} catch (Exception $e) {
    $ycomMembers = [];
    $ycomError = $e->getMessage();
}

// Validierung durchführen
$warnings = [];
$errors = [];

// 1. Mitglieder in Fahrtenbuch, aber nicht in YCom
foreach ($fbMembers as $fbMember) {
    $foundInYCom = false;
    foreach ($ycomMembers as $ycomMember) {
        if ($fbMember['membership_no'] === $ycomMember['membership_no']) {
            $foundInYCom = true;

            // 2. Mitgliedsnummer identisch, aber Name unterschiedlich
            if ($fbMember['first_name'] !== $ycomMember['first_name'] ||
                $fbMember['last_name'] !== $ycomMember['last_name']) {
                $warnings[] = [
                    'type' => 'name_mismatch',
                    'membership_no' => $fbMember['membership_no'],
                    'fb_name' => $fbMember['first_name'] . ' ' . $fbMember['last_name'],
                    'ycom_name' => $ycomMember['first_name'] . ' ' . $ycomMember['last_name']
                ];
            }
            break;
        }
    }

    if (!$foundInYCom && !empty($fbMember['membership_no'])) {
        $errors[] = [
            'type' => 'not_in_ycom',
            'membership_no' => $fbMember['membership_no'],
            'name' => $fbMember['first_name'] . ' ' . $fbMember['last_name']
        ];
    }
}

// 3. Mitglieder in YCom, aber nicht in Fahrtenbuch (Info)
$infoMissing = [];
foreach ($ycomMembers as $ycomMember) {
    $foundInFB = false;
    foreach ($fbMembers as $fbMember) {
        if ($fbMember['membership_no'] === $ycomMember['membership_no']) {
            $foundInFB = true;
            break;
        }
    }

    if (!$foundInFB) {
        $infoMissing[] = [
            'membership_no' => $ycomMember['membership_no'],
            'name' => $ycomMember['first_name'] . ' ' . $ycomMember['last_name']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YCom Validierung - <?= CLUB_NAME ?> Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> Administration - YCom Validierung
            </a>
            <a href="/admin/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>YCom Validierung</h2>
        <p class="text-muted">Überprüfung der Konsistenz zwischen YCom und Fahrtenbuch</p>

        <?php if (isset($ycomError)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i>
                <strong>YCom-Verbindungsfehler!</strong><br>
                <?php echo e($ycomError); ?>
            </div>
        <?php elseif (empty($ycomMembers)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Keine YCom-Mitglieder gefunden!</strong><br>
                Möglicherweise ist die YCom-Datenbank leer oder die Abfrage liefert keine Ergebnisse.
            </div>
        <?php endif; ?>

        <!-- Zusammenfassung -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-danger"><?php echo count($errors); ?></h3>
                        <p class="mb-0">Fehler</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?php echo count($warnings); ?></h3>
                        <p class="mb-0">Warnungen</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?php echo count($infoMissing); ?></h3>
                        <p class="mb-0">Fehlend im Fahrtenbuch</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fehler: In Fahrtenbuch, aber nicht in YCom -->
        <?php if (!empty($errors)): ?>
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-circle"></i> Nur im Fahrtenbuch (nicht in YCom)</h5>
            </div>
            <div class="card-body">
                <p class="text-danger">
                    <strong>Diese Mitglieder existieren im Fahrtenbuch, aber nicht in YCom.</strong><br>
                    Möglicherweise sind die Mitgliedsnummern falsch oder die Mitglieder wurden in YCom gelöscht.
                </p>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mitgliedsnr.</th>
                            <th>Name (Fahrtenbuch)</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errors as $error): ?>
                        <tr>
                            <td><?php echo e($error['membership_no']); ?></td>
                            <td><?php echo e($error['name']); ?></td>
                            <td>
                                <a href="/admin/members.php?edit=<?php echo $error['membership_no']; ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil"></i> Korrigieren
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Warnungen: Unterschiedliche Namen -->
        <?php if (!empty($warnings)): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Namensunterschiede</h5>
            </div>
            <div class="card-body">
                <p class="text-warning">
                    <strong>Bei diesen Mitgliedern stimmt die Mitgliedsnummer, aber Vor- oder Nachname unterscheiden sich.</strong><br>
                    Bitte überprüfen und ggf. im Fahrtenbuch korrigieren.
                </p>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mitgliedsnr.</th>
                            <th>Name (YCom)</th>
                            <th>Name (Fahrtenbuch)</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warnings as $warning): ?>
                        <tr>
                            <td><?php echo e($warning['membership_no']); ?></td>
                            <td><strong><?php echo e($warning['ycom_name']); ?></strong></td>
                            <td><?php echo e($warning['fb_name']); ?></td>
                            <td>
                                <a href="/admin/ycom-sync.php?debug=1" target="_blank" class="btn btn-sm btn-info">
                                    <i class="bi bi-arrow-repeat"></i> Sync
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info: In YCom, aber nicht im Fahrtenbuch -->
        <?php if (!empty($infoMissing)): ?>
        <div class="card mb-4 border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Nur in YCom (noch nicht importiert)</h5>
            </div>
            <div class="card-body">
                <p class="text-info">
                    <strong>Diese Mitglieder existieren in YCom, aber noch nicht im Fahrtenbuch.</strong><br>
                    Führen Sie eine Synchronisation aus, um sie zu importieren.
                </p>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mitgliedsnr.</th>
                            <th>Name (YCom)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($infoMissing as $info): ?>
                        <tr>
                            <td><?php echo e($info['membership_no']); ?></td>
                            <td><?php echo e($info['name']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="/admin/ycom-sync.php?debug=1" target="_blank" class="btn btn-primary">
                    <i class="bi bi-arrow-repeat"></i> Jetzt synchronisieren
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Alles OK -->
        <?php if (empty($errors) && empty($warnings) && empty($infoMissing) && !empty($ycomMembers)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            <strong>Alles in Ordnung!</strong><br>
            YCom und Fahrtenbuch sind synchron. Keine Probleme gefunden.
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
