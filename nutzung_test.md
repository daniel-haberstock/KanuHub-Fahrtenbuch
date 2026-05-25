# Test-Setup mit Demo-Daten

Wie ihr das Fahrtenbuch mit Demo-Daten ausprobieren könnt, bevor ihr es
produktiv mit eurem Verein nutzt.

## 1. Demo-Daten einspielen

Während der Installation (siehe [install.md](install.md)) statt
`schema.sql` die `schema_with_demo.sql` einspielen:

```bash
mysql -u fahrtenbuch -p fahrtenbuch < database/schema_with_demo.sql
```

Ihr habt jetzt:

- **10 Demo-Mitglieder** (siehe Login-Tabelle unten)
- **8 Demo-Boote** (3 Canadier, 3 Kajaks, 1 SUP, 1 Privatboot)
- **30 abgeschlossene Demo-Fahrten** über 2 Saisonen
- **2 aktive Fahrten** zum Testen von "Anschließen" und "Beenden"
- **1 Reservierung** in der nächsten Woche
- **1 Schadensmeldung** an einem Boot

## 2. Test-Logins

Login funktioniert über die Hauptseite → Button "Anmelden" → Magic-Link
per E-Mail. Im Demo-Setup wird **keine echte Mail verschickt** —
stattdessen wird der Link in die Log-Datei `logs/magic-link.log`
geschrieben (Schalter `BETA_MODE = true` in `config.php`):

```bash
tail -f logs/magic-link.log
# Beim Klick auf "Link senden" erscheint dort die URL.
# Diese in den Browser kopieren → eingeloggt.
```

| Demo-Mitglied | E-Mail | Rolle |
|---------------|--------|-------|
| Anna Beispiel | anna@demo.local | Vorstand / Admin |
| Ben Demo | ben@demo.local | Mitglied (aktiv) |
| Carla Test | carla@demo.local | Mitglied (Jugend) |
| Dieter Probe | dieter@demo.local | Mitglied |
| Eva Beispiel | eva@demo.local | Mitglied |
| Felix Demo | felix@demo.local | Mitglied (passiv) |
| Gabi Test | gabi@demo.local | Mitglied |
| Hannes Probe | hannes@demo.local | Mitglied (Vorstand) |
| Ina Demo | ina@demo.local | Mitglied |
| Jonas Test | jonas@demo.local | Mitglied (Jugend) |

Empfehlung für ersten Login: **anna@demo.local** (Vorstand) — sieht alle
Vereins-Statistiken und kann Boote bearbeiten.

## 3. Admin-Bereich

`/admin/` ist zusätzlich per HTTP-Basic-Auth geschützt
(`admin/.htpasswd`). Für die Demo:

```bash
htpasswd -bc admin/.htpasswd admin demo123
```

Dann:
- **URL:** `https://fahrtenbuch.kanuhub.de/admin/`
- **Basic-Auth:** `admin` / `demo123`

## 4. Test-Walkthrough — Was probiert werden sollte

### Hauptseite (anonym)
- [ ] Bootsliste lädt, Filter "Vereinsboot" / "Privat" funktioniert
- [ ] Boot anklicken → Steckbrief öffnet sich
- [ ] "Fahrt starten" → durchgehen bis Schritt 4, Crew wählen, fertigstellen
- [ ] In der rechten Spalte erscheint die neue aktive Fahrt
- [ ] "Anschließen" an eine bestehende aktive Fahrt
- [ ] "Reservieren" → für nächste Woche, dann sichtbar im Status-Panel

### Mitgliederbereich (eingeloggt als Anna)
- [ ] Tab "Statistik" — Anzahl Fahrten, km, Saison-Rückblick
- [ ] Tab "Errungenschaften" — Badges
- [ ] Tab "Rangliste" — Top-Mitglieder der Saison
- [ ] Tab "Fahrten" — eigene Fahrtenhistorie, "Letzte 3 bearbeiten"
- [ ] Tab "Arbeitsdienst" — Stunden eintragen
- [ ] Tab "Boote" — eigene Privatboote bearbeiten
- [ ] Tab "Einstellungen" — Sichtbarkeit der Statistik, E-Mail-Benachrichtigungen

### Admin (Basic-Auth)
- [ ] Mitglied anlegen / deaktivieren
- [ ] Boot anlegen / Tracker zuordnen
- [ ] Strecke anlegen
- [ ] Schadensmeldung als "erledigt" markieren
- [ ] Wetter-Konfiguration (wenn Standort + Pegel aktiviert)

### Auto-Logout
- [ ] Im Mitgliederbereich 5 Min nichts tun → automatischer Logout
- [ ] Timer rechts oben zählt sichtbar runter

## 5. Fertig getestet? Reset auf Produktiv

```bash
mysql -u root -p
```
```sql
DROP DATABASE fahrtenbuch;
CREATE DATABASE fahrtenbuch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON fahrtenbuch.* TO 'fahrtenbuch'@'localhost';
```
```bash
mysql -u fahrtenbuch -p fahrtenbuch < database/schema.sql
```

Dann `config.php`:
```php
define('BETA_MODE', false);
// SMTP-Werte für echten Mail-Versand setzen
```

`admin/.htpasswd` neu setzen mit sicherem Passwort.
Eigene Mitglieder anlegen (Admin → Mitglieder → Neu) und gut.

## Bekannte Demo-Daten-Eigenheiten

- Mailadressen `@demo.local` sind absichtlich nicht zustellbar — Magic-Link
  geht in die Log-Datei.
- Datumsangaben sind statisch; "vor 3 Tagen" etc. wirkt nach Tagen falsch.
- Reservierungs-Daten in `schema_with_demo.sql` sind als relative Termine
  (`CURDATE() + INTERVAL N DAY`) hinterlegt, bleiben also aktuell.
