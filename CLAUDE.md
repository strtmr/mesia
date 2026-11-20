# CLAUDE.md

@AGENTS.md

## Projektspezifisch

- Sprache im Projekt: Deutsch (Doku, Kommentare, UI-Texte). Code-Bezeichner Englisch.
- Doku pflegen: `docs/entwicklung.md` (Entwickler) und `docs/benutzerhandbuch.md` (Benutzer) bei jeder sichtbaren
  oder strukturellen Änderung mitziehen.
- Deployment und Server: `docs/betrieb.md` (Skripte in `deploy/`). Was nicht aufs Live-System gehört, steht als
  `export-ignore` in `.gitattributes`.
- Planung und Entscheidungen: `docs/architektur.md` (Kapitel 0 hat Vorrang), aktuelle Phase: `docs/spezifikation-phase-0-1.md`.
- Alle PHP-/Composer-/Console-/npm-Befehle laufen im DDEV-Container: `ddev exec bin/console …`, `ddev composer …`, `ddev npm …`.
- Vor dem Abschluss einer Änderung: `ddev exec vendor/bin/grumphp run` und `ddev exec bin/phpunit` müssen grün sein.
- Module unter `src/` mit Schichten Domain/Application/Infrastructure/UI. Entities liegen in `src/<Modul>/Domain/Model`
  und brauchen einen Mapping-Eintrag in `config/packages/doctrine.yaml`. Modulgrenzen: `deptrac.yaml`.
- Synchronisierte Entities implementieren `App\Shared\Domain\Syncable`. Das Änderungsprotokoll wird dann automatisch
  geschrieben. Schreibende Sync-Anwendungsfälle holen zu Beginn ihrer Transaktion `HouseholdWriteLock`.
- Normalisierungsregeln existieren zweimal (PHP `TextNormalizer`, JS `assets/shopping/core/normalize.js`). Beide
  gemeinsam ändern und `tests/fixtures/normalize.json` ergänzen.
- Hosting-Ziel: Hetzner Webhosting L (Shared): keine dauerhaft laufenden Prozesse (kein WebSocket/Mercure, Messenger
  per Cron).
