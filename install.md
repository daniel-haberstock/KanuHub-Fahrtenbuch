# Installation — Webserver

Installations-Anleitung für einen klassischen LAMP-Webserver (Apache/Nginx
+ PHP 8.1+ + MySQL/MariaDB). Geeignet für Shared Hosting, VPS, eigene Server.

## 1. Voraussetzungen

- **PHP 8.1 oder neuer** mit Extensions: `pdo_mysql`, `curl`, `mbstring`,
  `openssl`, `intl`, `gd` (für Profilbilder)
- **MySQL 5.7+** oder **MariaDB 10.4+**
- **Apache 2.4+** mit `mod_rewrite` & `mod_authn_file` ODER **Nginx 1.18+**
- **Postfach + SMTP-Zugang** für Magic-Link-Versand (Pflicht im Standard-Setup)
- **HTTPS-Zertifikat** (Let's Encrypt funktioniert) — Pflicht in Produktion,
  da Sessions sonst nicht sicher übertragen werden

## 2. Dateien auf den Server

### Option A — git clone
```bash
cd /var/www
git clone https://github.com/daniel-haberstock/KanuHub-Fahrtenbuch.git
cd fahrtenbuch
```

### Option B — FTP/SFTP
ZIP herunterladen, entpacken, alle Dateien hochladen.


## 3. Datenbank einrichten

```bash
mysql -u root -p
```

```sql
CREATE DATABASE fahrtenbuch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fahrtenbuch'@'localhost' IDENTIFIED BY 'EIN_GUTES_PASSWORT';
GRANT ALL PRIVILEGES ON fahrtenbuch.* TO 'fahrtenbuch'@'localhost';
FLUSH PRIVILEGES;
```

Eine der beiden SQL-Dateien einspielen:

```bash
# Variante A — leeres Schema (für Produktion)
mysql -u fahrtenbuch -p fahrtenbuch < database/schema.sql

# Variante B — Schema mit Demo-Daten (zum Testen, siehe nutzung_test.md)
mysql -u fahrtenbuch -p fahrtenbuch < database/schema_with_demo.sql
```

## 4. Konfiguration

```bash
cp config/config.sample.php config/config.php
nano config/config.php
```

Mindestens diese Werte setzen:

| Konstante | Beispiel |
|-----------|----------|
| `CLUB_NAME`, `CLUB_FULL_NAME` | Vereinsname |
| `DB_*` | Zugangsdaten der Datenbank |
| `MAGIC_LINK_PUBLIC_URL` | Volle URL des Fahrtenbuchs (`https://...`) |
| `SMTP_*` | SMTP-Zugang für Mail-Versand |
| `NOTIFY_MAIL_FROM`, `EMAIL_EMPFAENGER` | Absender + Vorstands-Adresse |

Optional je nach Standort:

| Konstante | Hinweis |
|-----------|---------|
| `WEATHER_LAT`, `WEATHER_LON` | Vereinsstandort (Open-Meteo, kein API-Key) |
| `WINDFINDER_LOCATION` | Slug von windfinder.com |
| `PEGEL_DRIVER` | Aktuell `bodensee`; eigenen Driver dazuschreiben |

## 5. Verzeichnisrechte

Webserver-User braucht Schreibrechte auf:

```bash
mkdir -p logs data
chown -R www-data:www-data logs data
chmod 750 logs data
chmod 640 config/config.php
```

## 6. Webserver-Konfiguration

### Apache

`.htaccess` ist mitgeliefert. Sicherstellen, dass `AllowOverride All` im
VirtualHost gesetzt ist und `mod_rewrite` aktiv:

```apache
<VirtualHost *:443>
    ServerName fahrtenbuch.kanuhub.de
    DocumentRoot /var/www/fahrtenbuch
    <Directory /var/www/fahrtenbuch>
        AllowOverride All
        Require all granted
    </Directory>
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/fahrtenbuch.kanuhub.de/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/fahrtenbuch.kanuhub.de/privkey.pem
</VirtualHost>
```

Module aktivieren:
```bash
a2enmod rewrite ssl authn_file
systemctl reload apache2
```

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name fahrtenbuch.kanuhub.de;
    root /var/www/fahrtenbuch;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/fahrtenbuch.kanuhub.de/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/fahrtenbuch.kanuhub.de/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }

    location ~ /\.(ht|git) { deny all; }
    location /config/      { deny all; }
    location /includes/    { deny all; }
}
```

## 7. Admin-Bereich schützen

Der Admin-Bereich (`/admin/`) verlangt zusätzlich zur App-Session eine
HTTP-Basic-Auth. Datei erstellen:

```bash
cd admin/
htpasswd -c .htpasswd admin
# Passwort eingeben
```

Weitere Admins später ohne `-c`:
```bash
htpasswd .htpasswd vorstand
```

## 8. Magic-Link testen

1. Im Admin-Bereich (Mitglieder anlegen) den ersten Mitglieds-Datensatz
   mit eurer E-Mail anlegen.
2. Auf der Hauptseite "Anmelden" klicken → E-Mail eingeben → Absenden.
3. Postfach checken → Klick auf den Link → ihr seid im Mitgliederbereich.

Falls keine Mail ankommt: SMTP-Werte prüfen, `logs/` checken.

## 9. Cronjob für Cleanup (empfohlen)

Abgelaufene Magic-Links + alte Logs aufräumen:

```cron
# /etc/cron.d/fahrtenbuch
15 3 * * * www-data /usr/bin/mysql -u fahrtenbuch fahrtenbuch -e "DELETE FROM auth_magic_links WHERE expires_at < NOW() - INTERVAL 1 DAY;"
```

Wenn ihr EFB-Sync (DKV) nutzt:
```cron
0 22 * * * www-data /usr/bin/php /var/www/fahrtenbuch/admin/efb-sync.php
```

## 10. Sicherheits-Checkliste

- [ ] HTTPS erzwungen (HTTP → HTTPS redirect)
- [ ] `config/config.php` Rechte 640, gehört root oder Webserver-User
- [ ] `.htpasswd` außerhalb des DocumentRoot ODER per `.htaccess` geschützt
- [ ] DB-User hat nur Zugriff auf `fahrtenbuch`-Datenbank
- [ ] `BETA_MODE` in config.php auf `false`
- [ ] `display_errors = 0` in php.ini oder config.php
- [ ] Regelmäßiges Backup der DB (mindestens täglich)

## 11. Update

```bash
cd /var/www/fahrtenbuch
git pull
# Eventuelle Schema-Änderungen in CHANGELOG.md prüfen und manuell ausführen
```

`config/config.php` wird durch `.gitignore` geschützt — kein Verlust beim Pull.

## Hilfe

- **Probleme:** Issues auf GitHub
- **Logs:** `logs/` (Browser-Konsole zusätzlich für JS-Fehler)
- **Debug:** `BETA_MODE = true` + `ini_set('display_errors', 1)` (NUR lokal!)
