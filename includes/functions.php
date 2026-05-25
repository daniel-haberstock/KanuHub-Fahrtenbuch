<?php
/**
 * Fahrtenbuch Beta - Hilfsfunktionen
 */

// Session auf Beta-Pfad beschränken (verhindert Kollision mit altem Fahrtenbuch)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/']);
}

/**
 * Gibt die aktuelle Saison zurück
 * Saison beginnt am 1. Oktober
 */
function getCurrentSeason() {
    $now = new DateTime();
    $currentYear = (int)$now->format('Y');
    $currentMonth = (int)$now->format('m');

    if ($currentMonth >= SEASON_START_MONTH) {
        return $currentYear . '/' . ($currentYear + 1);
    } else {
        return ($currentYear - 1) . '/' . $currentYear;
    }
}

/**
 * Gibt das Startdatum der aktuellen Saison zurück
 */
function getCurrentSeasonStartDate() {
    $now = new DateTime();
    $currentYear = (int)$now->format('Y');
    $currentMonth = (int)$now->format('m');

    if ($currentMonth >= SEASON_START_MONTH) {
        return $currentYear . '-' . SEASON_START_MONTH . '-' . SEASON_START_DAY;
    } else {
        return ($currentYear - 1) . '-' . SEASON_START_MONTH . '-' . SEASON_START_DAY;
    }
}

/**
 * Gibt das Enddatum der aktuellen Saison zurück
 */
function getCurrentSeasonEndDate() {
    $now = new DateTime();
    $currentYear = (int)$now->format('Y');
    $currentMonth = (int)$now->format('m');

    if ($currentMonth >= SEASON_START_MONTH) {
        return ($currentYear + 1) . '-' . (SEASON_START_MONTH - 1) . '-30';
    } else {
        return $currentYear . '-' . (SEASON_START_MONTH - 1) . '-30';
    }
}

/**
 * Prüft, ob ein Boot ein Vereinsboot ist (Name beginnt mit CLUB_BOAT_PREFIX)
 */
function isClubBoat($boatName) {
    $prefix = defined('CLUB_BOAT_PREFIX') ? CLUB_BOAT_PREFIX : 'Verein';
    return stripos($boatName, $prefix) === 0;
}

/**
 * Prüft, ob ein Datum in der Vergangenheit liegt
 */
function isDateInPast($date, $time = '23:59:59') {
    $dateTime = new DateTime($date . ' ' . $time);
    $now = new DateTime();
    return $dateTime < $now;
}

/**
 * Formatiert ein Datum im deutschen Format
 */
function formatDateGerman($date) {
    if (empty($date)) return '';
    $dt = new DateTime($date);
    return $dt->format('d.m.Y');
}

/**
 * Formatiert eine Uhrzeit im deutschen Format
 */
function formatTimeGerman($time) {
    if (empty($time)) return '';
    $dt = new DateTime($time);
    return $dt->format('H:i');
}

/**
 * Formatiert ein Datum mit Uhrzeit im deutschen Format
 */
function formatDateTimeGerman($date, $time) {
    return formatDateGerman($date) . ' ' . formatTimeGerman($time);
}

/**
 * Escaped HTML-Sonderzeichen
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generiert einen sicheren Hash für Passwörter
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Authentifiziert ein Mitglied über die REDAXO/YCom-API.
 * Gibt bei Erfolg ein Array mit Userdaten zurück, bei Fehler false.
 *
 * Response-Felder bei Erfolg:
 *   success, ycom_user_id, membership_no, email, first_name, last_name, salutation, status
 */
function authenticateViaApi(string $email, string $password): array|false
{
    $apiUrl = defined('REDAXO_AUTH_API_URL') ? REDAXO_AUTH_API_URL : '';
    $apiToken = defined('REDAXO_API_TOKEN') ? REDAXO_API_TOKEN : '';

    if (empty($apiUrl) || empty($apiToken)) {
        error_log('[Fahrtenbuch Auth] REDAXO_AUTH_API_URL oder REDAXO_API_TOKEN nicht konfiguriert');
        return false;
    }

    $payload = json_encode(['email' => $email, 'password' => $password]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiToken,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('[Fahrtenbuch Auth] cURL Fehler: ' . $curlError);
        return false;
    }

    $data = json_decode($response, true);

    if ($httpCode === 200 && !empty($data['success'])) {
        return $data;
    }

    // API-Fehler loggen (aber keine Details an den User)
    if ($httpCode !== 401) {
        error_log('[Fahrtenbuch Auth] API Fehler: HTTP ' . $httpCode . ' — ' . ($data['message'] ?? 'Unbekannt'));
    }

    return false;
}

/**
 * Ruft kommende Termine von der REDAXO Termine-API ab.
 *
 * @param int         $limit     Max. Anzahl Termine (default 20)
 * @param int         $months    Monate im Voraus (default 6)
 * @param string|null $kategorie Kategorie-Filter ('1' oder '2'), null = alle
 * @return array|false  Array mit 'count' und 'termine' bei Erfolg, false bei Fehler
 */
