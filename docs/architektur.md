# Smart Meal & Shopping Planner – Produkt- und Systemarchitektur

Entwurf v0.2 · Stand 2026-11-18 · Grundlage: PRD v0.2 + Antworten zum Fragenkatalog

> Kapitel 0 enthält die **getroffenen Entscheidungen** (v0.2). Die Kapitel 1–10 beschreiben weiterhin Module,
> Datenobjekte, Schnittstellen und Optionen. Wo sie einer Entscheidung aus Kapitel 0 widersprechen, gilt Kapitel 0.

---

## 0. Entscheidungen und Rahmenbedingungen (v0.2)

### 0.1 Rahmen

| Thema | Antwort | Konsequenz |
|-------|---------|------------|
| Zielgruppe | Zunächst privat (2 Personen, ein Haushalt), später eventuell ein Produkt | Haushalt als Mandant bleibt im Datenmodell, öffentliche Registrierung erst später. **Keinen AGPL-Code übernehmen** (Mealie, Tandoor, KitchenOwl), nur Ideen |
| Motivation | Lernprojekt mit echtem Nutzen im Alltag | Eigenentwicklung (Strategie 4). Sauberkeit und Lernwert sind wichtiger als Tempo. Tests und klare Modulgrenzen gehören dazu |
| Offline | **Ja**, die Liste muss offline funktionieren | Die Einkaufsliste wird eine offlinefähige Insel mit lokaler Datenhaltung und Sync-Queue |
| Liste | Eine Liste, Kategorien und Sortierung wie bei iOS Erinnerungen (Einkaufsliste) | Kategorien werden automatisch zugeordnet. Verschiebt man einen Artikel in eine andere Kategorie, merkt sich das System das. Kategorien und Artikel sind per Drag & Drop sortierbar |
| Abgehakte Artikel | Bleiben als **Historie** sichtbar (abgehakt) | Abhaken löscht nicht. Erneut hinzufügen = denselben Eintrag reaktivieren (wie bei Erinnerungen). Jeder Kauf wird als Ereignis gespeichert |
| Barcode | Nein | entfällt |
| Mahlzeiten | 1–2 warme Mahlzeiten pro Tag | Mahlzeit-Slots pro Tag konfigurierbar (z. B. Mittag, Abend) |
| Rezepte | Rezeptnamen vorhanden, einige Links | Gerichte auch ohne Rezept zulassen, **URL-Import** (schema.org JSON-LD, KI als Ersatz) |
| KI | Cloud ist in Ordnung. Wochenplan auf Knopfdruck **und** Chat | LLM-Port mit Tool Use. Chat-Agent mit Werkzeugen (Gerichte suchen, Slot belegen, auf die Liste setzen) |
| Angebote | vorerst weglassen | Phase 7/8 wandern ins Backlog. Der `OfferSource`-Port bleibt nur als Idee bestehen |
| Hosting | **Hetzner Webhosting L** (Managed, Shared) | siehe 0.2, prägt Echtzeit, Hintergrundjobs und Datenbank |
| Backend | **Symfony** | gesetzt |
| Frontend-Erfahrung | keine | Möglichst wenig Frontend-Komplexität: serverseitig gerendert, nur die Liste als JS-Insel |
| Native App | eher nicht | Keine konsequente API-first-Architektur. JSON-API nur, wo der Offline-Client sie braucht (Liste, Sync) |
| Sprache | Deutsch, Englisch eventuell sehr spät | Texte von Anfang an über den Symfony Translator (Deutsch als einzige Sprache). Das kostet kaum etwas und hält Englisch offen |

### 0.2 Was Hetzner Webhosting L bedeutet

Laut Hetzner (Stand Pressemitteilung 10/2025): 10 PHP-Prozesse, 384 MB Memory-Limit, 20 Datenbanken, 10 Cronjobs,
SSH, Redis, Node.js, PHP 8. **Nicht** möglich sind voraussichtlich dauerhaft laufende Prozesse (Daemons, WebSocket-
oder Mercure-Server, permanente Queue-Worker).

| Bereich | Entscheidung |
|---------|--------------|
| Datenbank | **PostgreSQL** (laut Hetzner im Tarif „inkl. Erweiterungen“). Genutzt werden JSONB (ChangeLog, KI-Payloads), `pg_trgm` für unscharfe Suche und später eventuell `pgvector`. Welche Erweiterungen es tatsächlich gibt, mit `SELECT * FROM pg_available_extensions` prüfen. Lokal: DDEV mit PostgreSQL in derselben Hauptversion |
| Echtzeit | **Kein** WebSocket/Mercure/SSE. SSE würde bei nur 10 PHP-Prozessen die Kapazität blockieren. Stattdessen: **Sync beim Öffnen, bei Fokus/Sichtbarkeit, bei `online`-Event und Polling alle 5–10 s bei geöffneter Liste**. Für 2 Personen völlig ausreichend |
| Hintergrundjobs | Symfony Messenger mit **Doctrine-Transport**, abgearbeitet per Cron jede Minute (`messenger:consume --time-limit=55`). Damit laufen auch längere KI-Aufrufe asynchron |
| KI-Aufrufe | Wochenplan als Hintergrundjob, Status per Polling. Chat zunächst synchron mit Ladeanzeige. Vorher `max_execution_time` und mögliches Puffern bei Streaming prüfen |
| Deployment | Zunächst von Hand per SSH: Git → `composer install --no-dev` → Migrations → `asset-map:compile` → Cache leeren. Später automatisiert (z. B. **Deployer** oder ein CI-Job). Die Frontend-Assets entstehen ohne Node-Build (AssetMapper) |
| Lokal | DDEV mit **derselben PHP- und MariaDB-Version** wie bei Hetzner |
| PHP | **8.5** (bei Hetzner verfügbar, ebenso 8.4) |
| Zu prüfen | PostgreSQL-Version und Erweiterungen, `max_execution_time`, ob die Document Root auf `public/` zeigen kann, Cron-Intervall von 1 Minute |

### 0.3 Technikstack (festgelegt bzw. stark bevorzugt)

