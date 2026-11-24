# Entwicklerdokumentation

Kurzreferenz für die Arbeit am Code. Hintergründe und Entscheidungen: [architektur.md](architektur.md),
aktueller Umfang: [spezifikation-phase-0-1.md](spezifikation-phase-0-1.md), Setup: [README](../README.md),
Deployment: [betrieb.md](betrieb.md).

## Module

| Modul | Inhalt | darf nutzen |
|-------|--------|-------------|
| `Shared` | Änderungsprotokoll, `Syncable`, `SyncableFields`, `SortKey`, Sync-Schnittstellen, Ereignis `HouseholdCreated` | – |
| `Identity` | Haushalt, Benutzer, Login, `/api/me`, Konsolenbefehle | Shared |
| `Catalog` | Kategorien, Artikel, Aliase, Startkatalog, `TextNormalizer`, `Unit` | Shared |
| `Shopping` | Liste, Einträge mit Beiträgen, Kaufhistorie, Läden (`/mehr/laeden`) | Shared, Catalog |
| `Notification` | Web-Push-Abos, Versand der Mitteilungen (`/api/push`, `app:push:send`) | Shared |
| `Admin` | Admin-Bereich `/admin`: Überblick, Auswertungen, Haushalte und Personen anlegen | Shared, Identity |
| `Sync` | `/api/sync/*`, Mutation-Dispatcher, Change-Feed | alle |

Schichten je Modul: `Domain` (Entities in `Domain/Model`, Regeln), `Application` (Anwendungsfälle, Mutation-Handler),
`Infrastructure` (Repositories, DBAL), `UI` (Controller, Konsole). Geprüft mit deptrac (`deptrac.yaml`).

Module koppeln sich über Ereignisse statt über direkte Aufrufe: `UserRegistration` löst `HouseholdCreated` aus,
`Catalog` legt daraufhin den Startkatalog an und `Shopping` die Liste. `CategoryManagement` löst beim Löschen
`CategoryDeleted` aus, `Shopping` verschiebt seine Einträge nach „Sonstiges“ (alles in einer Transaktion).

Den Haushalt des angemeldeten Benutzers liefert `Shared\Application\CurrentHousehold`, Benutzer-ID und Haushalt
zusammen `Shared\Application\CurrentUser` (beide umgesetzt in Identity). So müssen Module wie `Catalog` oder
`Notification` die Benutzer-Entity nicht kennen.

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

Alle Antworten sind JSON in snake_case. Schreibende Requests brauchen den Header `X-Requested-With: mesia`.
Ohne Anmeldung antwortet die API mit `401` statt mit einer Weiterleitung.

| Endpunkt | Zweck |
|----------|-------|
| `GET /api/me` | Benutzer und Haushalt, gleichzeitig Prüfung der Sitzung |
| `GET /api/sync/snapshot` | `{cursor, entities: {category: [...], article: [...], article_alias: [...], store: [...], shopping_list: [...], shopping_item: [...]}}` |
| `POST /api/sync/push` | `{device_id, mutations: [{id, type, at, payload}]}` → `{results: [{id, status, merged_into?, error?}]}` |
| `GET /api/sync/pull?since=&limit=` | `{changes: [{seq, type, id, op, data}], cursor, has_more}` |

**Läden** (`store`, Modul Shopping) werden wie Kategorien nur auf einer Seite gepflegt („Mehr → Läden“,
`StoreManagement`). Einträge tragen `store_ids` (JSON-Liste) und `valid_until` (Datum, `YYYY-MM-DD`). Unbekannte
oder gelöschte Laden-IDs in Mutationen fallen still weg (offline zugeordnet, inzwischen gelöscht), gelöschte Läden
blendet der Client aus. Beim Reaktivieren bleibt `store_ids`, `valid_until` wird geleert. Die Startliste legt
`CreateDefaultStoresOnHouseholdCreated` an. Sie ergänzt nur, was fehlt (`seed_key`, auch gelöschte, und gleiche
Namen zählen als vorhanden), und läuft per `app:household:initialize` bei jedem Deployment. Neue Märkte also einfach
in `DEFAULT_STORES` ergänzen, `seed_key` nie wiederverwenden.

