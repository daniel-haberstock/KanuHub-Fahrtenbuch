# Fahrtenbuch — Open-Source Kanu-/Ruder-Vereinsfahrtenbuch

> Teil des [**KanuHub**](https://kanuhub.de)-Ökosystems — Open-Source-Module für Kanu-/Sportvereine.


Web-basiertes Fahrtenbuch für Kanu-, Ruder- und Wassersportvereine.
Touch-/Kiosk-optimiert, ohne Cloud-Abhängigkeit, ohne Node/npm, läuft auf
jedem Standard-LAMP-Webserver.

> **Status:** Beta, produktiv eingesetzt im (Mein Verein).
> Veröffentlicht unter AGPL-3.0 zur freien Nutzung durch andere Vereine.

---

## Features

- **3-Spalten-Hauptbildschirm** — Bootsliste · Aktionen · Live-Status
- **Fahrt-Wizard**: Boot wählen → Crew → Start → Beenden (mit Distanz/Strecke)
- **Anschließen** an laufende Fahrten · **Reservieren** von Booten
- **Mitgliederbereich** mit Statistik, Rangliste, Errungenschaften (Badges),
  Saison-Rückblick, eigene Boote bearbeiten
- **Magic-Link-Login** — kein Passwort, Login per E-Mail-Link
- **Admin** — Mitglieder, Boote, Strecken, Schäden, Wetter-Konfig, Layout
- **Kiosk-fähig** — Vollbild im Chromium auf Raspberry Pi (Bootshaus-PC)
- **Verwandte Repos** (eigenständig, optional):
  - **[vereins-app](https://github.com/daniel-haberstock/KanuHub-App)** — Mitglieder-PWA
    mit Termine, Push, Chat, Reservierung
  - **[fahrtenbuch-tracker-firmware](https://github.com/daniel-haberstock/KanuHub-Tracker)** —
    GPS-Tracker auf ESP32-S3
  - **[raspberry-kiosk](https://github.com/daniel-haberstock/KanuHub-Kiosk)** —
    Bootshaus-Display-Setup (Pi mit Chromium-Kiosk)
  - **[fahrtenbuch-redaxo-plugin](https://github.com/daniel-haberstock/KanuHub-Fahrtenbuch-Redaxo-Plugins)** —
    REDAXO/YCom-Anbindung (Vereinswebsite als zentrale Mitgliederverwaltung)

## Technik

- **Backend:** PHP 8.1+, MySQL/MariaDB 10.4+
- **Frontend:** PHP-Templates + Vanilla JS + Bootstrap 5 (vendor lokal,
  kein CDN, kein Build-Prozess)
- **Mail:** PHPMailer (mitgeliefert, kein Composer nötig)
- **Auth:** Magic-Link über E-Mail (Standard) ODER REDAXO-API (optional)

## Schnellstart

1. Repo nach Webroot deployen (`/var/www/fahrtenbuch` oder Webhost)
2. Datenbank erstellen, eine der beiden SQL-Dateien einspielen:
   - `database/schema.sql` — leeres Schema (Produktiv-Start)
   - `database/schema_with_demo.sql` — Schema + Demo-Daten zum Ausprobieren
3. `config/config.sample.php` → `config/config.php`, Werte anpassen
4. Admin schützen: `admin/.htpasswd` setzen (siehe `admin/README_HTPASSWD.md`)
5. `logs/` und `data/` beschreibbar machen
6. Fertig — http(s)://fahrtenbuch.kanuhub.de/ aufrufen

**Ausführliche Anleitung:** [install.md](install.md)
**Kiosk-PC einrichten:** [install_client.md](install_client.md)
**Demo testen / Logins:** [nutzung_test.md](nutzung_test.md)

## Verzeichnis-Struktur

```
fahrtenbuch/
├── README.md, LICENSE, install*.md, nutzung_test.md
├── config/
│   └── config.sample.php          → kopieren zu config.php
├── database/
│   ├── schema.sql                  Leeres Schema
│   └── schema_with_demo.sql        Schema + Demo-Daten
├── index.php                       Frontend-Router (Layout v2)
├── statistik/                      Mitgliederbereich
├── admin/                          Admin-UI (Mitglieder, Boote, ...)
├── api/                            REST-Endpunkte (PHP)
├── assets/                         CSS, JS, Vendor (Bootstrap, Icons)
├── includes/                       Helper, Modals, PHPMailer
└── layouts/v2/                     Frontend-Template (3-Spalten)
```

## Lizenz

AGPL-3.0 — siehe [LICENSE](LICENSE). Änderungen am Code MÜSSEN ebenfalls
unter AGPL veröffentlicht werden, **auch beim Hosten als Webdienst**.
Das hält den Code in der Community.

## Mitmachen

Issues und Pull Requests willkommen. Bitte vor größeren Änderungen
ein Issue eröffnen zur Abstimmung.

## Credits

Ursprünglich entwickelt für den **Mein Verein e.V.** Bodensee.