| Schicht | Wahl | Begründung |
|---------|------|------------|
| Backend | Symfony (aktuelle Version, die zur PHP-Version bei Hetzner passt), Doctrine, Messenger, Security, Translator, Mailer | gesetzt |
| Oberfläche (Verwaltung, Plan, Gerichte, Chat) | **Twig + Symfony UX Turbo + Stimulus**, AssetMapper (kein Node-Build) | Wenig JavaScript, bleibt in der Symfony-Welt, gut zum Lernen |
| Offline-Einkaufsliste | **Sync-Kern in reinem JavaScript (ES-Module)** + **Dexie.js** (IndexedDB) + Oberfläche mit **Alpine.js** *(entschieden)* + SortableJS für Drag & Drop | Logik und Oberfläche sind getrennt, der Sync-Kern ist ohne UI testbar. Alpine ist das einfachste reaktive Werkzeug und braucht keinen Build-Schritt |
| Zielplattform | **iOS (Safari, PWA auf dem Home-Bildschirm)** hat Vorrang | Details in der Spezifikation für Phase 0/1, Kapitel „iOS-Optimierung“ |
| PWA | Web App Manifest + Service Worker (Workbox oder handgeschrieben) | App-Shell und Listen-Assets offline. Andere Seiten: Netzwerk zuerst, ersatzweise Cache (nur lesen) |
| Auth | Symfony Security: Login-Formular + **langlebiges Remember-me** (wichtig für die PWA), Einladung per Link, keine offene Registrierung. Passkeys später optional | einfach, sicher |
| Rezeptimport | `symfony/http-client` + `symfony/dom-crawler` + JSON-LD-Auswertung (z. B. `brick/structured-data`), ersatzweise LLM | Die meisten Rezeptseiten liefern schema.org/Recipe |
| KI | Eigener Port `LlmClient` mit Tool-Use-Unterstützung, dahinter ein Anbieter. Bibliothek: Symfony AI (0.x, beobachten) oder direkt `openai-php/client` bzw. das HTTP-API des Anbieters | austauschbar, Kosten werden protokolliert |
| Qualität | Git-Repo. **GrumPHP** als Pre-Commit-Hook (PHP-CS-Fixer, PHPStan, PHPUnit, deptrac, Lint), dieselben Prüfungen in einer CI-Pipeline. Vitest für den Sync-Kern, später Playwright mit WebKit. Deployment-Automatisierung folgt später | Lernprojekt: Tests von Anfang an |

### 0.4 Einkaufsliste: Präzisierungen

**Kategorien und Sortierung wie bei iOS Erinnerungen:**
- Feste Standardkategorien (z. B. Obst & Gemüse, Brot & Backwaren, Milchprodukte & Eier, Fleisch & Fisch, Kühlregal, Tiefkühl, Vorrat & Konserven, Nudeln/Reis/Getreide, Gewürze & Öle, Süßes & Snacks, Getränke, Drogerie, Haushalt, Sonstiges). Umbenennen, ergänzen und sortieren ist möglich.
- Ein neuer Artikel bekommt seine Kategorie über den Artikelkatalog (Seed-Wörterbuch + Aliase). Ist er unbekannt, landet er in „Sonstiges“, optional mit KI-Vorschlag als Hintergrundjob.
- Verschiebt man einen Artikel manuell in eine andere Kategorie, wird das für den Haushalt gespeichert und gilt künftig immer.
- Reihenfolge der Kategorien: haushaltsweit per Drag & Drop. Reihenfolge innerhalb einer Kategorie: manuell per Drag & Drop. Damit Umsortieren konfliktarm synchronisiert, wird die Position als **Fractional Index** gespeichert (Text-Schlüssel zwischen zwei Nachbarn).

**Historie und Reaktivieren:**
- Abhaken setzt `checked_at/by`. Der Eintrag bleibt im Bereich „Erledigt“ (ein- und ausklappbar) und erzeugt ein `PurchaseEvent(article, qty, unit, ts, by)`.
- Fügt man einen Artikel hinzu, der als erledigt existiert, wird **derselbe Eintrag reaktiviert**: Die Menge setzt sich zurück, und die alten Beiträge werden archiviert. Das gilt auch, wenn der Wochenplan einen Beitrag für einen erledigten Artikel liefert.
- Die Eingabe schlägt beim Tippen Artikel aus Historie und Katalog vor („Mil…“ → Milch). So wird die Historie zur Schnellauswahl.
- Aufräumen: Erledigte Einträge älter als X Tage werden ausgeblendet (nicht gelöscht). Die Kaufereignisse bleiben als Lerndaten erhalten.

**Offline und Sync (konkretisiert):**
- Der Client hält die komplette Liste in IndexedDB. Jede Änderung wird **sofort lokal** angewendet und landet in einer Outbox (Mutationen mit UUID).
- Sync-Zyklus: Outbox per `POST /api/sync/push` senden, danach `GET /api/sync/pull?since=<seq>`. Auslöser: App-Start, Fokus, `online`, Timer.
- Safari (iOS) unterstützt **keine Background Sync API**. Synchronisiert wird deshalb nur bei geöffneter App, was für dieses Szenario genügt. Die PWA sollte auf dem iPhone **zum Home-Bildschirm hinzugefügt** werden, damit iOS die lokalen Daten nicht nach Inaktivität entfernt.
- Konflikte: Last-Writer-Wins pro Feld. Abhaken, Reaktivieren und Löschen sind eigene Operationen mit Zeitstempel. Mengen über Beiträge, siehe 4.2.

### 0.5 Angepasster Phasenplan

| Phase | Inhalt | Ziel |
|-------|--------|------|
| **0 Fundament** | Symfony-Projekt in DDEV, Git-Repo + GrumPHP + CI, Hosting-Check, erstes Deployment von Hand (Automatisierung später), Login + Remember-me, Haushalt mit 2 Benutzern (per Konsole/Einladung angelegt), Konventionen (UUID v7, Version, ChangeLog), PWA-Grundgerüst, Tests/PHPStan/deptrac | leere, installierbare App online |
| **1 Einkaufsliste offline + Artikelkatalog + Sync** | Katalog mit deutschem Seed, Kategorien und Sortierung, Beiträge-Modell (vorerst `manual`), Abhaken/Historie/Reaktivieren, Autovervollständigung, Offline-Client, Sync-Protokoll, Polling | **MVP: ersetzt Erinnerungen für beide** |
| **2 Gerichte & Rezepte** | Gerichte (auch nur Name), Rezepte, Zutatenzeilen, URL-Import, deutscher Zutaten-Parser (Regeln, KI als Ersatz), „Zutaten auf die Liste“ | Rezepte liefern an die Liste |
| **3 Wochenplan + Bedarf + Grundvorrat + Feedback** | 1–2 Slots pro Tag, DayContext (Zeit, Anwesenheit), Bedarfsermittlung → Beiträge, Grundvorrat, Bewertung nach dem Kochen | Plan → Liste automatisch |
| **4a Regelbasierte Vorschläge** | Score-Planer mit Begründung | Vorschläge ohne KI |
| **4b KI: Wochenplan + Chat** | LLM-Port, Hintergrundjob für den Wochenplan, Chat-Agent mit Werkzeugen, neue Gerichte, Kostenprotokoll | KI-Planung |
| **5 Lernen** | Präferenzprofil aus Bewertungen, Kaufhistorie und angenommenen/abgelehnten Vorschlägen | bessere Vorschläge |
| *Backlog* | Angebote, Bestände, Kalender-Import (ICS), Passkeys, Englisch, öffentliche Registrierung/Produkt | – |