Kategorien werden nicht per Mutation geändert, sondern auf der Seite „Mehr → Kategorien“ (mit Verbindung). Verweist
eine Mutation auf eine inzwischen gelöschte Kategorie (`item.add`, `item.move`, `article.create/update`), landet der
Eintrag in der Fallback-Kategorie statt abgelehnt zu werden.

Status im Push-Ergebnis: `applied`, `merged` (auf bestehendes Objekt umgelenkt, `merged_into` = dessen ID), `ignored`
(veraltet bzw. ohne Wirkung), `rejected` (`error` lesbar), `duplicate` (schon verarbeitet, plus `original_status`).

### Mutationstypen

| Typ | Payload | Besonderheit |
|-----|---------|--------------|
| `item.add` | `item_id, list_id, name, category_id, sort_key, contribution{id, quantity?, unit?}, article_id?, note?, store_ids?, valid_until?` | gleicher Artikel schon auf der Liste → `merged`, erledigter Eintrag wird reaktiviert; Läden werden ergänzt, Datum gesetzt |
| `item.reactivate` | `item_id, contribution{…}` | alte Beiträge archiviert, manuelle Menge zurückgesetzt |
| `item.check` | `item_id` | `ignored`, wenn `at` vor dem letzten Reaktivieren liegt; erzeugt Kaufhistorie, `article.purchase_count` +1 |
| `item.uncheck` | `item_id` | entfernt den letzten Kaufhistorien-Eintrag, `article.purchase_count` −1 |
| `item.update` | `item_id, fields{name?, note?, qty_override?, unit_override?, store_ids?, valid_until?}` | fehlendes Feld = unverändert, `null` = leeren; `store_ids` ersetzt |
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

## Offline-Client (assets/shopping)

```
core/   reines JavaScript ohne DOM, getestet mit Vitest (tests/js)
  db.js        Dexie/IndexedDB: entities (bestätigter Serverzustand), outbox, meta (cursor, deviceId, householdId,
               snapshotVersion, people)
  state.js     buildState(server, outbox, {actorId}): Serverzustand + ausstehende Mutationen → Anzeigezustand (spiegelt die Server-Handler)
  status.js    „Hinzugefügt von … heute um …“ / „Erledigt von …“ für den Bearbeiten-Dialog
  commands.js  fachliche Aktionen → Mutationen (addFromText, checkItem, editItem, moveItem …)
  sync.js      SyncEngine: push → pull → Outbox bereinigen, Status, Timer
  quantity.js  Mengen: Grundrechenarten auswerten, Grenzen prüfen (MAX_QUANTITY = PHP Quantity::MAX, gemeinsam ändern)
  extras.js    Schnelleingabe „@Laden“ und „bis <Datum>“, Laden-Zuordnung und -Suche (searchStores), Datumsstatus
  quickadd.js, matcher.js, normalize.js, units.js, sortkey.js, view.js, api.js
ui/     Alpine.js: app.js (Einstieg, Store "sync"), list.js (Komponente)
```

- **Anzeige = Server + Outbox.** IndexedDB speichert nur Bestätigtes. Was die Oberfläche zeigt, wird im Speicher
  berechnet. Abgelehnte Mutationen verschwinden dadurch von selbst, und nach einem Pull muss nichts zurückgespielt werden.
- **Zyklus:** push → pull → bestätigte Mutationen aus der Outbox löschen → neu berechnen (in dieser Reihenfolge, sonst flackert es).
- **merged:** Server und Client lenken Folgemutationen auf die bestehende ID um (Server innerhalb eines Push, Client
  zwischen Batches und in der restlichen Outbox).
