<?php
/**
 * Fahrtenbuch — Beispiel-Konfiguration
 *
 * 1. Diese Datei kopieren:  cp config.sample.php config.php
 * 2. Werte unten anpassen.
 * 3. config.php NIE committen (steht in .gitignore).
 */

// ──────────────────────────────────────────────────────────────────────────
//  VEREIN — Branding
// ──────────────────────────────────────────────────────────────────────────
define('CLUB_NAME',          'Mein Verein');             // Kurzname (Header)
define('CLUB_FULL_NAME',     'Mein Kanuverein e.V.');    // Voller Vereinsname
define('CLUB_BOAT_PREFIX',   'MV');                       // Vereinsboot-Prefix
define('CLUB_LOGO_URL',      '/assets/logo.svg');         // Logo-Pfad oder externe URL
define('CLUB_EMAIL',         'noreply@kanuhub.de');      // Absender-E-Mail

// ──────────────────────────────────────────────────────────────────────────
//  DATENBANK
// ──────────────────────────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'fahrtenbuch');
define('DB_USER',    'fahrtenbuch');
define('DB_PASS',    'CHANGE_ME');
define('DB_CHARSET', 'utf8mb4');

// ──────────────────────────────────────────────────────────────────────────
//  ALLGEMEIN
// ──────────────────────────────────────────────────────────────────────────
date_default_timezone_set('Europe/Berlin');
define('BASE_URL',              '/');
define('APP_BASE_PATH',         '');                      // Leer wenn am Domain-Root, sonst z.B. '/fahrtenbuch'
define('ADMIN_SESSION_TIMEOUT', 1800);                    // Admin-Login: 30 Min
define('STATS_SESSION_TIMEOUT', 300);                     // Mitgliederbereich Auto-Logout: 5 Min

// Saison-Stichtag (z.B. 1. Oktober = Saisonwechsel)
define('SEASON_START_MONTH', 10);
define('SEASON_START_DAY',    1);

// ──────────────────────────────────────────────────────────────────────────
//  AUTHENTIFIZIERUNG — Magic-Link (Standard)
// ──────────────────────────────────────────────────────────────────────────
// Mitglieder loggen sich ohne Passwort ein: E-Mail eingeben → Link per Mail →
// Klick = eingeloggt. Erfordert funktionierenden SMTP unten.
define('MAGIC_LINK_TTL_MINUTES', 15);                     // Gültigkeitsdauer des Links
define('MAGIC_LINK_PUBLIC_URL',  '');                     // z.B. 'https://fahrtenbuch.kanuhub.de' (Pflicht)

// ──────────────────────────────────────────────────────────────────────────
//  E-MAIL (SMTP) — für Magic-Link, Benachrichtigungen, Feedback
// ──────────────────────────────────────────────────────────────────────────
define('SMTP_HOST',       'smtp.kanuhub.de');
define('SMTP_PORT',       587);
define('SMTP_USER',       'fahrtenbuch@kanuhub.de');
define('SMTP_PASS',       'CHANGE_ME');
define('SMTP_SECURE',     'tls');                          // 'tls' oder 'ssl'
define('NOTIFY_MAIL_FROM',      'fahrtenbuch@kanuhub.de');
define('NOTIFY_MAIL_FROM_NAME', CLUB_NAME . ' Fahrtenbuch');

// Adresse für Feedback-Formular + Schadensmeldungen
define('EMAIL_EMPFAENGER', 'vorstand@kanuhub.de');

// ──────────────────────────────────────────────────────────────────────────
//  WETTER — Optional, Standortdaten einsetzen oder leer lassen
// ──────────────────────────────────────────────────────────────────────────
// Open-Meteo (kostenlos, kein API-Key) für Wind/Temperatur
define('WEATHER_LAT', '');                                // z.B. '47.7735'  (leer = deaktiviert)
define('WEATHER_LON', '');                                // z.B. '8.9993'

// Windfinder-Widget (HTML-Embed)
define('WINDFINDER_LOCATION', '');                        // z.B. 'iznang_bodensee'
define('WINDFINDER_LABEL',    '');                        // Anzeigename