---

## 1. Was die Architektur prägt

Aus dem PRD ergeben sich einige Punkte, die die Architektur stärker bestimmen als alle anderen:

| # | Treiber | Folge für die Architektur |
|---|---------|---------------------------|
| T1 | **Einkaufsliste ist eigenständiger Kern** und muss ohne KI und ohne Wochenplan funktionieren | Die Liste hängt von keinem anderen Fachmodul ab. Alle anderen Module *liefern* nur an die Liste. |
| T2 | **Keine Quelle darf manuelle Einträge überschreiben** | Listeneinträge brauchen ein Modell mit **Beiträgen pro Quelle** (siehe 4.2). Das ist die wichtigste Entscheidung im Datenmodell. |
| T3 | **Mehrere Benutzer, Synchronisierung** | Alle Objekte bekommen von Anfang an Haushaltsbezug, clientseitig erzeugte IDs (UUID) und Versionen, auch wenn Phase 1 nur einen Benutzer hat. |
| T4 | **Mobile-first, Nutzung im Supermarkt** | Im Laden ist der Empfang oft schlecht. Ob die Liste **offline** funktionieren muss, ist die teuerste offene Frage (siehe Frage B1). |
| T5 | **Kernfunktionen unabhängig von KI** | KI ist ein austauschbarer Adapter am Rand. Es gibt immer einen manuellen Weg und einen regelbasierten Ersatz. |
| T6 | **Änderungen nachvollziehbar** | Ein zentrales Änderungsprotokoll (Change Log) dient zugleich der Sync, dem Audit und als Datenquelle fürs Lernen. |
| T7 | **Niedrige laufende Kosten, wenig Anbieterbindung** | Ein einziger Server, ein modularer Monolith, keine Pflicht-SaaS. Externe Dienste sind über Schnittstellen (Ports) austauschbar. |
| T8 | **Zutaten verbinden alles** | Rezepte, Liste, Vorrat und Angebote müssen über einen gemeinsamen **Artikelkatalog** zusammenfinden. Ohne ihn gibt es keine Duplikaterkennung, keine Kategorien und kein Angebots-Matching. |

**Die größten fachlichen Risiken** liegen nicht in der KI, sondern in der **Datenqualität bei Zutaten** (Normalisierung,
Einheiten, Synonyme) und in der **Verfügbarkeit von Angebotsdaten**.

---

## 2. Architekturstil

**Modularer Monolith mit Ports & Adapters (hexagonal).**

- Eine deploybare Anwendung, eine Datenbank, ein Server. Für einen Haushalt oder wenige Haushalte sind Microservices reiner Overhead.
- Fachmodule mit klaren Grenzen und eigenen Tabellen. Sie kommunizieren über öffentliche Services und Domain-Events, nicht über fremde Tabellen.
- Externe Abhängigkeiten (KI, Rezeptseiten, Angebote, Produktdaten, Push, Echtzeit) liegen hinter **Ports** (Interfaces). Jeder Port hat mindestens eine einfache oder leere Implementierung („Null-Adapter“), damit der Kern ohne den Dienst läuft.
- Modulgrenzen lassen sich in PHP automatisch prüfen, z. B. mit *deptrac*.
- API-first: Auch wenn die erste Oberfläche serverseitig gerendert wird, bekommen Liste und Sync eine saubere JSON-API. Das hält Offline-Clients, eine spätere native App und Integrationen offen.

---

## 3. Module

### 3.1 Übersicht und Abhängigkeiten

```
                           ┌────────────────────────────┐
                           │  M0 Identität & Haushalt   │  (alle Module hängen davon ab)
                           └────────────────────────────┘
                           ┌────────────────────────────┐
                           │  X  Change Log / Sync /    │  (Querschnitt)
                           │     Echtzeit / Audit       │
                           └────────────────────────────┘

   ┌─────────────────┐        ┌─────────────────────┐
   │ M1 Artikel-     │◄───────┤ M2 Einkaufsliste    │◄──────────────┐  liefern "Beiträge"
   │    katalog      │        │    (Kern)           │◄───────┐      │
   └────────▲────────┘        └─────────────────────┘        │      │
            │                                                │      │
            │      ┌──────────────────┐   ┌────────────────────┐   ┌┴──────────────┐
            ├──────┤ M3 Gerichte &    │◄──┤ M5 Bedarfs-        │   │ M6 Vorrat     │
            │      │    Rezepte       │   │    ermittlung      ├──►│ (Grundvorrat, │
            │      └────────▲─────────┘   └─────────▲──────────┘   │  Bestände)    │
            │               │                       │              └───────────────┘
            │      ┌────────┴─────────┐             │
            │      │ M4 Wochenplanung ├─────────────┘
            │      └────────▲─────────┘
            │               │ Vorschläge
            │      ┌────────┴─────────┐     ┌──────────────────────┐
            │      │ M8 Vorschlags-   │◄────┤ M7 Präferenzen &     │
            │      │    Engine        │     │    Feedback          │
            │      │ (Regeln + KI)    │     └──────────────────────┘
            │      └────────▲─────────┘
            │               │
   ┌────────┴────────┐      │        ┌──────────────────────┐
   │ M9 Angebote     ├──────┴───────►│ M10 Einkaufs-        │
   │                 │               │     optimierung      │
   └─────────────────┘               └──────────────────────┘
```

Pfeilrichtung: A ──► B bedeutet „A nutzt B“. Die Einkaufsliste (M2) nutzt nur M0, M1 und X. Alles andere schreibt über
eine definierte Schnittstelle in die Liste.

