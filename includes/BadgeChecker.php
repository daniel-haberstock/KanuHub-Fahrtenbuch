<?php
/**
 * BadgeChecker – Prüft Badge-Bedingungen und vergibt neue Badges
 *
 * Wird nach dem Beenden einer Fahrt aufgerufen (kein Cron nötig).
 * Nur die Crew-Mitglieder der aktuell beendeten Fahrt werden geprüft.
 */
class BadgeChecker
{
    /**
     * Prüft alle Badges für ein Mitglied und vergibt neue.
     *
     * @param int $memberId
     * @param PDO $db
     * @return array Neu verdiente Badge-Keys (nur die, die gerade neu waren)
     */
    public static function checkAndAward(int $memberId, PDO $db): array
    {
        // Tabelle sicherstellen (nur beim ersten Aufruf pro Request relevant)
        self::ensureTable($db);

        // Saisondaten berechnen (getCurrentSeason*-Funktionen kommen aus functions.php,
        // die vom aufrufenden Skript bereits eingebunden wurde)
        $seasonStart = getCurrentSeasonStartDate();
        $seasonEnd   = getCurrentSeasonEndDate();

        // Bereits verdiente Badges dieses Mitglieds
        $stmt = $db->prepare("SELECT badge_key FROM member_badges WHERE member_id = ?");
        $stmt->execute([$memberId]);
        $existing = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

        $newBadges = [];

        // ── Distanz-Badges ──────────────────────────────────────────
        $km = self::getSeasonKm($memberId, $db, $seasonStart, $seasonEnd);
        foreach ([1 => '1km', 10 => '10km', 50 => '50km', 100 => '100km',
                  250 => '250km', 500 => '500km', 1000 => '1000km',
                  1500 => '1500km', 2000 => '2000km'] as $threshold => $key) {
            if ($km >= $threshold && !isset($existing[$key])) {
                $newBadges[] = $key;
            }
        }

        // ── Fahrten-Badges ──────────────────────────────────────────
        $trips = self::getSeasonTripCount($memberId, $db, $seasonStart, $seasonEnd);
        if ($trips >= 1   && !isset($existing['erste-ausfahrt'])) $newBadges[] = 'erste-ausfahrt';
        if ($trips >= 5   && !isset($existing['trips_5']))        $newBadges[] = 'trips_5';
        if ($trips >= 10  && !isset($existing['trips_10']))       $newBadges[] = 'trips_10';
        if ($trips >= 50  && !isset($existing['trips_50']))       $newBadges[] = 'trips_50';
        if ($trips >= 100 && !isset($existing['trips_100']))      $newBadges[] = 'trips_100';

        // ── Jahreszeit-Badges ────────────────────────────────────────
        $paddledSeasons = self::getPaddledSeasons($memberId, $db);
        $seasonMap = ['spring' => 'fruehling', 'summer' => 'sommer',
                      'autumn' => 'herbst',    'winter' => 'winter'];
        foreach ($seasonMap as $season => $badgeKey) {
            if (in_array($season, $paddledSeasons) && !isset($existing[$badgeKey])) {
                $newBadges[] = $badgeKey;
            }
        }
        if (count(array_unique($paddledSeasons)) >= 4 && !isset($existing['jahreszeiten'])) {
            $newBadges[] = 'jahreszeiten';
        }

        // ── Boote ────────────────────────────────────────────────────
        $maxBoatCount = self::getMaxSameBoatCount($memberId, $db, $seasonStart, $seasonEnd);
        if ($maxBoatCount >= 5  && !isset($existing['stammkunde'])) $newBadges[] = 'stammkunde';
        if ($maxBoatCount >= 10 && !isset($existing['bootsliebe'])) $newBadges[] = 'bootsliebe';

        // Boot-Typ-Badges
        $boatTypeBadges = [
            'seekajak_verwendet'  => 'Seekajak',
            'rennkajak_verwendet' => 'Rennkajak',
            'surfski_verwendet'   => 'Surf Ski',
            'canadier_verwendet'  => 'Canadier',
            'sup_verwendet'       => 'SUP',
            'outrigger_verwendet' => 'Outrigger',
        ];
        foreach ($boatTypeBadges as $badgeKey => $boatType) {
            if (!isset($existing[$badgeKey]) && self::hasUsedBoatType($memberId, $db, $boatType)) {
                $newBadges[] = $badgeKey;
            }
        }

        // ── Gruppen-Badges ─────────────────────────────────────────────
        // Gemeinsame Ausfahrt (alle source_types)
        $groupTrips = self::getGroupTripCount($memberId, $db);
        if ($groupTrips >= 1  && !isset($existing['gemeinsame-ausfahrt-1']))  $newBadges[] = 'gemeinsame-ausfahrt-1';
        if ($groupTrips >= 5  && !isset($existing['gemeinsame-ausfahrt-5']))  $newBadges[] = 'gemeinsame-ausfahrt-5';
        if ($groupTrips >= 10 && !isset($existing['gemeinsame-ausfahrt-10'])) $newBadges[] = 'gemeinsame-ausfahrt-10';

        // Training (source_type = 'local_event')
        $trainingTrips = self::getGroupTripCountBySource($memberId, $db, 'local_event');
        if ($trainingTrips >= 1  && !isset($existing['training-1']))  $newBadges[] = 'training-1';
        if ($trainingTrips >= 5  && !isset($existing['training-5']))  $newBadges[] = 'training-5';
        if ($trainingTrips >= 10 && !isset($existing['training-10'])) $newBadges[] = 'training-10';
        if ($trainingTrips >= 20 && !isset($existing['training-20'])) $newBadges[] = 'training-20';

        // Gruppe beigetreten (source_type = 'manual')
        $manualTrips = self::getGroupTripCountBySource($memberId, $db, 'manual');
        if ($manualTrips >= 1  && !isset($existing['gruppe-beigetreten-1']))  $newBadges[] = 'gruppe-beigetreten-1';
        if ($manualTrips >= 5  && !isset($existing['gruppe-beigetreten-5']))  $newBadges[] = 'gruppe-beigetreten-5';
        if ($manualTrips >= 10 && !isset($existing['gruppe-beigetreten-10'])) $newBadges[] = 'gruppe-beigetreten-10';

        // Geplante Ausfahrt (source_type = 'termin')
        $terminTrips = self::getGroupTripCountBySource($memberId, $db, 'termin');
        if ($terminTrips >= 1  && !isset($existing['geplante-ausfahrt-1']))  $newBadges[] = 'geplante-ausfahrt-1';
        if ($terminTrips >= 5  && !isset($existing['geplante-ausfahrt-5']))  $newBadges[] = 'geplante-ausfahrt-5';
        if ($terminTrips >= 10 && !isset($existing['geplante-ausfahrt-10'])) $newBadges[] = 'geplante-ausfahrt-10';

        // ── Spezial-Badges ───────────────────────────────────────────
        if (!isset($existing['fruehaufsteher']) && self::hasMorningTrip($memberId, $db)) {
            $newBadges[] = 'fruehaufsteher';
        }
        if (!isset($existing['nachteule']) && self::hasNightTrip($memberId, $db)) {
            $newBadges[] = 'nachteule';
        }
        $differentBoats = self::getDifferentBoatsThisSeason($memberId, $db, $seasonStart, $seasonEnd);
        if ($differentBoats >= 3 && !isset($existing['allrounder'])) {
            $newBadges[] = 'allrounder';
        }
        $activeSeasons = self::getActiveSeasonsCount($memberId, $db);
        if ($activeSeasons >= 3 && !isset($existing['stammgast'])) $newBadges[] = 'stammgast';
        if ($activeSeasons >= 5 && !isset($existing['veteran']))   $newBadges[] = 'veteran';

        // ── Erlebnis-Badges (gesamt, nicht saisonal) ─────────────────
        // Einzel-Badges: einmalig für erstes Vorkommen
        foreach (['sonnenbrand', 'stechmuecke', 'regen', 'wellen', 'wind',
                  'sonnenaufgang', 'sonnenuntergang'] as $exp) {
            if (!isset($existing[$exp]) && self::hasTripExperience($memberId, $db, $exp)) {
                $newBadges[] = $exp;
            }
        }

        // Gekentert-Meilensteine (Gesamtanzahl über alle Saisons)
        $kenterCount = self::getTripExperienceCount($memberId, $db, 'gekentert');
        foreach ([1 => 'gekentert-1', 2 => 'gekentert-2', 5 => 'gekentert-5',
                  10 => 'gekentert-10', 20 => 'gekentert-20'] as $threshold => $key) {
            if ($kenterCount >= $threshold && !isset($existing[$key])) {
                $newBadges[] = $key;
            }
        }

        // ── Neue Badges speichern ────────────────────────────────────
        if (!empty($newBadges)) {
            $insert = $db->prepare(
                "INSERT IGNORE INTO member_badges (member_id, badge_key, seen) VALUES (?, ?, 0)"
            );
            foreach ($newBadges as $badgeKey) {
                $insert->execute([$memberId, $badgeKey]);
            }
        }

        return $newBadges;
    }

