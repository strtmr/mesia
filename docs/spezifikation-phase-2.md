# Spezifikation Phase 2: Gerichte und Rezepte

Beschlossen v1.0 · Stand 2026-11-23 · Basis: [architektur.md](architektur.md) (Kapitel 0, 3.2 M3, 4.2, 4.4),
[spezifikation-phase-0-1.md](spezifikation-phase-0-1.md)

**Ziel am Ende von Phase 2:** Der Haushalt sammelt seine Gerichte in Mesia, mit oder ohne Rezept. Ein Gericht lässt
sich mit wenigen Tipps samt passender Portionszahl auf die Einkaufsliste setzen. Die Mengen kommen dazu, ohne
Bestehendes zu überschreiben, und lassen sich auch wieder zurücknehmen. Rezepte von Webseiten lassen sich per Link
übernehmen. Damit liefert die App zum ersten Mal selbst Beiträge an die Liste, die Grundlage für den Wochenplan in
Phase 3.

---

## 1. Umfang

| Enthalten | Bewusst nicht enthalten (spätere Phase) |
|-----------|------------------------------------------|
| Gerichte mit Name, Aufwand, Schlagworten, Notiz, Quelle (Link) | Wochenplan, Mahlzeit-Slots (Phase 3) |
| Ein Rezept pro Gericht: Portionen, Zutaten, Zubereitung, Zeiten | Mehrere Varianten pro Gericht in der Oberfläche (das Datenmodell kann es schon) |
| Zutaten als Text eingeben, Mesia erkennt Menge, Einheit, Artikel | Erkennung per KI (Phase 4b) |
| „Auf die Liste“ mit Portionen, Vorschau zum Abwählen, Rückgängig | Echter Grundvorrat und Bestände (Phase 3/später) |
| „Meist im Haus“ lernen (abgewählte Zutaten standardmäßig aus) | Nährwerte, Kosten |
| Rezept-Import per Link (schema.org/Recipe) | Import ohne strukturierte Daten (KI, Phase 4b), Import aus Fotos |
| Kochansicht: Zubereitung groß, Portionen umrechnen, Bildschirm bleibt an | Bilder (siehe Entscheidung E1) |
| Zuletzt angesehene Rezepte offline lesbar | Gerichte offline bearbeiten |
| Mitteilung „Anna hat die Zutaten für Lasagne auf die Liste gesetzt“ | Teilen von Gerichten zwischen Haushalten |

---

## 2. Grundentscheidungen

| Thema | Entscheidung | Warum |
|-------|--------------|-------|
| Oberfläche | **Twig + Turbo + Stimulus**, serverseitig gerendert (wie Kategorien und Admin) | Architektur 0.1: nur die Liste ist eine JS-Insel. Gerichte pflegt man zu Hause mit Verbindung |
| Offline | Gerichte sind **nicht** synchronisiert (kein `Syncable`). Der Service Worker hält zuletzt angesehene Rezeptseiten nur zum Lesen vor | Volle Offline-Fähigkeit würde Sync-Protokoll, Client-Zustand und Konflikte verdoppeln. Im Laden reicht Lesen |
| Neues Modul | **`Recipe`** (M3) mit Dish, Recipe, IngredientLine, RecipeListing. Darf `Shared` und `Catalog` nutzen | Rezepte brauchen Artikel, Einheiten und die Normalisierung |
| Schreiben in die Liste | Über einen Port in `Shared` (`ShoppingListContributions`), umgesetzt in `Shopping` | Architektur 3.1: „Alles andere schreibt über eine definierte Schnittstelle in die Liste“. Recipe kennt Shopping nicht |
| Beiträge | Quelle `recipe`, `source_ref` = ID des Vorgangs „Auf die Liste“ (RecipeListing), dazu ein Anzeigetext (Name des Gerichts) | Regel 2 aus Architektur 4.2: Eine Quelle ändert nur ihre eigenen Beiträge. So lässt sich genau ein Vorgang zurücknehmen |
| Zutaten-Parser | **In PHP** (Formular und Import laufen auf dem Server). Erweitert die Grammatik der Schnelleingabe. Gemeinsame Fixtures mit `quickadd.js`, wo sich beide überschneiden | wie bei der Normalisierung: Regeln doppelt, Tests stellen Gleichheit sicher |
| Tab-Leiste | Neuer Tab **„Gerichte“** zwischen „Einkauf“ und „Mehr“. In Phase 3 kommt „Plan“ dazu | Gerichte sind ein Hauptbereich, kein Untermenü |

---

## 3. Datenmodell (Modul `Recipe`)

