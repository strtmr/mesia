# Spezifikation Phase 0 + 1: Fundament und offlinefähige Einkaufsliste

Entwurf v0.1 · Stand 2026-11-18 · Basis: [architektur.md](architektur.md), Kapitel 0

**Ziel am Ende von Phase 1 (MVP):** Zwei Personen nutzen auf ihren iPhones eine gemeinsame Einkaufsliste als PWA. Sie
funktioniert offline, ordnet Artikel automatisch Kategorien zu, lässt sich sortieren, behält abgehakte Artikel als
Historie und synchronisiert zuverlässig. Sie ersetzt damit iOS Erinnerungen.

---

## 1. Umfang

| Enthalten | Bewusst nicht enthalten |
|-----------|-------------------------|
| Login, Haushalt mit 2 Benutzern (über die Konsole angelegt) | Selbstregistrierung, Einladungs-Mails, Passkeys |
| Eine Einkaufsliste pro Haushalt | Mehrere Listen, Läden, Ladenlayouts |
| Artikelkatalog mit deutschem Seed, Aliasen, Kategorien | KI-Kategorisierung (kommt mit Phase 4b als Hintergrundjob) |
| Schnelleingabe mit Mengenerkennung („2 l Milch“) | Rezepte, Wochenplan (Beiträge sind vorerst nur `manual`) |
| Abhaken, Rückgängig, Erledigt-Bereich, Reaktivieren | Kaufauswertungen, Statistik |
| Drag & Drop für Kategorien und Artikel, Kategorie wechseln mit Lerneffekt | Web Push |
| Offline-Betrieb, Sync per Polling, Sync-Statusanzeige | Echtzeit per WebSocket/SSE |
| PWA, für iOS optimiert, Dark Mode | Englische Übersetzung (Struktur ist aber vorbereitet) |
| Git, GrumPHP, CI, Tests | automatisiertes Deployment |

---

## 2. Projektstruktur

```
mesuna/
├── .ddev/                      PHP + PostgreSQL in den Versionen von Hetzner
├── assets/
│   ├── app.js                  Turbo + Stimulus (Verwaltungsseiten)
│   ├── controllers/            Stimulus-Controller
│   ├── shopping/               ── Offline-Insel Einkaufsliste ──
│   │   ├── core/               reines JS, ohne DOM, mit Vitest getestet
│   │   │   ├── db.js           Dexie-Schema (IndexedDB)
│   │   │   ├── outbox.js       Mutationen anlegen/lesen/bestätigen
│   │   │   ├── sync.js         Push/Pull-Zyklus, Snapshot, Fehlerbehandlung
│   │   │   ├── commands.js     fachliche Aktionen: addItem, check, move …
│   │   │   ├── normalize.js    Text-Normalisierung (gleiche Regeln wie PHP)
│   │   │   ├── matcher.js      Freitext → Artikel (Alias, Normalform, Ähnlichkeit)
│   │   │   ├── quickadd.js     „2 l Milch“ → {qty, unit, name}
│   │   │   └── sortkey.js      Fractional Index (Bibliothek fractional-indexing)
│   │   └── ui/
│   │       └── list.js         Alpine-Komponente, bindet core an das DOM
│   ├── pwa/                    Service-Worker-Registrierung (der Service Worker selbst: templates/pwa/sw.js.twig)
│   └── styles/
├── config/
├── data/seed/                  categories.yaml, articles.yaml (deutscher Grundkatalog)
├── docs/
├── migrations/
├── src/
│   ├── Shared/                 Uuid, Clock, ChangeLog, Basisklassen, Doctrine-Typen
│   ├── Identity/               User, Household, Member, Security, Konsole-Commands
│   ├── Catalog/                Category, Article, ArticleAlias, Unit (Enum), Normalizer, Seeder
│   ├── Shopping/               ShoppingList, Item, Contribution, PurchaseEvent, Mutation-Handler
│   └── Sync/                   Controller (snapshot/push/pull), MutationDispatcher, ProcessedMutation
├── templates/
├── tests/
│   ├── fixtures/normalize.json gemeinsame Testfälle für PHP **und** JS
│   ├── Unit/  Integration/  Functional/
│   └── js/                     Vitest
├── grumphp.yml  deptrac.yaml  phpstan.dist.neon  .php-cs-fixer.dist.php
├── importmap.php               Alpine, Dexie, SortableJS, fractional-indexing (über AssetMapper)
└── package.json                **nur** für Vitest/Playwright (Dev), nicht für den Build
```

