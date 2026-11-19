# Entwicklerdokumentation

Kurzreferenz für die Arbeit am Code. Hintergründe und Entscheidungen: [architektur.md](architektur.md),
aktueller Umfang: [spezifikation-phase-0-1.md](spezifikation-phase-0-1.md), Setup: [README](../README.md).

## Module

| Modul | Inhalt | darf nutzen |
|-------|--------|-------------|
| `Shared` | Änderungsprotokoll, `Syncable`, `SyncableFields`, `SortKey`, Sync-Schnittstellen, Ereignis `HouseholdCreated` | – |
| `Identity` | Haushalt, Benutzer, Login, `/api/me`, Konsolenbefehle | Shared |
| `Catalog` | Kategorien, Artikel, Aliase, Startkatalog, `TextNormalizer`, `Unit` | Shared |
| `Shopping` | Liste, Einträge mit Beiträgen, Kaufhistorie | Shared, Catalog |
| `Sync` | `/api/sync/*`, Mutation-Dispatcher, Change-Feed | alle |

Schichten je Modul: `Domain` (Entities in `Domain/Model`, Regeln), `Application` (Anwendungsfälle, Mutation-Handler),
`Infrastructure` (Repositories, DBAL), `UI` (Controller, Konsole). Geprüft mit deptrac (`deptrac.yaml`).

Module koppeln sich über Ereignisse statt über direkte Aufrufe: `UserRegistration` löst `HouseholdCreated` aus,
`Catalog` legt daraufhin den Startkatalog an und `Shopping` die Liste.

## Synchronisierte Entities

1. Entity implementiert `App\Shared\Domain\Syncable` und nutzt den Trait `SyncableFields`
   (Haushalt, Version, Zeitstempel, Soft Delete).
2. `syncEntityType()` liefert einen stabilen Namen, `toSyncSnapshot()` den vollständigen Zustand (snake_case, mit `syncMeta()`).
3. Mapping-Eintrag in `config/packages/doctrine.yaml` (falls neues Modul), dann `make:migration`.
4. Einen `SnapshotProvider` des Moduls ergänzen, damit die Entity im Snapshot erscheint.

Jede Änderung landet dann automatisch im `change_log` (Doctrine-Listener, gleiche Transaktion). Gelöscht wird nur weich
(`softDelete()`), sonst erfahren die Geräte nichts von der Löschung.

**Aggregate:** Kind-Objekte ohne eigenen Sync (z. B. `ItemContribution`) ändern immer auch ihr Wurzelobjekt
(`touch()`). Nur so erzeugt Doctrine ein Update und damit einen Protokolleintrag.

## Sync-API

Alle Antworten sind JSON in snake_case. Schreibende Requests brauchen den Header `X-Requested-With: mealplanner`.
Ohne Anmeldung antwortet die API mit `401` statt mit einer Weiterleitung.

| Endpunkt | Zweck |
|----------|-------|
| `GET /api/me` | Benutzer und Haushalt, gleichzeitig Prüfung der Sitzung |
| `GET /api/sync/snapshot` | `{cursor, entities: {category: [...], article: [...], article_alias: [...], shopping_list: [...], shopping_item: [...]}}` |
| `POST /api/sync/push` | `{device_id, mutations: [{id, type, at, payload}]}` → `{results: [{id, status, merged_into?, error?}]}` |
| `GET /api/sync/pull?since=&limit=` | `{changes: [{seq, type, id, op, data}], cursor, has_more}` |

Status im Push-Ergebnis: `applied`, `merged` (auf bestehendes Objekt umgelenkt, `merged_into` = dessen ID), `ignored`
(veraltet bzw. ohne Wirkung), `rejected` (`error` lesbar), `duplicate` (schon verarbeitet, plus `original_status`).

### Mutationstypen

| Typ | Payload | Besonderheit |
|-----|---------|--------------|
| `item.add` | `item_id, list_id, name, category_id, sort_key, contribution{id, quantity?, unit?}, article_id?, note?` | gleicher Artikel schon auf der Liste → `merged`, erledigter Eintrag wird reaktiviert |
| `item.reactivate` | `item_id, contribution{…}` | alte Beiträge archiviert, manuelle Menge zurückgesetzt |
| `item.check` | `item_id` | `ignored`, wenn `at` vor dem letzten Reaktivieren liegt; erzeugt Kaufhistorie |
| `item.uncheck` | `item_id` | entfernt den letzten Kaufhistorien-Eintrag |
| `item.update` | `item_id, fields{name?, note?, qty_override?, unit_override?}` | fehlendes Feld = unverändert, `null` = leeren |
| `item.move` | `item_id, category_id, sort_key` | Kategoriewechsel wird vom Artikel übernommen |
| `item.delete` | `item_id` | Soft Delete |
| `article.create` | `article_id, name, category_id` | Name/Alias existiert → `merged` |
| `article.update` | `article_id, name?, category_id?` | |
| `alias.add` | `alias_id, article_id, alias` | |

### Neuen Mutationstyp hinzufügen

1. Payload-Klasse (readonly, Validator-Attribute) und Handler im zuständigen Modul unter `Application/Mutation`.
2. Handler implementiert `MutationHandler` (`mutationType()`, `payloadClass()`, `handle()`). Er wird automatisch registriert.
3. Fachliche Fehler als `MutationRejected` werfen, nie selbst `flush()` aufrufen (macht der Dispatcher).
4. Functional-Test in `tests/Functional/SyncApiTest.php` ergänzen.

## Stolperfallen

- **Zeitstempel sind sekundengenau.** DBAL speichert sie in PostgreSQL ohne Sekundenbruchteile. Der Dispatcher kürzt
  `at`/`now` deshalb auf ganze Sekunden, damit Vergleiche vor und nach dem Neuladen gleich ausfallen.
- **Geräteuhr:** `at` aus der Zukunft wird auf „jetzt“ begrenzt.
- **jsonb** speichert Schlüssel in eigener Reihenfolge. In Tests `assertEquals` statt `assertSame` verwenden.
- **Partielle Indizes:** Die `where`-Bedingung im Mapping muss so geschrieben sein, wie PostgreSQL sie zurückgibt
  (z. B. `'(deleted_at IS NULL)'`), sonst meldet `doctrine:schema:validate` dauerhaft eine Abweichung.
- **Sortierschlüssel** (`sort_key`) haben Collation `C` und werden byteweise verglichen (Format von fractional-indexing).
- **Normalisierung** gibt es in PHP und JS. Beide gemeinsam ändern und `tests/fixtures/normalize.json` ergänzen.
- **Test-Container:** private Services nur über `self::getContainer()` in KernelTestCase/WebTestCase holen
  (Trait `CreatesUsers` für Testbenutzer).
- **DAMA-Testbundle:** Jeder Test läuft in einer Transaktion, die für DBAL unsichtbar ist. Code, der eine Transaktion
  voraussetzt, muss sie im Test selbst öffnen.

## Konsolenbefehle

| Befehl | Zweck |
|--------|-------|
| `app:household:create <name>` | Haushalt anlegen (inkl. Startkatalog und Liste) |
| `app:user:create <email> <name> [--household=ID]` | Benutzer anlegen, Passwort wird abgefragt |
| `app:user:set-password <email>` | Passwort neu setzen |
| `app:household:initialize [--household=ID]` | fehlende Grunddaten in bestehenden Haushalten ergänzen (idempotent) |
