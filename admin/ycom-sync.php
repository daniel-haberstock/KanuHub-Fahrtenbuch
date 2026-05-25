<?php
/**
 * YCom Sync Cronjob - Synchronisiert Mitglieder aus YCom ins Fahrtenbuch
 *
 * Kann manuell oder als Cronjob ausgeführt werden:
 *   Browser (eingeloggt):  /admin/ycom-sync.php?debug=1
 *   Browser (Cronjob-URL): /admin/ycom-sync.php?token=SYNC_SECRET_TOKEN
 *   CLI:                   php /pfad/zu/admin/ycom-sync.php
 *
 * Cronjob via PHP-CLI (täglich um 3:00 Uhr):
 *   0 3 * * * /usr/bin/php /mnt/web114/e1/80/5535580/htdocs/fahrtenbuch_1/admin/ycom-sync.php >> /mnt/web114/e1/80/5535580/htdocs/fahrtenbuch_1/admin/ycom-sync.log 2>&1
 *
 * Cronjob via HTTP (wenn kein CLI-Zugang verfügbar):
 *   0 3 * * * wget -q -O /dev/null "https://fahrtenbuch.kanuhub.de/admin/ycom-sync.php?token=CHANGE_ME_TOKEN"
 */

// Zugang: CLI ohne Auth, Browser mit Admin-Login ODER gültigem Secret-Token.
if (php_sapi_name() === 'cli') {
    // Cronjob via PHP-CLI – kein Login nötig
    require_once __DIR__ . '/../config/config.php';
} elseif (
    isset($_GET['token']) &&
    defined('SYNC_SECRET_TOKEN') &&
    SYNC_SECRET_TOKEN !== '' &&
    hash_equals(SYNC_SECRET_TOKEN, $_GET['token'])
) {
    // Cronjob via HTTP mit Secret-Token – kein Login nötig
    require_once __DIR__ . '/../config/config.php';
} else {
    // Normaler Browser-Aufruf – Admin-Login erforderlich
    require_once __DIR__ . '/auth_check.php';
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$debug = isset($_GET['debug']) || php_sapi_name() === 'cli';

if ($debug) {
    echo "=== YCom Sync gestartet ===\n";
    echo "Zeit: " . date('Y-m-d H:i:s') . "\n\n";
}

try {
    $db = Database::getInstance()->getConnection();

    // === YCom Daten abrufen ===
    // Verbindung zur YCom-Datenbank herstellen
    $ycomDb = new PDO(
        'mysql:host=' . YCOM_DB_HOST . ';dbname=' . YCOM_DB_NAME . ';charset=utf8mb4',
        YCOM_DB_USER,
        YCOM_DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Mitglieder aus YCom abrufen
    // Hinweis: Passwort wird NICHT mehr synchronisiert — Auth erfolgt live via REDAXO-API
    $ycomQuery = "
        SELECT
            u.id,
            u.mitglied_nr as membership_no,
            u.firstname as first_name,
            u.name as last_name,
            u.email,
            u.anrede,
            u.ycom_groups
        FROM rex_ycom_user u
        WHERE u.status = 1
        AND u.mitglied_nr IS NOT NULL
        AND u.mitglied_nr != ''
    ";

    $ycomStmt = $ycomDb->prepare($ycomQuery);
    $ycomStmt->execute();
    $ycomMembers = $ycomStmt->fetchAll();

    if ($debug) {
        echo "YCom-Mitglieder gefunden: " . count($ycomMembers) . "\n\n";
    }

    // === Mapping-Funktionen ===

    /**
     * Mappt YCom anrede zu Salutation
     * 1 = Herr, 2 = Frau
     */
    function mapSalutation($anrede) {
        if ($anrede == 1) {
            return 'Herr';
        } else if ($anrede == 2) {
            return 'Frau';
        }
        return 'Herr'; // Default
    }

    /**
     * Mappt YCom Groups zu Status
     * ycom_groups kann komma-getrennt sein - nur den ersten Wert verwenden
     * 1 = Vollmitglied (Aktiv), 2 = Gastmitglied, 3 = Jugendmitglied, 4 = Vorstand (Ehrenmitglied), 5 = Passivmitglied (Passiv)
     */
    function mapStatus($ycomGroups) {
        // Wenn komma-getrennt, nur ersten Wert nehmen
        $groupId = $ycomGroups;
        if (strpos($ycomGroups, ',') !== false) {
            $parts = explode(',', $ycomGroups);
            $groupId = trim($parts[0]);
        }

        // Mapping
        if ($groupId == 1) {
            return 'Aktiv'; // Vollmitglied
        } else if ($groupId == 2) {
            return 'Gastmitglied';
        } else if ($groupId == 3) {
            return 'Jugendmitglied';
        } else if ($groupId == 4) {
            return 'Ehrenmitglied'; // Vorstand
        } else if ($groupId == 5) {
            return 'Passiv'; // Passivmitglied
        }

        return 'Aktiv'; // Default
    }

    // === Synchronisation ===
    $stats = [
        'updated' => 0,
        'created' => 0,
        'deactivated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];

    $db->beginTransaction();

    // Schritt 1: Alle YCom-Mitglieder synchronisieren
    $ycomMembershipNos = [];
    foreach ($ycomMembers as $ycomMember) {
        try {
            $ycomMembershipNos[] = $ycomMember['membership_no'];

            // Prüfen, ob Mitglied bereits existiert (nach Mitgliedsnummer)
            $checkQuery = "SELECT id, first_name, last_name FROM members WHERE membership_no = :membership_no";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute(['membership_no' => $ycomMember['membership_no']]);
            $existingMember = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingMember) {
                // Update Stammdaten (Passwort-Auth erfolgt live via REDAXO-API)
                $updateQuery = "
                    UPDATE members
                    SET first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        salutation = :salutation,
                        status = :status,
                        valid_until = NULL,
                        updated_at = NOW()
                    WHERE membership_no = :membership_no
                ";

                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->execute([
                    'first_name' => $ycomMember['first_name'],
                    'last_name' => $ycomMember['last_name'],
                    'email' => $ycomMember['email'],
                    'salutation' => mapSalutation($ycomMember['anrede']),
                    'status' => mapStatus($ycomMember['ycom_groups']),
                    'membership_no' => $ycomMember['membership_no']
                ]);

                $stats['updated']++;

                if ($debug) {
                    echo "✓ Aktualisiert: {$ycomMember['first_name']} {$ycomMember['last_name']} ({$ycomMember['membership_no']})\n";
                }
            } else {
                // Neues Mitglied erstellen (ohne Passwort — Auth via REDAXO-API)
                $insertQuery = "
                    INSERT INTO members (
                        membership_no, first_name, last_name, email, salutation, status, valid_until
                    ) VALUES (
                        :membership_no, :first_name, :last_name, :email, :salutation, :status, NULL
                    )
                ";

                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->execute([
                    'membership_no' => $ycomMember['membership_no'],
                    'first_name' => $ycomMember['first_name'],
                    'last_name' => $ycomMember['last_name'],
                    'email' => $ycomMember['email'],
                    'salutation' => mapSalutation($ycomMember['anrede']),
                    'status' => mapStatus($ycomMember['ycom_groups'])
                ]);

                $stats['created']++;

                if ($debug) {
                    echo "+ Erstellt: {$ycomMember['first_name']} {$ycomMember['last_name']} ({$ycomMember['membership_no']})\n";
                }
            }
        } catch (Exception $e) {
            $stats['errors']++;
            if ($debug) {
                echo "✗ Fehler bei {$ycomMember['membership_no']}: " . $e->getMessage() . "\n";
            }
        }
    }

    // Schritt 2: Mitglieder deaktivieren, die nicht mehr in YCom sind
    if (!empty($ycomMembershipNos)) {
        $placeholders = implode(',', array_fill(0, count($ycomMembershipNos), '?'));
        $deactivateQuery = "
            UPDATE members
            SET valid_until = CURDATE()
            WHERE membership_no NOT IN ($placeholders)
            AND (valid_until IS NULL OR valid_until > CURDATE())
            AND membership_no IS NOT NULL
            AND membership_no != ''
        ";

        $deactivateStmt = $db->prepare($deactivateQuery);
        $deactivateStmt->execute($ycomMembershipNos);
        $stats['deactivated'] = $deactivateStmt->rowCount();

        if ($debug && $stats['deactivated'] > 0) {
            echo "\n! Deaktiviert (nicht mehr in YCom): {$stats['deactivated']}\n";
        }
    }

    $db->commit();

    if ($debug) {
        echo "\n=== Synchronisation abgeschlossen ===\n";
        echo "Erstellt:      {$stats['created']}\n";
        echo "Aktualisiert:  {$stats['updated']}\n";
        echo "Deaktiviert:   {$stats['deactivated']}\n";
        echo "Übersprungen:  {$stats['skipped']}\n";
        echo "Fehler:        {$stats['errors']}\n";
    }

    // Log für Cronjob
    $logFile = __DIR__ . '/ycom-sync.log';
    $logEntry = date('Y-m-d H:i:s') . " - Sync abgeschlossen: {$stats['created']} neu, {$stats['updated']} aktualisiert, {$stats['deactivated']} deaktiviert, {$stats['errors']} Fehler\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    if ($debug) {
        echo "\n!!! FEHLER !!!\n";
        echo $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
    }

    // Fehler-Log
    $logFile = __DIR__ . '/ycom-sync.log';
    $logEntry = date('Y-m-d H:i:s') . " - FEHLER: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    exit(1);
}

if ($debug) {
    echo "\nFertig!\n";
}
