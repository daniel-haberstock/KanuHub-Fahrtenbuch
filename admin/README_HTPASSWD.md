# Admin-Bereich Passwortschutz

## .htpasswd Datei erstellen

Für den Passwortschutz des Admin-Bereichs muss eine `.htpasswd`-Datei erstellt werden.

### Über die Kommandozeile:

```bash
cd /mnt/web114/e1/80/5535580/htdocs/fahrtenbuch_1/admin/
htpasswd -c .htpasswd admin
```

Sie werden nach einem Passwort gefragt. Dieses Passwort wird verschlüsselt in der `.htpasswd` gespeichert.

### Über Online-Generator:

Falls `htpasswd` nicht verfügbar ist, können Sie einen Online-Generator verwenden:
- https://hostingcanada.org/htpasswd-generator/
- https://www.web2generators.com/apache-tools/htpasswd-generator

Erstellen Sie dort ein Passwort-Hash und speichern Sie das Ergebnis in:
`/mnt/web114/e1/80/5535580/htdocs/fahrtenbuch_1/admin/.htpasswd`

Format der .htpasswd:
```
admin:$apr1$xyz123$abc...
```

### Weiteren Benutzer hinzufügen:

```bash
htpasswd .htpasswd benutzername
```

**Wichtig:** Verwenden Sie `-c` nur beim ersten Mal, sonst wird die Datei überschrieben!

## Logout-Funktion

Der Logout-Button unter `/logout.php` setzt die Browser-Credentials zurück.
Nach dem Logout muss der Browser-Tab geschlossen werden, damit die Abmeldung vollständig ist.

**Hinweis:** Die logout.php befindet sich absichtlich außerhalb des `/admin/` Verzeichnisses,
damit man sich nicht erst einloggen muss, um sich auszuloggen.