Alle Tabellen mit `household_id`, UUID v7, `created_at/by`, `updated_at`. Soft Delete per `deleted_at` bei Dish
(Gerichte verschwinden aus der Übersicht, ihre Listen-Beiträge bleiben gültig).

```
Dish            (id, household_id, name[100], effort [quick|normal|elaborate]?, tags jsonb[], note text?,
                 source_url[1000]?, last_listed_at?, deleted_at?)
Recipe          (id, dish_id, servings int, prep_minutes?, cook_minutes?, steps text?  (Markdown, ein Schritt
                 je Absatz), source_type [manual|import], is_default bool)
IngredientLine  (id, recipe_id, position int, raw_text[200], quantity decimal(10,3)?, quantity_max?
                 (bei "2-3"), unit?, article_id?, name[100], note[200]?, optional bool, group[60]?)
RecipeListing   (id, household_id, dish_id, recipe_id, servings, created_by, created_at, undone_at?)
```

- `IngredientLine.raw_text` bleibt immer erhalten (so wie eingegeben oder importiert). Die erkannten Felder lassen
  sich neu berechnen, wenn der Parser besser wird.
- `article_id` verweist auf den Artikel des Haushalts (Catalog). Ohne Treffer bleibt sie leer. Beim „Auf die
  Liste“ wird dann wie bei der Schnelleingabe ein Artikel in „Sonstiges“ angelegt.
- `RecipeListing` ist der Vorgang „Auf die Liste“: Er ist `source_ref` der Beiträge, Grundlage für Rückgängig, für die
  Mitteilung und für „zuletzt gekocht“ (`Dish.last_listed_at`). In Phase 3 tritt der Plan-Slot an seine Stelle.

**Erweiterungen bestehender Module**

| Modul | Änderung |
|-------|----------|
| Catalog | `Unit` um `zehe`, `scheibe`, `msp`, `tasse`, `cl` ergänzen (PHP-Enum und `units.js`). `Article.usually_at_home` (bool, synchronisiert): „Meist im Haus“, dazu `Article.skipped_count` (wie oft in der Vorschau abgewählt) |
| Shopping | `ItemContribution.label[100]?` (z. B. „Lasagne“), im Snapshot mitgeliefert. Neue Methode zum Entfernen der Beiträge eines `source_ref` (Regel 3 aus Architektur 4.2: Eintrag verschwindet, wenn nichts mehr übrig ist **und** niemand ihn angefasst hat) |
| Shared | Port `ShoppingListContributions` (siehe 5.2) |
| Notification | Feed erkennt Vorgänge „Auf die Liste“ (siehe 5.4) |

---

## 4. Zutaten erkennen

### 4.1 Grammatik (Erweiterung der Schnelleingabe)

| Eingabe | Menge | Einheit | Artikel / Name | Notiz |
|---------|-------|---------|----------------|-------|
| `500 g Hackfleisch` | 500 | g | Hackfleisch | |
| `1/2 TL Salz`, `½ TL Salz` | 0,5 | tl | Salz | |
| `1 ½ Zwiebeln` | 1,5 | stk | Zwiebeln | |
| `2-3 Knoblauchzehen` | 2 (max 3) | stk | Knoblauch (Alias „Knoblauchzehe“) | |
| `1 Dose gehackte Tomaten (400 g)` | 1 | dose | Tomaten, gehackt (Dose) | 400 g |
| `200 g Mehl (Type 405)` | 200 | g | Mehl | Type 405 |
| `Salz und Pfeffer` | – | – | zwei Zeilen: Salz, Pfeffer | |
| `Olivenöl zum Braten`, `etwas Petersilie` | – | – | Olivenöl, Petersilie | zum Braten |
| `Für die Soße:` | – | – | Gruppe „Für die Soße“ (keine Zutat) | |
| `optional: Parmesan`, `Parmesan (optional)` | – | – | Parmesan, `optional = true` | |

Rechenausdrücke („2x500 g“) und Einheiten-Schreibweisen übernimmt der Parser aus der Schnelleingabe.

### 4.2 Artikel zuordnen

Reihenfolge, der erste Treffer gewinnt:
1. Normalform von Name oder Alias exakt (wie in der Liste, `TextNormalizer`).
2. Ohne Beiwörter: bekannte Zubereitungs- und Formwörter abschneiden („gehackt“, „frisch“, „gewürfelt“, „gerieben“ …)
   und erneut suchen. Das abgeschnittene Wort wandert in die Notiz.
3. Zusammengesetzte Wörter: längstes Endstück, das ein Artikel ist („Rinderhackfleisch“ → „Hackfleisch“,
   „Cocktailtomaten“ → „Tomaten“). Mindestens 4 Buchstaben, damit nicht „Ei“ in „Brei“ gefunden wird.