function fetchTermine(int $limit = 20, int $months = 6, ?string $kategorie = null): array|false
{
    $apiUrl = defined('REDAXO_TERMINE_API_URL') ? REDAXO_TERMINE_API_URL : '';
    $apiToken = defined('REDAXO_API_TOKEN') ? REDAXO_API_TOKEN : '';

    if (empty($apiUrl) || empty($apiToken)) {
        error_log('[Fahrtenbuch Termine] REDAXO_TERMINE_API_URL oder REDAXO_API_TOKEN nicht konfiguriert');
        return false;
    }

    // Query-Parameter
    $queryParams = [
        'limit'  => $limit,
        'months' => $months,
    ];
    if ($kategorie !== null) {
        $queryParams['kategorie'] = $kategorie;
    }

    $url = $apiUrl . '?' . http_build_query($queryParams);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . $apiToken,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('[Fahrtenbuch Termine] cURL Fehler: ' . $curlError);
        return false;
    }

    $data = json_decode($response, true);

    if ($httpCode === 200 && !empty($data['success'])) {
        return $data;
    }

    error_log('[Fahrtenbuch Termine] API Fehler: HTTP ' . $httpCode . ' — ' . ($data['message'] ?? 'Unbekannt'));
    return false;
}

/**
 * @deprecated Authentifizierung erfolgt jetzt via REDAXO-API (authenticateViaApi).
 *             Diese Funktion wird nur noch als Fallback vorgehalten.
 *
 * Verifiziert ein Passwort
 * Unterstützt sowohl bcrypt (PHP password_hash) als auch YCom-Hashes (SHA1, SHA256)
 */
function verifyPassword($password, $hash) {
    // Leerer Hash = kein Passwort gesetzt
    if (empty($hash)) {
        return false;
    }

    // PHP bcrypt/argon2 Hash (beginnt mit $2y$ oder $argon2)
    if (strpos($hash, '$') === 0) {
        // Zuerst versuchen: SHA1 des Passworts mit bcrypt-Hash vergleichen
        // Dies wird verwendet, wenn Passwörter als password_hash(sha1($password)) gespeichert sind
        if (password_verify(sha1($password), $hash)) {
            return true;
        }
        // Fallback: Direktes Passwort mit bcrypt-Hash vergleichen
        return password_verify($password, $hash);
    }

    // YCom SHA1 Hash (40 Zeichen hex)
    if (strlen($hash) === 40 && ctype_xdigit($hash)) {
        return sha1($password) === $hash;
    }

    // YCom SHA256 Hash (64 Zeichen hex)
    if (strlen($hash) === 64 && ctype_xdigit($hash)) {
        return hash('sha256', $password) === $hash;
    }

    // YCom SHA1 mit Präfix (sha1:hash)
    if (strpos($hash, 'sha1:') === 0) {
        $hashValue = substr($hash, 5);
        return sha1($password) === $hashValue;
    }

    // YCom SHA256 mit Präfix (sha256:hash)
    if (strpos($hash, 'sha256:') === 0) {
        $hashValue = substr($hash, 7);
        return hash('sha256', $password) === $hashValue;
    }

    // Fallback: Direkter Vergleich (nur für Notfälle, nicht empfohlen)
    return $password === $hash;
}

/**
 * Löst eine member_id aus crew_id und crew_name auf.
 * Falls crew_id leer ist, wird die Mitgliedsnummer aus dem Namen extrahiert
 * (Format: "Vorname Nachname (1153)") und per membership_no nachgeschlagen.
 */
function resolveCrewMemberId(?string $crewId, ?string $crewName, PDO $db): ?int
{
    if ($crewId !== null && $crewId !== '' && $crewId !== '0') {
        return (int)$crewId;
    }
    if ($crewName && preg_match('/\((\d+)\)\s*$/', $crewName, $m)) {
        $stmt = $db->prepare("SELECT id FROM members WHERE membership_no = :no LIMIT 1");
        $stmt->execute(['no' => $m[1]]);
        $resolved = $stmt->fetchColumn();
        return $resolved ? (int)$resolved : null;
    }
    return null;
}

/**
 * Gibt JSON zurück
 */
function jsonResponse($data, $statusCode = 200) {
    // Jeden zuvor gepufferten Output (PHP-Warnings etc.) verwerfen, damit das JSON sauber ist
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Prüft Admin-Login
 */
function isAdminLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION[ADMIN_SESSION_NAME]) && $_SESSION[ADMIN_SESSION_NAME] === true;
}

/**
 * Admin-Login erforderlich
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Liest einen Anwendungs-Setting-Wert aus der DB.
 * Memoized per Request. Gibt $default zurueck falls Tabelle/Key nicht existiert.
 */
function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $val  = $stmt->fetchColumn();
        $cache[$key] = ($val !== false) ? (string)$val : $default;
    } catch (\Throwable $e) {
        error_log('[Fahrtenbuch getSetting] ' . $e->getMessage());
        $cache[$key] = $default;
    }
    return $cache[$key];
}

/**
 * Speichert einen Anwendungs-Setting-Wert in der DB (UPSERT).
 */
function setSetting(string $key, string $value): bool
{
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'INSERT INTO app_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
        );
        $stmt->execute([$key, $value]);
        return true;
    } catch (\Throwable $e) {
        error_log('[Fahrtenbuch setSetting] ' . $e->getMessage());
        return false;
    }
}