// Sturmwarnung-Link (externe URL, wird im Wetter-Modal eingebettet)
define('STORM_WARNING_ENABLED', false);
define('STORM_WARNING_URL',     '');
define('STORM_WARNING_LABEL',   '');

// Pegel-Driver (admin/pegel-drivers/) — leer = deaktiviert
// Mitgelieferte Driver: 'bodensee'
define('PEGEL_DRIVER',      '');
define('PEGEL_WSV_STATION', '');                          // z.B. 'KONSTANZ'
define('PEGEL_DELTA_CM',    0);                           // Korrektur-Offset cm

// Wassertemperatur-API (Treiber-spezifisch)
define('WATERTEMP_URL',     '');

// Farbschwellen Header-Icons
define('WEATHER_WATERTEMP_WARM',  17);                    // °C blau→grün
define('WEATHER_WIND_WARN',       25);                    // km/h grün→orange
define('WEATHER_WIND_DANGER',     35);                    // km/h orange→rot
define('WEATHER_PEGEL_LOW',      290);                    // cm orange→grün

// ──────────────────────────────────────────────────────────────────────────
//  SESSION-SICHERHEIT
// ──────────────────────────────────────────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure',    1);                    // 1 wenn HTTPS, 0 für lokales http
ini_set('session.cookie_samesite', 'Lax');

// ──────────────────────────────────────────────────────────────────────────
//  FEHLERBEHANDLUNG
// ──────────────────────────────────────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors',     1);

// ──────────────────────────────────────────────────────────────────────────
//  REDAXO-INTEGRATION (OPTIONAL)
// ──────────────────────────────────────────────────────────────────────────
// Wenn ihr REDAXO + YCom als zentrale Mitglieder-/Login-Verwaltung nutzt,
// hier die API-URLs eintragen. Dann ersetzt REDAXO-Auth den Magic-Link.
// Plugin in /redaxo_plugin/fahrtenbuch_auth/ installieren.
// Leer lassen = Magic-Link-Auth wird verwendet.
define('REDAXO_API_TOKEN',         '');
define('REDAXO_AUTH_API_URL',      '');                    // z.B. https://kanuhub.de/api/fahrtenbuch/auth
define('REDAXO_TERMINE_API_URL',   '');                    // optional
define('REDAXO_BADGE_BASE_URL',    '');                    // optional, ohne REDAXO: /assets/badges/

// Direkter DB-Zugriff auf YCom (für Mitglieder-Sync und Validierungs-Skripte
// in admin/ycom-*.php). Nur setzen, wenn ihr die YCom-DB direkt erreichen
// dürft. Sonst leer lassen — dann sind die Sync-Skripte deaktiviert.
define('YCOM_DB_HOST',             '');                    // z.B. localhost oder rdbms.example.de
define('YCOM_DB_NAME',             '');
define('YCOM_DB_USER',             '');
define('YCOM_DB_PASS',             '');

// ──────────────────────────────────────────────────────────────────────────
//  EFB-INTEGRATION (OPTIONAL — Deutscher Kanuverband)
// ──────────────────────────────────────────────────────────────────────────
// Sync von Fahrten ins elektronische Fahrtenbuch des DKV. Nur DE-Vereine.
define('EFB_ENABLED',          false);
define('EFB_URL_LOGIN',        'https://efb.kanu-efb.de/services/login');
define('EFB_URL_REQUEST',      'https://efb.kanu-efb.de/services');
define('EFB_USERNAME',         '');
define('EFB_PASSWORD',         '');
define('EFB_CLUB_NAME',        CLUB_FULL_NAME);
define('EFB_SYNC_START_DATE',  '2024-01-01');             // Fahrten davor ignorieren

// ──────────────────────────────────────────────────────────────────────────
//  YCOM-SYNC (OPTIONAL — nur bei REDAXO)
// ──────────────────────────────────────────────────────────────────────────
// Token für Cronjob ohne Admin-Login. Leer = deaktiviert.
define('SYNC_SECRET_TOKEN', '');                          // z.B. bin2hex(random_bytes(16))

// ──────────────────────────────────────────────────────────────────────────
//  BETA-MARKIERUNG — ausschalten für Produktiv
// ──────────────────────────────────────────────────────────────────────────
define('BETA_MODE', false);