4. Unscharf per `pg_trgm` (Ähnlichkeit ≥ 0,5), nur als Vorschlag, nicht automatisch. *Voraussetzung: Erweiterung bei
   Hetzner verfügbar (offen seit Phase 0, prüfen). Sonst entfällt Stufe 4.*

Im Formular steht neben jeder Zeile der erkannte Artikel. Korrigiert man ihn, speichert Mesia den Zeilennamen als
**Alias** dieses Artikels (Lernen ohne KI, Architektur 4.3).

---

## 5. Abläufe

### 5.1 Gericht anlegen und bearbeiten

- **Schnell:** nur Name (z. B. „Pizza bestellen“, „Reste“). Ein Gericht ohne Zutaten lässt sich nicht auf die Liste
  setzen, später aber im Plan verwenden.
- **Mit Rezept:** Portionen, Zutaten als Textfeld (eine Zeile pro Zutat), Zubereitung als Textfeld, Zeiten, Aufwand,
  Schlagworte. Nach dem Speichern zeigt die Detailseite die erkannten Zutaten. Nicht erkannte sind markiert und
  lassen sich per Tipp einem Artikel zuordnen.
- **Per Link:** URL einfügen → Vorschau (Name, Portionen, Zutaten, Schritte) → „Übernehmen“. Danach normal bearbeitbar.

### 5.2 „Auf die Liste“

```
┌──────────────────────────────────────┐
│ Lasagne auf die Liste      Abbrechen │
│                                      │
│ Portionen          [ –   4   + ]     │
│                                      │
│ ☑ Hackfleisch               500 g    │
│ ☑ Tomaten, gehackt (Dose)   2 Dosen  │
│ ☑ Lasagneplatten            1 Pck    │
│ ☑ Mozzarella                2 Stk    │
│ ☐ Olivenöl         meist im Haus     │
│ ☐ Salz             meist im Haus     │
│ ☐ Parmesan         optional          │
│                                      │
│ [   6 Zutaten auf die Liste setzen  ]│
└──────────────────────────────────────┘
```

1. Portionen vorbelegt mit denen des Rezepts. Mengen werden umgerechnet:
   - Stückartige Einheiten (stk, dose, pck, bund, flasche, glas, becher, zehe) **aufrunden** auf ganze Zahlen.
   - g/ml auf sinnvolle Stufen runden (bis 100: auf 5, darüber auf 25, ab 1000: auf 50).
   - EL, TL, Prise, Msp, Tasse und Zutaten ohne Menge kommen **ohne Menge** auf die Liste (man kauft eine Flasche Öl,
     keine 2 EL).
2. Vorausgewählt: alle Zutaten außer `optional` und Artikeln mit „meist im Haus“.
3. Wählt man eine Zutat ab, die nicht „meist im Haus“ ist, merkt Mesia sich das nach dem zweiten Mal für den Artikel.
   Setzt man ein Häkchen bei „meist im Haus“, wird das Merkmal wieder entfernt.
4. „Auf die Liste setzen“: Der Server legt ein `RecipeListing` an und ruft den Port auf. **Eine Transaktion** mit
   `HouseholdWriteLock`, Änderungsprotokoll wie bei Sync-Mutationen. Je Zutat gilt dasselbe wie bei `item.add`:
   - offener Eintrag vorhanden → Beitrag dazu (Menge kommt dazu, nichts wird überschrieben)
   - erledigter Eintrag vorhanden → reaktivieren (alte Beiträge archiviert, wie in Phase 1)
   - sonst neuer Eintrag in der Kategorie des Artikels
5. Danach Hinweis „6 Zutaten für Lasagne auf der Liste · Rückgängig“. Die Geräte sehen sie beim nächsten Abgleich.

**Port** (`Shared\Application\Shopping\ShoppingListContributions`):

```php
/** @param list<ContributionRequest> $items  (articleId, name, categoryId, quantity?, unit?) */
public function add(Uuid $householdId, Uuid $actorId, Uuid $sourceRef, string $label, array $items): int;
public function remove(Uuid $householdId, Uuid $actorId, Uuid $sourceRef): int;
```

### 5.3 Rückgängig

„Rückgängig“ (direkt danach und auf der Detailseite unter „Zuletzt auf die Liste gesetzt“) entfernt **nur** die
Beiträge dieses Vorgangs:
- Ein Eintrag mit weiteren Beiträgen (z. B. manuell „Milch“) bleibt, nur die Menge sinkt.
- Ein Eintrag, der nur aus diesem Vorgang besteht, nicht abgehakt und nicht bearbeitet wurde, wird gelöscht.
- Bereits abgehakte Einträge bleiben unberührt (gekauft ist gekauft).