Jedes Modul unter `src/` ist pragmatisch geschichtet: `Domain/` (Entities, Regeln), `Application/` (Handler,
Services), `Infrastructure/` (Repositories, Doctrine), `UI/` (Controller, Forms).

**deptrac-Regeln:** Alle Module dürfen `Shared` nutzen. `Shopping` darf `Catalog` nutzen, umgekehrt nicht. `Sync` darf
`Shopping` und `Catalog` nutzen (es verteilt die Mutationen an deren Handler). `Identity` hängt von keinem Fachmodul ab.

---

## 3. Datenmodell

Konventionen: `id UUID` (v7, vom Client erzeugbar), `version INT` (+1 bei jeder Änderung), `created_at/updated_at
TIMESTAMPTZ`, `deleted_at TIMESTAMPTZ NULL` für synchronisierte Tabellen.

### 3.1 Identity

| Tabelle | Felder |
|---------|--------|
| `app_user` | id, email (unique), password_hash, display_name, household_id, created_at |
| `household` | id, name, created_at |

Für Phase 1 genügt: ein Benutzer gehört genau einem Haushalt. `household_member` (auch für Personen ohne Login) kommt
erst mit Phase 3 (Portionen, Präferenzen).

### 3.2 Catalog

**Entscheidung:** Der Seed-Katalog wird beim Anlegen eines Haushalts **in den Haushalt kopiert**. Dadurch braucht es
keine Override-Tabellen, und jeder Haushalt kann seinen Katalog frei ändern. Bei einigen hundert Artikeln pro Haushalt
ist das unproblematisch.

| Tabelle | Felder |
|---------|--------|
| `category` | id, household_id, seed_key (z. B. `produce`, NULL bei eigenen), name, icon (Emoji), sort_key, is_fallback (genau eine pro Haushalt: „Sonstiges“), version, deleted_at |
| `article` | id, household_id, name, name_normalized, category_id, default_unit NULL, seed_key NULL, version, deleted_at |
| `article_alias` | id, article_id, household_id, alias_normalized, source (`seed`\|`user`), created_at · unique(household_id, alias_normalized) |

`Unit` ist in Phase 1 ein **PHP-Enum und eine JS-Konstante** (keine Tabelle): `g, kg, ml, l, stk, pck, bund, dose,
flasche, glas, becher, el, tl, prise`, jeweils mit Anzeigename und Erkennungsvarianten („Stück“, „St.“, „Stk“ → `stk`).

### 3.3 Shopping

| Tabelle | Felder |
|---------|--------|
| `shopping_list` | id, household_id, name, version |
| `shopping_list_item` | id, list_id, household_id, article_id NULL, name, category_id, note NULL, qty_override NUMERIC NULL, unit_override NULL, sort_key, checked_at NULL, checked_by NULL, activated_at, version, created_by, updated_by, created_at, updated_at, deleted_at |
| `item_contribution` | id, item_id, source_type (`manual` in Phase 1), source_ref NULL, quantity NUMERIC NULL, unit NULL, active BOOL, created_by, created_at |
| `purchase_event` | id, household_id, item_id, article_id NULL, name, quantity NULL, unit NULL, user_id, purchased_at |

**Regeln:**
- `article_id` ist eindeutig pro Liste unter den nicht gelöschten Einträgen (Index unique(list_id, article_id) WHERE deleted_at IS NULL). Das ist die serverseitige Absicherung gegen Duplikate.
- Angezeigte Menge = `qty_override` falls gesetzt, sonst die Summe der aktiven Beiträge mit kompatibler Einheit. Inkompatible Einheiten werden getrennt angezeigt („500 g + 1 Pck“).
- `activated_at` wird beim Anlegen und beim Reaktivieren gesetzt (siehe 4.4).

### 3.4 Sync

| Tabelle | Felder |
|---------|--------|
| `change_log` | seq BIGSERIAL (PK), household_id, entity_type, entity_id, op (`upsert`\|`delete`), snapshot JSONB, actor_id, mutation_id NULL, created_at · Index (household_id, seq) |
| `processed_mutation` | mutation_id (PK, UUID), household_id, device_id, status, result JSONB, processed_at |

Der ChangeLog speichert immer den **vollständigen Zustand nach der Änderung**. Der Client übernimmt ihn per Upsert. Das
ist einfacher und robuster als Diffs. Für die Audit-Ansicht genügt das ebenfalls.

---

## 4. Sync-Protokoll

### 4.1 Grundprinzip

