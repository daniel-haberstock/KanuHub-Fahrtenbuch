<?php
/**
 * API: Kombinierte Event-Liste (REDAXO-Termine + Lokale Events)
 *
 * GET /api/events/upcoming.php
 * GET /api/events/upcoming.php?days=14
 *
 * Query-Parameter:
 *   days — Tage im Voraus (default: 14, max: 90)
 *
 * Kombiniert REDAXO-Termine (wenn konfiguriert) mit lokalen Events
 * zu einer einheitlichen, chronologisch sortierten Liste.
 * Prüft ob für Events bereits eine trip_group existiert.
 *
 * Response:
 * {
 *   "success": true,
 *   "events": [
 *     {
 *       "id": 42,
 *       "source": "redaxo",
 *       "name": "Wanderfahrt",
 *       "description": "Treffpunkt am Steg...",
 *       "date": "2026-05-15",
 *       "time": "18:00",
 *       "date_time": "2026-05-15 18:00:00",
 *       "color": "#fd7e14",
 *       "icon": "bi-calendar-event",
 *       "can_join": true,
 *       "has_group": true,
 *       "trip_group_id": 7,
 *       "active_trips": 3,
 *       "verantwortlich": "Max Mustermann",
 *       "kategorie": "1"
 *     }
 *   ]
 * }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Nur GET erlaubt'], 405);
}

try {
    $days = min(max((int)($_GET['days'] ?? 14), 1), 90);
    $db = Database::getInstance()->getConnection();
    $now = new DateTime();
    $events = [];

    // ── 1. Lokale Events laden (direkt berechnet, kein HTTP-Call) ───────
    $localEvents = getLocalEvents($db, $days);
    foreach ($localEvents as $le) {
        $le['can_join'] = canJoinEvent($le['date_time']);
        // Trip-Group-Info hinzufügen
        $groupInfo = findTripGroup($db, 'local_event', $le['id'], $le['date']);
        $le['has_group'] = $groupInfo !== null;
        $le['trip_group_id'] = $groupInfo['id'] ?? null;
        $le['active_trips'] = $groupInfo['active_trips'] ?? 0;
        $le['verantwortlich'] = '';
        $le['kategorie'] = '';
        $events[] = $le;
    }

    // ── 2. REDAXO-Termine laden (wenn konfiguriert) ─────────────────────
    if (defined('REDAXO_API_TOKEN') && !empty(REDAXO_API_TOKEN)
        && defined('REDAXO_TERMINE_API_URL') && !empty(REDAXO_TERMINE_API_URL)) {

        $months = max(1, ceil($days / 30));
        $termineResult = fetchTermine(20, $months);

        if ($termineResult && !empty($termineResult['termine'])) {
            $endDate = (clone $now)->modify("+{$days} days");

            foreach ($termineResult['termine'] as $termin) {
                $terminDate = new DateTime($termin['datum']);

                // Nur Events im Zeitfenster
                if ($terminDate < $now || $terminDate > $endDate) {
                    continue;
                }

                // date_time auswerten: wenn vorhanden und nicht Mitternacht → konkrete Uhrzeit
                $hasTime = false;
                $time = '';
                $dateTime = $termin['datum'] . ' 00:00:00';

                if (!empty($termin['date_time'])) {
                    $dt = new DateTime($termin['date_time']);
                    $timeStr = $dt->format('H:i:s');
                    if ($timeStr !== '00:00:00') {
                        $hasTime = true;
                        $time = $dt->format('H:i');
                        $dateTime = $termin['date_time'];
                    }
                }

                $event = [
                    'id'             => (int)$termin['id'],
                    'source'         => 'redaxo',
                    'name'           => $termin['bezeichnung'],
                    'description'    => $termin['beschreibung'] ?? '',
                    'date'           => $termin['datum'],
                    'time'           => $time,
                    'date_time'      => $dateTime,
                    'color'          => $hasTime ? '#fd7e14' : '#0d6efd', // Orange wenn Uhrzeit, Blau sonst
                    'icon'           => 'bi-calendar-event',
                    'is_recurring'   => false,
                    'can_join'       => $hasTime ? canJoinEvent($dateTime) : false,
                    'verantwortlich' => $termin['verantwortlich'] ?? '',
                    'kategorie'      => $termin['kategorie'] ?? '',
                ];

                // Trip-Group-Info
                $groupInfo = findTripGroup($db, 'termin', $termin['id'], $termin['datum']);
                $event['has_group'] = $groupInfo !== null;
                $event['trip_group_id'] = $groupInfo['id'] ?? null;
                $event['active_trips'] = $groupInfo['active_trips'] ?? 0;

                $events[] = $event;
            }
        }
    }

    // ── 3. Chronologisch sortieren und ggf. begrenzen ─────────────────
    usort($events, function ($a, $b) {
        return strcmp($a['date_time'], $b['date_time']);
    });

    // limit: Default 4 (Dashboard), max 200 (App-Liste mit wiederkehrenden Events).
    // per_event_limit: Falls gesetzt, max. N Vorkommnisse PRO Event-ID (für App).
    $limit = min(max((int)($_GET['limit'] ?? 4), 1), 200);

    $perEventLimit = (int)($_GET['per_event_limit'] ?? 0);
    if ($perEventLimit > 0) {
        $perEventLimit = min($perEventLimit, 20);
        $countByEventId = [];
        $events = array_values(array_filter($events, function($e) use (&$countByEventId, $perEventLimit) {
            $eid = (string)($e['id'] ?? '');
            if ($eid === '') return false;
            $countByEventId[$eid] = ($countByEventId[$eid] ?? 0) + 1;
            return $countByEventId[$eid] <= $perEventLimit;
        }));
    }

    $events = array_slice($events, 0, $limit);

    jsonResponse([
        'success' => true,
        'count'   => count($events),
        'events'  => $events,
    ]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/events/upcoming] Fehler: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Fehler beim Laden der Events.'], 500);
}

// ═══════════════════════════════════════════════════════════════════════════
// Hilfsfunktionen (inline, da auch von local.php verwendet)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Lädt und berechnet alle lokalen Events für die nächsten $days Tage.
 */
