<?php
/**
 * TripEmailHelper – versendet Fahrt- und Wochen-Statistik-E-Mails
 */

require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

class TripEmailHelper
{
    /**
     * Versendet eine Benachrichtigungs-E-Mail und aktualisiert den Queue-Status.
     *
     * @param int    $notificationId  ID in email_notifications
     * @param int    $memberId
     * @param string $type            'trip' oder 'weekly'
     * @param string $referenceKey    'YYYY-MM-DD' für trip, 'YYYY-WXX' für weekly
     * @param PDO    $db
     */
    public static function send(int $notificationId, int $memberId, string $type, string $referenceKey, PDO $db): void
    {
        // ── Mitglied laden ────────────────────────────────────────────────
        $stmt = $db->prepare("SELECT first_name, last_name, email FROM members WHERE id = :id");
        $stmt->execute(['id' => $memberId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member || empty($member['email'])) {
            throw new \RuntimeException("Mitglied $memberId hat keine E-Mail-Adresse.");
        }

        // ── Statistik berechnen ───────────────────────────────────────────
        if ($type === 'trip') {
            $data = self::calcTripStats($memberId, $referenceKey, $db);
            $subject = 'Deine Paddel-Statistik vom ' . date('d.m.Y', strtotime($referenceKey));
        } else {
            $data = self::calcWeeklyStats($memberId, $referenceKey, $db);
            // Woche als lesbares Datum: Mo-So
            [$year, $week] = sscanf($referenceKey, '%d-W%d');
            $monday = new \DateTime();
            $monday->setISODate($year, $week, 1);
            $sunday = clone $monday;
            $sunday->modify('+6 days');
            $subject = 'Deine Wochen-Statistik ' . $monday->format('d.m.') . '–' . $sunday->format('d.m.Y');
        }

        $body = self::buildEmailBody($member['first_name'], $type, $data, $referenceKey);

        // ── E-Mail versenden ──────────────────────────────────────────────
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = EMAIL_SERVER;
        $mail->SMTPAuth   = true;
        $mail->Username   = NOTIFY_MAIL_FROM;
        $mail->Password   = NOTIFY_MAIL_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(NOTIFY_MAIL_FROM, NOTIFY_MAIL_FROM_NAME);
        $mail->addAddress($member['email'], $member['first_name'] . ' ' . $member['last_name']);

        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();

        // ── Status auf 'sent' setzen ──────────────────────────────────────
        $db->prepare("UPDATE email_notifications SET status='sent', sent_at=NOW() WHERE id=:id")
           ->execute(['id' => $notificationId]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Statistik: einzelne Fahrt (nach jeder Fahrt)
    // ─────────────────────────────────────────────────────────────────────
    private static function calcTripStats(int $memberId, string $date, PDO $db): array
    {
        // km heute
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(t.distance), 0)
            FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = :mid AND t.is_completed = 1 AND t.start_date = :date
        ");
        $stmt->execute(['mid' => $memberId, 'date' => $date]);
        $kmToday = (float)$stmt->fetchColumn();

        // Persönlicher Durchschnitt pro Fahrt (letzte 90 Tage, nicht heute)
        $stmt = $db->prepare("
            SELECT COALESCE(AVG(t.distance), 0)
            FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = :mid AND t.is_completed = 1
              AND t.start_date >= DATE_SUB(:date, INTERVAL 90 DAY)
              AND t.start_date < :date
        ");
        $stmt->execute(['mid' => $memberId, 'date' => $date]);
        $personalAvg = (float)$stmt->fetchColumn();

        // Club-Durchschnitt heute (alle Mitglieder die heute aktiv waren)
        $stmt = $db->prepare("
            SELECT COALESCE(AVG(daily_km), 0) FROM (
                SELECT tc.member_id, SUM(t.distance) AS daily_km
                FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
                WHERE t.is_completed = 1 AND t.start_date = :date
                GROUP BY tc.member_id
            ) sub
        ");
        $stmt->execute(['date' => $date]);
        $clubAvg = (float)$stmt->fetchColumn();

        return array_merge(
            self::calcRanking($memberId, $date, $db),
            [
                'km'           => $kmToday,
                'personal_avg' => $personalAvg,
                'club_avg'     => $clubAvg,
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Statistik: Wochen-Zusammenfassung
    // ─────────────────────────────────────────────────────────────────────
    private static function calcWeeklyStats(int $memberId, string $weekKey, PDO $db): array
    {
        // weekKey = "2026-W15"
        [$year, $week] = sscanf($weekKey, '%d-W%d');
        $monday = new \DateTime();
        $monday->setISODate($year, $week, 1);
        $sunday = clone $monday;
        $sunday->modify('+6 days');
        $mondayStr = $monday->format('Y-m-d');
        $sundayStr = $sunday->format('Y-m-d');

        // km diese Woche
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(t.distance), 0)
            FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
            WHERE tc.member_id = :mid AND t.is_completed = 1
              AND t.start_date BETWEEN :mon AND :sun
        ");
        $stmt->execute(['mid' => $memberId, 'mon' => $mondayStr, 'sun' => $sundayStr]);
        $kmWeek = (float)$stmt->fetchColumn();

        // Persönlicher Wochendurchschnitt (letzte 12 Wochen, nicht diese Woche)
        $stmt = $db->prepare("
            SELECT COALESCE(AVG(weekly_km), 0) FROM (
                SELECT YEARWEEK(t.start_date, 3) AS yw, SUM(t.distance) AS weekly_km
                FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
                WHERE tc.member_id = :mid AND t.is_completed = 1
                  AND t.start_date >= DATE_SUB(:mon, INTERVAL 84 DAY)
                  AND t.start_date < :mon
                GROUP BY YEARWEEK(t.start_date, 3)
            ) sub
        ");
        $stmt->execute(['mid' => $memberId, 'mon' => $mondayStr]);
        $personalAvg = (float)$stmt->fetchColumn();

        // Club-Wochendurchschnitt (alle Mitglieder diese Woche)
        $stmt = $db->prepare("
            SELECT COALESCE(AVG(weekly_km), 0) FROM (
                SELECT tc.member_id, SUM(t.distance) AS weekly_km
                FROM trips t JOIN trip_crew tc ON t.id = tc.trip_id
                WHERE t.is_completed = 1 AND t.start_date BETWEEN :mon AND :sun
                GROUP BY tc.member_id
            ) sub
        ");
        $stmt->execute(['mon' => $mondayStr, 'sun' => $sundayStr]);
        $clubAvg = (float)$stmt->fetchColumn();

        return array_merge(
            self::calcRanking($memberId, $sundayStr, $db),
            [
                'km'           => $kmWeek,
                'personal_avg' => $personalAvg,
                'club_avg'     => $clubAvg,
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Jahresrang berechnen (basierend auf Datum innerhalb Saison)
    // ─────────────────────────────────────────────────────────────────────
    private static function calcRanking(int $memberId, string $asOfDate, PDO $db): array
    {
        // Saison bestimmen (1.10. - 30.9.)
        $year  = (int)date('Y', strtotime($asOfDate));
        $month = (int)date('m', strtotime($asOfDate));
        if ($month >= 10) {
            $seasonStart = $year . '-10-01';
            $seasonEnd   = ($year + 1) . '-09-30';
        } else {
            $seasonStart = ($year - 1) . '-10-01';
            $seasonEnd   = $year . '-09-30';
        }

        // Rangliste (nur sichtbare Mitglieder)
        $stmt = $db->prepare("
            SELECT m.id, COALESCE(SUM(t.distance), 0) AS season_km
            FROM members m
            JOIN trip_crew tc ON m.id = tc.member_id
            JOIN trips t ON tc.trip_id = t.id
            WHERE t.is_completed = 1
              AND t.start_date BETWEEN :start AND :end
              AND (m.hide_statistics IS NULL OR m.hide_statistics != 1)
            GROUP BY m.id
            ORDER BY season_km DESC
        ");
        $stmt->execute(['start' => $seasonStart, 'end' => $seasonEnd]);
        $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $myRank     = null;
        $mySeasonKm = 0.0;
        $kmGap      = null;
        $gapLabel   = '';

        foreach ($ranking as $pos => $row) {
            if ((int)$row['id'] === $memberId) {
                $myRank     = $pos + 1;
                $mySeasonKm = (float)$row['season_km'];

                if ($myRank === 1) {
                    // Abstand zu Platz 2
                    if (isset($ranking[1])) {
                        $kmGap    = round($mySeasonKm - (float)$ranking[1]['season_km'], 1);
                        $gapLabel = 'lead'; // führt
                    }
                } else {
                    // Fehlende km zu Platz drüber
                    $kmGap    = round((float)$ranking[$pos - 1]['season_km'] - $mySeasonKm, 1);
                    $gapLabel = 'behind'; // fehlt noch
                }
                break;
            }
        }

        return [
            'rank'        => $myRank,
            'season_km'   => $mySeasonKm,
            'km_gap'      => $kmGap,
            'gap_label'   => $gapLabel,
            'total_ranked'=> count($ranking),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // E-Mail-Text zusammenbauen
    // ─────────────────────────────────────────────────────────────────────
    private static function buildEmailBody(string $firstName, string $type, array $d, string $referenceKey): string
    {
        $isTrip  = ($type === 'trip');
        $km      = number_format($d['km'], 1, ',', '.');
        $skm     = number_format($d['season_km'], 1, ',', '.');

        // Vergleich zum persönlichen Durchschnitt
        $personalCmp = self::comparePercent($d['km'], $d['personal_avg']);
        // Vergleich zum Club
        $clubCmp = self::comparePercent($d['km'], $d['club_avg']);

        // Rang-Zeile
        if ($d['rank'] === null) {
            $rankLine = "Du bist in dieser Saison noch nicht in der Rangliste vertreten.";
        } elseif ($d['gap_label'] === 'lead') {
            $rankLine = "Du führst die Rangliste an! Dein Vorsprung auf Platz 2 beträgt " . number_format($d['km_gap'], 1, ',', '.') . " km. Stark!";
        } else {
            $rankLine = "Dir fehlen noch " . number_format($d['km_gap'], 1, ',', '.') . " km bis Platz " . ($d['rank'] - 1) . ".";
        }

        if ($isTrip) {
            $intro     = "heute bist du $km km gepaddelt – super gemacht!";
            $zeitraum  = "heute";
        } else {
            [$year, $week] = sscanf($referenceKey, '%d-W%d');
            $monday = new \DateTime();
            $monday->setISODate($year, $week, 1);
            $sunday = clone $monday;
            $sunday->modify('+6 days');
            $intro    = "diese Woche (". $monday->format('d.m.') . "–" . $sunday->format('d.m.Y') . ") bist du insgesamt $km km gepaddelt – Respekt!";
            $zeitraum = "diese Woche";
        }

        $body  = "Hallo $firstName,\n\n";
        $body .= "$intro\n\n";
        $body .= "Dein aktueller Stand im Jahresrang (Paddeljahr 1.10.\u{2013}30.9.):\n";
        if ($d['rank'] !== null) {
            $body .= "  \u{2192} Platz {$d['rank']} von {$d['total_ranked']} mit $skm km in dieser Saison.\n";
        }
        $body .= "  \u{2192} $rankLine\n\n";
        $body .= "Dein Vergleich:\n";
        $body .= "  \u{2192} $zeitraum bist du $personalCmp wie sonst unterwegs gewesen.\n";
        $body .= "  \u{2192} Im Vergleich zu anderen Mitgliedern warst du $zeitraum $clubCmp.\n\n";
        $body .= "Viel Spaß beim nächsten Ausflug!\n";
        $body .= "Dein " . NOTIFY_MAIL_FROM_NAME;

        return $body;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Prozentualer Vergleich als lesbarer Text
    // ─────────────────────────────────────────────────────────────────────
    private static function comparePercent(float $value, float $avg): string
    {
        if ($avg <= 0) {
            return "zum ersten Mal aktiv";
        }
        $pct = round((($value - $avg) / $avg) * 100);
        if ($pct > 5) {
            return abs($pct) . "% weiter als";
        } elseif ($pct < -5) {
            return abs($pct) . "% kürzer als";
        } else {
            return "genauso weit wie";
        }
    }
}