```
  iPhone (offline-fähig)                                   Server (Symfony)
  ─────────────────────                                    ────────────────
  Aktion → sofort lokal in IndexedDB anwenden
         → Mutation in die Outbox
  Sync-Zyklus:
    1. POST /api/sync/push  {mutations[]}   ─────────►   in Reihenfolge, jede in eigener Transaktion,
                                                         idempotent über mutation_id
                            ◄─────────  {results[]}
    2. GET /api/sync/pull?since=<cursor>    ─────────►   change_log WHERE seq > cursor (max 500)
                            ◄─────────  {changes[], cursor, has_more}
    3. Änderungen per Upsert lokal übernehmen, Cursor speichern
```

**Auslöser:** App-Start, `visibilitychange` → sichtbar, `online`-Event, nach jeder lokalen Aktion (entprellt, 1 s),
Timer alle 8 s, solange die Liste sichtbar ist. Safari kennt keine Background Sync API. Ohne geöffnete App wird also
nicht synchronisiert, und das ist so gewollt.

**Server ist die Wahrheit:** Nach dem Pull ist der lokale Zustand = Serverzustand + noch nicht bestätigte eigene
Mutationen. Der Client wendet nach jedem Pull die offenen Outbox-Mutationen erneut lokal an (Rebase).

### 4.2 Endpunkte

| Methode | Pfad | Zweck |
|---------|------|-------|
| GET | `/api/sync/snapshot` | Erstbefüllung: alle Kategorien, Artikel, Aliase, Liste, Einträge + `cursor` |
| POST | `/api/sync/push` | Mutationen senden |
| GET | `/api/sync/pull?since=123` | Änderungen seit Cursor. Antwortet mit `410 Gone`, falls der Log bereinigt wurde → neuer Snapshot |
| GET | `/api/me` | Wer bin ich, Haushalt. Antwortet mit `401` bei abgelaufener Sitzung |

**Authentifizierung:** dieselbe Session wie die Webseiten (Cookie, `HttpOnly`, `Secure`, `SameSite=Lax`) plus
langlebiges Remember-me (z. B. 1 Jahr). CSRF-Schutz für die JSON-API: Pflicht-Header `X-Requested-With: mesia`
und Prüfung des `Origin`-Headers.

### 4.3 Beispiel Push

```json
POST /api/sync/push
{
  "device_id": "0193…-iphone-a",
  "mutations": [
    {
      "id": "0193a1b2-…",              // mutation_id (UUID v7)
      "type": "item.add",
      "at": "2026-11-20T17:03:11.120Z", // Zeitpunkt auf dem Gerät
      "payload": {
        "item_id": "0193a1b3-…",
        "list_id": "…",
        "article_id": "…",             // oder null, wenn kein Katalogtreffer
        "name": "Milch",
        "category_id": "…",
        "sort_key": "a3V",
        "contribution": { "id": "…", "quantity": 2, "unit": "l" }
      }
    },
    { "id": "…", "type": "item.check", "at": "…", "payload": { "item_id": "…" } }
  ]
}
```

```json
200 OK
{
  "results": [
    { "id": "0193a1b2-…", "status": "merged", "merged_into": "0192ff…" },
    { "id": "…", "status": "applied" }
  ]
}
```

Status: `applied` · `duplicate` (schon verarbeitet, gleiches Ergebnis wie beim ersten Mal) · `merged` · `ignored`
(veraltet, z. B. Abhaken nach späterem Reaktivieren) · `rejected` (fachlich ungültig, mit `error`. Der Client verwirft
die Mutation und zeigt einen Hinweis).

### 4.4 Mutationstypen und Regeln

| Typ | Payload | Serverregel |
|-----|---------|-------------|
| `item.add` | item_id, list_id, article_id?, name, category_id, sort_key, note?, contribution | Gibt es für `article_id` schon einen Eintrag: **zusammenführen** (Beitrag an den bestehenden Eintrag, Status `merged`). War der bestehende Eintrag erledigt: reaktivieren, siehe `item.reactivate` |
| `item.reactivate` | item_id, contribution | `checked_at = NULL`, `activated_at = at`, alte Beiträge `active = false`, neuer Beitrag, `qty_override = NULL` |
| `item.check` | item_id | Wird ignoriert, wenn `at < activated_at` (das Abhaken ist älter als ein Reaktivieren). Sonst `checked_at = at` und ein `purchase_event` |
| `item.uncheck` | item_id | Rückgängig (Beiträge bleiben). Löscht das zugehörige `purchase_event` |
| `item.update` | item_id, fields {name?, note?, qty_override?, unit_override?} | Last-Writer-Wins pro Feld in der Reihenfolge, in der der Server die Mutationen erhält |
| `item.move` | item_id, category_id, sort_key | Bei Kategoriewechsel und vorhandenem Artikel zusätzlich `article.category_id` setzen (**Lerneffekt**) |
| `item.delete` | item_id | Soft Delete |
| `article.create` | article_id, name, category_id | Bei Kollision des Normalnamens: zusammenführen, Antwort `merged_into` |
| `article.update` | article_id, fields | LWW |
| `alias.add` | article_id, alias | Wird angelegt, wenn der Benutzer einen Freitext einem Artikel zuordnet |
| `category.create/update/delete` | … | Löschen verschiebt die Einträge nach „Sonstiges“. Die Fallback-Kategorie ist nicht löschbar |
| `category.move` | category_id, sort_key | – |