- **Laden:** `app.js` importiert `shopping/ui/app.js` nur, wenn die Seite `[data-shopping-app]` enthält. Die Listenseite
  setzt `turbo-cache-control: no-cache`, damit Turbo keine Alpine-Ausgabe wiederverwendet.
- **Alpine:** `$refs`/`$root` sind in Methoden nach einem `await` nicht mehr verfügbar. Die Komponente merkt sich ihr
  Wurzelelement in `init()`.
- **Alpine:** `@input` im Template läuft vor `x-model`, sieht also den alten Wert. Für Reaktionen auf Eingaben
  `$watch('query', …)` nutzen. Bindungen wie `:disabled` immer boolesch machen (`=== true`), ein `undefined` entfernt
  das Attribut nicht zuverlässig.
- **Längen:** Name 100, Notiz 200 Zeichen (`NAME_MAX_LENGTH`/`NOTE_MAX_LENGTH` in `quickadd.js`, gleich den
  Spaltenlängen und Payload-Constraints). Gezählt wird wie `mb_strlen` (`textLength()`).
- **Layout der Listenseite (iOS-Tastatur):** Die Seite ist eine feste Hülle in Größe von `visualViewport`
  (`trackVisualViewport()` in `ui/list.js` setzt `--vv-height`, `--vv-offset` und `html[data-keyboard]`). Nur
  `.app-main` scrollt. Wegen `transform` beziehen sich fixierte Elemente darin (Eingabeleiste, Tab-Leiste, Toast) auf
  die Hülle, sitzen also bei offener Tastatur direkt darüber. iOS ändert beim Öffnen der Tastatur nur den sichtbaren
  Bereich, nicht das Layout. Mit Chromium lässt sich das nicht testen, nur auf dem iPhone.
- **Abhaken bleibt stehen:** `check()` in `ui/list.js` speichert sofort, merkt sich den Eintrag aber in
  `keptInPlace`. `buildListView(state, {keepInPlace})` zeigt ihn weiter in seiner Gruppe (`checked: true`,
  durchgestrichen). Erst nach `KEEP_IN_PLACE_MS` ohne weiteres Abhaken wandert alles nach „Erledigt“. Danach
  ignoriert die Liste Tipps auf Kreise für `SETTLE_GUARD_MS`, damit kein Tipp die nachgerückte Zeile trifft. Ein Tipp
  auf einen noch stehenden Eintrag schickt `item.uncheck`.
- **„Erledigt“:** Der Kreis reaktiviert, der Name öffnet den Bearbeiten-Dialog. Dessen Knopf „Wieder auf die Liste“
  (`reactivateFromEditor()`) schickt `item.uncheck`, solange der Eintrag noch an seinem Platz steht (Menge bleibt),
  sonst `item.reactivate`.
- **Wer und wann:** Beiträge tragen `created_by`, Einträge `checked_by`. Namen liefert `/api/me`
  (`household.members`). Die SyncEngine speichert sie mit der eigenen ID in `meta.people` (auch offline verfügbar)
  und gibt die ID an `buildState` weiter, damit eigene, noch nicht gesendete Änderungen den Benutzer schon tragen.
- **Snapshot-Version:** Bekommt der Snapshot ein Feld, das schon vorhandene Objekte brauchen, `SNAPSHOT_VERSION` in
  `sync.js` erhöhen. Jedes Gerät lädt dann einmal einen frischen Snapshot (Outbox bleibt erhalten).
- **Laden-Filter:** `buildListView(state, {storeFilter})` zeigt nur Einträge dieses Ladens und liefert `storeChips`
  (Läden mit offenen Einträgen) und `hiddenCount`. Ein Filter auf einen Laden ohne offene Einträge wirkt nicht und
  wird verworfen. Gemerkt pro Gerät in `localStorage` (`mesia.storeFilter`).
