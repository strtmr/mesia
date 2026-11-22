# Mesia

Essensplanung und gemeinsame, offlinefähige Einkaufsliste als PWA. Lernprojekt mit Symfony.

- Benutzerhandbuch: [docs/benutzerhandbuch.md](docs/benutzerhandbuch.md)
- Entwicklerdokumentation (Module, Sync-API, Stolperfallen): [docs/entwicklung.md](docs/entwicklung.md)
- Brand Book (Farben, Logo, Typografie, UI-Regeln): [docs/brand-book.md](docs/brand-book.md)
- Betrieb und Deployment (Hetzner, GitHub Actions): [docs/betrieb.md](docs/betrieb.md)
- Architektur und Entscheidungen: [docs/architektur.md](docs/architektur.md)
- Aktuelle Spezifikation (Phase 0 + 1): [docs/spezifikation-phase-0-1.md](docs/spezifikation-phase-0-1.md)

## Stack

PHP 8.5 · Symfony 8.1 · PostgreSQL 17 · Doctrine · Twig + Turbo/Stimulus (AssetMapper, kein Node-Build) ·
Einkaufsliste als Offline-Insel mit Alpine.js + Dexie.js (IndexedDB) + SortableJS

## Lokale Entwicklung (DDEV)

```bash
ddev start
ddev composer install
ddev npm ci                                    # nur Test-Werkzeuge (Vitest)
ddev exec bin/console doctrine:migrations:migrate -n
```

App: https://mealplanner.ddev.site

### Haushalt und Benutzer anlegen

Es gibt keine Selbstregistrierung. Haushalt und Benutzer werden per Konsole angelegt, das Passwort wird verdeckt abgefragt:

```bash
ddev exec bin/console app:household:create "Familie Muster"
ddev exec bin/console app:user:create marco@example.com Marco      # --household=<ID> nur nötig bei mehreren Haushalten
ddev exec bin/console app:user:set-password marco@example.com       # Passwort zurücksetzen
ddev exec bin/console app:household:initialize                     # fehlende Grunddaten nach Updates ergänzen
```

Ein neuer Haushalt bekommt automatisch den Startkatalog (`data/seed/`) und eine Einkaufsliste.

### Auf dem iPhone testen

Service Worker und PWA-Installation brauchen HTTPS mit gültigem Zertifikat. Am einfachsten: `ddev share`
(öffentlicher Tunnel, URL im Safari öffnen, „Teilen“ → „Zum Home-Bildschirm“).

### Testdatenbank einmalig anlegen

```bash
ddev exec bin/console doctrine:database:create --env=test --if-not-exists
ddev exec bin/console doctrine:migrations:migrate --env=test -n
```

## Qualität

| Befehl | Zweck |
|--------|-------|
| `ddev exec vendor/bin/grumphp run` | alle Pre-Commit-Prüfungen über alle Dateien |
| `ddev exec bin/phpunit --testsuite unit` | schnelle Unit-Tests |
| `ddev exec bin/phpunit --testsuite integration` | Tests mit Kernel und PostgreSQL |
| `ddev npm run test:unit` | JavaScript-Tests (Vitest) |
| `ddev exec bash tests/e2e/run.sh` | Rauchtest im Browser (siehe docs/entwicklung.md) |
| `ddev exec vendor/bin/php-cs-fixer fix` | Code-Stil korrigieren |
| `ddev exec vendor/bin/phpstan analyse` | statische Analyse (Level 8) |
| `ddev exec vendor/bin/deptrac analyse` | Modulgrenzen prüfen |

Der Git-Hook (GrumPHP) läuft auf dem Host und führt die Prüfungen per `ddev exec` im Container aus. DDEV muss
dafür laufen. Nach einem frischen Clone: `ddev exec vendor/bin/grumphp git:init`.

## Struktur

```
src/<Modul>/Domain           Entities (Domain/Model), Regeln, Interfaces
src/<Modul>/Application      Anwendungsfälle, Services
src/<Modul>/Infrastructure   Doctrine, DBAL, externe Dienste
src/<Modul>/UI               Controller, Forms
```

Module: `Shared`, `Identity`, `Catalog`, `Shopping`, `Sync`. Erlaubte Abhängigkeiten stehen in `deptrac.yaml`.