### 3.2 Modulbeschreibungen

| Modul | Verantwortung | Phase | Abhängig von |
|-------|---------------|-------|--------------|
| **M0 Identität & Haushalt** | Benutzer, Login (Passkey, Magic Link, Passwort), Haushalt als Mandant, Einladungen, Rollen, Mitglieder (auch Personen ohne Login, z. B. Kinder, für Präferenzen und Portionen) | 0 | – |
| **X Change Log / Sync / Echtzeit** | Fortlaufendes Änderungsprotokoll pro Haushalt, idempotente Mutationen, Abruf von Änderungen seit einem Cursor, Push an verbundene Clients, Audit-Ansicht | 0–2 | M0 |
| **M1 Artikelkatalog** | Kanonische Artikel/Zutaten, Synonyme, Kategorien, Einheiten und Umrechnungen, Normalisierung von Freitext, Lernen aus Korrekturen | 1 | M0 |
| **M2 Einkaufsliste** | Listen, Einträge, Beiträge pro Quelle, Abhaken und Reaktivieren, Zusammenführen von Duplikaten, Sortierung nach Kategorien oder Ladenlayout, Einkaufsabschluss und Historie | 1 | M0, M1, X |
| **M3 Gerichte & Rezepte** | Gerichte, Rezeptvarianten, Zutatenzeilen, Zeiten, Tags, Ernährungsformen, Import von URLs (schema.org), Zutaten-Parser | 3 | M0, M1 |
| **M4 Wochenplanung** | Wochenplan, Slots (Tag × Mahlzeit), Rahmenbedingungen pro Tag (Zeit, Anwesenheit, Termine), Status (vorgeschlagen, bestätigt, gekocht, ausgelassen) | 4 | M3 |
| **M5 Bedarfsermittlung** | Bestätigter Plan × Portionen − Vorrat ⇒ Bedarf ⇒ Beiträge an die Liste. Bei Planänderung wird neu berechnet, dabei werden **nur eigene Beiträge** ersetzt | 4 | M2, M3, M4, M6 |
| **M6 Vorrat** | Grundvorrat (immer im Haus), optional echte Bestände, Hinweise auf fehlende Artikel. Etwas landet nur nach **Freigabe** auf der Liste | 4 / später | M1, M2 |
| **M7 Präferenzen & Feedback** | Bewertungen nach dem Kochen, Regeln (Abneigungen, Ernährungsformen, Häufigkeiten), daraus berechnetes Präferenzprofil | 4–6 | M0, M3 |
| **M8 Vorschlags-Engine** | Wochenvorschläge, Ersatzgerichte, neue Gerichte, Begründungen. Zwei Stufen: **regelbasiert** (ohne KI) und **KI-gestützt** über einen Port | 5 | M3, M4, M7, (M6, M9) |
| **M9 Angebote** | Angebotsquellen (Adapter), Import, Zuordnung Angebot → Artikel, Gültigkeit, Region | 7 | M1 |
| **M10 Einkaufsoptimierung** | Auswahl des Ladens, Aufteilung auf mehrere Läden, Angebote im Plan berücksichtigen | 8 | M2, M9 |
| *Querschnitt* | Benachrichtigungen (Web Push, E-Mail), Einstellungen, Import/Export (JSON, CSV, evtl. Cooklang), Backups | laufend | – |

---

## 4. Datenobjekte

### 4.1 Gemeinsame Konventionen (ab Tag 1)

- `id` als **UUID v7**, vom Client erzeugbar (offline anlegen ohne Rückfrage beim Server).
- `household_id` an jedem fachlichen Objekt (Mandantentrennung).
- `version` (Ganzzahl) für Optimistic Locking und Sync. `created_at/by`, `updated_at/by`.
- Soft Delete (`deleted_at`) für alles, was synchronisiert wird. Sonst können gelöschte Einträge auf anderen Geräten wieder auftauchen.
- Jede schreibende Operation erzeugt einen **ChangeLog-Eintrag** (`seq`, `household_id`, `actor`, `entity`, `entity_id`, `op`, `diff`, `ts`, `client_mutation_id`).

### 4.2 Einkaufsliste: Beiträge statt Überschreiben

```
ShoppingList        (id, household_id, name, store_id?, sort_mode)
ShoppingListItem    (id, list_id, article_id?, display_name, category_id?,
                     note, checked_at?, checked_by?, manual_override_qty?, position?)
ItemContribution    (id, item_id, source_type, source_ref?, quantity?, unit?,
                     created_by, created_at, active)
   source_type ∈ { manual, meal_plan, recipe, pantry, suggestion, import }
   source_ref  = z. B. meal_plan_id/slot_id, recipe_id, pantry_hint_id
ShoppingTrip        (id, list_id, store_id?, started_at, finished_at)   → Kaufhistorie
PurchasedItem       (trip_id, article_id, quantity, unit, price?)
```

**Regeln:**
1. Ein Eintrag (= Artikel auf der Liste) sammelt die **Beiträge** aller Quellen. Angezeigt wird die Summe, sofern die Einheiten umrechenbar sind (sonst „500 g + 1 Pck.“), und die Herkunft lässt sich aufklappen.
2. Eine Quelle darf **nur ihre eigenen Beiträge** anlegen, ändern oder entfernen. Wird der Plan neu berechnet, werden nur `meal_plan`-Beiträge dieses Plans ersetzt.
3. Ein Eintrag verschwindet automatisch nur, wenn kein Beitrag mehr übrig ist **und** niemand ihn manuell angefasst hat (kein manueller Beitrag, kein Override, nicht abgehakt).
4. Ändert ein Mensch die Menge, wird das als manueller Override gespeichert. Danach überschreibt keine Quelle mehr die Gesamtmenge, sie zeigt höchstens den Hinweis „Plan verlangt jetzt 200 g mehr“.
5. Duplikate werden über `article_id` erkannt (über M1-Normalisierung, nicht über den Text). Beim manuellen Anlegen wird vorgeschlagen, den neuen Eintrag mit einem bestehenden zusammenzuführen.

Damit sind „manuell vs. automatisch unterscheiden“, „aus mehreren Quellen übernehmen“ und „nichts unbeabsichtigt
überschreiben“ im Datenmodell gelöst und nicht nur in der Oberfläche.

