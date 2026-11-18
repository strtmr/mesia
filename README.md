# Mealplanner

Essensplanung und gemeinsame, offlinefähige Einkaufsliste als PWA. Lernprojekt mit Symfony.

- Architektur und Entscheidungen: [docs/architektur.md](docs/architektur.md)
- Aktuelle Spezifikation (Phase 0 + 1): [docs/spezifikation-phase-0-1.md](docs/spezifikation-phase-0-1.md)

## Stack

PHP 8.5 · Symfony 8.1 · PostgreSQL 17 · Doctrine · Twig + Turbo/Stimulus (AssetMapper, kein Node-Build) ·
Einkaufsliste als Offline-Insel mit Alpine.js + Dexie.js (folgt in Phase 1)

## Lokale Entwicklung (DDEV)

```bash
ddev start
ddev composer install
ddev npm ci                                    # nur Test-Werkzeuge (Vitest)
ddev exec bin/console doctrine:migrations:migrate -n
```

App: https://mealplanner.ddev.site

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
