<?php
/**
 * API: Fahrt beenden
 * Startet außerdem den Badge-Check für alle Crew-Mitglieder und
 * gibt Club-Fortschritt sowie neu verdiente Badges zurück.
 */

header('Content-Type: application/json');

// ── Globaler Fehler-Handler als Sicherheitsnetz ──────────────────────────
// Fängt ALLES ab, auch Fehler die vor/außerhalb des try-Blocks passieren
set_error_handler(function($severity, $msg, $file, $line) {
    throw new ErrorException($msg, 0, $severity, $file, $line);
});
set_exception_handler(function(\Throwable $e) {
    error_log('[Fahrtenbuch end.php] UNCAUGHT: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler: ' . ($e->getMessage() ?: get_class($e))
    ]);
    exit;
});

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Badge-Klassen OPTIONAL laden (verhindert 500 bei fehlenden Tabellen/PHP-Fehlern)
$badgesAvailable = false;
try {
    require_once __DIR__ . '/../../includes/BadgeChecker.php';
    require_once __DIR__ . '/../../includes/BadgeRenderer.php';
    $badgesAvailable = true;
} catch (\Throwable $e) {
    error_log('[Fahrtenbuch end.php] Badge-Klassen konnten nicht geladen werden: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

try {
    $db = Database::getInstance()->getConnection();

    $tripId      = $_POST['trip_id']      ?? null;
    $endDate     = $_POST['end_date']     ?? null;
    $endTime     = $_POST['end_time']     ?? null;
    $routeCustom = $_POST['route_custom'] ?? null;
    $routeId     = $_POST['route_id']     ?? null;
    $distance        = isset($_POST['distance']) ? str_replace(',', '.', $_POST['distance']) : null;
    $sourceTable     = $_POST['source_table'] ?? null;
    $isTrailer       = ($_POST['is_trailer']  ?? '0') === '1';
    $tripExperiences = $_POST['trip_experiences'] ?? [];
    $tripNotes       = trim($_POST['trip_notes'] ?? '');

    // Sonnenaufgang / Sonnenuntergang aus Notiztext automatisch erkennen
    if (!empty($tripNotes)) {
        $notesLower = mb_strtolower($tripNotes, 'UTF-8');
        if (preg_match('/sonn[e]?n\s*aufgang|sunrise/u', $notesLower)
            && !in_array('sonnenaufgang', $tripExperiences)) {
            $tripExperiences[] = 'sonnenaufgang';
        }
        if (preg_match('/sonn[e]?n\s*untergang|sunset|vollmond/u', $notesLower)
            && !in_array('sonnenuntergang', $tripExperiences)) {
            $tripExperiences[] = 'sonnenuntergang';
        }
    }

    // Sammelbeendigung: IDs der Gruppen-Trips die mit beendet werden sollen
    $groupEndTripIds = $_POST['group_end_trip_ids'] ?? [];

    if (!$tripId || !$endDate || !$endTime || !$distance) {
        jsonResponse(['success' => false, 'message' => 'Alle Pflichtfelder müssen ausgefüllt sein'], 400);
    }

    // Tabelle wählen (trips oder trailer_trips)
    if ($sourceTable === 'trailer_trips' || $sourceTable === 'trips') {
        $tripTable = $sourceTable;
    } else {
        $tripTable = $isTrailer ? 'trailer_trips' : 'trips';
    }

    // Prüfen, ob die Fahrt existiert
    $checkStmt = $db->prepare("SELECT id FROM $tripTable WHERE id = :trip_id");
    $checkStmt->execute(['trip_id' => $tripId]);
    if (!$checkStmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Fahrt nicht gefunden'], 404);
    }

    // Fahrt als abgeschlossen speichern
    $stmt = $db->prepare("
        UPDATE $tripTable
        SET
            end_date     = :end_date,
            end_time     = :end_time,
            route_id     = :route_id,
            route_custom = :route_custom,
            distance     = :distance,
            is_completed = 1
        WHERE id = :trip_id
    ");
    $result = $stmt->execute([
        'trip_id'      => $tripId,
        'end_date'     => $endDate,
        'end_time'     => $endTime,
        'route_id'     => $routeId ?: null,
        'route_custom' => $routeCustom,
        'distance'     => $distance,
    ]);

    if (!$result) {
        jsonResponse(['success' => false, 'message' => 'Fehler beim Beenden der Fahrt'], 500);
    }

    // ── Erlebnisse + Notizen speichern (graceful fallback wenn Spalten fehlen) ──
    try {
        $expJson = !empty($tripExperiences) ? json_encode(array_values($tripExperiences)) : null;
        $db->prepare("UPDATE $tripTable SET trip_experiences = :exp, trip_notes = :notes WHERE id = :id")
           ->execute(['exp' => $expJson, 'notes' => ($tripNotes !== '' ? $tripNotes : null), 'id' => $tripId]);
    } catch (\Throwable $e) {
        error_log('[Fahrtenbuch end.php] trip_experiences/trip_notes konnten nicht gespeichert werden: ' . $e->getMessage());
    }

    // ── Badge-Check für Crew-Mitglieder ──────────────────────────
    $newBadgesHtml  = '';
    $newBadgesCount = 0;
    $allNewBadges   = [];
    $allBadgeKeys   = [];

    // Nur bei normalen Fahrten (nicht Trailer) UND wenn Badge-Klassen geladen wurden
    if ($tripTable === 'trips' && $badgesAvailable) {
        try {
            $allNewBadges = BadgeChecker::checkAfterTrip((int)$tripId, $db);
            foreach ($allNewBadges as $memberBadges) {
                foreach ($memberBadges as $key) {
                    if (!in_array($key, $allBadgeKeys)) {
                        $allBadgeKeys[] = $key;
                    }
                }
            }

            if (!empty($allBadgeKeys)) {
                $newBadgesCount = count($allBadgeKeys);
                $newBadgesHtml  = BadgeRenderer::renderBadgeList($allBadgeKeys);
            }
        } catch (\Throwable $e) {
            // Badge-Check ist optional – Fehler nicht nach außen geben
            error_log('[Fahrtenbuch end.php] Badge-Check Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    // ── E-Mail-Queue befüllen für Crew-Mitglieder ────────────────
    if ($tripTable === 'trips') {
        try {
            $tripDateStmt = $db->prepare("SELECT start_date FROM trips WHERE id = :id");
            $tripDateStmt->execute(['id' => $tripId]);
            $tripDate = $tripDateStmt->fetchColumn();

            if (!$tripDate) {
                error_log("[Fahrtenbuch end.php] E-Mail-Queue: start_date ist leer für trip_id=$tripId");
            } else {
                $weekKey = date('Y-\WW', strtotime($tripDate));

                // Crew laden – auch Einträge OHNE member_id (member_name enthält ggf. Mitgliedsnr.)
                $crewStmt = $db->prepare(
                    "SELECT tc.member_id, tc.member_name
                     FROM trip_crew tc
                     WHERE tc.trip_id = :id"
                );
                $crewStmt->execute(['id' => $tripId]);
                $crewRows = $crewStmt->fetchAll(PDO::FETCH_ASSOC);

                error_log("[Fahrtenbuch end.php] E-Mail-Queue: trip_id=$tripId, date=$tripDate, week=$weekKey, crew=" . count($crewRows));

                // Mitglieds-IDs sammeln (member_id direkt oder aus member_name parsen)
                $memberIds = [];
                foreach ($crewRows as $crew) {
                    if (!empty($crew['member_id'])) {
                        $memberIds[] = (int)$crew['member_id'];
                    } elseif (!empty($crew['member_name']) && preg_match('/\((\d+)\)\s*$/', $crew['member_name'], $m)) {
                        // "Martin Lenhart-Höß (1153)" → membership_no = 1153
                        $membershipNo = $m[1];
                        $resolveStmt = $db->prepare("SELECT id FROM members WHERE membership_no = :no LIMIT 1");
                        $resolveStmt->execute(['no' => $membershipNo]);
                        $resolvedId = $resolveStmt->fetchColumn();
                        if ($resolvedId) {
                            $memberIds[] = (int)$resolvedId;
                            error_log("[Fahrtenbuch end.php] E-Mail-Queue: member_name='{$crew['member_name']}' → member_id=$resolvedId (via membership_no=$membershipNo)");
                        }
                    }
                }
                $memberIds = array_unique($memberIds);

                if (empty($memberIds)) {
                    error_log("[Fahrtenbuch end.php] E-Mail-Queue: Keine Mitglieder-IDs gefunden");
                } else {
                    // Benachrichtigungs-Einstellungen für alle gefundenen Mitglieder laden
                    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
                    $settingsStmt = $db->prepare(
                        "SELECT id, email_notify_after_trip, email_notify_weekly
                         FROM members WHERE id IN ($placeholders)"
                    );
                    $settingsStmt->execute($memberIds);
                    $settingsRows = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

                    $insertNotify = $db->prepare(
                        "INSERT IGNORE INTO email_notifications (member_id, type, reference_key)
                         VALUES (:mid, :type, :ref)"
                    );

                    $inserted = 0;
                    foreach ($settingsRows as $member) {
                        error_log("[Fahrtenbuch end.php] E-Mail-Queue: member_id={$member['id']}, after_trip={$member['email_notify_after_trip']}, weekly={$member['email_notify_weekly']}");
                        if ($member['email_notify_after_trip']) {
                            $insertNotify->execute(['mid' => $member['id'], 'type' => 'trip', 'ref' => $tripDate]);
                            $inserted++;
                        }
                        if ($member['email_notify_weekly']) {
                            $insertNotify->execute(['mid' => $member['id'], 'type' => 'weekly', 'ref' => $weekKey]);
                            $inserted++;
                        }
                    }
                    error_log("[Fahrtenbuch end.php] E-Mail-Queue: $inserted Einträge eingefügt");
                }
            }
        } catch (\Throwable $e) {
            error_log('[Fahrtenbuch end.php] E-Mail-Queue FEHLER: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    // ── Sammelbeendigung: Gruppen-Trips mit beenden ──────────────
    $groupEndedCount = 0;
    if (!empty($groupEndTripIds) && is_array($groupEndTripIds) && $tripTable === 'trips') {
        // trip_group_id der Hauptfahrt ermitteln
        $mainTripStmt = $db->prepare("SELECT trip_group_id FROM trips WHERE id = :id");
        $mainTripStmt->execute(['id' => $tripId]);
        $mainTripGroupId = $mainTripStmt->fetchColumn();

        if ($mainTripGroupId) {
            foreach ($groupEndTripIds as $groupTripId) {
                $groupTripId = (int)$groupTripId;
                if ($groupTripId === (int)$tripId) continue; // Hauptfahrt überspringen

                // Prüfen ob Trip zur gleichen Gruppe gehört und noch aktiv ist
                $checkGroupStmt = $db->prepare("
                    SELECT id FROM trips
                    WHERE id = :id AND trip_group_id = :group_id AND is_completed = 0
                ");
                $checkGroupStmt->execute(['id' => $groupTripId, 'group_id' => $mainTripGroupId]);
                if (!$checkGroupStmt->fetch()) continue;

                // Mit gleichen Werten beenden
                $groupEndStmt = $db->prepare("
                    UPDATE trips SET
                        end_date = :end_date, end_time = :end_time,
                        route_id = :route_id, route_custom = :route_custom,
                        distance = :distance, is_completed = 1
                    WHERE id = :trip_id
                ");
                $groupEndStmt->execute([
                    'trip_id'      => $groupTripId,
                    'end_date'     => $endDate,
                    'end_time'     => $endTime,
                    'route_id'     => $routeId ?: null,
                    'route_custom' => $routeCustom,
                    'distance'     => $distance,
                ]);
                $groupEndedCount++;

                // E-Mail-Queue für Gruppen-Trip Crew
                try {
                    $gCrewStmt = $db->prepare(
                        "SELECT tc.member_id, tc.member_name FROM trip_crew tc WHERE tc.trip_id = :id"
                    );
                    $gCrewStmt->execute(['id' => $groupTripId]);
                    $gMemberIds = [];
                    foreach ($gCrewStmt->fetchAll(PDO::FETCH_ASSOC) as $gc) {
                        if (!empty($gc['member_id'])) {
                            $gMemberIds[] = (int)$gc['member_id'];
                        } elseif (!empty($gc['member_name']) && preg_match('/\((\d+)\)\s*$/', $gc['member_name'], $gm)) {
                            $gResolve = $db->prepare("SELECT id FROM members WHERE membership_no = :no LIMIT 1");
                            $gResolve->execute(['no' => $gm[1]]);
                            $gResId = $gResolve->fetchColumn();
                            if ($gResId) $gMemberIds[] = (int)$gResId;
                        }
                    }
                    $gMemberIds = array_unique($gMemberIds);
                    if (!empty($gMemberIds)) {
                        $gPh = implode(',', array_fill(0, count($gMemberIds), '?'));
                        $gSettings = $db->prepare("SELECT id, email_notify_after_trip, email_notify_weekly FROM members WHERE id IN ($gPh)");
                        $gSettings->execute($gMemberIds);
                        $gInsert = $db->prepare("INSERT IGNORE INTO email_notifications (member_id, type, reference_key) VALUES (:mid, :type, :ref)");
                        $gTripDate = $endDate;
                        $gWeekKey  = date('Y-\WW', strtotime($gTripDate));
                        foreach ($gSettings->fetchAll(PDO::FETCH_ASSOC) as $gMember) {
                            if ($gMember['email_notify_after_trip']) {
                                $gInsert->execute(['mid' => $gMember['id'], 'type' => 'trip', 'ref' => $gTripDate]);
                            }
                            if ($gMember['email_notify_weekly']) {
                                $gInsert->execute(['mid' => $gMember['id'], 'type' => 'weekly', 'ref' => $gWeekKey]);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('[Fahrtenbuch end.php] E-Mail-Queue Gruppen-Trip #' . $groupTripId . ': ' . $e->getMessage());
                }

                // Badge-Check für diesen Gruppen-Trip
                if ($badgesAvailable) {
                    try {
                        $groupTripBadges = BadgeChecker::checkAfterTrip($groupTripId, $db);
                        // Badges zusammenführen
                        foreach ($groupTripBadges as $memberId => $badges) {
                            if (!isset($allNewBadges[$memberId])) {
                                $allNewBadges[$memberId] = [];
                            }
                            foreach ($badges as $key) {
                                if (!in_array($key, $allNewBadges[$memberId])) {
                                    $allNewBadges[$memberId][] = $key;
                                }
                                if (!in_array($key, $allBadgeKeys)) {
                                    $allBadgeKeys[] = $key;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        error_log('[Fahrtenbuch end.php] Badge-Check Gruppen-Trip #' . $groupTripId . ': ' . $e->getMessage());
                    }
                }
            }

            // Prüfen ob alle Trips der Gruppe beendet sind → Gruppe als completed markieren
            $remainingStmt = $db->prepare("
                SELECT COUNT(*) FROM trips WHERE trip_group_id = :group_id AND is_completed = 0
            ");
            $remainingStmt->execute(['group_id' => $mainTripGroupId]);
            $remainingCount = (int)$remainingStmt->fetchColumn();

            if ($remainingCount === 0) {
                $db->prepare("UPDATE trip_groups SET is_completed = 1, completed_at = NOW() WHERE id = :id")
                   ->execute(['id' => $mainTripGroupId]);
            }

            // Badge-HTML aktualisieren wenn neue Badges dazukamen
            if ($badgesAvailable && !empty($allBadgeKeys)) {
                $newBadgesCount = count($allBadgeKeys);
                $newBadgesHtml  = BadgeRenderer::renderBadgeList($allBadgeKeys);
            }
        }
    }

    // ── Club-Fortschritt (aktualisierte Werte nach der Fahrt) ────
    $clubProgress = [];
    try {
        $seasonStart     = getCurrentSeasonStartDate();
        $seasonEnd       = getCurrentSeasonEndDate();
        $lastSeasonStart = date('Y-m-d', strtotime($seasonStart . ' -1 year'));
        $lastSeasonEnd   = date('Y-m-d', strtotime($seasonEnd   . ' -1 year'));
        $curYear  = (int)date('Y');
        $curMonth = (int)date('m');
        $lastYear = (int)date('Y', strtotime('-1 year'));

        $baseQ = "SELECT COALESCE(SUM(distance),0) FROM trips WHERE is_completed=1";

        $s = $db->prepare("$baseQ AND start_date>=? AND start_date<=?");
        $s->execute([$seasonStart, $seasonEnd]);
        $sCurrent = (float)$s->fetchColumn();
        $s->closeCursor();

        $s->execute([$lastSeasonStart, $lastSeasonEnd]);
        $sLast = (float)$s->fetchColumn();
        $s->closeCursor();

        $s = $db->prepare("$baseQ AND YEAR(start_date)=? AND MONTH(start_date)=?");
        $s->execute([$curYear, $curMonth]);
        $mCurrent = (float)$s->fetchColumn();
        $s->closeCursor();

        $s->execute([$lastYear, $curMonth]);
        $mLast = (float)$s->fetchColumn();
        $s->closeCursor();

        $clubProgress = [
            'season_current'  => $sCurrent,
            'season_last'     => $sLast,
            'season_pct'      => $sLast > 0 ? min(100, round($sCurrent / $sLast * 100)) : ($sCurrent > 0 ? 100 : 0),
            'month_current'   => $mCurrent,
            'month_last'      => $mLast,
            'month_pct'       => $mLast  > 0 ? min(100, round($mCurrent  / $mLast  * 100)) : ($mCurrent  > 0 ? 100 : 0),
            'season_diff'     => $sCurrent - $sLast,
            'month_diff'      => $mCurrent - $mLast,
        ];
    } catch (\Throwable $e) {
        error_log('[Fahrtenbuch end.php] Club-Fortschritt Fehler: ' . $e->getMessage());
    }

    // ── Crew-Statistiken (pro Mitglied: Saison-km, Rang, Badges) ──
    $crewStats = [];
    if ($tripTable === 'trips') {
        try {
            $seasonStart = $seasonStart ?? getCurrentSeasonStartDate();
            $seasonEnd   = $seasonEnd   ?? getCurrentSeasonEndDate();
            $lastSeasonStart = $lastSeasonStart ?? date('Y-m-d', strtotime($seasonStart . ' -1 year'));
            $lastSeasonEnd   = $lastSeasonEnd   ?? date('Y-m-d', strtotime($seasonEnd   . ' -1 year'));

            // Crew-Mitglieder dieser Fahrt
            $crewStmt = $db->prepare("
                SELECT tc.member_id, m.first_name, m.last_name
                FROM trip_crew tc
                JOIN members m ON tc.member_id = m.id
                WHERE tc.trip_id = ? AND tc.member_id IS NOT NULL
            ");
            $crewStmt->execute([(int)$tripId]);
            $crewMembers = $crewStmt->fetchAll();

            // Gesamte Saison-Rangliste berechnen (alle Mitglieder)
            $rankStmt = $db->prepare("
                SELECT m.id, COALESCE(SUM(t.distance),0) AS km
                FROM members m
                JOIN trip_crew tc ON m.id = tc.member_id
                JOIN trips t ON tc.trip_id = t.id
                WHERE t.is_completed = 1 AND t.start_date BETWEEN ? AND ?
                GROUP BY m.id ORDER BY km DESC
            ");
            $rankStmt->execute([$seasonStart, $seasonEnd]);
            $fullRanking = $rankStmt->fetchAll();

            // Ranking-Map: member_id → { position, km, gap_to_next }
            $rankMap = [];
            foreach ($fullRanking as $idx => $r) {
                $gap = 0;
                if ($idx > 0) {
                    $gap = round((float)$fullRanking[$idx - 1]['km'] - (float)$r['km'], 1);
                }
                $rankMap[(int)$r['id']] = [
                    'rank' => $idx + 1,
                    'km'   => (float)$r['km'],
                    'gap'  => $gap,
                ];
            }

            foreach ($crewMembers as $cm) {
                $mid = (int)$cm['member_id'];

                // Saison-km (aus Rangliste oder 0)
                $seasonKm = $rankMap[$mid]['km'] ?? 0;
                $rank     = $rankMap[$mid]['rank'] ?? null;
                $gap      = $rankMap[$mid]['gap'] ?? 0;

                // Vorjahres-km
                $vjStmt = $db->prepare("
                    SELECT COALESCE(SUM(t.distance),0)
                    FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
                    WHERE tc.member_id = ? AND t.is_completed = 1
                    AND t.start_date BETWEEN ? AND ?
                ");
                $vjStmt->execute([$mid, $lastSeasonStart, $lastSeasonEnd]);
                $lastSeasonKm = (float)$vjStmt->fetchColumn();
                $diffKm       = round($seasonKm - $lastSeasonKm, 1);

                // Badge-Infos (Anzahl verdient + neue dieser Fahrt)
                $badgeCount  = 0;
                $newBadgeKeys = [];
                if ($badgesAvailable) {
                    try {
                        $bStmt = $db->prepare("SELECT COUNT(*) FROM member_badges WHERE member_id = ?");
                        $bStmt->execute([$mid]);
                        $badgeCount = (int)$bStmt->fetchColumn();

                        // Neue Badges dieser Fahrt (aus $allNewBadges wenn vorhanden)
                        if (isset($allNewBadges[$mid])) {
                            $newBadgeKeys = $allNewBadges[$mid];
                        }
                    } catch (\Throwable $e) {}
                }

                $crewStats[] = [
                    'member_id'      => $mid,
                    'name'           => $cm['first_name'] . ' ' . $cm['last_name'],
                    'season_km'      => round($seasonKm, 1),
                    'last_season_km' => round($lastSeasonKm, 1),
                    'diff_km'        => $diffKm,
                    'rank'           => $rank,
                    'rank_total'     => count($fullRanking),
                    'gap_to_next'    => $gap,
                    'badge_count'    => $badgeCount,
                    'new_badges'     => $newBadgeKeys,
                    'trip_distance'  => (float)$distance,
                ];
            }
        } catch (\Throwable $e) {
            error_log('[Fahrtenbuch end.php] Crew-Stats Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    // Boot-Notiz für Rückkehr laden
    $boatNoteEnd = '';
    try {
        $noteStmt = $db->prepare("
            SELECT b.note_end FROM boats b
            INNER JOIN $tripTable t ON t.boat_id = b.id
            WHERE t.id = :trip_id
        ");
        $noteStmt->execute(['trip_id' => $tripId]);
        $boatNoteEnd = $noteStmt->fetchColumn() ?: '';
    } catch (\Throwable $e) {
        // Spalte existiert evtl. noch nicht – kein Abbruch
    }

    // Tracker-Session beenden (fire-and-forget, kein Abbruch bei Fehler)
    try {
        $trackerSess = $db->prepare("
            SELECT id, tracker_id, status FROM tracker_sessions
            WHERE trip_id = :tid AND status IN ('assigned','active')
        ");
        $trackerSess->execute(['tid' => $tripId]);
        $tSess = $trackerSess->fetch();
        if ($tSess) {
            $db->prepare("UPDATE tracker_sessions SET status='finished', ended_at=NOW() WHERE id=:id")
               ->execute(['id' => $tSess['id']]);
            if ($tSess['status'] === 'assigned') {
                $db->prepare("UPDATE tracker SET status='idle', fast_poll_until=DATE_ADD(NOW(),INTERVAL 10 MINUTE) WHERE id=:id")
                   ->execute(['id' => $tSess['tracker_id']]);
            } else {
                $db->prepare("UPDATE tracker SET fast_poll_until=DATE_ADD(NOW(),INTERVAL 10 MINUTE) WHERE id=:id")
                   ->execute(['id' => $tSess['tracker_id']]);
            }
        }
    } catch (\Throwable $e) {
        error_log('[Fahrtenbuch end.php] Tracker-Session-Ende Fehler: ' . $e->getMessage());
    }

    jsonResponse([
        'success'            => true,
        'message'            => $groupEndedCount > 0
            ? 'Fahrt und ' . $groupEndedCount . ' weitere Gruppenfahrt(en) beendet'
            : 'Fahrt erfolgreich beendet',
        'new_badges_count'   => $newBadgesCount,
        'new_badges_html'    => $newBadgesHtml,
        'club_progress'      => $clubProgress,
        'crew_stats'         => $crewStats,
        'boat_note_end'      => $boatNoteEnd,
        'group_ended_count'  => $groupEndedCount,
        'trip_id'            => (int)$tripId,
        'has_tracker_session'=> !empty($tSess),
    ]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch end.php] Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => $e->getMessage() ?: get_class($e)], 500);
}
