<?php
/**
 * Proxy für KTTG Sturmwarnung
 * Umgeht X-Frame-Options / CORS-Einschränkungen der externen Seite
 */

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300'); // 5 Minuten cachen

$url = defined('STORM_WARNING_URL') && !empty(STORM_WARNING_URL) ? STORM_WARNING_URL : '';
if (empty($url)) {
    echo '<html><body style="font-family:sans-serif;padding:20px;color:#666;text-align:center;">
        <p><i>Keine Sturmwarnungs-URL konfiguriert.</i></p>
    </body></html>';
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => (defined('CLUB_NAME') ? CLUB_NAME : 'Fahrtenbuch') . '/1.0',
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$html) {
    echo '<html><body style="font-family:sans-serif;padding:20px;color:#666;">
        <p><i>Sturmwarnung konnte nicht geladen werden.</i></p>
        <p><a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">
            Direkt bei KTTG öffnen &rarr;
        </a></p>
    </body></html>';
    exit;
}

// Relative URLs zu absoluten umwandeln
$baseUrl = 'https://www.kttg.ch/kapo/htm/';
$html = preg_replace('/(src|href)="(?!https?:\/\/|\/\/)([^"]+)"/i', '$1="' . $baseUrl . '$2"', $html);

// In valides HTML5 wrappen (verhindert Quirks Mode)
echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><base href="' . $baseUrl . '"></head><body style="margin:0;padding:8px;">';
echo $html;
echo '</body></html>';
