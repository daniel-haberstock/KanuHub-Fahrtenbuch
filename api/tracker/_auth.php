<?php
/**
 * Tracker-API: HMAC-Auth + Replay-Schutz
 *
 * Verwendung (am Anfang jedes Tracker-Endpoints):
 *   require_once __DIR__ . '/_auth.php';
 *   [$db, $tracker, $body] = trackerAuth();
 *
 * Header-Contract (vom ESP gesetzt):
 *   X-Tracker-Hwid : 12-hex Hardware-UID (aus ESP-MAC)
 *   X-Timestamp    : Unix-Sekunden
 *   X-Nonce        : 16 Byte hex (32 chars)
 *   X-Signature    : hex(HMAC_SHA256(api_token, canonical))
 *
 * canonical = METHOD\nPATH\nTIMESTAMP\nNONCE\nSHA256(BODY)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

const TRACKER_MAX_SKEW_S = 120;

function trackerLog(string $msg, array $ctx = []): void {
    $dir = __DIR__ . '/../../logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $line = sprintf(
        "[%s] %s %s %s\n",
        date('Y-m-d H:i:s'),
        $msg,
        json_encode($ctx, JSON_UNESCAPED_SLASHES),
        $_SERVER['REMOTE_ADDR'] ?? '-'
    );
    @file_put_contents($dir . '/tracker.log', $line, FILE_APPEND);
}

function trackerFail(int $code, string $error, string $message, array $ctx = []): void {
    trackerLog("auth-fail $error", $ctx + ['msg' => $message, 'code' => $code]);
    jsonResponse(['error' => $error, 'message' => $message], $code);
}

function trackerReadBody(): string {
    $raw = file_get_contents('php://input');
    if ($raw === false) { return ''; }
    $enc = strtolower($_SERVER['HTTP_CONTENT_ENCODING'] ?? '');
    if ($enc === 'gzip' && $raw !== '') {
        $dec = @gzdecode($raw);
        if ($dec === false) {
            trackerFail(400, 'bad_gzip', 'gzip decode failed');
        }
        $raw = $dec;
    }
    return $raw;
}

/**
 * @return array{0: PDO, 1: array, 2: string}  [$db, $tracker, $rawBody]
 */
function trackerAuth(): array {
    header('Content-Type: application/json; charset=utf-8');

    $headers = [
        'hwid'  => $_SERVER['HTTP_X_TRACKER_HWID'] ?? '',
        'ts'    => $_SERVER['HTTP_X_TIMESTAMP']    ?? '',
        'nonce' => $_SERVER['HTTP_X_NONCE']        ?? '',
        'sig'   => $_SERVER['HTTP_X_SIGNATURE']    ?? '',
    ];

    foreach ($headers as $k => $v) {
        if ($v === '') { trackerFail(401, 'missing_header', "X-$k"); }
    }

    $hwid  = strtolower((string)$headers['hwid']);
    $ts    = (int)$headers['ts'];
    $nonce = (string)$headers['nonce'];
    $sig   = strtolower((string)$headers['sig']);

    if (!preg_match('/^[a-f0-9]{12}$/', $hwid)) {
        trackerFail(401, 'bad_hwid', 'hwid must be 12 hex');
    }

    $skew = abs(time() - $ts);
    if ($skew > TRACKER_MAX_SKEW_S) {
        trackerFail(401, 'stale_timestamp', "skew=${skew}s", ['hwid' => $hwid]);
    }

    if (!preg_match('/^[a-f0-9]{16,64}$/i', $nonce)) {
        trackerFail(401, 'bad_nonce', 'nonce format', ['hwid' => $hwid]);
    }

    try {
        $db = Database::getInstance()->getConnection();
    } catch (\Throwable $e) {
        error_log('[Fahrtenbuch tracker auth] db: ' . $e->getMessage());
        trackerFail(500, 'db_error', 'database unavailable');
    }

    $stmt = $db->prepare("SELECT id, hwid, nummer, api_token, aktiv, status, firmware_target FROM tracker WHERE hwid = :h LIMIT 1");
    $stmt->execute(['h' => $hwid]);
    $tracker = $stmt->fetch();
    if (!$tracker) { trackerFail(401, 'unknown_tracker', 'not enrolled', ['hwid' => $hwid]); }
    if ((int)$tracker['aktiv'] !== 1) {
        trackerFail(403, 'tracker_disabled', 'not activated by admin', ['hwid' => $hwid]);
    }

    $trackerId = (int)$tracker['id'];

    $body   = trackerReadBody();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

    $canonical = $method . "\n" . $path . "\n" . $ts . "\n" . $nonce . "\n" . hash('sha256', $body);
    $expected  = hash_hmac('sha256', $canonical, $tracker['api_token']);

    if (!hash_equals($expected, $sig)) {
        trackerFail(401, 'bad_signature', 'hmac mismatch', ['tid' => $trackerId]);
    }

    // Replay-Schutz: Nonce einfügen; Duplikat -> Replay
    try {
        $ins = $db->prepare("INSERT INTO tracker_nonces (tracker_id, nonce, created_at) VALUES (:tid, :n, NOW())");
        $ins->execute(['tid' => $trackerId, 'n' => $nonce]);
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') {
            trackerFail(401, 'replay', 'nonce reused', ['tid' => $trackerId]);
        }
        error_log('[Fahrtenbuch tracker auth] nonce insert: ' . $e->getMessage());
        trackerFail(500, 'db_error', 'nonce store failed');
    }

    // Last-seen aktualisieren + alte Nonces aufräumen (günstig: ab & zu)
    $db->prepare("UPDATE tracker SET last_seen = NOW() WHERE id = :id")->execute(['id' => $trackerId]);
    if (random_int(0, 99) === 0) {
        $db->exec("DELETE FROM tracker_nonces WHERE created_at < (NOW() - INTERVAL 10 MINUTE)");
    }

    return [$db, $tracker, $body];
}
