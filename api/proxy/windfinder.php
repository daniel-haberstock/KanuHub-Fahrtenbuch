<?php
/**
 * Windfinder Widget Proxy
 * Holt die Windfinder-Vorhersageseite per cURL und gibt sie zentriert aus.
 * Umgeht Cross-Origin-Einschränkungen und ermöglicht eigenes Styling.
 */

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300'); // 5 Minuten cachen

$location = defined('WINDFINDER_LOCATION') ? WINDFINDER_LOCATION : 'konstanz';
$url = 'https://de.windfinder.com/widget/forecast/' . urlencode($location) . '?unit_wave=m&unit_rain=mm&unit_temperature=c&unit_wind=bft&unit_pressure=hPa&days=1&show_day=1&show_waves=0';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0',
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$html) {
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:20px;color:#666;text-align:center;">
        <p><i>Wettervorhersage konnte nicht geladen werden.</i></p>
        <p><a href="https://de.windfinder.com/forecast/<?= htmlspecialchars($location) ?>" target="_blank" rel="noopener">
            Direkt bei Windfinder öffnen &rarr;
        </a></p>
    </body></html>';
    exit;
}

// Relative URLs zu absoluten umwandeln
$html = preg_replace('/(src|href)="\/(?!\/)([^"]+)"/i', '$1="https://de.windfinder.com/$2"', $html);
$html = preg_replace('/(src|href)="(?!https?:\/\/|\/\/)(?!data:)([^"]+)"/i', '$1="https://de.windfinder.com/widget/forecast/$2"', $html);

// Zentrierung-CSS vor </head> injizieren
$centerCss = '<style>body{display:flex !important;justify-content:center !important;padding:12px 0 !important;margin:0 !important;}</style>';
$html = str_ireplace('</head>', $centerCss . '</head>', $html);

// Original-HTML komplett ausgeben (mit eigenem Head-CSS + Windfinder-CSS)
echo $html;
