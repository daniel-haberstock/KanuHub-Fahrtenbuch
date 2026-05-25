<?php
/**
 * Test-Script für Login-Debugging
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

echo "<h1>Login Debug Test</h1>";

// Test-Passwort
$testPassword = isset($_POST['test_password']) ? $_POST['test_password'] : 'password';
$testEmail = isset($_POST['test_email']) ? $_POST['test_email'] : '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Login Debugging</h2>

    <div class="card mb-4">
        <div class="card-header">Test Login</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label>E-Mail:</label>
                    <input type="email" name="test_email" class="form-control" value="<?php echo htmlspecialchars($testEmail); ?>" required>
                </div>
                <div class="mb-3">
                    <label>Passwort:</label>
                    <input type="text" name="test_password" class="form-control" value="<?php echo htmlspecialchars($testPassword); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Test Login</button>
            </form>
        </div>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $testEmail): ?>
    <div class="card mb-4">
        <div class="card-header">Debug-Informationen</div>
        <div class="card-body">
            <?php
            // Mitglied aus Datenbank laden
            $query = "SELECT id, first_name, last_name, email, password FROM members WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->execute(['email' => $testEmail]);
            $member = $stmt->fetch();

            if (!$member) {
                echo "<div class='alert alert-danger'>Kein Mitglied mit dieser E-Mail gefunden!</div>";
            } else {
                echo "<div class='alert alert-info'>";
                echo "<strong>Mitglied gefunden:</strong><br>";
                echo "ID: " . $member['id'] . "<br>";
                echo "Name: " . htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) . "<br>";
                echo "E-Mail: " . htmlspecialchars($member['email']) . "<br>";
                echo "</div>";

                $hash = $member['password'];
                echo "<div class='alert alert-secondary'>";
                echo "<strong>Passwort-Hash aus DB:</strong><br>";
                echo "<code>" . htmlspecialchars($hash) . "</code><br>";
                echo "Länge: " . strlen($hash) . " Zeichen<br>";
                echo "Beginnt mit '$': " . (strpos($hash, '$') === 0 ? 'Ja' : 'Nein') . "<br>";
                echo "</div>";

                echo "<div class='alert alert-primary'>";
                echo "<strong>Test-Passwort:</strong><br>";
                echo "<code>" . htmlspecialchars($testPassword) . "</code><br>";
                echo "</div>";

                // Test verschiedene Verifikationsmethoden
                echo "<div class='alert alert-warning'>";
                echo "<strong>Verifikations-Tests:</strong><br>";

                // Test 1: Direkt password_verify
                $test1 = password_verify($testPassword, $hash);
                echo "1. password_verify() direkt: " . ($test1 ? '<span class="text-success">✓ ERFOLG</span>' : '<span class="text-danger">✗ FEHLGESCHLAGEN</span>') . "<br>";

                // Test 2: verifyPassword Funktion
                $test2 = verifyPassword($testPassword, $hash);
                echo "2. verifyPassword() Funktion: " . ($test2 ? '<span class="text-success">✓ ERFOLG</span>' : '<span class="text-danger">✗ FEHLGESCHLAGEN</span>') . "<br>";

                // Test 3: SHA1 (für Vergleich)
                $sha1Hash = sha1($testPassword);
                $test3 = ($sha1Hash === $hash);
                echo "3. SHA1 Vergleich: " . ($test3 ? '<span class="text-success">✓ ERFOLG</span>' : '<span class="text-danger">✗ FEHLGESCHLAGEN</span>') . "<br>";
                echo "&nbsp;&nbsp;&nbsp;SHA1 Hash: <code>" . $sha1Hash . "</code><br>";

                echo "</div>";

                // Endgültiges Ergebnis
                if ($test2) {
                    echo "<div class='alert alert-success'><strong>✓ LOGIN ERFOLGREICH!</strong></div>";
                } else {
                    echo "<div class='alert alert-danger'><strong>✗ LOGIN FEHLGESCHLAGEN!</strong></div>";

                    echo "<div class='alert alert-info'>";
                    echo "<strong>Mögliche Ursachen:</strong><br>";
                    echo "- Falsches Passwort eingegeben<br>";
                    echo "- Hash wurde beschädigt beim Speichern<br>";
                    echo "- Hash-Format nicht erkannt<br>";
                    echo "</div>";
                }
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">Alle Mitglieder mit E-Mail</div>
        <div class="card-body">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Hash-Typ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT id, first_name, last_name, email, password FROM members WHERE email IS NOT NULL AND email != '' ORDER BY last_name, first_name";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $members = $stmt->fetchAll();

                    foreach ($members as $m) {
                        $hashType = 'Unbekannt';
                        if (empty($m['password'])) {
                            $hashType = 'Leer';
                        } elseif (strpos($m['password'], '$2y$') === 0) {
                            $hashType = 'bcrypt';
                        } elseif (strlen($m['password']) === 40 && ctype_xdigit($m['password'])) {
                            $hashType = 'SHA1';
                        } elseif (strlen($m['password']) === 64 && ctype_xdigit($m['password'])) {
                            $hashType = 'SHA256';
                        }

                        echo "<tr>";
                        echo "<td>" . $m['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($m['email']) . "</td>";
                        echo "<td><span class='badge bg-secondary'>" . $hashType . "</span></td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