### 5.4 Auf der Liste

- Die Liste bleibt kompakt: kein zusätzliches Symbol in der Zeile.
- Im Bearbeiten-Dialog stehen die Beiträge einzeln: „Für Lasagne: 500 g“, „Selbst hinzugefügt: 200 g“.
- Mengen gleicher Größe werden umgerechnet zusammengezählt: 500 g + 1 kg = 1,5 kg, 250 ml + 1 l = 1,25 l
  (bisher nur bei gleicher Einheit). Nötig, sobald Rezepte und Menschen unterschiedliche Einheiten verwenden.
- **Mitteilung:** Der Vorgang läuft im Änderungsprotokoll unter der ID des `RecipeListing` (wie eine Mutation).
  Der Feed meldet „Anna hat die Zutaten für Lasagne auf die Liste gesetzt.“ statt jeder einzelnen Zutat.

### 5.5 Kochansicht

- Detailseite eines Gerichts: Zutaten (umrechenbar über den Portionen-Regler, nur Anzeige), Zubereitung als
  nummerierte Schritte, Zeiten, Link zur Quelle.
- „Kochen“: Schritte groß, der Bildschirm bleibt an (Wake Lock API, iOS ab 16.4, ohne Unterstützung einfach aus).
- Offline: Der Service Worker speichert aufgerufene Gerichte-Seiten (Netzwerk zuerst, ohne Verbindung die
  gespeicherte Fassung mit Hinweis „Stand von …“). Bearbeiten ist offline gesperrt.

---

## 6. Rezept-Import per Link

1. `symfony/http-client` lädt die Seite (Timeout 10 s, höchstens 2 MB, nur http/https, keine privaten IP-Adressen
   wegen SSRF).
2. JSON-LD aus `<script type="application/ld+json">` lesen, auch in `@graph` und Listen, Typ `Recipe`.
3. Übernommen: `name`, `recipeYield` (erste Zahl → Portionen), `recipeIngredient[]` (→ Parser), `recipeInstructions`
   (Text, `HowToStep`, `HowToSection` → Gruppen), `prepTime`/`cookTime` (ISO 8601 → Minuten), Seiten-URL als Quelle.
4. Nicht übernommen: Bilder (E1), Bewertungen, Nährwerte.
5. Kein `Recipe` gefunden → Hinweis „Auf dieser Seite hat Mesia kein Rezept gefunden. Du kannst es von Hand anlegen.“
   (KI als Ersatz kommt in Phase 4b.)
6. Tests mit gespeicherten Beispielseiten in `tests/fixtures/recipes/` (selbst gebaut nach dem Muster gängiger
  Rezeptseiten, keine kopierten Inhalte).

---

## 7. Oberfläche

```
Tab „Gerichte“                         Detail „Lasagne“
┌──────────────────────────────────┐   ┌──────────────────────────────────┐
│ Gerichte                    +    │   │ ‹ Gerichte         Bearbeiten    │
│ [ Suchen …                    ]  │   │ Lasagne                          │
│ [Schnell] [Vegetarisch] [Pasta]  │   │ normal · 30 + 45 min · Pasta     │
│                                  │   │ [ Auf die Liste ]  [ Kochen ]    │
│ Chili sin Carne      schnell     │   │                                  │
│ Lasagne              vor 5 Tagen │   │ ZUTATEN          Portionen – 4 + │
│ Pizza bestellen                  │   │ 500 g Hackfleisch                │
│ …                                │   │ 2 Dosen Tomaten, gehackt         │
└──────────────────────────────────┘   │ …                                │
                                       │ ZUBEREITUNG                      │
                                       │ 1. Zwiebeln würfeln …            │
                                       └──────────────────────────────────┘
```

- Übersicht alphabetisch, Suche filtert sofort (Stimulus, ohne Server). Schlagworte als Filter-Chips.
  „vor 5 Tagen“ = zuletzt auf die Liste gesetzt.
- Neues Gericht: „+“ → Name, dann wahlweise „Zutaten eingeben“ oder „Von einer Webseite übernehmen“.
- Gestaltung nach Brand Book: Listengruppen, Chips wie die Auswahl in Zeilen, Primärknopf in Signal-Orange.
  Neue Komponenten (Portionen-Regler, Zutatenliste mit Häkchen, Filter-Chips) kommen ins Brand Book.

---

## 8. Qualitätssicherung