### 4.3 Artikelkatalog

```
Article        (id, household_id? [null = globaler Seed], name, plural?, category_id,
                default_unit, is_staple_candidate, off_barcode?, merged_into_id?)
ArticleAlias   (article_id, alias, source [seed|user|ai])      → "Paradeiser" → Tomate
Category       (id, name, icon, default_sort)
StoreLayout    (store_id, category_id, sort)                    → Sortierung pro Laden
Unit           (code, name, dimension [mass|volume|count|other])  g, kg, ml, l, Stk, EL, TL, Bund, Pck, Prise
UnitConversion (article_id?, from_unit, to_unit, factor)        → 1 Zwiebel ≈ 150 g, 1 EL Mehl ≈ 10 g
```

Die Normalisierung von Freitext läuft so: Kleinschreibung, Umlaute, Plural → Singular, Alias-Lookup, Ähnlichkeitssuche
(Trigramm), erst danach optional KI. **Jede Korrektur durch den Benutzer wird als Alias gespeichert**, dadurch lernt das
System mit der Zeit und ohne KI-Kosten.

### 4.4 Gerichte und Rezepte

```
Dish           (id, household_id, name, description?, tags[], cuisine?, diet_flags[],
                effort [quick|normal|elaborate], active)
Recipe         (id, dish_id, name?, servings, prep_min, cook_min, steps? (Markdown),
                source_url?, source_type [manual|import|ai], is_default)
IngredientLine (id, recipe_id, article_id?, quantity?, unit?, note?, optional, raw_text, group?)
```

Die Trennung zwischen **Gericht** (das Konzept „Bolognese“) und **Rezept** (eine konkrete Variante) erfüllt die
PRD-Anforderungen „Varianten“ und „keine vollständige Rezeptdatenbank nötig“: Ein Gericht kann auch nur Name plus
Zutatenliste ohne Zubereitungsschritte sein.

### 4.5 Planung

```
MealPlan       (id, household_id, week_start, status [draft|confirmed|archived])
PlanSlot       (id, plan_id, date, meal_type, dish_id?, recipe_id?, free_text?, servings,
                status [suggested|confirmed|cooked|skipped], origin [manual|rule|ai],
                reason?, leftovers_from_slot_id?)
DayContext     (household_id, date, available_minutes?, attendees[member_id], absent[],
                notes, calendar_ref?)
```

### 4.6 Vorrat

```
StapleItem     (household_id, article_id, min_qty?, unit?)          Grundvorrat
StockLevel     (household_id, article_id, qty, unit, updated_at)    optional, später
PantryHint     (id, article_id, reason, status [open|approved|dismissed])
               → bei approved entsteht ein ItemContribution(source_type=pantry)
```

### 4.7 Präferenzen, Feedback, Lernen

```
Member            (id, household_id, user_id?, name, diets[], portion_factor)
PreferenceRule    (id, household_id, member_id?, type [avoid|prefer|diet|max_per_week|min_gap_days],
                   target [article|dish|tag|cuisine], value)
Rating            (id, slot_id?, dish_id, member_id, score [very_good|good|ok|never_again], comment?, ts)
PreferenceProfile (household_id/member_id, dish_scores, tag_affinity, ingredient_affinity,
                   last_cooked, computed_at)   → wird aus Ratings, Plänen und Regeln berechnet, jederzeit neu erzeugbar
```

### 4.8 Vorschläge und KI

```
SuggestionRun  (id, household_id, kind [week|replace_slot|new_dish|parse_recipe|match_offer],
                engine [rules|llm], provider?, model?, input_hash, tokens_in/out, cost_estimate, ts)
Suggestion     (id, run_id, payload (JSON), status [open|accepted|modified|rejected], reason)
```

Angenommene, geänderte und abgelehnte Vorschläge sind selbst ein Lernsignal. Kosten und Modell werden protokolliert,
damit die laufenden Kosten sichtbar bleiben.

### 4.9 Angebote

```
Retailer       (id, name, region?)
OfferSource    (id, retailer_id, adapter, schedule, last_run, status)
Offer          (id, retailer_id, product_name, brand?, price, base_price?, unit?,
                valid_from, valid_to, region?, raw (JSON))
OfferMatch     (offer_id, article_id, confidence, confirmed_by?, method [rule|fuzzy|ai|user])
```

---

## 5. Schnittstellen

### 5.1 Nach außen (Client ↔ Server)

| Schnittstelle | Zweck | Hinweise |
|---------------|-------|----------|
| **REST/JSON-API** | CRUD für alle Module | Ressourcen pro Modul, z. B. `/lists/{id}/items`, `/dishes`, `/plans/{week}` |
| **Sync-Endpunkte** | `POST /sync/push` (Liste von Mutationen mit `client_mutation_id`, `base_version`) · `GET /sync/pull?since=<seq>` | Idempotent. Konflikte werden feldweise gelöst (Last-Writer-Wins), „abgehakt“ ist ein Zeitstempel. Für Haushaltsgröße reicht das, CRDTs sind nicht nötig. |
| **Echtzeitkanal** | Benachrichtigung „es gibt Änderungen seit seq X“ | SSE oder Mercure, ersatzweise Polling alle paar Sekunden bei geöffneter Liste. Clients holen die Daten danach über `pull`. |
| **Web Push** | Erinnerungen („Plan für nächste Woche bestätigen“, „Bewerte das Essen von gestern“) | Unter iOS nur, wenn die PWA zum Home-Bildschirm hinzugefügt wurde |
| **Import/Export** | JSON-Komplettexport, CSV, Rezept-URL-Import | Datenhoheit, keine Bindung an die eigene App |

### 5.2 Intern: Ports für austauschbare Dienste