- **Drag & Drop:** SortableJS verschiebt DOM-Knoten. `onEnd` setzt das DOM zurück und löst nur eine Mutation aus,
  Alpine rendert die neue Reihenfolge.
- UI-Texte stehen im Twig-Template bzw. in `translations/`, nicht im JavaScript.
- **Mengen in Eingabefeldern** immer mit `formatInputNumber()` (ohne Tausenderpunkt) füllen, sonst wird „1.000“ beim
  Speichern als 1 gelesen. `formatNumber()` nur für die Anzeige.
- **Abgelehnte Mutationen** zeigt die App mit Artikelname und der Server-Meldung an. Meldungen in Payload-Constraints
  deshalb deutsch und für Benutzer formulieren (der Dispatcher gibt sie unverändert weiter, Details ins Log).

## Service Worker (Offline-Start)

`/sw.js` wird von `ServiceWorkerController` aus `templates/pwa/sw.js.twig` gerendert, nicht als statische Datei
ausgeliefert. Nur so kennt er die versionierten AssetMapper-URLs.

| Anfrage | Strategie |
|---------|-----------|
| `/assets/*` | Cache zuerst (bei der Installation vollständig vorab geladen) |
| `/einkauf` (Listenhülle), `/` | Netzwerk zuerst, nach 4 s oder ohne Verbindung die gespeicherte Hülle |
| andere Seiten (auch Turbo-Requests) | Netzwerk zuerst, ohne Verbindung `public/offline.html` |
| `/api/*`, `/login`, `/logout`, Profiler | nie angefasst |

- **Die Listenhülle muss benutzerunabhängig bleiben** (keine Namen, keine Daten im Twig-Template), sie wird für alle
  Benutzer des Geräts aus dem Cache ausgeliefert. Daten kommen ausschließlich aus IndexedDB.
- **Version:** Hash über alle Asset-Pfade, `app.version` und die statischen Dateien. Ändert sich etwas, installiert
  der Browser den neuen Service Worker, löscht alte Caches und zeigt „Neue Version verfügbar – Neu laden“
  (`assets/pwa/register-service-worker.js`, `controllers/app_update_controller.js`).
- Neue statische Datei unter `public/`, die offline gebraucht wird → in `ServiceWorkerController::STATIC_FILES` eintragen.
- **Entwicklung:** Auch in dev ist der Service Worker aktiv. Jede Asset-Änderung erzeugt eine neue Version (Hinweis
  oben). Zum Zurücksetzen: Browser-Entwicklertools → Application → Storage → „Clear site data“.

## Mitteilungen (Web Push)

Modul `Notification`. Ein Abo gehört einem **Gerät** (Push-Endpoint des Browsers), nicht dem Haushalt, und wird
nicht synchronisiert.

- **Abo:** `push_settings_controller.js` (Mehr → Mitteilungen) fragt die Erlaubnis an, abonniert per
  `pushManager.subscribe()` mit dem öffentlichen VAPID-Schlüssel und meldet das Abo an `POST /api/push/subscription`
  (`DELETE` zum Abmelden, `GET /api/push?endpoint=…` für den Stand). Gleicher Endpoint = Update (Häufigkeit,
  anderes Konto).
- **Was gemeldet wird:** Einträge im `change_log` des Haushalts mit `entity_type = shopping_item`, deren Mutation
  `item.add` oder `item.reactivate` war (über `processed_mutation`), von jemand anderem als dem Abo-Inhaber
  (`DbalAddedItemsFeed`). `push_subscription.last_seq` merkt sich, bis wohin ein Gerät informiert ist. Ein neues Abo
  beginnt bei der aktuellen `seq`, nichts wird rückwirkend gemeldet.
- **Versand:** `app:push:send` (Cron jede Minute, `LockFactory`-Lock gegen Überlappung) ruft `PushDispatcher` auf.
  Pro fälligem Abo eine Sammel-Nachricht (`NotificationText`, höchstens drei Namen, Texte in `push.*`).
  „Alle 15 Minuten“ (`PushFrequency::QuarterHour`) wartet, bis die letzte Nachricht 15 Minuten her ist, und sammelt
  bis dahin. Antwortet der Push-Dienst mit 404/410, wird das Abo gelöscht; andere Fehler versucht der nächste Lauf.
