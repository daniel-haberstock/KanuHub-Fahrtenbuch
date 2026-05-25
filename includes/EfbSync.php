<?php
/**
 * EfbSync – DKV eFB-Synchronisation für das Fahrtenbuch
 *
 * Überträgt abgeschlossene Fahrten in das DKV elektronische Fahrtenbuch
 * via HTTP POST (XML, Session-Cookie). Nur Push-Richtung.
 *
 * Ablauf:
 *   1. login()        – Session-Cookie holen
 *   2. syncTrips()    – Fahrten als XML senden
 *   3. syncDone()     – Session beenden
 *
 * Voraussetzung: Mitglieder mit gesetzter efb_id, Fahrten abgeschlossen,
 * Boot ist kein Anhänger, Startdatum >= EFB_SYNC_START_DATE.
 */

class EfbSync
{
    private PDO $db;
    private string $cookieFile;
    private array $log = [];

    private const TRIP_TYPE_MAP = [
        'Normal'         => 'NORMAL',
        'Tour'           => 'TOUR',
        'Wanderfahrt'    => 'WANDERFAHRT',
        'Regatta'        => 'REGATTA',
        'Trainingslager' => 'TRAININGSLAGER',
        'Schulung'       => 'SCHULUNG',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->cookieFile = sys_get_temp_dir() . '/efb_session_' . md5(EFB_USERNAME) . '.txt';
    }

    /**
     * Schritt 1: Login, Session-Cookie speichern.
     */
    public function login(): bool
    {
        $postData = http_build_query([
            'username'    => EFB_USERNAME,
            'password'    => EFB_PASSWORD,
            'projectname' => EFB_CLUB_NAME,
            'clubname'    => EFB_CLUB_NAME,
        ]);

        @unlink($this->cookieFile);
        $response = $this->httpPost(EFB_URL_LOGIN, $postData);
        if ($response === null) {
            $this->log[] = 'Login: kein Response';
            return false;
        }

        // Manche Server (Schulung) senden HTTP 200 + leeren Body + Session-Cookie
        if (trim($response) === '') {
            $cookieSize = file_exists($this->cookieFile) ? filesize($this->cookieFile) : 0;
            if ($cookieSize > 0) {
                $this->log[] = 'Login: leere Antwort, aber Session-Cookie gesetzt (' . $cookieSize . ' bytes) – OK';
                return true;
            }
            $this->log[] = 'Login: leere Antwort und kein Cookie gesetzt';
            return false;
        }

        $xml = @simplexml_load_string($response);
        if ($xml === false) {
            $this->log[] = 'Login: XML-Parse-Fehler';
            return false;
        }

        $code = (int)($xml->header->code ?? -1);
        $msg  = trim((string)($xml->header->message ?? ''));
        if ($code !== 1) {
            $this->log[] = "Login fehlgeschlagen (code=$code): $msg";
            return false;
        }
        return true;
    }

    /**
     * Schritt 2: Fahrten übertragen.
     *
     * @param array $trips  Ausgabe von getTripsToSync()
     * @return array ['synced'=>int, 'errors'=>int, 'syncedIds'=>int[]]
     */
    public function syncTrips(array $trips): array
    {
        if (empty($trips)) {
            return ['synced' => 0, 'errors' => 0, 'syncedIds' => []];
        }

        $xml  = "<?xml version='1.0' encoding='UTF-8' ?>\n<xml>\n";
        $xml .= "<request command=\"SyncTrips\">\n";

        // tripID → trip_id (DB) Mapping für Result-Auswertung
        $tripIdMap = [];
        foreach ($trips as $trip) {
            $efbTripId = $this->buildTripIdString((int)$trip['trip_id']);
            $tripIdMap[$efbTripId] = (int)$trip['trip_id'];
            $xml .= $this->buildTripXml($trip);
        }
        $xml .= "</request>\n</xml>\n";

        $postData = 'xmlCode=' . urlencode($xml);
        $response = $this->httpPost(EFB_URL_REQUEST, $postData, true);
        if ($response === null) {
            $this->log[] = 'SyncTrips: kein Response';
            return ['synced' => 0, 'errors' => count($trips), 'syncedIds' => []];
        }

        $results = $this->parseSyncResponse($response);

        $synced = 0;
        $errors = 0;
        $syncedIds = [];
        foreach ($results as $efbTripId => $code) {
            $dbId = $tripIdMap[$efbTripId] ?? null;
            if ($code === -1) {
                $errors++;
                $this->log[] = "Trip $efbTripId: Fehler (code=-1)";
                continue;
            }
            if ($code === 0 || $code === 1 || $code === 2) {
                $synced++;
                if ($dbId !== null && !in_array($dbId, $syncedIds, true)) {
                    $syncedIds[] = $dbId;
                }
            }
        }

        return ['synced' => $synced, 'errors' => $errors, 'syncedIds' => $syncedIds];
    }