| Port | Aufgabe | Null-/Basis-Adapter | Mögliche Adapter |
|------|---------|---------------------|------------------|
| `LlmClient` | Strukturierte Antworten (JSON-Schema / Tool Use) | deaktiviert | Anthropic, OpenAI, Mistral (EU), lokal (Ollama) |
| `MealSuggester` | Wochen- und Ersatzvorschläge | **Regel-Planer** | LLM-Planer |
| `RecipeImporter` | URL → RecipeDraft | schema.org JSON-LD | Python-Sidecar `recipe-scrapers`, LLM über HTML/Foto |
| `IngredientParser` | „2 EL Olivenöl, kalt gepresst“ → Menge, Einheit, Artikel, Notiz | Regex/Regeln (deutsch) | LLM mit Cache |
| `CategoryClassifier` | Artikel → Kategorie | Seed-Wörterbuch + Aliase | LLM, Open Food Facts |
| `ProductData` | Barcode → Produkt | – | Open Food Facts |
| `OfferSource` | Angebote holen | manuelle Eingabe | Händler-Webseiten, Aggregatoren, Prospektfoto → LLM-Vision |
| `CalendarSource` | Termine/Abwesenheiten | manuell | ICS-Abo-URL (iCloud, Google, CalDAV) |
| `RealtimePublisher` | Clients benachrichtigen | keiner (Polling) | Mercure, SSE, Reverb |
| `Notifier` | Push/Mail | keiner | Web Push, SMTP |

### 5.3 Wichtige interne Events

`PlanConfirmed`, `PlanSlotChanged` → M5 berechnet den Bedarf neu · `SlotCooked` → Feedback-Anfrage ·
`RatingGiven` → Profil neu berechnen · `ItemChecked`, `TripFinished` → Kaufhistorie, Vorrat ·
`ArticleCorrected` → Alias lernen · `OffersImported` → Matching

---

## 6. Technische Optionen pro Schicht

### 6.1 Backend

| Option | Pro | Contra |
|--------|-----|--------|
| **PHP + Symfony** | Starke Modulgrenzen (Bundles), Doctrine, Messenger (Queue ohne Redis über Doctrine-Transport), Mercure-Integration, API Platform, Symfony UX (Turbo/Stimulus), *Symfony AI* | Mehr Konfiguration. Symfony AI ist **noch 0.x/experimentell** |
| **PHP + Laravel** | Schneller Start, Livewire, Reverb (WebSocket in PHP), Prism (KI), Queues mit Datenbank-Treiber | Modulgrenzen muss man selbst durchsetzen. Eloquent verleitet zu Kopplung |
| **PHP + Slim/Mezzio** | Schlank | Vieles selbst bauen |
| **BaaS (z. B. PocketBase)** | Auth, Realtime und Sync fast geschenkt, ein Binary | Komplexe Fachlogik (Beiträge, Bedarfsermittlung) passt schlecht, keine PHP-Basis |

*Tendenz:* PHP passt zu deinem Know-how und zur Hosting-Situation. Symfony und Laravel sind beide geeignet; die Wahl
sollte von deiner Erfahrung abhängen (Frage F2). Eine Fachlogik wie in Kapitel 4 spricht eher für einen klassischen
Framework-Monolithen als für eine BaaS-Lösung.

### 6.2 Datenbank

| Option | Pro | Contra |
|--------|-----|--------|
| **PostgreSQL** | `pg_trgm` (unscharfe Suche für Artikel und Angebote), JSONB, Volltext, später `pgvector` für Ähnlichkeitssuche von Gerichten | Ein Dienst mehr als SQLite |
| **MariaDB/MySQL** | Oft schon vorhanden | Schwächere unscharfe Suche, kein pgvector-Äquivalent im Standard |
| **SQLite (WAL)** | Kein Dienst, Backup = Datei kopieren, reicht für Haushaltsgröße | Bei vielen Haushalten und parallelen Workern begrenzt |

*Tendenz:* Über das ORM abstrahieren. PostgreSQL bietet die meisten Vorteile für M1 und M9. SQLite ist für einen rein
privaten Einsatz ehrlich gesagt ausreichend.

### 6.3 Frontend / PWA: die wichtigste Weichenstellung

| Option | Offline-Liste | Aufwand | Passt zu |
|--------|---------------|---------|----------|
| **A Serverseitig gerendert** (Twig + Turbo/Stimulus, oder Livewire) | schwierig | niedrig | PHP-Know-how, schnelle Ergebnisse |
| **B SPA** (Vue/Svelte/React) + IndexedDB (Dexie) + Service Worker (Workbox) | sehr gut | hoch | Echte Offline-Fähigkeit, spätere native App über Capacitor |
| **C Hybrid**: Verwaltungsbereiche serverseitig, **Einkaufsliste als offlinefähige Insel** (kleine JS-Komponente mit IndexedDB und Sync-Queue) | gut | mittel | *Tendenz*: gute Balance, wenn Offline gebraucht wird |

Ergänzend: Barcode-Scan über die `BarcodeDetector`-API bzw. zxing-js/html5-qrcode, Drag & Drop im Wochenplan über SortableJS.

### 6.4 Sync und Echtzeit

- **Eigene, einfache Sync** (Mutation-Queue + Pull über `seq`) wie in 5.1. Das kostet etwa ein bis zwei Wochen Arbeit, ist gut testbar und an keinen Anbieter gebunden.
- Alternativen: CRDT-Bibliotheken (Yjs, Automerge) sind für Listen mit wenigen Benutzern überdimensioniert und haben kein PHP-Backend. Sync-Engines wie PowerSync oder ElectricSQL bringen Anbieterbindung oder eigene Infrastruktur mit.
- Echtzeit: **Polling** genügt anfangs. Danach **SSE/Mercure** (Mercure-Hub: ein Go-Binary, selbst gehostet kostenlos) oder **Laravel Reverb**.

### 6.5 KI

- **Strukturierte Ausgaben** (JSON-Schema / Tool Use) und eine **Validierung** gegen Katalog und Regeln. Nichts aus der KI landet ungeprüft in den Daten.
- **Kontext kompakt halten**: Profilzusammenfassung, Gerichte-Shortlist und Rahmenbedingungen, nicht die ganze Datenbank. Eine Wochenplanung kostet dann typischerweise Cent-Beträge.
- **Cache** für Parser- und Matching-Ergebnisse (gleiche Zutatenzeile → gleiches Ergebnis).
- PHP-Bibliotheken: `openai-php/client` (auch für OpenAI-kompatible Endpunkte), *Symfony AI* (Platform/Agent, noch 0.x), *LLPhant*, *Neuron AI*, *Prism* (Laravel). Empfehlung: einen eigenen schmalen `LlmClient`-Port vorsehen und dahinter eine Bibliothek austauschbar nutzen.
- **Regelbasierter Planer zuerst**: Bewertung, „lange nicht gegessen“, Zeitbedarf passend zum Tag, Regeln, Abwechslung bei Tags/Küchen, später Angebote. Das bringt viel Nutzen ohne laufende Kosten, und die KI ergänzt Kreativität, neue Gerichte und Begründungen.