    /**
     * Badge-Check für alle Crew-Mitglieder einer Fahrt.
     * Gibt alle neuen Badges aller Crewmitglieder zurück.
     *
     * @return array ['member_id' => ['badge_key1', ...], ...]
     */
    public static function checkAfterTrip(int $tripId, PDO $db): array
    {
        $stmt = $db->prepare(
            "SELECT tc.member_id FROM trip_crew tc WHERE tc.trip_id = ? AND tc.member_id IS NOT NULL"
        );
        $stmt->execute([$tripId]);
        $memberIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $results = [];
        foreach ($memberIds as $memberId) {
            $newBadges = self::checkAndAward((int)$memberId, $db);
            if (!empty($newBadges)) {
                $results[(int)$memberId] = $newBadges;
            }
        }
        return $results;
    }

    /**
     * Gibt alle verdienten Badge-Keys eines Mitglieds zurück.
     * @return array ['earned' => [...], 'new' => [...]]
     */
    public static function getMemberBadges(int $memberId, PDO $db): array
    {
        $stmt = $db->prepare(
            "SELECT badge_key, seen FROM member_badges WHERE member_id = ? ORDER BY earned_at"
        );
        $stmt->execute([$memberId]);
        $rows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $earned  = array_column($rows, 'badge_key');
        $new     = array_column(array_filter($rows, fn($r) => (int)$r['seen'] === 0), 'badge_key');
        return ['earned' => $earned, 'new' => $new];
    }

