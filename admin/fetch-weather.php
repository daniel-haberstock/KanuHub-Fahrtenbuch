<?php
/**
 * Wetterdaten-Fetch — Wind, Pegel, Wassertemperatur
 *
 * Schreibt alle Messwerte in die DB-Tabelle weather_data (type, data JSON, fetched_at).
 * Wind wird bei jedem Aufruf gespeichert (stündlicher Cron empfohlen).
 * Pegel + Wassertemperatur nur 1× täglich.
 *
 * Pegel + Wassertemp-Logik ist in einem Driver-File gekapselt:
 *   admin/pegel-drivers/{PEGEL_DRIVER}.php
 * Für andere Gewässer: Datei kopieren, anpassen, PEGEL_DRIVER in config.php setzen.
 *
 * Aufruf:
 *   CLI:             php admin/fetch-weather.php
 *   HTTP mit Token:  /admin/fetch-weather.php?token=SYNC_SECRET_TOKEN
 *   Browser (Login): /admin/fetch-weather.php?debug=1
 *
 * Cronjob (stündlich):
 *   0 * * * * /usr/bin/php /pfad/admin/fetch-weather.php >> /pfad/admin/fetch-weather.log 2>&1
 */

if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/../config/config.php';
} elseif (
    isset($_GET['token']) &&
    defined('SYNC_SECRET_TOKEN') &&
    SYNC_SECRET_TOKEN !== '' &&
    hash_equals(SYNC_SECRET_TOKEN, $_GET['token'])
) {
    require_once __DIR__ . '/../config/config.php';
} else {
    require_once __DIR__ . '/auth_check.php';
}

require_once __DIR__ . '/../config/database.php';

$debug = isset($_GET['debug']) || php_sapi_name() === 'cli';
$db    = Database::getInstance()->getConnection();

// ── Hilfsfunktionen ───────────────────────────────────────────────────────

function fw_log(string $msg): void {
    global $debug;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($debug) echo $line . "\n";
    error_log('[fetch-weather] ' . $msg);
}

function fw_curl(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Fahrtenbuch/2026)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json,text/html,*/*'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        fw_log("HTTP $code bei $url" . ($err ? " — $err" : ''));
        return null;
    }
    return $body;
}

function fw_insert(PDO $db, string $type, array $data): void {
    $stmt = $db->prepare(
        'INSERT INTO weather_data (type, data, fetched_at) VALUES (?, ?, NOW())'
    );
    $stmt->execute([$type, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

function fw_has_today(PDO $db, string $type): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM weather_data WHERE type = ? AND DATE(fetched_at) = CURDATE()'
    );
    $stmt->execute([$type]);
    return (int)$stmt->fetchColumn() > 0;
}

// ── Konfig ────────────────────────────────────────────────────────────────

$lat        = defined('WEATHER_LAT')  ? WEATHER_LAT  : '47.67';
$lon        = defined('WEATHER_LON')  ? WEATHER_LON  : '9.17';
$driverName = defined('PEGEL_DRIVER') ? PEGEL_DRIVER : '';
$driverFile = $driverName !== ''
    ? __DIR__ . '/pegel-drivers/' . basename($driverName) . '.php'
    : '';

fw_log('=== Wetterdaten-Fetch gestartet ===');

// Driver laden
if ($driverFile !== '' && file_exists($driverFile)) {
    require_once $driverFile;
    fw_log('Driver geladen: ' . $driverName);
} elseif ($driverName !== '') {
    fw_log('Driver nicht gefunden: ' . $driverFile);
}

// ── 1. Wind (Open-Meteo) — immer speichern ────────────────────────────────

fw_log('Wind: Open-Meteo abrufen...');

$omUrl = 'https://api.open-meteo.com/v1/forecast'
    . '?latitude='  . urlencode($lat)
    . '&longitude=' . urlencode($lon)
    . '&current_weather=true'
    . '&hourly=windspeed_10m,winddirection_10m,windgusts_10m,temperature_2m,weathercode'
    . '&forecast_days=2'
    . '&timezone=Europe%2FBerlin';

$omBody = fw_curl($omUrl);
if ($omBody) {
    $om = json_decode($omBody, true);
    if (isset($om['current_weather'], $om['hourly'])) {
        $cw = $om['current_weather'];

        $times  = $om['hourly']['time']              ?? [];
        $speeds = $om['hourly']['windspeed_10m']     ?? [];
        $dirs   = $om['hourly']['winddirection_10m'] ?? [];
        $gusts  = $om['hourly']['windgusts_10m']     ?? [];
        $temps  = $om['hourly']['temperature_2m']    ?? [];
        $wcodes = $om['hourly']['weathercode']       ?? [];

        $nowTs    = time();
        $forecast = [];
        foreach ($times as $i => $t) {
            if (strtotime($t) < $nowTs - 1800) continue;
            if (count($forecast) >= 24) break;
            $forecast[] = [
                'time'  => $t,
                'speed' => $speeds[$i] ?? null,
                'dir'   => $dirs[$i]   ?? null,
                'gust'  => $gusts[$i]  ?? null,
                'temp'  => $temps[$i]  ?? null,
                'wcode' => isset($wcodes[$i]) ? (int)$wcodes[$i] : null,
            ];
        }

        fw_insert($db, 'wind', [
            'temp'          => $cw['temperature']  ?? null,
            'windspeed'     => $cw['windspeed']     ?? null,
            'winddirection' => $cw['winddirection'] ?? null,
            'windcode'      => $cw['weathercode']   ?? null,
            'forecast'      => $forecast,
        ]);
        fw_log('Wind: OK — ' . ($cw['windspeed'] ?? '?') . ' km/h');
    } else {
        fw_log('Wind: Unerwartetes JSON-Format');
    }
} else {
    fw_log('Wind: Abruf fehlgeschlagen');
}

// ── 2. Pegel — 1× täglich via Driver ─────────────────────────────────────

if (function_exists('pegel_fetch')) {
    if (!fw_has_today($db, 'pegel')) {
        fw_log('Pegel: abrufen (Driver: ' . $driverName . ')...');
        $cmVal = pegel_fetch();
        if ($cmVal !== null) {
            fw_insert($db, 'pegel', ['value_cm' => $cmVal]);
            fw_log('Pegel: OK — ' . $cmVal . ' cm');
        } else {
            fw_log('Pegel: kein Wert vom Driver zurückgegeben');
        }
    } else {
        fw_log('Pegel: bereits heute gespeichert, kein Abruf nötig');
    }
} else {
    fw_log('Pegel: kein Driver geladen (pegel_fetch nicht definiert)');
}

// ── 3. Wassertemperatur — 1× täglich via Driver ───────────────────────────

if (function_exists('watertemp_fetch')) {
    if (!fw_has_today($db, 'watertemp')) {
        fw_log('Wassertemp: abrufen (Driver: ' . $driverName . ')...');
        $wtVal = watertemp_fetch();
        if ($wtVal !== null) {
            fw_insert($db, 'watertemp', ['value' => $wtVal]);
            fw_log('Wassertemp: OK — ' . $wtVal . ' °C');
        } else {
            fw_log('Wassertemp: kein Wert vom Driver zurückgegeben');
        }
    } else {
        fw_log('Wassertemp: bereits heute gespeichert, kein Abruf nötig');
    }
} else {
    fw_log('Wassertemp: kein Driver geladen (watertemp_fetch nicht definiert)');
}

fw_log('=== Fertig ===');