### 6.6 Angebote

Es gibt **keine offizielle, offene Angebots-API** deutscher Händler.

| Weg | Bewertung |
|-----|-----------|
| Inoffizielle Endpunkte von Aggregatoren (marktguru, kaufDA/Bonial) | technisch einfach, aber **AGB-/Rechtsrisiko** und können jederzeit brechen |
| Scraping einzelner Händler-Webseiten | wartungsintensiv, robots.txt und AGB prüfen |
| Bezahlte Scraper (z. B. Apify-Actors) | funktioniert, verursacht aber laufende Kosten |
| **Manuell oder halbautomatisch**: Prospektfoto/PDF → LLM-Vision → Angebotsliste zur Prüfung | rechtlich unkritisch für den Eigengebrauch, wenig Infrastruktur. *Guter Einstieg* |
| Open Prices (Open Food Facts) | offen, aber in Deutschland noch dünne Abdeckung, eher Preis-Historie als Wochenangebote |

Das Angebots-Modul wird deshalb von Anfang an als **austauschbarer Adapter** geplant.

### 6.7 Betrieb

- Ein Server (vorhanden oder VPS, ca. 4–8 €/Monat), HTTPS über Caddy/Let’s Encrypt.
- Hintergrundjobs über Cron und eine Datenbank-Queue, kein Redis nötig.
- Backups: tägliches Datenbank-Dump plus restic/borg auf externen Speicher.
- Lokale Entwicklung mit DDEV (vorhanden).

---

## 7. Eigenentwicklung oder vorhandene Komponenten

### 7.1 Komplette Open-Source-Anwendungen

| Anwendung | Stack / Lizenz | Stärken | Schwächen für dieses PRD |
|-----------|----------------|---------|--------------------------|
| **Grocy** | PHP, **MIT** | Sehr umfangreich: Vorrat, Einkaufsliste, Rezepte, Wochenplan, Barcodes, API, Preis-Historie pro Laden | Wirkt wie ein ERP, nicht mobile-first, keine Echtzeit-Sync, keine KI. Tiefe Anpassungen sind schwierig. Höchstens als Vorlage oder Backend-Option |
| **Mealie** | Python + Vue, AGPL-3.0 | Ausgezeichneter Rezeptimport, Wochenplan → Liste, polierte UI, große Community, API | Einkaufsliste schwächer, **kein Offline-Modus**, Python, Lernen und Angebote fehlen |
| **Tandoor** | Django, AGPL-3.0 | Stärkste Rezeptverwaltung, Nährwerte, Zutatenverknüpfung | Einkaufsliste wenig intuitiv, schwerer zu betreiben |
| **KitchenOwl** | Flutter + Flask, AGPL-3.0 | Einkaufsliste im Stil von Bring, **Echtzeit-Sync**, native Apps | Rezepte eher dünn, Projekt noch 0.x |

### 7.2 Bewertung

| Strategie | Aufwand | Passung zum PRD | Kontrolle | Laufende Kosten |
|-----------|---------|-----------------|-----------|-----------------|
| **1 Fertige App nutzen** (Mealie oder KitchenOwl) | minimal | ca. 50–60 % (keine Beiträge-Logik, kein Lernen, keine Angebote) | gering | ~0 € |
| **2 Fertige App + eigene Erweiterung über API** (z. B. Mealie + KI-Planer-Dienst) | mittel | ca. 70 %, aber die Liste bleibt fremd und das Kernproblem T2 ungelöst | mittel | ~0 € + KI |
| **3 Grocy forken/erweitern** (PHP) | mittel–hoch | ca. 65 %, UI-Neubau nötig | hoch, aber fremde Codebasis | ~0 € |
| **4 Eigenentwicklung mit Bibliotheken und offenen Daten** | hoch | 100 % | voll | ~0 € + KI |

*Tendenz:* **Strategie 4**, weil der Kern (eine eigenständige Liste mit Beiträgen pro Quelle, Offline, Lernen) genau
das ist, was die vorhandenen Apps nicht bieten. Trotzdem lohnt es sich, **Mealie und KitchenOwl ein bis zwei Wochen
parallel zu nutzen** als Referenz für UX und Funktionsumfang. Wichtig: AGPL-Code nicht übernehmen, wenn das Produkt
später unter einer anderen Lizenz vertrieben werden soll. Ideen und Datenformate übernehmen ist unproblematisch.

### 7.3 Bausteine, die sich gut nutzen lassen

**Backend (PHP)**

| Baustein | Zweck | Lizenz |
|----------|-------|--------|
| Symfony *oder* Laravel | Framework | MIT |
| Doctrine ORM + Migrations / Eloquent | Persistenz | MIT |
| API Platform (Symfony) | REST-API, OpenAPI, Mercure-Anbindung | MIT |
| Symfony Messenger / Laravel Queues | Hintergrundjobs ohne Redis | MIT |
| Mercure-Hub / Laravel Reverb | Echtzeit | AGPL (Hub, unverändert betreiben ist unproblematisch) / MIT |
| `web-auth/webauthn-lib` | Passkeys | MIT |
| `minishlink/web-push` | Web Push | MIT |
| `brick/structured-data` + `symfony/dom-crawler` | schema.org Recipe (JSON-LD/Microdata) auslesen | MIT |
| `php-units-of-measure` | Einheitenumrechnung (Küchen-Einheiten trotzdem als eigene Tabelle) | MIT |
| `brick/money` | Preise | MIT |
| Loupe (SQLite-basierte Suche in PHP) / Meilisearch | Tippfehlertolerante Suche ohne oder mit Zusatzdienst | MIT |
| `openai-php/client`, Symfony AI, LLPhant, Neuron AI, Prism | KI-Anbindung | MIT |
| deptrac | Modulgrenzen prüfen | MIT |

**Frontend**

| Baustein | Zweck | Lizenz |
|----------|-------|--------|
| Workbox | Service Worker, Caching, Background Sync | MIT |
| Dexie.js | IndexedDB (Offline-Daten) | Apache-2.0 |
| Turbo/Stimulus, Alpine.js *oder* Vue/Svelte | UI | MIT |
| SortableJS | Drag & Drop im Wochenplan | MIT |
| zxing-js / html5-qrcode | Barcode-Scan | Apache-2.0 |

**Daten und Formate**