    /**
     * Gibt Badges zurück, bei denen das Mitglied "knapp dran" ist (innerhalb eines Schwellenwerts).
     * @return array [['key' => '...', 'progress' => 0.85, 'label' => '...'], ...]
     */
    public static function getAlmostBadges(int $memberId, PDO $db): array
    {
        $seasonStart = getCurrentSeasonStartDate();
        $seasonEnd   = getCurrentSeasonEndDate();

        $stmt = $db->prepare("SELECT badge_key FROM member_badges WHERE member_id = ?");
        $stmt->execute([$memberId]);
        $existing = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

        $km    = self::getSeasonKm($memberId, $db, $seasonStart, $seasonEnd);
        $trips = self::getSeasonTripCount($memberId, $db, $seasonStart, $seasonEnd);

        $almost = [];
        $thresholds = [1 => '1km', 10 => '10km', 50 => '50km', 100 => '100km',
                       250 => '250km', 500 => '500km', 1000 => '1000km',
                       1500 => '1500km', 2000 => '2000km'];
        foreach ($thresholds as $threshold => $key) {
            if (isset($existing[$key])) continue;
            $pct = $threshold > 0 ? $km / $threshold : 0;
            if ($pct >= 0.7 && $pct < 1.0) {
                $almost[] = [
                    'key'      => $key,
                    'progress' => round($pct * 100),
                    'current'  => round($km, 1),
                    'target'   => $threshold,
                    'unit'     => 'km',
                    'label'    => BadgeRenderer::getLabel($key),
                ];
            }
        }
        $tripThresholds = [1 => 'erste-ausfahrt', 5 => 'trips_5', 10 => 'trips_10', 50 => 'trips_50', 100 => 'trips_100'];
        foreach ($tripThresholds as $threshold => $key) {
            if (isset($existing[$key])) continue;
            $pct = $threshold > 0 ? $trips / $threshold : 0;
            if ($pct >= 0.7 && $pct < 1.0) {
                $almost[] = [
                    'key'      => $key,
                    'progress' => round($pct * 100),
                    'current'  => $trips,
                    'target'   => $threshold,
                    'unit'     => 'Fahrten',
                    'label'    => BadgeRenderer::getLabel($key),
                ];
            }
        }

        // Gruppen-Badges (fast erreicht)
        $groupTrips   = self::getGroupTripCount($memberId, $db);
        $trainingTrips = self::getGroupTripCountBySource($memberId, $db, 'local_event');
        $manualTrips   = self::getGroupTripCountBySource($memberId, $db, 'manual');
        $terminTrips   = self::getGroupTripCountBySource($memberId, $db, 'termin');

        $groupThresholds = [
            ['count' => $groupTrips,    'thresholds' => [1 => 'gemeinsame-ausfahrt-1', 5 => 'gemeinsame-ausfahrt-5', 10 => 'gemeinsame-ausfahrt-10']],
            ['count' => $trainingTrips, 'thresholds' => [1 => 'training-1', 5 => 'training-5', 10 => 'training-10', 20 => 'training-20']],
            ['count' => $manualTrips,   'thresholds' => [1 => 'gruppe-beigetreten-1', 5 => 'gruppe-beigetreten-5', 10 => 'gruppe-beigetreten-10']],
            ['count' => $terminTrips,   'thresholds' => [1 => 'geplante-ausfahrt-1', 5 => 'geplante-ausfahrt-5', 10 => 'geplante-ausfahrt-10']],
        ];
        foreach ($groupThresholds as $group) {
            foreach ($group['thresholds'] as $threshold => $key) {
                if (isset($existing[$key])) continue;
                $pct = $threshold > 0 ? $group['count'] / $threshold : 0;
                if ($pct >= 0.7 && $pct < 1.0) {
                    $almost[] = [
                        'key'      => $key,
                        'progress' => round($pct * 100),
                        'current'  => $group['count'],
                        'target'   => $threshold,
                        'unit'     => 'Gruppenfahrten',
                        'label'    => BadgeRenderer::getLabel($key),
                    ];
                }
            }
        }

        // Sortiert nach progress absteigend (nächste zuerst)
        usort($almost, fn($a, $b) => $b['progress'] <=> $a['progress']);
        return array_slice($almost, 0, 5);
    }