- **Senden:** `WebPushSender` (minishlink/web-push mit Symfony-HTTP-Client als PSR-18). In Tests ersetzt
  `InMemoryPushSender` den Versand (`when@test` in `services.yaml`).
- **Service Worker:** `push` zeigt die Mitteilung (gleicher `tag`, ersetzt die vorige), `notificationclick` holt ein
  offenes Fenster nach vorn oder öffnet die Liste.
- **Schlüssel:** `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` (erzeugt `app:push:generate-keys`), `VAPID_SUBJECT`
  (https- oder mailto-URL, Standard `DEFAULT_URI`). Ohne Schlüssel zeigt die App „nicht eingerichtet“ und der
  Versand tut nichts. Lokal liegen Schlüssel in `.env.local`.
- **Testen am Gerät:** Headless-Chromium meldet `Notification.permission` immer als `denied`. Echte Zustellung nur
  auf einem Gerät mit HTTPS prüfbar (iPhone: installierte App, iOS 16.4+).

## Admin-Bereich

Modul `Admin`, nur mit `ROLE_ADMIN` (`access_control` für `^/admin` und `#[IsGranted]` am Controller). Vergeben
wird die Rolle nur per Konsole (`app:user:admin <email>`, Spalte `app_user.roles`). Unter „Mehr“ erscheint der
Link nur für Admins. Admin-Funktionen betreffen die Benutzer nicht: **keine neue Version, kein Eintrag in den
Neuigkeiten** (CLAUDE.md).

- **Auswertungen** (`AdminStatistics`, umgesetzt per SQL in `DbalAdminStatistics`): liest die Tabellen der anderen
  Module direkt, ohne deren Klassen. Artikel werden über Haushalte hinweg per `name_normalized` zusammengefasst.
  „Selbst angelegt“ = `article.source = 'user'`, angezeigt mit der **aktuellen Bezeichnung**: Name des zuletzt
  geänderten Listeneintrags, sonst der Artikelname (umbenannt wird in der App nur der Eintrag). „Oft gekauft“ =
  `purchase_event` (Abhaken, Rückgängig löscht den Eintrag wieder).
- **Löschen** (nur „Selbst angelegt“): `Catalog\Application\ArticleRemoval` löscht den Artikel in jedem Haushalt
  weich, samt Aliasen (Sperre, Änderungsprotokoll), und löst `ArticleDeleted` aus. Shopping entfernt daraufhin die
  erledigten Einträge dieses Artikels, offene bleiben stehen. Dafür darf `Admin` auch `Catalog` nutzen.
- **Anlegen:** `UserRegistration::createHouseholdWithUser()` legt Haushalt und erste Person in einer Transaktion an
  (Startkatalog und Liste per `HouseholdCreated`, wie `app:household:create`). Ob die E-Mail frei ist, prüft der
  Controller vorher, damit das Formular eine Meldung am Feld zeigen kann.
- Rollenänderungen melden laufende Sitzungen ab (Symfony vergleicht die Rollen), „Angemeldet bleiben“ meldet
  gleich wieder an.

## Tests

| Befehl | Was |
|--------|-----|
| `ddev exec bin/phpunit` | PHP: Unit, Integration, Functional (Sync-API) |
| `ddev npm run test:unit` | JS-Kern (Vitest, IndexedDB per fake-indexeddb) |
| `ddev exec bash tests/e2e/run.sh` | Rauchtest im echten Browser (Playwright/Chromium, iPhone-Viewport) gegen die Dev-App, inkl. Kaltstart offline und zweitem Gerät. Legt einen Haushalt „E2E-Test“ an, Screenshots unter `var/e2e/`. Einmalig: `ddev exec 'sudo npx playwright install-deps chromium && npx playwright install chromium --only-shell'` (nach `ddev restart` erneut nötig) |