| Quelle | Zweck | Lizenz |
|--------|-------|--------|
| Open Food Facts | Produktdaten, Barcodes, Kategorien | ODbL |
| Open Prices | Preisdaten (Community) | ODbL |
| schema.org/Recipe | De-facto-Standard auf Rezeptseiten | offen |
| Cooklang | Textformat für Rezepte (Import/Export) | MIT |
| `recipe-scrapers` (Python, optionaler Sidecar) | Hunderte Rezeptseiten | MIT |

**Selbst bauen** (hier liegt der eigentliche Wert): Beiträge-Logik der Liste, Sync, Artikelkatalog mit
Normalisierung und deutschem Seed-Wörterbuch, Bedarfsermittlung, Regel-Planer, Präferenzprofil, Angebots-Matching.

---

## 8. Inkrementelle Entwicklung (Vorschlag mit leichten Anpassungen zum PRD)

| Phase | Inhalt | Änderung gegenüber PRD | Ergebnis |
|-------|--------|------------------------|----------|
| **0 Fundament** | Auth, Haushalt, Konventionen (UUID, Version, ChangeLog), PWA-Grundgerüst, Deployment, Backup | neu | auslieferbare, leere App |
| **1 Einkaufsliste + Artikelkatalog** | Liste, Beiträge-Modell (vorerst nur `manual`), Kategorien, Duplikate, Abhaken, Einkaufsabschluss; Offline, falls B1 = ja | Katalog wird vorgezogen | ersetzt Erinnerungen für eine Person |
| **2 Mehrbenutzer + Sync** | Einladungen, Sync-Endpunkte, Echtzeit (Polling → SSE) | kleiner, weil Phase 0 vorbereitet | **MVP**: gemeinsame Liste |
| **3 Gerichte & Rezepte** | Gerichte, Varianten, Zutatenzeilen, URL-Import, Zutaten-Parser, „Zutaten auf die Liste“ (`recipe`-Beitrag) | – | Rezepte liefern an die Liste |
| **4 Wochenplan + Bedarf + Grundvorrat + einfaches Feedback** | Plan, DayContext, M5-Neuberechnung, Grundvorrat, Bewertung nach dem Kochen | **Feedback vorgezogen**, damit früh Lerndaten entstehen | Plan → Liste automatisch |
| **5a Regelbasierte Vorschläge** | Score-basierter Planer mit Begründungen | neu, vor der KI | Vorschläge ohne Kosten |
| **5b KI-Vorschläge** | LLM-Adapter, neue Gerichte, Ersatz, Chat-artige Wünsche | – | KI-Planung |
| **6 Lernen** | Präferenzprofil, Auswertung angenommener und abgelehnter Vorschläge | – | bessere Vorschläge |
| **7 Angebote** | Einstieg mit manuell/Prospektfoto, danach weitere Adapter, Matching | – | Angebote im Plan |
| **8 Optimierung** | Ladenwahl, mehrere Läden, Bestände, Kalender-Import | – | Komfort |

---

## 9. Laufende Kosten (Schätzung)

| Posten | Kosten/Monat |
|--------|--------------|
| Server (vorhanden / kleiner VPS) | 0 – 8 € |
| Domain | ~1 € |
| Mercure/SSE, Datenbank, Queue, Push (selbst gehostet) | 0 € |
| KI (Wochenplan plus gelegentliches Parsen und Matching, Cache) | grob 0,10 – 2 € pro Haushalt, stark abhängig von Modell und Nutzung |
| Angebotsdaten | 0 € (manuell/Foto) bis zweistellig (bezahlte Scraper) |

---

## 10. Risiken

| Risiko | Gegenmaßnahme |
|--------|---------------|
| Schlechte Zutaten-Normalisierung → Duplikate, falsche Mengen | Katalog früh bauen, deutsches Seed-Wörterbuch, aus Korrekturen lernen, KI nur als Ergänzung mit Cache |
| Offline-Sync wird komplex | Einfaches Mutationsmodell, nur die Liste offline, gute Tests |
| Keine verlässlichen Angebotsdaten | Adapter-Architektur, Einstieg halbautomatisch, Angebote sind kein Kernfeature |
| KI erfindet Zutaten oder Gerichte | Strukturierte Ausgabe, Validierung, der Mensch bestätigt immer |
| Scope Creep | Jede Phase muss für sich nutzbar sein, MVP = Phase 2 |
| Symfony AI noch nicht stabil | Eigener Port, Bibliothek austauschbar |

---

## 11. Offene Entscheidungen (aus PRD Kap. 7, ergänzt)

Siehe Fragenkatalog in der Chat-Antwort. Die wichtigsten Weichen: privat oder Produkt · Offline ja/nein ·
Symfony oder Laravel · Frontend-Ansatz · Datenbank · KI-Anbieter und Datenschutz · Angebotsquellen.

---

### Quellen (Recherche 2026-11)

- Symfony AI Status: [packagist symfony/ai-agent](https://packagist.org/packages/symfony/ai-agent), [Symfony AI v0.2.0](https://dev.to/james_miller_8dc58a89cb9e/symfony-ai-v020-released-feature-breakdown-and-practical-guide-5aof), [Core-Team Symfony AI](https://www.thedroptimes.com/node/71731)
- Grocy: [github.com/grocy/grocy](https://github.com/grocy/grocy), [Changelog](https://grocy.info/changelog)
- Vergleich Mealie/Tandoor/KitchenOwl: [cooklang.org Vergleich](https://cooklang.org/blog/42-tandoor-vs-mealie-vs-kitchenowl/), [Mealie vs KitchenOwl](https://selfhosting-sh.pages.dev/compare/mealie-vs-kitchenowl/), [Tandoor vs KitchenOwl](https://selfhosting-sh.pages.dev/compare/tandoor-vs-kitchenowl/)
- Angebotsdaten: [mydealz-Diskussion](https://www.mydealz.de/diskussion/digitale-angebote-supermarkt-2817760), [offers-api (marktguru-Wrapper)](https://github.com/Nusscookie/offers-api), [Apify DE Supermarket Offers](https://apify.com/nerdrx/de-supermarket-offers), [Lebensmittel Zeitung zu marktguru](https://www.lebensmittelzeitung.net/handel/nachrichten/digital-inspirieren-stationaer-verkaufen-wie-marktguru-aktionsangebote-und-rezeptzutaten-verknuepft-192638)