    // =========================================================
    // Private Helper
    // =========================================================

    private static function getSeasonKm(int $memberId, PDO $db, string $start, string $end): float
    {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(t.distance), 0)
            FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = ? AND t.is_completed = 1
            AND t.start_date >= ? AND t.start_date <= ?
        ");
        $stmt->execute([$memberId, $start, $end]);
        return (float)$stmt->fetchColumn();
    }

    private static function getSeasonTripCount(int $memberId, PDO $db, string $start, string $end): int
    {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT t.id)
            FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = ? AND t.is_completed = 1
            AND t.start_date >= ? AND t.start_date <= ?
        ");
        $stmt->execute([$memberId, $start, $end]);
        return (int)$stmt->fetchColumn();
    }

    private static function getPaddledSeasons(int $memberId, PDO $db): array
    {
        $stmt = $db->prepare("
            SELECT DISTINCT MONTH(t.start_date) FROM trips t
            JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = ? AND t.is_completed = 1
        ");
        $stmt->execute([$memberId]);
        $months  = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $seasons = [];
        foreach ($months as $m) {
            $m = (int)$m;
            if ($m >= 3  && $m <= 5)  $seasons[] = 'spring';
            if ($m >= 6  && $m <= 8)  $seasons[] = 'summer';
            if ($m >= 9  && $m <= 11) $seasons[] = 'autumn';
            if ($m === 12 || $m <= 2) $seasons[] = 'winter';
        }
        return array_unique($seasons);
    }

    private static function getMaxSameBoatCount(int $memberId, PDO $db, string $start, string $end): int
    {
        $stmt = $db->prepare("
            SELECT COALESCE(MAX(cnt), 0) FROM (
                SELECT COALESCE(t.boat_name, b.boat_name, 'Unbekannt') AS boat_name, COUNT(*) AS cnt
                FROM trips t
                JOIN trip_crew tc ON t.id = tc.trip_id
                LEFT JOIN boats b ON t.boat_id = b.id
                WHERE tc.member_id = ? AND t.is_completed = 1
                AND t.start_date >= ? AND t.start_date <= ?
                GROUP BY boat_name
            ) sub
        ");
        $stmt->execute([$memberId, $start, $end]);
        return (int)$stmt->fetchColumn();
    }

    private static function getDifferentBoatsThisSeason(int $memberId, PDO $db, string $start, string $end): int
    {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT COALESCE(t.boat_name, b.boat_name, 'Unbekannt'))
            FROM trips t
            JOIN trip_crew tc ON t.id = tc.trip_id
            LEFT JOIN boats b ON t.boat_id = b.id
            WHERE tc.member_id = ? AND t.is_completed = 1
            AND t.start_date >= ? AND t.start_date <= ?
        ");
        $stmt->execute([$memberId, $start, $end]);
        return (int)$stmt->fetchColumn();
    }

    private static function hasMorningTrip(int $memberId, PDO $db): bool
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = ? AND t.is_completed = 1 AND t.start_time < '07:00:00'
        ");
        $stmt->execute([$memberId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function hasNightTrip(int $memberId, PDO $db): bool
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = ? AND t.is_completed = 1 AND t.end_time > '20:00:00'
        ");
        $stmt->execute([$memberId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Zählt alle abgeschlossenen Gruppen-Fahrten eines Mitglieds (alle source_types).
     */
    private static function getGroupTripCount(int $memberId, PDO $db): int
    {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT t.id)
            FROM trips t
            JOIN trip_crew tc ON t.id = tc.trip_id
            JOIN trip_groups tg ON t.trip_group_id = tg.id
            WHERE tc.member_id = ? AND t.is_completed = 1
        ");
        $stmt->execute([$memberId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Zählt abgeschlossene Gruppen-Fahrten nach source_type (local_event, manual, termin).
     */
    private static function getGroupTripCountBySource(int $memberId, PDO $db, string $sourceType): int
    {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT t.id)
            FROM trips t
            JOIN trip_crew tc ON t.id = tc.trip_id
            JOIN trip_groups tg ON t.trip_group_id = tg.id
            WHERE tc.member_id = ? AND t.is_completed = 1 AND tg.source_type = ?
        ");
        $stmt->execute([$memberId, $sourceType]);
        return (int)$stmt->fetchColumn();
    }

    private static function hasUsedBoatType(int $memberId, PDO $db, string $boatType): bool
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM trips t
            JOIN trip_crew tc ON t.id = tc.trip_id
            JOIN boats b ON t.boat_id = b.id
            WHERE tc.member_id = ? AND t.is_completed = 1 AND b.boat_type = ?
        ");
        $stmt->execute([$memberId, $boatType]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function getActiveSeasonsCount(int $memberId, PDO $db): int
    {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT
                CONCAT(
                    YEAR(DATE_SUB(t.start_date, INTERVAL IF(MONTH(t.start_date)>=10,0,1) YEAR)),
                    '-',
                    YEAR(DATE_SUB(t.start_date, INTERVAL IF(MONTH(t.start_date)>=10,0,1) YEAR))+1
                ))
            FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = ? AND t.is_completed = 1
        ");
        $stmt->execute([$memberId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Prüft ob ein Mitglied jemals eine bestimmte Erfahrung erlebt hat.
     */
    private static function hasTripExperience(int $memberId, PDO $db, string $experience): bool
    {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
                WHERE tc.member_id = ? AND t.is_completed = 1
                AND trip_experiences IS NOT NULL
                AND JSON_CONTAINS(trip_experiences, ?)
            ");
            $stmt->execute([$memberId, json_encode($experience)]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false; // Spalte noch nicht migriert
        }
    }

    /**
     * Zählt wie oft ein Mitglied eine bestimmte Erfahrung erlebt hat (gesamt).
     */
    private static function getTripExperienceCount(int $memberId, PDO $db, string $experience): int
    {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
                WHERE tc.member_id = ? AND t.is_completed = 1
                AND trip_experiences IS NOT NULL
                AND JSON_CONTAINS(trip_experiences, ?)
            ");
            $stmt->execute([$memberId, json_encode($experience)]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0; // Spalte noch nicht migriert
        }
    }

    /**
     * Stellt sicher, dass die member_badges-Tabelle existiert und
     * die erwartete Struktur hat (earned_at mit DEFAULT).
     * Wird einmal pro Request ausgeführt.
     */
    private static function ensureTable(PDO $db): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $db->exec("
            CREATE TABLE IF NOT EXISTS member_badges (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                member_id   INT NOT NULL,
                badge_key   VARCHAR(50) NOT NULL,
                seen        TINYINT(1) NOT NULL DEFAULT 0,
                earned_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_member_badge (member_id, badge_key),
                INDEX idx_member (member_id),
                INDEX idx_badge_key (badge_key),
                FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Falls Tabelle schon existiert aber earned_at fehlt oder kein DEFAULT hat:
        // Spalte sicher ergänzen/korrigieren
        try {
            $cols = $db->query("SHOW COLUMNS FROM member_badges LIKE 'earned_at'")->fetchAll();
            if (empty($cols)) {
                $db->exec("ALTER TABLE member_badges ADD COLUMN earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
            }
        } catch (\Throwable $e) {
            // Spalte existiert bereits – ok
        }
    }
}