    /**
     * Schritt 3: Session beenden.
     */
    public function syncDone(): void
    {
        $xml  = "<?xml version='1.0' encoding='UTF-8' ?>\n<xml>\n";
        $xml .= "<request command=\"SyncDone\">\n";
        $xml .= "</request>\n</xml>\n";

        $postData = 'xmlCode=' . urlencode($xml);
        $this->httpPost(EFB_URL_REQUEST, $postData, true);
        @unlink($this->cookieFile);
    }

    /**
     * Liefert alle sync-fälligen Fahrten inkl. Besatzung/Boot/Strecke.
     *
     * Bedingungen:
     *  - Fahrt abgeschlossen (end_time gesetzt, is_completed = 1)
     *  - Boot kein Anhänger
     *  - Startdatum >= EFB_SYNC_START_DATE
     *  - Mind. 1 Besatzungsmitglied mit efb_id
     *  - Fahrt neu oder seit letztem Sync geändert
     *
     * @return array Liste von Fahrt-Records mit aggregierter Crew und Streckeninfo
     */
    public function getTripsToSync(): array
    {
        $sql = "
            SELECT
                t.id                AS trip_id,
                t.boat_id,
                t.boat_name,
                t.start_date,
                t.start_time,
                t.end_date,
                t.end_time,
                t.distance,
                t.session_type,
                t.route_id,
                t.route_custom,
                t.comments,
                UNIX_TIMESTAMP(t.updated_at) * 1000 AS changed_at_ms,
                b.boat_type,
                r.start_point       AS route_start,
                r.end_point         AS route_end,
                r.description       AS route_description,
                rw.name             AS route_water_name,
                sg.name             AS session_group_name,
                sg.organizer        AS session_group_organizer
            FROM trips t
            LEFT JOIN boats b           ON b.id = t.boat_id
            LEFT JOIN routes r          ON r.id = t.route_id
            LEFT JOIN waters rw         ON rw.id = r.water_id
            LEFT JOIN session_groups sg ON sg.id = t.session_group_id
            WHERE t.end_time IS NOT NULL
              AND t.is_completed = 1
              AND (b.boat_type IS NULL OR b.boat_type != 'Anhänger')
              AND t.start_date >= :sync_start
              AND (
                    t.efb_sync_at IS NULL
                 OR UNIX_TIMESTAMP(t.updated_at) > t.efb_sync_at
              )
              AND EXISTS (
                    SELECT 1
                    FROM trip_crew tc
                    JOIN members m ON m.id = tc.member_id
                    WHERE tc.trip_id = t.id
                      AND m.efb_id IS NOT NULL
              )
            ORDER BY t.start_date ASC, t.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sync_start' => EFB_SYNC_START_DATE]);
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($trips)) {
            return [];
        }

        // Crew pro Fahrt laden (nur Mitglieder mit efb_id)
        $tripIds = array_column($trips, 'trip_id');
        $placeholders = implode(',', array_fill(0, count($tripIds), '?'));
        $crewStmt = $this->db->prepare("
            SELECT tc.trip_id, m.efb_id, m.first_name, m.last_name
            FROM trip_crew tc
            JOIN members m ON m.id = tc.member_id
            WHERE tc.trip_id IN ($placeholders)
              AND m.efb_id IS NOT NULL
            ORDER BY tc.seat_position ASC
        ");
        $crewStmt->execute($tripIds);
        $crewByTrip = [];
        foreach ($crewStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $crewByTrip[(int)$row['trip_id']][] = $row;
        }

        foreach ($trips as &$t) {
            $t['crew'] = $crewByTrip[(int)$t['trip_id']] ?? [];
        }
        unset($t);

        // Fahrten ohne Crew (theoretisch durch EXISTS ausgeschlossen) sicherheitshalber filtern
        return array_values(array_filter($trips, fn($t) => !empty($t['crew'])));
    }

    public function getLog(): array
    {
        return $this->log;
    }

    // ─────────────────────────────────────────────────────────────────
    // Private Helfer
    // ─────────────────────────────────────────────────────────────────

    private function buildTripIdString(int $tripId): string
    {
        // eFB erwartet eindeutige String-ID. Muster: {Vereinskürzel}_{ID}
        return CLUB_NAME . '_' . $tripId;
    }