## Stolperfallen

- **Zeitstempel sind sekundengenau.** DBAL speichert sie in PostgreSQL ohne Sekundenbruchteile. Der Dispatcher kürzt
  `at`/`now` deshalb auf ganze Sekunden, damit Vergleiche vor und nach dem Neuladen gleich ausfallen.
- **Geräteuhr:** `at` aus der Zukunft wird auf „jetzt“ begrenzt.
- **jsonb** speichert Schlüssel in eigener Reihenfolge. In Tests `assertEquals` statt `assertSame` verwenden.
- **Partielle Indizes:** Die `where`-Bedingung im Mapping muss so geschrieben sein, wie PostgreSQL sie zurückgibt
  (z. B. `'(deleted_at IS NULL)'`), sonst meldet `doctrine:schema:validate` dauerhaft eine Abweichung.
- **Sortierschlüssel** (`sort_key`) haben Collation `C` und werden byteweise verglichen (Format von fractional-indexing).
- **Startkatalog** (`data/seed/`): Regeln stehen im Kopf von `articles.yaml` (Mehrzahl als Name → Einzahl als Alias).
  Er wird beim Anlegen eines Haushalts kopiert. Spätere Erweiterungen übernimmt `app:catalog:update` (läuft bei jedem
  Deployment, Regeln im Kommentar von `CatalogUpdater`: nur ergänzen, Seed-Artikel in die neue Mehrzahl umbenennen,
  nichts löschen, Eigenes nicht anfassen). Ob ein Artikel aus dem Startkatalog stammt, steht in `article.source`.
  Nach Änderungen die Anzahlen in `HouseholdInitializationTest` und `SyncApiTest` anpassen, `SeedCatalogTest` prüft
  auf Dubletten.
- **Normalform ist länger als das Original:** ä/ö/ü/ß werden zu ae/oe/ue/ss. Spalten für Normalformen
  (`name_normalized`, `alias_normalized`) sind deshalb doppelt so lang wie die Eingabegrenze (200 statt 100).
- **Datenfehler beim Speichern** (SQLSTATE 22/23) lehnt der Dispatcher nur für die betroffene Mutation ab und
  protokolliert sie als Fehler. Andere Fehler (z. B. Datenbank weg) lassen den Push scheitern, damit das Gerät es
  später erneut versucht. So kann eine einzelne fehlerhafte Mutation den Abgleich nicht dauerhaft blockieren.
- **`change_log.mutation_id`** wird nur gesetzt, wenn `flush()` innerhalb von `ChangeContext::runForMutation()`
  läuft (der Doctrine-Listener schreibt das Protokoll beim Flush). Mitteilungen hängen davon ab.
- **`make:migration` mit Tabellen aus einem anderen Branch:** Liegen in der Dev-Datenbank Tabellen, die der aktuelle
  Branch nicht kennt (z. B. aus einem Feature-Branch), erzeugt Doctrine dafür `DROP TABLE`. Generierte Migrationen
  vor dem Ausführen immer lesen und fremde Änderungen entfernen.
- **Normalisierung** gibt es in PHP und JS. Beide gemeinsam ändern und `tests/fixtures/normalize.json` ergänzen.
- **Test-Container:** private Services nur über `self::getContainer()` in KernelTestCase/WebTestCase holen
  (Trait `CreatesUsers` für Testbenutzer).
- **DAMA-Testbundle:** Jeder Test läuft in einer Transaktion, die für DBAL unsichtbar ist. Code, der eine Transaktion
  voraussetzt, muss sie im Test selbst öffnen.

## Design (Brand Book)

