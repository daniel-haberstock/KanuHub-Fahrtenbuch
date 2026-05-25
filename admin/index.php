<?php
/**
 * Fahrtenbuch - Admin Übersicht
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// Prüfe ob Boote-Tabelle leer ist (für XML-Import Anzeige)
$stmt = $db->query("SELECT COUNT(*) as count FROM boats");
$boatsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
$showXmlImport = ($boatsCount == 0);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= CLUB_NAME ?> Fahrtenbuch - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* Stelle sicher, dass alle Kacheln gleich hoch sind */
        .row {
            display: flex;
            flex-wrap: wrap;
        }
        .row > [class*="col-"] {
            display: flex;
            margin-bottom: 1.5rem;
        }
        .card {
            width: 100%;
            display: flex;
            flex-direction: column;
        }
        .card-body {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .card-text {
            flex-grow: 1;
            min-height: 48px;
        }
        .card-body .btn {
            margin-top: auto;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">
                <i class="bi bi-gear"></i> <?= CLUB_NAME ?> Fahrtenbuch - Administration
            </a>
            <div>
                <a href="/admin/logout.php?redirect=/" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-house"></i> Zurück zur Hauptseite
                </a>
                <span class="text-light me-2 small">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
                </span>
                <a href="/admin/logout.php?redirect=/" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Abmelden
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2>Administration</h2>
                <p class="text-muted">Verwaltung von Strecken, Mitgliedern und Booten</p>
            </div>
        </div>

        <div class="row mt-4">
            <!-- 1. Mitglieder -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-people display-1 text-success"></i>
                        <?php if (defined('REDAXO_API_TOKEN') && !empty(REDAXO_API_TOKEN)): ?>
                            <h5 class="card-title mt-3">Mitgliederliste</h5>
                            <p class="card-text">Mitgliederdaten, Status und Kontaktinformationen einsehen</p>
                            <a href="/admin/members.php" class="btn btn-success">
                                <i class="bi bi-arrow-right"></i> Anzeigen
                            </a>
                        <?php else: ?>
                            <h5 class="card-title mt-3">Mitglieder verwalten</h5>
                            <p class="card-text">Mitgliederdaten, Status und Kontaktinformationen verwalten</p>
                            <a href="/admin/members.php" class="btn btn-success">
                                <i class="bi bi-arrow-right"></i> Verwalten
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 2. Arbeitsdienst -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-tools display-1 text-warning"></i>
                        <h5 class="card-title mt-3">Arbeitsdienst</h5>
                        <p class="card-text">Arbeitsstunden für mehrere Mitglieder erfassen</p>
                        <a href="/admin/work-duty.php" class="btn btn-warning">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>

            <!-- 3. Boote verwalten -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-water display-1 text-info"></i>
                        <h5 class="card-title mt-3">Boote verwalten</h5>
                        <p class="card-text">Bootsnamen, Typen, Sitzplätze und Lagerort verwalten</p>
                        <a href="/admin/boats.php" class="btn btn-info">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- GPS-Tracker -->
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <i class="bi bi-geo-alt-fill display-1 text-success"></i>
                        <h5 class="card-title mt-3">GPS-Tracker</h5>
                        <p class="card-text">Tracker verwalten, Status und Akkustand überwachen, Freigaben setzen</p>
                        <a href="/admin/tracker.php" class="btn btn-success">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>

            <!-- Layout-Auswahl -->
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <i class="bi bi-palette display-1 text-primary"></i>
                        <h5 class="card-title mt-3">Layout-Auswahl</h5>
                        <p class="card-text">Zwischen neuem v2-Design und klassischem Layout umschalten</p>
                        <a href="<?= APP_BASE_PATH ?>/admin/layout-settings.php" class="btn btn-primary">
                            <i class="bi bi-arrow-right"></i> Umschalten
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Boot-Icons verwalten -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-image display-1 text-info"></i>
                        <h5 class="card-title mt-3">Boot-Icons</h5>
                        <p class="card-text">Icons für Boote hochladen und verwalten</p>
                        <a href="/admin/boat-icons.php" class="btn btn-info">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>

            <!-- 4. Fahrten korrigieren -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-journal-text display-1 text-primary"></i>
                        <h5 class="card-title mt-3">Fahrten korrigieren</h5>
                        <p class="card-text">Fahrtenbuch-Einträge bearbeiten oder löschen</p>
                        <a href="/admin/trips.php" class="btn btn-primary">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>

            <!-- 5. Schäden verwalten -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-triangle display-1 text-danger"></i>
                        <h5 class="card-title mt-3">Schäden verwalten</h5>
                        <p class="card-text">Gemeldete Bootsschäden bearbeiten und beheben</p>
                        <a href="/admin/damages.php" class="btn btn-danger">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>

            <!-- 6. Reservierungen verwalten -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-check display-1 text-warning"></i>
                        <h5 class="card-title mt-3">Reservierungen verwalten</h5>
                        <p class="card-text">Bootsreservierungen einsehen und verwalten</p>
                        <a href="/admin/reservations.php" class="btn btn-warning">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- 7. Strecken verwalten -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-signpost-2 display-1 text-primary"></i>
                        <h5 class="card-title mt-3">Strecken verwalten</h5>
                        <p class="card-text">Start- und Zielpunkte, Gewässer und Entfernungen</p>
                        <a href="/admin/routes.php" class="btn btn-primary">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>

            <!-- 8. Gewässer verwalten -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-droplet display-1 text-primary"></i>
                        <h5 class="card-title mt-3">Gewässer verwalten</h5>
                        <p class="card-text">Gewässernamen und Beschreibungen verwalten</p>
                        <a href="/admin/waters.php" class="btn btn-primary">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>

            <!-- 9. Anhänger-Statistik -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-truck display-1 text-warning"></i>
                        <h5 class="card-title mt-3">Anhänger-Statistik</h5>
                        <p class="card-text">Kilometerstand und Fahrten der Anhänger einsehen</p>
                        <a href="/admin/trailer-statistics.php" class="btn btn-warning">
                            <i class="bi bi-arrow-right"></i> Anzeigen
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- 10. Administratoren verwalten -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-person-gear display-1 text-dark"></i>
                        <h5 class="card-title mt-3">Administratoren</h5>
                        <p class="card-text">Admin-Zugänge für Vorstände anlegen und verwalten</p>
                        <a href="/admin/admins.php" class="btn btn-dark">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>

            <!-- 11. Systemmeldungen -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-chat-square-text display-1 text-secondary"></i>
                        <h5 class="card-title mt-3">Systemmeldungen</h5>
                        <p class="card-text">Hinweise und Warnungen bei Fahrtbeginn, Rückkehr und Reservierung bearbeiten</p>
                        <a href="/admin/messages.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-right"></i> Bearbeiten
                        </a>
                    </div>
                </div>
            </div>

            <!-- 12. Lokale Events -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-event display-1 text-success"></i>
                        <h5 class="card-title mt-3">Lokale Events</h5>
                        <p class="card-text">Wiederkehrende Trainings und Vereins-Ausfahrten verwalten</p>
                        <a href="/admin/local-events.php" class="btn btn-success">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- 13. Nicht zugeordnete Personen -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-person-x display-1 text-warning"></i>
                        <h5 class="card-title mt-3">Import: Nicht zugeordnet</h5>
                        <p class="card-text">Personen aus dem EFA2-Import prüfen, zuordnen oder ignorieren</p>
                        <a href="/admin/unmatched-persons.php" class="btn btn-warning">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>

            <!-- 14. DKV eFB-Synchronisation -->
            <?php if (defined('EFB_ENABLED')): ?>
            <div class="col-md-4">
                <div class="card border-<?= EFB_ENABLED ? 'success' : 'secondary' ?>">
                    <div class="card-body text-center">
                        <i class="bi bi-person-badge display-1 <?= EFB_ENABLED ? 'text-success' : 'text-secondary' ?>"></i>
                        <h5 class="card-title mt-3">DKV eFB-Sync</h5>
                        <p class="card-text">Fahrten ans DKV elektronische Fahrtenbuch übertragen</p>
                        <a href="/admin/efb-sync.php?debug=1" class="btn btn-<?= EFB_ENABLED ? 'success' : 'secondary' ?>">
                            <i class="bi bi-cloud-upload"></i> Sync ausführen
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 15. Historische Fahrten importieren -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-history display-1 text-info"></i>
                        <h5 class="card-title mt-3">Historische Fahrten</h5>
                        <p class="card-text">Alte EFA2-Fahrtenbuch-Daten importieren (XML)</p>
                        <a href="/admin/import-alt.php" class="btn btn-info">
                            <i class="bi bi-upload"></i> Importieren
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($showXmlImport): ?>
        <div class="row mt-4">
            <!-- 10. XML-Datenimport (nur bei leerer Boots-Tabelle) -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-download display-1 text-primary"></i>
                        <h5 class="card-title mt-3">XML-Datenimport</h5>
                        <p class="card-text">Alte EFA-Daten importieren</p>
                        <a href="/admin/import-xml.php" class="btn btn-primary">
                            <i class="bi bi-play-circle"></i> Import starten
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php /* Fahrtgruppen - Aktuell ausgeblendet
        <div class="row mt-4">
            <!-- Fahrtgruppen verwalten -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill display-1 text-success"></i>
                        <h5 class="card-title mt-3">Fahrtgruppen verwalten</h5>
                        <p class="card-text">Wanderfahrten, Regatten und Trainingslager gruppieren</p>
                        <a href="/admin/session-groups.php" class="btn btn-success">
                            <i class="bi bi-arrow-right"></i> Verwalten
                        </a>
                    </div>
                </div>
            </div>
        </div>
        */ ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
