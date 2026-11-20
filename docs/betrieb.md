# Betrieb und Deployment

Ziel: Hetzner Webhosting L (Apache, PHP-FPM, PostgreSQL, SSH auf Port 222, keine Daemons).
Deployt wird automatisch per GitHub Actions, sobald auf `main` alle Prüfungen grün sind.

## Ablauf

```
push auf main → CI (Prüfungen, Tests) → Job "Deployment":
  deploy/build.sh    git archive + composer --no-dev + asset-map:compile (ohne Tests, Doku, Dev-Werkzeuge)
  deploy/release.sh  tar über SSH nach releases/<zeit>-<commit>, dann deploy/remote.sh auf dem Server:
                     .env.local + Logs verlinken → cache:warmup → Migrationen → current umschalten → alte Releases löschen
                     danach Test von APP_URL/login, bei Fehler automatisch zurück auf das vorherige Release
```

Verzeichnisse auf dem Server (`<basis>`, z. B. `/usr/home/<benutzer>/public_html/projects/mesia`):

| Pfad | Inhalt |
|------|--------|
| `releases/<release>/` | ein vollständiges Release je Deployment (die letzten 5 bleiben) |
| `current` → `releases/…` | aktives Release. **Document Root der Subdomain: `<basis>/current/public`** |
| `previous` → `releases/…` | Release davor (für Rollback) |
| `shared/.env.local` | Zugangsdaten, einmalig von Hand angelegt, nie im Repo |
| `shared/log/` | `prod-JJJJ-MM-TT.log`, 14 Tage |

## Einmalige Einrichtung

**1. Hetzner-Verwaltung (konsoleH)**

1. PostgreSQL-Datenbank mit Benutzer anlegen. Host, Name, Benutzer und Passwort notieren.
2. Subdomain anlegen, Document Root `<basis>/current/public` (hier: `public_html/projects/mesia/current/public`).
   Den dafür angelegten leeren Ordner `current/public` ersetzt das erste Deployment durch den Symlink.
   **Achtung:** `<basis>` liegt unter `public_html`, dem Web-Verzeichnis der Hauptdomain. Ohne Sperre wären
   Zugangsdaten, Logs und Code über `https://<hauptdomain>/projects/mesia/…` abrufbar. Deshalb sperrt
   `<basis>/.htaccess` alles (Schritt 2, danach pflegt es jedes Deployment), `public/.htaccess` gibt nur die App frei.
3. PHP-Version der Subdomain: 8.5. SSL-Zertifikat (Let's Encrypt) aktivieren und **HTTPS erzwingen**.

**2. Zugangsdaten auf dem Server** (per SSH)

```bash
mkdir -p ~/public_html/projects/mesia/shared
# Zuerst sperren, BEVOR Zugangsdaten angelegt werden (gleicher Inhalt, den später deploy/remote.sh schreibt)
printf '<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n' > ~/public_html/projects/mesia/.htaccess
cd ~/public_html/projects/mesia/shared
php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'   # Ausgabe = APP_SECRET
nano .env.local && chmod 600 .env.local
```

Prüfen, dass die Sperre wirkt (muss `403` liefern, niemals `200`):

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://<hauptdomain>/projects/mesia/shared/.env.local
```

```dotenv
APP_ENV=prod
APP_SECRET=<ausgabe von oben>
DATABASE_URL="postgresql://<benutzer>:<passwort>@<host>:5432/<datenbank>?serverVersion=<version>&charset=utf8"
DEFAULT_URI=https://<subdomain>
```

Sonderzeichen im Passwort URL-kodieren (`@` → `%40`, `:` → `%3A`, `?` → `%3F`, `/` → `%2F`, `#` → `%23`,
`(` → `%28`, `$` → `%24`). Am einfachsten ist ein langes Passwort nur aus Buchstaben und Ziffern.

**PostgreSQL-Version (`<version>`):** Zuerst einen Platzhalter eintragen (z. B. `serverVersion=16`), dann den
Hosting-Check (Schritt 3) laufen lassen. Er verbindet sich mit den Zugangsdaten aus `.env.local` und zeigt die
echte Version. Diese eintragen, nur die Hauptversion genügt (z. B. `16`). Alternativ in phpPgAdmin oder per
`psql -h <host> -U <benutzer> <datenbank> -c 'SHOW server_version'`.