    /**
     * Baut pro Besatzungsmitglied ein <trip>-Element (gleiche tripID, unterschiedliche userID).
     */
    private function buildTripXml(array $t): string
    {
        $efbTripId  = $this->buildTripIdString((int)$t['trip_id']);
        $tripType   = self::TRIP_TYPE_MAP[$t['session_type'] ?? 'Normal'] ?? 'NORMAL';
        $startDate  = (string)$t['start_date'];
        $endDate    = (string)($t['end_date'] ?? $t['start_date']);
        $startTime  = !empty($t['start_time']) ? substr((string)$t['start_time'], 0, 5) : null;
        $endTime    = !empty($t['end_time'])   ? substr((string)$t['end_time'], 0, 5)   : null;
        $km         = number_format((float)($t['distance'] ?? 0), 1, '.', '');
        $changedMs  = (int)($t['changed_at_ms'] ?? (time() * 1000));

        // Gewässer / Von / Nach aus Route oder Freitext
        $waterText = $t['route_water_name'] ?? null;
        $fromText  = $t['route_start'] ?? null;
        $toText    = $t['route_end'] ?? null;
        if (empty($waterText) && empty($fromText) && empty($toText) && !empty($t['route_custom'])) {
            // Freitext-Route als Gewässer-Text nutzen
            $waterText = $t['route_custom'];
        }

        // Kommentar: ggf. Fahrttyp-Präfix (wie efa)
        $comment = trim((string)($t['comments'] ?? ''));
        if ($tripType !== 'NORMAL' && !empty($t['session_type'])) {
            $prefix = $t['session_type'] . ': ';
            $comment = $comment !== '' ? $prefix . $comment : $prefix . $t['session_type'];
        }

        $groupName      = $t['session_group_name'] ?? null;
        $groupOrganizer = $t['session_group_organizer'] ?? null;

        $xml = '';
        foreach ($t['crew'] as $crew) {
            $efbId = (int)($crew['efb_id'] ?? 0);
            if ($efbId <= 0) continue;

            $x  = "<trip>";
            $x .= "<tripID>" . htmlspecialchars($efbTripId, ENT_XML1) . "</tripID>";
            $x .= "<userID>$efbId</userID>";

            // hat keine boat_efb_id → immer boatText
            $x .= "<boatText><![CDATA[" . ($t['boat_name'] ?? '') . "]]></boatText>";

            $x .= "<begdate>$startDate</begdate>";
            $x .= "<enddate>$endDate</enddate>";
            if ($startTime) $x .= "<begtime>$startTime</begtime>";
            if ($endTime)   $x .= "<endtime>$endTime</endtime>";

            $x .= "<triptype>$tripType</triptype>";

            if (!empty($groupName)) {
                $x .= "<tripgroup>";
                $x .= "<name><![CDATA[$groupName]]></name>";
                if (!empty($groupOrganizer)) {
                    $x .= "<organizer><![CDATA[$groupOrganizer]]></organizer>";
                }
                $x .= "</tripgroup>";
            }

            $x .= "<lines><line>";
            if (!empty($waterText)) $x .= "<waterText><![CDATA[$waterText]]></waterText>";
            if (!empty($fromText))  $x .= "<fromText><![CDATA[$fromText]]></fromText>";
            if (!empty($toText))    $x .= "<toText><![CDATA[$toText]]></toText>";
            $x .= "<kilometers>$km</kilometers>";
            $x .= "</line></lines>";

            if ($comment !== '') {
                $x .= "<comment><![CDATA[$comment]]></comment>";
            }

            $x .= "<changeDate>$changedMs</changeDate>";
            $x .= "<status>1</status>";
            $x .= "<deleted>0</deleted>";
            $x .= "</trip>\n";

            $xml .= $x;
        }
        return $xml;
    }

    /**
     * Parst die SyncTrips-Response. Gibt ['tripID' => resultCode, ...] zurück.
     * Bei mehreren <trip>-Elementen pro tripID (Mehrsitzer) wird der schlechteste Code behalten.
     */
    private function parseSyncResponse(string $body): array
    {
        $results = [];
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            $this->log[] = 'SyncTrips: Response-XML nicht parsebar';
            return $results;
        }

        $tripNodes = $xml->response->trip ?? [];
        foreach ($tripNodes as $trip) {
            $id   = trim((string)($trip->tripID ?? $trip->tripid));
            $code = (int)$trip->result;
            if ($id === '') continue;
            // Fehler gewinnt, sonst letzter Wert
            if (!isset($results[$id]) || $results[$id] !== -1) {
                $results[$id] = $code === -1 ? -1 : $code;
            }
        }
        return $results;
    }

    private function httpPost(string $url, string $postData, bool $useCookie = false): ?string
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ];
        if ($useCookie) {
            $opts[CURLOPT_COOKIEFILE] = $this->cookieFile;
        }
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $err !== '') {
            $this->log[] = "HTTP-Fehler ($url): $err";
            return null;
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->log[] = "HTTP-Status $httpCode bei $url";
            return null;
        }
        return (string)$response;
    }
}