**Abhaken gegen Reaktivieren:** Dafür werden die Gerätezeitstempel (`at`) verwendet, nicht die Reihenfolge beim Server.
So gewinnt ein späteres „Milch wieder auf die Liste“ gegen ein älteres, offline gemachtes Abhaken.

### 4.5 Fehlerfälle

| Fall | Verhalten |
|------|-----------|
| Offline | Alles läuft lokal weiter. Die Statusanzeige zeigt „Offline · 3 Änderungen ausstehend“ |
| 401 (Sitzung abgelaufen) | Die Outbox bleibt erhalten, Hinweis „Bitte neu anmelden“. Nach dem Login wird weiter synchronisiert |
| 5xx / Timeout | Exponentielles Backoff (2 s … 60 s) |
| `rejected` | Mutation verwerfen, lokalen Zustand per Pull korrigieren, kurzer Hinweis |
| 410 bei Pull | Neuer Snapshot, danach Outbox-Rebase |

---

## 5. Artikelerkennung und Schnelleingabe

### 5.1 Schnelleingabe (`quickadd.js`)

Erkannte Muster (Groß- und Kleinschreibung egal, Komma oder Punkt als Dezimaltrenner):

| Eingabe | Ergebnis |
|---------|----------|
| `Milch` | name=Milch |
| `2 l Milch` · `2l Milch` · `Milch 2l` · `Milch, 2 Liter` | qty=2, unit=l, name=Milch |
| `3 Zwiebeln` | qty=3, unit=stk, name=Zwiebeln |
| `500g Hack` | qty=500, unit=g, name=Hack |
| `Butter (die gute)` | name=Butter, note=die gute |

### 5.2 Normalisierung (`normalize.js` und `Catalog\Normalizer` mit gleichen Regeln)

1. Kleinschreibung, Trimmen
2. ß→ss, ä→ae, ö→oe, ü→ue, danach übrige diakritische Zeichen entfernen (é→e, î→i: „Crème fraîche“ → „creme fraiche“)
3. Alle Zeichen außer `a–z`, `0–9`, `%` → Leerzeichen (also auch Bindestriche: „TK-Erbsen“ → „tk erbsen“), Mehrfach-Leerzeichen zusammenfassen

**Mehrzahl-Reduktion** (Endungen `-en`, `-n`, `-e`, `-s`) ist *kein* Teil der Normalform. Sie wird beim Matching nur
als **Ausweichschritt** versucht, wenn es keinen exakten Treffer gibt. Grund: „Eis“ würde sonst zu „Ei“.

**Testfälle stehen in `tests/fixtures/normalize.json`** und werden von PHPUnit **und** Vitest gelesen. So bleiben
beide Implementierungen synchron.

### 5.3 Matching (`matcher.js`)

1. Exakter Treffer auf `name_normalized` oder `alias_normalized` → Artikel
2. Sonst Mehrzahl-Reduktion und erneut exakt suchen („bananen“ → „banane“)
3. Sonst Ähnlichkeit (Trigramm/Levenshtein, Schwelle z. B. 0,8) → Vorschlag in der Autovervollständigung, nicht automatisch
4. Kein Treffer → neuer Artikel im Haushaltskatalog, Kategorie „Sonstiges“, ein `article.create` in der Outbox

Die Autovervollständigung zeigt beim Tippen zuerst erledigte Einträge (Historie), dann Katalogartikel.

---

## 6. Oberfläche

### 6.1 Navigation

Untere Tab-Leiste (wie iOS-Apps), in Phase 1 nur **Einkauf** und **Mehr** (Kategorien, Konto, Abmelden, Sync-Status,
App-Version). Weitere Tabs kommen in späteren Phasen dazu.

### 6.2 Listenansicht

