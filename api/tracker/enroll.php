<?php
/**
 * Tracker-API: Self-Enrollment
 * POST { } (leerer Body ok)
 *
 * Verwendet das Shared Enrollment-Secret (app_config.enrollment_secret) statt
 * eines per-Device-Tokens, weil der Tracker noch keinen Token hat.
 *
 * Header:
 *   X-Tracker-Hwid  : 12-hex (aus ESP-MAC)
 *   X-Timestamp     : Unix-Sekunden
 *   X-Nonce         : 16-32 hex
 *   X-Signature     : hex(HMAC_SHA256(enrollment_secret, canonical))
 *
 * Ablauf:
 *   - Tracker existiert noch nicht -> INSERT (aktiv=0, api_token=random).
 *   - Tracker existiert, aktiv=0    -> Token regenerieren (erneuter Enroll).
 *   - Tracker existiert, aktiv=1    -> 409, Admin muss erst zurücksetzen.
 *
 * Response: { tracker_id, api_token, activated, nummer }
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'method', 'message' => 'POST required'], 405);
}

function enrollLog(string $msg, array $ctx = []): void {
    $dir = __DIR__ . '/../../logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    @file_put_contents($dir . '/tracker.log',
        sprintf("[%s] enroll %s %s %s\n",
            date('Y-m-d H:i:s'), $msg,
            json_encode($ctx, JSON_UNESCAPED_SLASHES),
            $_SERVER['REMOTE_ADDR'] ?? '-'),
        FILE_APPEND);
}
function enrollFail(int $code, string $error, string $message, array $ctx = []): void {
    enrollLog("fail $error", $ctx + ['msg' => $message]);
    jsonResponse(['error' => $error, 'message' => $message], $code);
}

$hwid  = strtolower($_SERVER['HTTP_X_TRACKER_HWID'] ?? '');
$ts    = (int)($_SERVER['HTTP_X_TIMESTAMP'] ?? 0);
$nonce = (string)($_SERVER['HTTP_X_NONCE'] ?? '');
$sig   = strtolower((string)($_SERVER['HTTP_X_SIGNATURE'] ?? ''));

if (!preg_match('/^[a-f0-9]{12}$/', $hwid))        enrollFail(400, 'bad_hwid', 'hwid format');
if (abs(time() - $ts) > 120)                       enrollFail(401, 'stale_timestamp', 'skew');
if (!preg_match('/^[a-f0-9]{16,64}$/', $nonce))    enrollFail(400, 'bad_nonce', 'nonce format');
if (!preg_match('/^[a-f0-9]{64}$/', $sig))         enrollFail(400, 'bad_signature_format', 'sig format');

try {
    $db = Database::getInstance()->getConnection();

    $secret = $db->query("SELECT v FROM app_config WHERE k = 'enrollment_secret'")->fetchColumn();
    if (!$secret) {
        enrollFail(500, 'no_enrollment_secret', 'server not configured');
    }

    $body   = file_get_contents('php://input') ?: '';
    $method = 'POST';
    $path   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $canon  = $method . "\n" . $path . "\n" . $ts . "\n" . $nonce . "\n" . hash('sha256', $body);
    $expect = hash_hmac('sha256', $canon, $secret);

    if (!hash_equals($expect, $sig)) {
        enrollFail(401, 'bad_signature', 'enrollment secret mismatch', ['hwid' => $hwid]);
    }

    // Nonce gegen Replay (innerhalb 120 s)
    try {
        $ins = $db->prepare("INSERT INTO tracker_nonces (tracker_id, nonce, created_at) VALUES (0, :n, NOW())");
        $ins->execute(['n' => 'enroll:' . $nonce]);
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') enrollFail(401, 'replay', 'nonce reused');
        throw $e;
    }

    $stmt = $db->prepare("SELECT id, hwid, nummer, aktiv FROM tracker WHERE hwid = :h LIMIT 1");
    $stmt->execute(['h' => $hwid]);
    $existing = $stmt->fetch();

    $newToken = bin2hex(random_bytes(32));

    if (!$existing) {
        $ins = $db->prepare("
            INSERT INTO tracker (hwid, nummer, api_token, status, aktiv, created_at)
            VALUES (:h, NULL, :t, 'offline', 0, NOW())
        ");
        $ins->execute(['h' => $hwid, 't' => $newToken]);
        $id = (int)$db->lastInsertId();
        enrollLog('created', ['hwid' => $hwid, 'id' => $id]);
        jsonResponse([
            'tracker_id' => $id,
            'api_token'  => $newToken,
            'activated'  => false,
            'nummer'     => null,
            'message'    => 'enrolled - waiting for admin activation'
        ]);
    }

    // Bereits existent
    if ((int)$existing['aktiv'] === 1) {
        enrollFail(409, 'already_enrolled',
            'tracker already active; admin must reset before re-enroll',
            ['hwid' => $hwid, 'id' => (int)$existing['id']]);
    }

    // Inaktiv -> Token rotieren (Reflash-Szenario)
    $upd = $db->prepare("UPDATE tracker SET api_token = :t, last_seen = NOW() WHERE id = :id");
    $upd->execute(['t' => $newToken, 'id' => $existing['id']]);
    enrollLog('re-enrolled', ['hwid' => $hwid, 'id' => (int)$existing['id']]);

    jsonResponse([
        'tracker_id' => (int)$existing['id'],
        'api_token'  => $newToken,
        'activated'  => false,
        'nummer'     => $existing['nummer'],
        'message'    => 'token rotated - waiting for admin activation'
    ]);

} catch (\Throwable $e) {
    error_log('[Fahrtenbuch tracker enroll] ' . $e->getMessage());
    enrollFail(500, 'db_error', 'internal error');
}
