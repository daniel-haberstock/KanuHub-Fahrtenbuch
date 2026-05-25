<?php
/**
 * API: Lokale Events (aus local_events Tabelle)
 *
 * GET /api/events/local.php
 * GET /api/events/local.php?days=14
 *
 * Query-Parameter:
 *   days — Tage im Voraus (default: 14, max: 90)
 *
 * Berechnet nächste Occurrences für wiederkehrende Events.
 * Gibt nur aktive Events zurück, die im Zeitraum liegen.
 *
 * Response:
 * {
 *   "success": true,
 *   "events": [
 *     {
 *       "id": 5,
 *       "name": "Paddeltreff",
 *       "description": "Gemeinsam paddeln...",
 *       "date": "2026-05-15",
 *       "time": "18:00",
 *       "date_time": "2026-05-15 18:00:00",
 *       "color": "#28a745",
 *       "icon": "bi-calendar-event",
 *       "is_recurring": true,
 *       "source": "local"
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

    $today = new DateTime();
    $endDate = (clone $today)->modify("+{$days} days");

    // Alle aktiven Events laden
    $stmt = $db->query("SELECT * FROM local_events WHERE is_active = 1");
    $localEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];

    foreach ($localEvents as $event) {
        if ($event['is_recurring']) {
            // Wiederkehrend: Alle Occurrences im Zeitfenster berechnen
            $occurrences = calculateOccurrences($event, $today, $endDate);
            foreach ($occurrences as $date) {
                $events[] = formatLocalEvent($event, $date);
            }
        } else {
            // Einmalig: Prüfen ob one_time_date im Zeitfenster liegt
            if (!empty($event['one_time_date'])) {
                $eventDate = new DateTime($event['one_time_date']);
                if ($eventDate >= $today && $eventDate <= $endDate) {
                    $events[] = formatLocalEvent($event, $event['one_time_date']);
                }
            }
        }
    }

    // Nach Datum+Zeit sortieren
    usort($events, function ($a, $b) {
        return strcmp($a['date_time'], $b['date_time']);
    });

    jsonResponse([
        'success' => true,
        'count'   => count($events),
        'events'  => $events,
    ]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch api/events/local] Fehler: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Fehler beim Laden der Events.'], 500);
}

/**
 * Berechnet alle Vorkommnisse eines wiederkehrenden Events im Zeitfenster.
 *
 * @param array    $event   Das Event aus der DB
 * @param DateTime $from    Startdatum
 * @param DateTime $to      Enddatum
 * @return string[] Array von Datumsstrings (Y-m-d)
 */
function calculateOccurrences(array $event, DateTime $from, DateTime $to): array
{
    $occurrences = [];
    $recurrenceType = $event['recurrence_type'];
    $recurrenceDay = (int)$event['recurrence_day'];

    // Gültigkeitszeitraum beachten
    $validFrom = !empty($event['valid_from']) ? new DateTime($event['valid_from']) : null;
    $validTo = !empty($event['valid_to']) ? new DateTime($event['valid_to']) : null;

    // Effektives Fenster: Schnittmenge aus [from, to] und [validFrom, validTo]
    $effectiveFrom = $from;
    $effectiveTo = $to;
    if ($validFrom && $validFrom > $effectiveFrom) {
        $effectiveFrom = $validFrom;
    }
    if ($validTo && $validTo < $effectiveTo) {
        $effectiveTo = $validTo;
    }

    if ($effectiveFrom > $effectiveTo) {
        return []; // Kein überlappender Zeitraum
    }

    if ($recurrenceType === 'weekly') {
        // Wochentag: 1=Mo, 2=Di, ..., 7=So (ISO 8601, wie PHP 'N')
        $current = clone $effectiveFrom;

        // Zum nächsten passenden Wochentag springen
        $currentDayOfWeek = (int)$current->format('N');
        $diff = $recurrenceDay - $currentDayOfWeek;
        if ($diff < 0) {
            $diff += 7;
        }
        if ($diff > 0) {
            $current->modify("+{$diff} days");
        }

        // Alle Wochen durchgehen
        while ($current <= $effectiveTo) {
            $occurrences[] = $current->format('Y-m-d');
            $current->modify('+7 days');
        }
    } elseif ($recurrenceType === 'monthly') {
        // Tag im Monat (1-31)
        $current = clone $effectiveFrom;
        $current->modify('first day of this month');

        while ($current <= $effectiveTo) {
            $year = (int)$current->format('Y');
            $month = (int)$current->format('m');
            $daysInMonth = (int)$current->format('t');

            // Tag existiert in diesem Monat?
            $day = min($recurrenceDay, $daysInMonth);
            $date = new DateTime("{$year}-{$month}-{$day}");

            if ($date >= $effectiveFrom && $date <= $effectiveTo) {
                $occurrences[] = $date->format('Y-m-d');
            }

            $current->modify('first day of next month');
        }
    }

    return $occurrences;
}

/**
 * Formatiert ein lokales Event für die API-Antwort.
 */
function formatLocalEvent(array $event, string $date): array
{
    $time = substr($event['start_time'], 0, 5); // HH:MM
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