```
┌──────────────────────────────────────┐
│ Einkauf                  ☁︎✓  •••    │  ☁︎✓ synchron · ☁︎ 3 ausstehend · ⚠︎ offline
├──────────────────────────────────────┤
│ 🥬 Obst & Gemüse                      │
│  ○ Zwiebeln                    3 Stk │
│  ○ Bananen                           │
│ 🥛 Milchprodukte & Eier               │
│  ○ Milch                         2 l │
│  ○ Butter               die gute     │
│ 📦 Sonstiges                          │
│  ○ Geschenkpapier                    │
│                                      │
│ ▸ Erledigt (14)                      │  aufklappbar, sortiert nach Erledigt-Zeitpunkt
├──────────────────────────────────────┤
│ ＋ Artikel hinzufügen …              │  bleibt nach „Return“ fokussiert (Serieneingabe)
├──────────────────────────────────────┤
│   🛒 Einkauf          ⋯ Mehr         │
└──────────────────────────────────────┘
```

| Geste | Aktion |
|-------|--------|
| Tippen auf ○ | abhaken (kurze Animation, Eintrag rutscht nach „Erledigt“). 4 s lang „Rückgängig“-Toast |
| Tippen auf den Namen | Bearbeiten-Sheet (von unten): Name, Menge, Einheit, Kategorie, Notiz, Löschen |
| Lang drücken + ziehen | Umsortieren, auch in eine andere Kategorie (= Kategoriewechsel mit Lerneffekt) |
| Tippen auf einen erledigten Eintrag | reaktivieren |
| Nach links wischen | löschen *(optional, falls mit vertretbarem Aufwand umsetzbar)* |

### 6.3 Kategorien verwalten (unter „Mehr“)

Liste der Kategorien mit Emoji. Sortieren per Drag & Drop, umbenennen, neu anlegen, löschen (Einträge wandern nach
„Sonstiges“). Das ist eine klassische Turbo-Seite, die Änderungen laufen aber ebenfalls über ChangeLog und Sync.

---

## 7. iOS-Optimierung

| Thema | Umsetzung |
|-------|-----------|
| Installation | Web App Manifest (`display: standalone`, `theme_color`, Icons inkl. 180 px `apple-touch-icon`). Einmalige Anleitung „Zum Home-Bildschirm hinzufügen“, wenn die Seite nicht im Standalone-Modus läuft (`navigator.standalone`) |
| Getrennte Speicher | Die Home-Bildschirm-App hat **eigene Cookies und Daten**, getrennt von Safari. Man meldet sich also in der installierten App an, und das Remember-me-Cookie muss langlebig sein |
| Datenhaltung | `navigator.storage.persist()` anfragen. Die App liegt auf dem Home-Bildschirm. Der Server ist die Wahrheit, schlimmstenfalls wird neu befüllt (nur eine nicht gesendete Outbox wäre verloren) |
| Kein Background Sync | Sync bei Sichtbarkeit, Fokus, `online` und per Timer (siehe 4.1) |
| Safe Areas | `viewport-fit=cover`, Abstände mit `env(safe-area-inset-*)` für Notch und Home-Indikator. Die Eingabeleiste sitzt über dem Home-Indikator |
| Tastatur | Eingabefelder mit mindestens **16 px Schrift** (sonst zoomt Safari beim Fokus), `enterkeyhint="done"`, `autocapitalize="sentences"`. Die Eingabeleiste bleibt bei offener Tastatur sichtbar (`visualViewport`-API) |
| Touch | Trefferflächen ≥ 44 × 44 pt, `-webkit-tap-highlight-color: transparent`, `touch-action: manipulation` (kein Doppeltipp-Zoom). SortableJS mit `delayOnTouchOnly` (lang drücken zum Ziehen, damit Scrollen weiter funktioniert) |
| Scrollen | `overscroll-behavior: contain` in der Liste, kein Body-Scroll unter Sheets |
| Optik | Systemschrift (`-apple-system`), Dark Mode über `prefers-color-scheme`, dezente Animationen, `prefers-reduced-motion` beachten |
| Statusleiste | `apple-mobile-web-app-status-bar-style` passend zum Theme |
| Nicht verfügbar | Vibration API (kein haptisches Feedback), Background Sync. Web Push nur für installierte PWAs (erst in späteren Phasen relevant) |
| Testen | Service Worker brauchen HTTPS. Auf dem iPhone entweder per `ddev share` (öffentlicher Tunnel) oder mit installierter mkcert-Root-CA im lokalen WLAN. Automatisiert: Playwright mit **WebKit** und iPhone-Geräteprofil |

---