Gestaltungsregeln, Farben, Typografie, Komponenten und Kontrastwerte: [brand-book.md](brand-book.md) (lebende Fassung,
Grundlage `MESIA_Brand_Book_v1.pdf`). Hier nur die technischen Punkte. Palette:
`#01161e` Ink, `#124559` Deep, `#598392` Slate, `#aec3b0` Sage, `#eff6e0` Cream, `#f7f5ef` Paper (Hintergrund hell), `#ff6a2b` Signal-Orange.

- **Hintergrundfarbe** steht außer in `app.css` auch in `templates/base.html.twig` (`theme-color`), `public/manifest.webmanifest` und `public/offline.html`. Alle gemeinsam ändern.
- **Farben nur über Tokens** in `assets/styles/app.css` (`--mesia-*` Palette, `--color-*` Rollen, hell und dunkel).
  `--color-accent` (Deep/Sage) für Links und Überschriften, `--color-action` (Signal-Orange, Schrift Ink) für
  Primärknöpfe, Plus und Häkchen. Gedämpfter Text ist aus Slate abgedunkelt (`#4a6b78`, 4,5:1).
- **Schriften** (OFL, selbst ausgeliefert aus `assets/fonts/`, Quelle npm `@fontsource…`, Lizenzen daneben):
  Manrope für die App, Archivo Black nur für die Wortmarke (`.wordmark`, Versalien per CSS). Kein Google-Fonts-Abruf.
- **Radien:** Eingaben 8, Knöpfe 10, Karten 14, große Flächen 20 px.
- **Dichte:** Grundschrift 16 px (Eingaben nie kleiner, sonst zoomt iOS), Zeilen und Trefferflächen 44 px
  (`--touch-target`), Seitentitel 28 px, Kategorie-Überschriften 16 px in Markenfarbe.
- **Bildmarke im Template:** `templates/app/_brand_mark.html.twig`, `variant: 'sticker'` (Standard, Login) oder
  `'flat'` (Seitenkopf, Farben per CSS `.brand-mark__table` / `.brand-mark__bowl`).
- **Icons:** Quellen sind `public/icons/icon.svg` (abgerundet), `icon-maskable.svg` (randlos, auch iOS) und
  `favicon.svg` (Stufe Kontur). PNGs und `public/favicon.ico` erzeugt `ddev exec node scripts/render-icons.mjs`.
  Es rendert mit Chromium (Playwright), weil ImageMagick SVG-Konturen nicht darstellt. ImageMagick fügt nur die
  `favicon.ico` zusammen.

## Version und Neuigkeiten

Jede für Benutzer sichtbare Änderung bekommt einen Eintrag in `data/release-notes.yaml` (neueste Version oben, kurz,
ohne Technik). `app.version` in `config/services.yaml` muss der obersten Version entsprechen, das prüft
`ReleaseNotesTest`. Die App zeigt die Einträge unter „Mehr → Neuigkeiten“ (`/neuigkeiten`). Die Version fließt auch
in die Service-Worker-Version ein.

## Konsolenbefehle

| Befehl | Zweck |
|--------|-------|
| `app:household:create <name>` | Haushalt anlegen (inkl. Startkatalog und Liste) |
| `app:user:create <email> <name> [--household=ID]` | Benutzer anlegen, Passwort wird abgefragt |
| `app:user:set-password <email>` | Passwort neu setzen |
| `app:household:initialize [--household=ID]` | fehlende Grunddaten in bestehenden Haushalten ergänzen (idempotent) |
| `app:catalog:update [--dry-run] [--household=ID]` | Katalog-Abgleich: Erweiterungen des Startkatalogs übernehmen (idempotent, `--dry-run` zeigt nur an) |
| `app:user:admin <email> [--revoke]` | Admin-Rechte vergeben oder entziehen (Admin-Bereich unter Mehr → Admin) |
| `app:push:send` | fällige Mitteilungen verschicken (Cron jede Minute) |
| `app:push:generate-keys` | VAPID-Schlüsselpaar für `.env.local` erzeugen |