| Bereich | Tests |
|---------|-------|
| Parser | Unit-Tests mit einer Fixture-Datei je Fall aus 4.1 (`tests/fixtures/ingredients.json`), Überschneidungen mit `quickadd.js` gemeinsam geprüft |
| Zuordnung | Integrationstest gegen den Startkatalog: 50 typische Zutatenzeilen, Trefferquote ≥ 90 % |
| Auf die Liste | Functional: neue Einträge, Zusammenführen mit offenem Eintrag, Reaktivieren eines erledigten, Rundung, „meist im Haus“, Änderungsprotokoll, Mitteilung |
| Rückgängig | Functional: nur eigene Beiträge, Eintrag bleibt bei fremden Beiträgen, abgehakte bleiben |
| Import | Functional mit HTTP-Mock und Fixture-Seiten, SSRF-Schutz |
| Oberfläche | Rauchtest: Gericht anlegen, auf die Liste, Liste zeigt die Zutaten, Rückgängig |

---

## 9. Arbeitspakete und Abnahmekriterien

> **Stand 2026-11-23:** 2.1 umgesetzt: Modul `Recipe` (Dish, Recipe, IngredientLine), Tab „Gerichte“ mit Suche und
> Schlagwort-Filter, Anlegen/Bearbeiten/Löschen (weich), Detailseite mit Zutaten und nummerierten Schritten. Zutaten
> sind vorerst nur Text (`raw_text`), erkannt werden sie ab 2.2. `RecipeListing` kommt mit 2.3.

| # | Paket | Fertig, wenn … | Version |
|---|-------|----------------|---------|
| 2.1 | Modul `Recipe`: Entities, Migration, Tab „Gerichte“, Übersicht mit Suche, Anlegen/Bearbeiten/Löschen, Detailseite | Ein Gericht mit Rezept lässt sich anlegen, finden und ändern | |
| 2.2 | Zutaten-Parser (PHP) und Zuordnung zum Katalog, Einheiten erweitert, Korrektur speichert Alias | Fixtures grün, Trefferquote ≥ 90 % | |
| 2.3 | „Auf die Liste“: Vorschau, Portionen, Rundung, Port + Umsetzung in Shopping, `RecipeListing`, Rückgängig, „meist im Haus“ | Lasagne für 4 landet auf beiden Geräten auf der Liste und lässt sich zurücknehmen, ohne manuelle Einträge zu verändern | **0.9.0** (2.1–2.3 zusammen) |
| 2.4 | Liste: Beiträge im Bearbeiten-Dialog, Umrechnung g/kg und ml/l, Mitteilung für Vorgänge | 500 g + 1 kg zeigt 1,5 kg, Mitteilung nennt das Gericht | 0.9.x |
| 2.5 | Rezept-Import per Link | 3 Fixture-Seiten mit unterschiedlicher JSON-LD-Struktur werden vollständig übernommen | 0.10.0 |
| 2.6 | Kochansicht, Wake Lock, Rezepte offline lesbar | Im Flugmodus ist ein zuvor geöffnetes Rezept lesbar | 0.10.x |
| 2.7 | **Alltagstest** 2 Wochen: echte Gerichte des Haushalts erfassen und nutzen | Mindestens 15 Gerichte erfasst, Zutaten landen korrekt auf der Liste | |

Reihenfolge: 2.1 → 2.2 → 2.3 sind voneinander abhängig. 2.4–2.6 können danach in beliebiger Reihenfolge folgen.

---

## 10. Entscheidungen und offene Punkte

| # | Thema | Vorschlag | Stand |
|---|-------|-----------|-------|
| E1 | Bilder zu Gerichten | **Keine** in Phase 2 (Speicherplatz, Urheberrecht bei importierten Bildern, Aufwand). Später eigene Fotos | beschlossen |
| E2 | Gerichte offline | Nur lesend aus dem Cache (Kapitel 2) | beschlossen |
| E3 | Tab „Gerichte“ | Neuer Tab zwischen „Einkauf“ und „Mehr“ | beschlossen |
| E4 | „Meist im Haus“ lernen | Ja, nach zweimaligem Abwählen. Wird in Phase 3 zum Grundvorrat | beschlossen |
| E5 | Varianten | Datenmodell ja (Dish → Recipe), Oberfläche zeigt ein Rezept pro Gericht | beschlossen |
| E6 | Rezeptseiten für Import-Tests | Welche Seiten nutzt ihr? (z. B. Chefkoch, Lecker, eigene Blogs) | offen |
| E7 | `pg_trgm` bei Hetzner | prüfen (`SELECT name FROM pg_available_extensions`), sonst ohne Stufe 4 der Zuordnung | offen |