## 8. Service Worker und Offline-Seiten

- **Precache** (bei Installation): App-Shell der Listenseite `/einkauf`, JS-Module der Insel, CSS, Icons, Manifest. Die Listenseite ist eine **benutzerunabhängige Hülle**, alle Daten kommen aus IndexedDB. Nur so funktioniert sie offline.
- **Andere Seiten** (Kategorien, Mehr): Netzwerk zuerst, ersatzweise eine einfache Offline-Seite.
- **API-Aufrufe** werden nie vom Service Worker gecacht, darum kümmert sich die Sync-Schicht.
- **Updates:** Neue Version → Hinweis „Neue Version verfügbar – neu laden“. Die Asset-Versionierung über AssetMapper liefert die Hashes.

---

## 9. Qualitätssicherung

### 9.1 GrumPHP (Pre-Commit)

Die Git-Hooks laufen auf dem Host (WSL), PHP läuft aber in DDEV. GrumPHP wird deshalb so konfiguriert, dass es im
Container läuft (`git_hook_variables.EXEC_GRUMPHP_COMMAND: 'ddev exec php'`).

| Task | Zweck |
|------|-------|
| `composer` | composer.json/lock validieren |
| `phpcsfixer` | Code-Stil (Symfony-Regelset) |
| `phpstan` | statische Analyse, Ziel Level 8 + Doctrine- und Symfony-Erweiterung |
| `phpunit` | nur die schnelle Unit-Suite (Integrationstests laufen in der CI) |
| `deptrac` | Modulgrenzen |
| `yamllint`, `twigcs`/Twig-Lint | Konfiguration und Templates |
| `shell` → `npm run test:unit` | Vitest für den Sync-Kern (in DDEV) |

### 9.2 CI-Pipeline (bei jedem Push)

Komplette PHPUnit-Suite inkl. Integrationstests gegen PostgreSQL, Doctrine-Schema-Validierung, alle GrumPHP-Tasks,
Vitest. Später Playwright-E2E (WebKit). Plattform: **GitHub Actions** (privates Repo auf GitHub).

### 9.3 Wichtige Testfälle

- Sync: Idempotenz (dieselbe Mutation doppelt), Zusammenführen bei gleichzeitigem Offline-Hinzufügen desselben Artikels, Abhaken gegen späteres Reaktivieren, Pull mit Paging, 410 → Snapshot.
- Schnelleingabe und Normalisierung über die gemeinsamen Fixtures.
- Mandantentrennung: Ein Haushalt sieht nie Daten eines anderen (Functional-Tests auf alle Endpunkte).

---

## 10. Arbeitspakete und Abnahmekriterien

### Phase 0: Fundament

> **Stand 2026-11-18:** 0.2–0.6 umgesetzt. Offen: 0.1 (PostgreSQL bei Hetzner prüfen), 0.7 (Deployment).
>
> **Stand 2026-11-19:** 0.1 und 0.7 vorbereitet: Hosting-Check-Skript, apache-pack mit `.htaccess`, Logs als Datei,
> Deployment per GitHub Actions mit Release-Verzeichnissen und automatischem Rollback ([betrieb.md](betrieb.md)).
> Offen: Einrichtung bei Hetzner und GitHub, erstes Deployment. Messenger-Cron erst, wenn es asynchrone Nachrichten gibt.
>
> Für 0.7 vormerken: `symfony/apache-pack` (.htaccess für Apache), dort `AddType application/manifest+json .webmanifest`
> (lokal per `.ddev/nginx/pwa.conf` gelöst), `APP_SECRET` für prod setzen. `/sw.js` liefert seit 1.6 Symfony aus
> (inkl. `Cache-Control: no-cache`), dafür ist keine Serverkonfiguration nötig.
>
> Umsetzung 0.5/0.6: Login mit Remember-me (1 Jahr, Signatur enthält den Passwort-Hash), Login-Drosselung (5 Versuche / 15 min),
> E-Mail unabhängig von Groß-/Kleinschreibung, Befehle `app:household:create`, `app:user:create`, `app:user:set-password`.
> Layout mit Tab-Leiste (Einkauf, Mehr), Safe Areas, Dark Mode, Manifest + Icons, Service Worker (Netzwerk zuerst,
> Offline-Seite), Hinweis „Zum Home-Bildschirm“ für iOS Safari.
>
> Erkenntnisse aus 0.2–0.4:
> - PostgreSQL `jsonb` speichert Schlüssel in eigener Reihenfolge. Clients dürfen sich in Snapshots nicht auf die Reihenfolge verlassen.
> - Doctrine ORM 3.8 löst `postPersist/postUpdate/postRemove` innerhalb der flush()-Transaktion aus. Das Änderungsprotokoll wird darin per DBAL geschrieben.
> - Sequenznummern werden erst beim Commit sichtbar. Darum serialisiert `HouseholdWriteLock` (pg_advisory_xact_lock) die Schreibvorgänge pro Haushalt.
> - DAMADoctrineTestBundle öffnet die Test-Transaktion auf Treiberebene. Für DBAL ist darin keine Transaktion aktiv.

