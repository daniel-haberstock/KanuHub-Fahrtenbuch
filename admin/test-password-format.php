<?php
/**
 * Test-Script: Zeigt das Passwort-Format von YCom-Benutzern
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

echo "<h2>Passwort-Format Test</h2>";
echo "<p>Zeigt die ersten 3 Passwort-Hashes aus YCom und der members-Tabelle</p>";

echo "<h3>YCom Passwörter (rex_ycom_user):</h3>";
$ycomQuery = "SELECT id, firstname, name, email, password, LENGTH(password) as hash_length
              FROM rex_ycom_user
              WHERE password IS NOT NULL AND password != ''
              LIMIT 3";
$ycomStmt = $db->prepare($ycomQuery);
$ycomStmt->execute();
$ycomUsers = $ycomStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>E-Mail</th><th>Hash (erste 20 Zeichen)</th><th>Hash-Länge</th><th>Format</th></tr>";
foreach ($ycomUsers as $user) {
    $hashPreview = substr($user['password'], 0, 20) . '...';
    $hashLength = $user['hash_length'];

    // Format erkennen
    $format = 'Unbekannt';
    if (strpos($user['password'], '$2y$') === 0) {
        $format = 'bcrypt';
    } elseif (strlen($user['password']) === 40 && ctype_xdigit($user['password'])) {
        $format = 'SHA1';
    } elseif (strlen($user['password']) === 64 && ctype_xdigit($user['password'])) {
        $format = 'SHA256';
    } elseif (strpos($user['password'], 'sha1:') === 0) {
        $format = 'SHA1 (mit Präfix)';
    } elseif (strpos($user['password'], 'sha256:') === 0) {
        $format = 'SHA256 (mit Präfix)';
    }

    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['firstname']} {$user['name']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td><code>{$hashPreview}</code></td>";
    echo "<td>{$hashLength}</td>";
    echo "<td><strong>{$format}</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Members Passwörter (members):</h3>";
$membersQuery = "SELECT id, first_name, last_name, email, password, LENGTH(password) as hash_length
                 FROM members
                 WHERE password IS NOT NULL AND password != ''
                 LIMIT 3";
$membersStmt = $db->prepare($membersQuery);
$membersStmt->execute();
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>E-Mail</th><th>Hash (erste 20 Zeichen)</th><th>Hash-Länge</th><th>Format</th></tr>";
foreach ($members as $member) {
    $hashPreview = substr($member['password'], 0, 20) . '...';
    $hashLength = $member['hash_length'];

    // Format erkennen
    $format = 'Unbekannt';
    if (strpos($member['password'], '$2y$') === 0) {
        $format = 'bcrypt';
    } elseif (strlen($member['password']) === 40 && ctype_xdigit($member['password'])) {
        $format = 'SHA1';
    } elseif (strlen($member['password']) === 64 && ctype_xdigit($member['password'])) {
        $format = 'SHA256';
    } elseif (strpos($member['password'], 'sha1:') === 0) {
        $format = 'SHA1 (mit Präfix)';
    } elseif (strpos($member['password'], 'sha256:') === 0) {
        $format = 'SHA256 (mit Präfix)';
    }

    echo "<tr>";
    echo "<td>{$member['id']}</td>";
    echo "<td>{$member['first_name']} {$member['last_name']}</td>";
    echo "<td>{$member['email']}</td>";
    echo "<td><code>{$hashPreview}</code></td>";
    echo "<td>{$hashLength}</td>";
    echo "<td><strong>{$format}</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h3>Test-Login:</h3>";
echo "<p>Geben Sie eine E-Mail und ein Test-Passwort ein, um den Login zu testen:</p>";
echo "<form method='POST'>";
echo "<input type='email' name='test_email' placeholder='E-Mail' required>";
echo "<input type='password' name='test_password' placeholder='Passwort' required>";
echo "<button type='submit' name='test_login'>Test Login</button>";
echo "</form>";

if (isset($_POST['test_login'])) {
    $testEmail = $_POST['test_email'];
    $testPassword = $_POST['test_password'];

    require_once __DIR__ . '/../includes/functions.php';

    $query = "SELECT password FROM members WHERE email = :email LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute(['email' => $testEmail]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $passwordHash = $result['password'];
        $isValid = verifyPassword($testPassword, $passwordHash);

        echo "<div style='margin-top: 20px; padding: 10px; border: 2px solid " . ($isValid ? 'green' : 'red') . "'>";
        echo "<h4>Login-Test Ergebnis:</h4>";
        echo "<p>E-Mail: <code>{$testEmail}</code></p>";
        echo "<p>Passwort-Hash: <code>" . substr($passwordHash, 0, 30) . "...</code></p>";
        echo "<p>Hash-Länge: " . strlen($passwordHash) . "</p>";
        echo "<p><strong>Login: " . ($isValid ? '✓ ERFOLGREICH' : '✗ FEHLGESCHLAGEN') . "</strong></p>";

        // Verschiedene Hash-Varianten testen
        echo "<h5>Hash-Tests:</h5>";
        echo "<ul>";
        echo "<li>SHA1: " . (sha1($testPassword) === $passwordHash ? '✓' : '✗') . "</li>";
        echo "<li>SHA256: " . (hash('sha256', $testPassword) === $passwordHash ? '✓' : '✗') . "</li>";
        echo "<li>bcrypt: " . (password_verify($testPassword, $passwordHash) ? '✓' : '✗') . "</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div style='margin-top: 20px; padding: 10px; border: 2px solid red;'>";
        echo "<p>E-Mail nicht gefunden!</p>";
        echo "</div>";
    }
}

echo "<hr>";
echo "<a href='/admin/'>Zurück zur Administration</a>";
