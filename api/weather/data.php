<?php
/**
 * API: Aktuelle Wetterdaten (Wind, Pegel, Wassertemperatur)
 * Liest aus der DB-Tabelle weather_data.
 * Kein Auth erforderlich.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    // ── Wind — neueste Zeile ──────────────────────────────────────
    $stmt = $db->query(
        "SELECT data, fetched_at FROM weather_data
         WHERE type = 'wind' ORDER BY fetched_at DESC LIMIT 1"
    );
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $wind = null;
    if ($row) {
        $d    = json_decode($row['data'], true) ?? [];
        $wind = [
            'updated'  => $row['fetched_at'],
            'current'  => [
                'temp'          => $d['temp']          ?? null,
                'windspeed'     => $d['windspeed']      ?? null,
                'winddirection' => $d['winddirection']  ?? null,
                'windcode'      => $d['windcode']       ?? null,
            ],
            'forecast' => $d['forecast'] ?? [],
        ];
    }

    // ── Pegel — neueste Zeile + letzte 31 Tage ───────────────────
    $stmt  = $db->query(
        "SELECT data, fetched_at FROM weather_data
         WHERE type = 'pegel' ORDER BY fetched_at DESC LIMIT 31"
    );
    $rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pegel = null;
    if ($rows) {
        $first = json_decode($rows[0]['data'], true) ?? [];
        $pegel = [
            'updated'    => $rows[0]['fetched_at'],
            'current_cm' => $first['value_cm'] ?? null,
            'unit'       => 'cm ü.A.',
            'history'    => array_map(fn($r) => [
                'date'  => substr($r['fetched_at'], 0, 10),
                'value' => json_decode($r['data'], true)['value_cm'] ?? null,
            ], $rows),
        ];
    }

    // ── Wassertemperatur — neueste Zeile + letzte 31 Tage ────────
    $stmt      = $db->query(
        "SELECT data, fetched_at FROM weather_data
         WHERE type = 'watertemp' ORDER BY fetched_at DESC LIMIT 31"
    );
    $rows      = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $watertemp = null;
    if ($rows) {
        $first     = json_decode($rows[0]['data'], true) ?? [];
        $watertemp = [
            'updated' => $rows[0]['fetched_at'],
            'current' => $first['value'] ?? null,
            'unit'    => '°C',
            'history' => array_map(fn($r) => [
                'date'  => substr($r['fetched_at'], 0, 10),
                'value' => json_decode($r['data'], true)['value'] ?? null,
            ], $rows),
        ];
    }

    echo json_encode(
        compact('wind', 'pegel', 'watertemp'),
        JSON_UNESCAPED_UNICODE
    );

} catch (\Throwable $e) {
    error_log('[weather/data.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['wind' => null, 'pegel' => null, 'watertemp' => null]);
}