| # | Paket | Fertig, wenn … |
|---|-------|----------------|
| 0.1 | Hosting prüfen | PHP-Version, PostgreSQL-Version und Erweiterungen, `max_execution_time`, Document Root, Cron dokumentiert |
| 0.2 | DDEV + Symfony-Skeleton (Webapp ohne unnötige Pakete), PostgreSQL | `ddev start` → Startseite unter https erreichbar |
| 0.3 | Git-Repo, GrumPHP, PHPStan, CS-Fixer, deptrac, PHPUnit, Vitest, CI | Commit mit absichtlichem Fehler wird blockiert, CI ist grün |
| 0.4 | Shared: UUID v7, Clock, ChangeLog-Infrastruktur | Unit-Tests grün |
| 0.5 | Identity: User, Household, Login, Remember-me, `app:household:create`, `app:user:create` | Beide Benutzer können sich anmelden und bleiben angemeldet |
| 0.6 | Layout, Tab-Leiste, Manifest, Icons, Service-Worker-Gerüst, iOS-Metadaten | Auf dem iPhone installierbar, öffnet ohne Browserleiste |
| 0.7 | Erstes Deployment von Hand auf Hetzner, Cronjob für Messenger | App läuft unter der eigenen Domain mit HTTPS |

### Phase 1: Einkaufsliste

> **Stand 2026-11-19:** 1.1–1.3 (Server) umgesetzt: Katalog-, Listen- und Sync-Entities, Startkatalog per
> `HouseholdCreated`, Mutationstypen `item.*`, `article.create/update`, `alias.add`, Endpunkte snapshot/push/pull,
> `/api/me`. Noch offen: Kategorie-Mutationen (mit 1.7), 410 bei bereinigtem Log (es wird noch nichts bereinigt),
> Client (1.4 ff.).
>
> **Stand 2026-11-19 (2):** 1.4 und 1.5 umgesetzt: JS-Kern (Dexie, Outbox, Sync-Engine, Schnelleingabe, Matching,
> Sortierschlüssel) und Alpine-Oberfläche (Kategorien, Abhaken mit Rückgängig, Erledigt/Reaktivieren, Bearbeiten-Sheet,
> Drag & Drop, Vorschläge, Sync-Status). Ergänzung zum Protokoll: Nach "merged" lenkt der Server Folgemutationen im
> selben Push auf die bestehende ID um. Offen für 1.6: App-Start ohne Verbindung (Precache der Listenhülle und der
> JS-Module im Service Worker). Eine geöffnete Liste arbeitet bereits offline. Abweichungen: Der Startkatalog hat 274 Aliase (ein redundanter Alias wurde entfernt).
> Zeitstempel werden sekundengenau verarbeitet (siehe docs/entwicklung.md).
>
> **Stand 2026-11-19 (3):** 1.6 umgesetzt. Der Service Worker wird von Symfony gerendert (`/sw.js`), kennt so alle
> versionierten Asset-URLs und cacht sie bei der Installation. Die Listenhülle kommt per Netzwerk zuerst (4 s Timeout),
> sonst aus dem Cache. Andere Seiten zeigen offline die Offline-Seite. Neue Version → Hinweis „Neu laden“.
> Fehlerfälle aus 4.5 sind abgedeckt (Offline mit Zähler, 401-Hinweis, Backoff, Hinweis bei `rejected`, Snapshot bei 410).
> Abnahme automatisiert im Browser-Rauchtest: Kaltstart im Flugmodus, hinzufügen und abhaken, zweites Gerät sieht
> die Änderungen nach ca. 8 s. Noch offen: Test auf einem echten iPhone.
>
> **Stand 2026-11-20:** Startkatalog überarbeitet: Obst und Gemüse, das man meist in mehreren Stück kauft, heißt jetzt
> in der Mehrzahl (Äpfel, Zwiebeln …, die Einzahl ist Alias). Neu sind laktosefreie und vegane Alternativen
> (Milchprodukte, Fleischersatz, Mayonnaise, Ei-Ersatz). Bestehende Haushalte bekommen Erweiterungen per
> Katalog-Abgleich (`app:catalog:update`, bei jedem Deployment).
>
> **Stand 2026-11-20 (2):** 1.7 umgesetzt: „Mehr → Kategorien“ (Turbo-Seiten mit Verbindung) zum Sortieren per Ziehen,
> Umbenennen, Symbol ändern, Anlegen und Löschen. Änderungen laufen über das Änderungsprotokoll zu allen Geräten.
> Abweichung: Kategorien sind nicht offline bearbeitbar (keine Kategorie-Mutationen). Mutationen mit gelöschter
> Kategorie weichen auf „Sonstiges“ aus.
>
> **Stand 2026-11-21:** Aus dem Alltagstest: Listenseite als feste Hülle über der iOS-Tastatur, Grundrechenarten in
> Mengen, verständliche Hinweise bei unplausiblen Mengen. Abweichung von 6.2: Abhaken zeigt keinen „Rückgängig“-Toast
> mehr, nur ein kurzes Häkchen (Wunsch aus dem Alltag). Rückgängig per Antippen in „Erledigt“.
>
> **Stand 2026-11-22:** „Erledigt“ ist nach Häufigkeit sortiert (Kaufzähler `purchase_count` am Artikel, aus der
> Kaufhistorie nachgetragen), nicht mehr nach Erledigt-Zeitpunkt wie in 6.2 skizziert. Wischen zum Entfernen ist
> für „Erledigt“ umgesetzt (horizontales Scrollen mit Scroll Snap, `item.delete`).