**3. Hosting-Check** (von WSL aus, ändert nichts)

```bash
ssh -p 222 <benutzer>@<host> 'sh -s -- ~/public_html/projects/mesia' < deploy/hosting-check.sh
```

Zeigt PHP-Binaries und -Erweiterungen, Limits, Werkzeuge und die PostgreSQL-Version. Wichtig: welches CLI-PHP
dieselbe Version wie die Subdomain hat (→ Variable `DEPLOY_PHP`).

**4. Deploy-Schlüssel** (nur für GitHub Actions, ohne Passphrase)

```bash
ssh-keygen -t ed25519 -N "" -C "github-actions mesia" -f ~/.ssh/mesia_deploy
ssh-copy-id -p 222 -i ~/.ssh/mesia_deploy.pub <benutzer>@<host>   # oder in konsoleH hinterlegen
ssh-keyscan -p 222 <host>                                               # Ausgabe = DEPLOY_KNOWN_HOSTS
```

**5. GitHub** → Repository → Settings → Environments → `production` anlegen (optional: nur Branch `main` erlauben)

| Art | Name | Wert |
|-----|------|------|
| Variable | `DEPLOY_HOST` | SSH-Host |
| Variable | `DEPLOY_USER` | SSH-Benutzer |
| Variable | `DEPLOY_PORT` | `222` (Standard) |
| Variable | `DEPLOY_PATH` | **absoluter** Pfad zu `<basis>` (kein `~`) |
| Variable | `DEPLOY_PHP` | CLI-PHP laut Hosting-Check, z. B. `php` |
| Variable | `APP_URL` | `https://<subdomain>` (ohne `/` am Ende) |
| Variable | `DEPLOY_PHP_VERSION` | optional, PHP-Version für den Build (Standard `8.5`) |
| Secret | `DEPLOY_SSH_KEY` | Inhalt von `~/.ssh/mesia_deploy` (privater Schlüssel) |
| Secret | `DEPLOY_KNOWN_HOSTS` | Ausgabe von `ssh-keyscan` |

Fehlt etwas davon, bricht der Deployment-Job im ersten Schritt mit einer Meldung ab, welche Einträge fehlen.
Die Einträge müssen in der Umgebung `production` liegen (oder als Repository-Variablen bzw. -Secrets).

**6. Erstes Deployment:** Actions → CI → „Run workflow“ (oder ein Push auf `main`).

**7. Haushalt und Konten anlegen** (Passwort wird abgefragt)

```bash
ssh -t -p 222 <benutzer>@<host>
cd ~/public_html/projects/mesia/current
php bin/console app:household:create "<Haushaltsname>"           # gibt die Haushalts-ID aus
php bin/console app:user:create <email> <Name> --household=<ID>   # für jede Person
```

## Im Betrieb

| Aufgabe | Befehl |
|---------|--------|
| Logs ansehen | `ssh -p 222 <benutzer>@<host> 'tail -n 100 ~/public_html/projects/mesia/shared/log/prod-*.log'` |
| Rollback | `ssh -p 222 <benutzer>@<host> 'sh -s rollback <basis>' < deploy/remote.sh` |
| Passwort neu setzen | auf dem Server: `php bin/console app:user:set-password <email>` |
| Von Hand deployen | `ddev exec deploy/build.sh var/build`, dann in WSL mit gesetzten `DEPLOY_*`-Variablen `deploy/release.sh var/build` |

## Gut zu wissen

- **Rollback setzt nur den Code zurück**, nicht die Datenbank. Migrationen deshalb so schreiben, dass auch die
  vorherige Version noch damit läuft (Spalten erst im nächsten Release entfernen).
- Nach dem Umschalten kann PHP bis zu ca. 2 Minuten lang noch Dateien des alten Releases ausliefern (Realpath-Cache).
- **Cronjobs** sind noch nicht nötig. Sobald es asynchrone Nachrichten gibt: jede Minute
  `cd <basis>/current && <php> bin/console messenger:consume async --time-limit=55`.
- **Backup:** Hetzner sichert Webspace und Datenbanken. Ein eigener Datenbank-Dump folgt später.
- Lokal nachstellen: `deploy/remote.sh` lässt sich im DDEV-Container gegen ein Verzeichnis unter `/tmp` ausprobieren.
