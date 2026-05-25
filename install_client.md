# Bootshaus-Kiosk auf Raspberry Pi

Wenn ihr ein Tablet/Display im Bootshaus wollt, auf dem das Fahrtenbuch
fest läuft — Vollbild, keine Adressleiste, kein Schließen-Button — dann
nehmt einen Raspberry Pi als Kiosk-Client.

> **Empfehlung:** Komplettes Setup ist im separaten Repo
> **[raspberry-kiosk](https://github.com/daniel-haberstock/KanuHub-Kiosk)**
> dokumentiert. Hier nur die Kurzfassung, was im Fahrtenbuch dafür
> konfiguriert werden muss.

## Hardware

- Raspberry Pi 4 oder 5 (Pi 5 empfohlen)
- 32 GB+ SD-Karte
- HDMI-Display (Touch optional, sonst Maus an USB)
- Stromversorgung, Ethernet-Kabel (stabiler als WLAN)

## Schritte (Kurz)

### 1. Auf dem Fahrtenbuch-Server

In `config/config.php` einen Kiosk-Token setzen, falls ihr den
Heartbeat-Status sehen wollt (kommt aus der Vereins-App, nicht dem
Fahrtenbuch — überspringen wenn ihr keine App habt):

```php
define('KIOSK_AUTH_TOKEN', 'random_string_32_chars');
```

### 2. Auf dem Pi

Folge der Anleitung im **[raspberry-kiosk-Repo](https://github.com/daniel-haberstock/KanuHub-Kiosk)**.
Quintessenz:

```bash
# Ubuntu Server 24.04 LTS flashen, einloggen, dann:
git clone https://github.com/daniel-haberstock/KanuHub-Kiosk.git
cd raspberry-kiosk
sudo ./kiosk-setup.sh https://fahrtenbuch.kanuhub.de
```

Während des Setups gefragt:
- **Kiosk-URL:** `https://fahrtenbuch.kanuhub.de`
- **Erlaubte Domains:** `fahrtenbuch.kanuhub.de` (Whitelist)
- **HTTP-Basic-Auth-User:** der Admin-User aus eurer `.htpasswd`
- **Heartbeat:** ja/nein

Nach 10-15 Min Reboot → Pi startet im Vollbild-Kiosk.

## Touch-/Tablet-Optimierung im Fahrtenbuch

Im Beta wurde dafür gesorgt, dass alle Buttons ≥44px Touch-Größe haben
und kein Hover-Status benötigt wird. Sollten Bugs auftreten:

- Touch funktioniert nicht in Modal: Bootstrap-Modal-Backdrop-Klick prüfen
- Pinch-Zoom unterdrücken: `<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">` ist gesetzt
- Auto-Logout-Timer (5 Min) ist sinnvoll für Kiosk-Mode, damit niemand
  ungewollt fremde Sessions findet

## Wartung

- **Updates:** Pi pullt Ubuntu-Updates automatisch (außer Kernel). Browser
  läuft im Snap, updated sich selbst.
- **Crash-Recovery:** Watchdog im raspberry-kiosk-Setup startet Chromium
  bei Absturz neu.
- **SSH-Zugang:** Setup-Skript richtet SSH ein. Niemals SSH ins Bootshaus
  ohne sicheres Passwort + ggf. WireGuard-VPN.

## Tipps

- **Energie sparen:** Display nachts ausschalten (HDMI-CEC), z.B. mit Cron:
  ```cron
  0 23 * * * /usr/bin/vcgencmd display_power 0
  0  7 * * * /usr/bin/vcgencmd display_power 1
  ```
  (Pi 5: Chip-Variante kann anders sein)
- **Backup-WLAN:** zweites SSID als Fallback in `wpa_supplicant.conf`
- **Druckerlose Fahrt-Bestätigung:** im Bootshaus kein QR-Code für
  Anmelde-Bestätigung — Fahrt wird im UI sofort als gestartet markiert