function getLocalEvents(PDO $db, int $days): array
{
    $today = new DateTime();
    $endDate = (clone $today)->modify("+{$days} days");

    $stmt = $db->query("SELECT * FROM local_events WHERE is_active = 1");
    $localEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($localEvents as $event) {
        if ($event['is_recurring']) {
            $occurrences = calculateRecurrences($event, $today, $endDate);
            foreach ($occurrences as $date) {
                $events[] = buildLocalEvent($event, $date);
            }
        } else {
            if (!empty($event['one_time_date'])) {
                $eventDate = new DateTime($event['one_time_date']);
                if ($eventDate >= $today && $eventDate <= $endDate) {
                    $events[] = buildLocalEvent($event, $event['one_time_date']);
                }
            }
        }
    }

    return $events;
}

/**
 * Berechnet Occurrences eines wiederkehrenden Events.
 */
function calculateRecurrences(array $event, DateTime $from, DateTime $to): array
{
    $occurrences = [];
    $type = $event['recurrence_type'];
    $day = (int)$event['recurrence_day'];

    $validFrom = !empty($event['valid_from']) ? new DateTime($event['valid_from']) : null;
    $validTo = !empty($event['valid_to']) ? new DateTime($event['valid_to']) : null;

    $effFrom = $from;
    $effTo = $to;
    if ($validFrom && $validFrom > $effFrom) $effFrom = $validFrom;
    if ($validTo && $validTo < $effTo) $effTo = $validTo;
    if ($effFrom > $effTo) return [];

    if ($type === 'weekly') {
        $current = clone $effFrom;
        $currentDay = (int)$current->format('N');
        $diff = $day - $currentDay;
        if ($diff < 0) $diff += 7;
        if ($diff > 0) $current->modify("+{$diff} days");

        while ($current <= $effTo) {
            $occurrences[] = $current->format('Y-m-d');
            $current->modify('+7 days');
        }
    } elseif ($type === 'monthly') {
        $current = clone $effFrom;
        $current->modify('first day of this month');

        while ($current <= $effTo) {
            $daysInMonth = (int)$current->format('t');
            $actualDay = min($day, $daysInMonth);
            $date = new DateTime($current->format('Y-m') . '-' . str_pad($actualDay, 2, '0', STR_PAD_LEFT));

            if ($date >= $effFrom && $date <= $effTo) {
                $occurrences[] = $date->format('Y-m-d');
            }
            $current->modify('first day of next month');
        }
    }

    return $occurrences;
}

/**
 * Formatiert ein lokales Event.
 */
function buildLocalEvent(array $event, string $date): array
{
    $time = substr($event['start_time'], 0, 5);
    return [
        'id'           => (int)$event['id'],
        'source'       => 'local',
        'name'         => $event['name'],
        'description'  => $event['description'] ?: '',
        'date'         => $date,
        'time'         => $time,
        'date_time'    => $date . ' ' . $event['start_time'],
        'color'        => $event['color'],
        'icon'         => $event['icon'],
        'is_recurring' => (bool)$event['is_recurring'],
    ];
}

/**
 * Prüft ob ein Event im Teilnahme-Zeitfenster liegt.
 * Fenster: 1 Stunde vor bis 20 Minuten nach Startzeit.
 */
function canJoinEvent(string $dateTimeStr): bool
{
    if (empty($dateTimeStr) || strpos($dateTimeStr, '00:00:00') !== false) {
        return false; // Keine konkrete Uhrzeit
    }

    $now = new DateTime();
    $eventTime = new DateTime($dateTimeStr);
    $earliest = (clone $eventTime)->modify('-1 hour');
    $latest = (clone $eventTime)->modify('+20 minutes');

    return $now >= $earliest && $now <= $latest;
}

/**
 * Sucht eine existierende trip_group für ein Event an einem bestimmten Datum.
 * Gibt null zurück wenn keine existiert, oder ein Array mit id und active_trips.
 */
function findTripGroup(PDO $db, string $sourceType, int $sourceId, string $date): ?array
{
    $stmt = $db->prepare("
        SELECT tg.id,
               (SELECT COUNT(*) FROM trips t WHERE t.trip_group_id = tg.id AND t.end_date IS NULL) as active_trips
        FROM trip_groups tg
        WHERE tg.source_type = :source_type
          AND tg.source_id = :source_id
          AND DATE(tg.started_at) = :date
          AND tg.is_completed = 0
        HAVING active_trips > 0
        ORDER BY tg.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([
        'source_type' => $sourceType,
        'source_id'   => $sourceId,
        'date'        => $date,
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}