| # | Paket | Fertig, wenn … |
|---|-------|----------------|
| 1.1 | Seed-Daten (Entwurf liegt vor: [categories.yaml](../data/seed/categories.yaml), [articles.yaml](../data/seed/articles.yaml), 17 Kategorien, 365 Artikel, 352 Aliase). Seeder kopiert sie in den Haushalt und prüft die Eindeutigkeit nach Normalisierung | `app:household:create` legt einen vollständigen Katalog an. Ein Test schlägt bei doppelten Namen oder Aliasen fehl |
| 1.2 | Catalog + Shopping: Entities, Regeln, Handler je Mutationstyp | Unit- und Integrationstests für alle Regeln aus 4.4 |
| 1.3 | Sync-Endpunkte snapshot/push/pull | Functional-Tests inkl. Idempotenz, Merge, 410 |
| 1.4 | JS-Core: db, outbox, sync, commands, normalize, matcher, quickadd, sortkey | Vitest grün, gemeinsame Fixtures bestanden |
| 1.5 | Alpine-UI der Liste: anzeigen, hinzufügen, abhaken, Rückgängig, Erledigt, bearbeiten, Drag & Drop | Bedienbar auf dem iPhone |
| 1.6 | Offline: Precache, Statusanzeige, Fehlerfälle aus 4.5 | Flugmodus: hinzufügen und abhaken → Flugmodus aus → beim anderen Gerät nach ≤ 10 s sichtbar |
| 1.7 | Kategorien verwalten | Umbenennen und Sortieren wirken auf beiden Geräten |
| 1.8 | Feinschliff iOS (Kapitel 7), Dark Mode | Checkliste aus Kapitel 7 abgehakt |
| 1.9 | **Alltagstest** 2 Wochen parallel zu Erinnerungen | Keine verlorenen oder doppelten Einträge, danach Umstieg |

---

## 11. Entscheidungen und offene Punkte

| Thema | Stand |
|-------|-------|
| PHP | **8.5** (Hetzner bietet 8.4 und 8.5). DDEV nutzt dieselbe Version. Falls ein Werkzeug 8.5 noch nicht unterstützt, wird auf 8.4 ausgewichen |
| Symfony | aktuelle Version 8.x (braucht PHP ≥ 8.4) |
| Repo/CI | GitHub (privat) + GitHub Actions |
| Domain | später. Bis zum ersten Deployment (Paket 0.7) wird nur lokal mit DDEV gearbeitet |
| PostgreSQL bei Hetzner | **offen:** Version und Erweiterungen. Prüfen, sobald eine Datenbank im konsoleH angelegt ist (`SELECT version();`, `SELECT name FROM pg_available_extensions ORDER BY 1;`). Bis dahin nutzt DDEV PostgreSQL 17, ohne Abhängigkeit von Erweiterungen (`pg_trgm` erst ab Phase 2 nötig, Phase 1 matcht im Client) |
| Seed-Katalog | Entwurf liegt vor und wartet auf deine Durchsicht |
