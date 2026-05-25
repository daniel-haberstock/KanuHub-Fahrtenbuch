<?php
/**
 * Pegel-Driver: Bodensee
 *
 * Implementiert pegel_fetch() und watertemp_fetch()
 * für die fetch-weather.php Cron-Infrastruktur.
 *
 * Für andere Gewässer diese Datei kopieren und anpassen:
 *   admin/pegel-drivers/rhein.php, neckar.php, ...
 * Danach PEGEL_DRIVER in config.php auf den neuen Namen setzen.
 *
 * Benötigte Config-Konstanten:
 *   PEGEL_WSV_STATION  — Stationsname in Pegelonline WSV (z.B. 'KONSTANZ')
 *                        Alle Stationen: pegelonline.wsv.de/webservices/rest-api/v2/stations.json
 *   WATERTEMP_URL      — VOWIS-Stationswrapper-URL
 */

/**
 * Liefert aktuellen Pegel in Zentimetern.
 * Quelle: Pegelonline WSV (Wasser- und Schifffahrtsverwaltung des Bundes)
 * Wert kommt direkt in cm — keine Umrechnung nötig.
 */
function pegel_fetch(): ?int {
    $station = defined('PEGEL_WSV_STATION') ? trim(PEGEL_WSV_STATION) : '';
    if ($station === '') return null;

    $url  = 'https://www.pegelonline.wsv.de/webservices/rest-api/v2/stations/'
          . urlencode($station) . '/W/currentmeasurement.json';
    $body = fw_curl($url);
    if (!$body) return null;

    $data = json_decode($body, true);
    $val  = $data['value'] ?? $data['currentMeasurement']['value'] ?? null;
    if ($val === null) {
        fw_log('Pegel DEBUG: ' . substr($body, 0, 300));
        return null;
    }

    $delta = defined('PEGEL_DELTA_CM') ? (int)PEGEL_DELTA_CM : 0;
    return (int)round((float)$val) + $delta;
}

/**
 * Liefert aktuelle Wassertemperatur in °C.
 * Quelle: VOWIS Vorarlberg (stationswrapper/bodensee)
 */
function watertemp_fetch(): ?float {
    $url = defined('WATERTEMP_URL') ? WATERTEMP_URL : '';
    if ($url === '') return null;

    $body = fw_curl($url);
    if (!$body) return null;

    // Versuch 1: eingebettetes JSON-Objekt (window.__DATA__ oder ähnlich)
    if (preg_match('/window\.__DATA__\s*=\s*(\{.+?\});/s', $body, $m)) {
        $jd = json_decode($m[1], true);
        $v  = $jd['temperature'] ?? $jd['temp'] ?? $jd['wasser_temp'] ?? null;
        if ($v !== null) return (float)$v;
    }

    // Versuch 2: JSON-Array in <script>
    if (preg_match('/<script[^>]*>\s*(\[\{.+?\}\])\s*<\/script>/s', $body, $m)) {
        $arr = json_decode($m[1], true);
        if (is_array($arr)) {
            foreach ($arr as $item) {
                if (isset($item['temperature'])) return (float)$item['temperature'];
                if (isset($item['temp']))        return (float)$item['temp'];
            }
        }
    }

    // Versuch 3: Regex auf sichtbaren Temperaturwert (z.B. "12,4 °C" oder "12.4°C")
    if (preg_match('/(\d{1,2}[.,]\d)\s*°?C/u', strip_tags($body), $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }

    return null;
}
