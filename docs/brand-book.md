# Mesuna Brand Book

> **Life, intelligently connected.**
> Make life simpler. Connect everything that matters.

Lebende Fassung des Brand Books, gepflegt im Projekt. Grundlage ist `MESIA_Brand_Book_v1.pdf` (Projektroot,
Version 1.0). Abweichungen und Ergänzungen gegenüber dem PDF sind mit **(neu)** bzw. **(geändert)** markiert.

**Verbindlich für Werte ist der Code:** Farben, Abstände und Radien stehen als Tokens in
[`assets/styles/app.css`](../assets/styles/app.css). Dieses Dokument erklärt sie und muss bei Änderungen mitgezogen
werden (und umgekehrt).

| Version | Datum | Änderung |
|---|---|---|
| 1.0 | – | PDF: Markenidee, Logo-Konzept, Startpalette, Typografie, Tokens |
| 1.9 | 2026-11-26 | Umbenennung von MESIA in Mesuna, Tokens `--mesuna-*`. Logo nach Vorlage `docs/brand/mesuna-logo.png`: Bildmarke aus feinen, gleich starken Linien statt Sticker, Wortmarke „MESUNA“ in Manrope 400 mit weiter Laufweite statt Archivo Black, App-Icon auf Ink statt Deep |
| 1.8 | 2026-11-25 | Startbild für die installierte App (iOS) |
| 1.7 | 2026-11-24 | Hintergrund hell: Paper `#f7f5ef` statt Cream, dunklere Schrift auf getönten Etiketten |
| 1.6 | 2026-11-24 | Läden: Chips (Filter und Auswahl), Datumsmarkierung heute/abgelaufen, Erfolgsfarbe im Dunkelmodus |
| 1.5 | 2026-11-23 | Auswahlfelder: Chip in der Zeile, Pfeil auf/ab im Formular |
| 1.4 | 2026-11-23 | Komponente Schalter (Mitteilungen), Abhaken bleibt stehen, Metazeile im Sheet |
| 1.3 | 2026-11-22 | Streetstyle: Signal-Orange als Aktionsfarbe, Sticker-Bildmarke mit Größenstufen, Wortmarke „MESIA“ in Archivo Black |
| 1.2 | 2026-11-22 | Warnfarbe hell auf `#b0740f` (Kontrast 3:1) |
| 1.1 | 2026-11-22 | Eigene Farbpalette, Bildmarke als SVG nachgebaut, Manrope eingebunden, Abstände und Dichte der App, Kontrastwerte gemessen |

---

## 1. Überblick

Mesuna ist als skalierbare digitale Produktmarke gedacht, nicht nur als Meal-Planning- oder Einkaufslisten-App. Der
Einstieg liegt bei Einkauf, Rezepten und Planung, die Markenidee bleibt offen für weitere Alltagsfunktionen.

- **Markenidee:** Technologie verbindet Dinge, die im Alltag zusammengehören, und nimmt unnötige Organisation aus dem Weg.
- **Positionierung:** Eine intelligente Plattform, die Planung, Essen und Einkauf zu einer einfachen,
  zusammenhängenden Erfahrung verbindet.
- **Markenversprechen:** Make life simpler. Connect everything that matters.

### Entscheidungsstand

| Element | Stand |
|---|---|
| Name | **(geändert)** Mesuna (im PDF noch MESIA). Herleitung: „mesa“ (Tisch) + „una“ (eins), ein Tisch für den ganzen Haushalt |
| Claim | „Life, intelligently connected.“ (exakt so) |
| Bildmarke | M als Tisch mit Schüssel, **(geändert)** fein und flach aus Linien, siehe Kapitel 3 |
| App-Icon | **(geändert 1.9)** Ink, M und Tischplatte in Creme, Schüssel in Signal-Orange (wie die Logo-Vorlage) |
| Farben | **(geändert)** eigene Palette plus Signal-Orange, siehe Kapitel 5 |
| Typografie | Manrope für App und **(geändert)** Wortmarke „MESUNA“ |
| Markenrecht, Domain | Stand 2026-11-25: keine Marke „Mesuna“ gefunden (nur US-Register geprüft, DPMA/EUIPO offen). mesuna.de und mesuna.app frei, mesuna.com vergeben (ohne Website). App läuft unter mesuna.strtmr.de |

---

## 2. Markenpersönlichkeit und Ton

Mesuna wirkt intelligent, ohne kühl oder kompliziert zu sein.

| Eigenschaft | Bedeutung |
|---|---|
| Intelligent | hilfreich, vorausschauend, kompetent, ohne mit Technik zu prahlen |
| Einfach | Komplexität verschwindet im Hintergrund, die Bedienung bleibt klar |
| Verbunden | Planung, Rezepte, Einkauf und weitere Funktionen greifen ineinander |
| Nahbar | freundlich und zugänglich, aber nicht kindlich oder verspielt |
| Hochwertig | reduziert, konsistent und sorgfältig gestaltet |
| Skalierbar | nicht auf eine Funktion oder enge Zielgruppe festgelegt |

**Nicht:** klassische Rezept- oder Restaurantmarke, verspielter Familienplaner, kaltes B2B-Dashboard, überladene
KI-Marke mit Tech-Symbolen.

### Kommunikationsstil

Kurz, klar, freundlich und lösungsorientiert. Wie ein kompetenter Begleiter: hilfreich, nicht belehrend.

**(neu) Regeln für Texte in der App**, aus der bisherigen Umsetzung:

- Du-Form, aktive Sätze, keine Technikbegriffe („Synchron“ statt „Sync-Status 200“).
- Fehlermeldungen sagen, was passiert ist und was man tun kann:
  „Die Menge 1.111.111.111 ist zu groß, höchstens 99.999 sind möglich.“
- Bestätigungen möglichst ohne Text, wenn eine sichtbare Reaktion reicht (Abhaken: nur Häkchen und Bewegung).
- Hinweise im Laden sind kurz. Lange Erklärungen gehören auf die Hilfe-Seite.
- Neuigkeiten aus Benutzersicht: „Mengen lassen sich ausrechnen“ statt „Parser erweitert“.

---

## 3. Logo-System

Die Bildmarke verbindet zwei Ebenen: Das M bildet das Tischgestell, die Schüssel steht für Mahlzeiten. **(geändert
1.9)** Flach und fein statt Sticker: keine Kontur, kein Versatz, keine Drehung. Die Marke soll erwachsen und ruhig
wirken, nicht comichaft. **Vorlage:** `docs/brand/mesuna-logo.png` (Bildmarke über Wortmarke auf Ink). Die SVG-Fassung
in der App ist daraus nachgemessen.

### Bildmarke (geändert: fein)

- **Aufbau:** M aus feinen, gleich starken Linien als Tischgestell (senkrechte Beine, V-Kerbe von den oberen Ecken
  bis etwa 60 % der Höhe), darüber eine Tischplatte als Linie gleicher Stärke mit runden Enden, darauf eine Schüssel
  als flache Halbellipse (oben gerade). Alle Enden und Ecken rund. **Kein Dampf:** Dampflinien erinnerten zu sehr an das Java-Logo, die Wärme trägt die Farbe der Schüssel.
- **Farben:** Tischplatte und M in Deep (dunkel: Creme), Schüssel immer Signal-Orange. Auf dem App-Icon Creme auf Ink.
- **Proportionen** (Zeichenfläche 370 × 290): alle Linien 16 stark. Tischplatte 366 lang (mit runden Enden), Schüssel
  206 × 65, M 296 × 166 (Beine bei x 45 und 325, V-Spitze bei y 227). Abstand Schüssel–Platte 17, Platte–M 21.
- **Favicon:** dieselbe Form, Linien deutlich kräftiger (Platte 40, M 48) und das M etwas schmaler, damit sie bei
  16 px lesbar bleibt.

### Einsatz

| Einsatz | Größe | Datei |
|---|---|---|
| Login | 120 px breit | `templates/app/_brand_mark.html.twig` (Farben per CSS `.brand-mark__table` / `.brand-mark__bowl`) |
| Seitenkopf | 30 px hoch, links neben dem Titel, Fuß des M auf der Grundlinie | dasselbe Template |
| App-Icon, Startbild | siehe Kapitel 4 | `public/icons/*.svg`, `scripts/render-splash.mjs` |

### Wortmarke und Claim

- **(geändert)** „MESUNA“ in Versalien, **Manrope 400**, Laufweite +0,28 em. Kein Farbakzent und kein Punkt: Das
  Orange gehört der Schüssel. Im HTML steht „Mesuna“, die Versalien kommen aus CSS (`.wordmark`), damit Screenreader
  den Namen normal vorlesen.
- **Logo (Bildmarke über Wortmarke):** Die Wortmarke ist etwa 1,75-mal so breit wie die Bildmarke, Abstand dazwischen
  etwa ein Viertel der Bildmarkenbreite. Beides zentriert.
- Farbe: Ink auf hellem, Creme auf dunklem Grund. Auf Signal-Orange immer Ink.
- Im Fließtext heißt die App „Mesuna“ (groß geschrieben, wie ein Name).
- Claim exakt: „Life, intelligently connected.“, Manrope Medium, gedämpfte Textfarbe.

### Schutzraum und Verwendung

- Rund um das Logo mindestens die Höhe der Schüssel frei lassen.
- Nicht stauchen, drehen, mit Illustrationen kombinieren, mit Effekten (Konturen, Verläufe, Schatten) versehen oder
  außerhalb der Palette umfärben.

---

## 4. App-Icon und Favicon

| Datei | Größe | Hinweis |
|---|---|---|
| `public/icons/icon.svg` | Vektor | abgerundetes Quadrat (Radius 112/512), Quelle für die PNGs |
| `public/icons/icon-maskable.svg` | Vektor | randlos, Zeichen in der sicheren Zone für Android |
| `public/icons/icon-192.png`, `icon-512.png` | 192, 512 | aus `icon.svg` |
| `public/icons/icon-maskable-512.png` | 512 | aus `icon-maskable.svg` |
| `public/icons/apple-touch-icon.png` | 180 | randlos, ohne Transparenz (iOS rundet selbst ab) |
| `public/icons/favicon.svg` | Vektor | Zeichen vergrößert, Linien kräftiger (Kapitel 3) |
| `public/favicon.ico` | 16, 32, 48 | aus `favicon.svg` |

**Regeln:** nur das Symbol, ohne Wortmarke und Claim. **(geändert 1.9)** Hintergrund Ink wie die Logo-Vorlage, M und
Tischplatte Creme, Schüssel Signal-Orange. Bildmarke 300 von 512 breit (randlos: 280). Das M muss in kleinen Größen klar erkennbar bleiben. PNGs und `favicon.ico` neu erzeugen: `ddev exec
node scripts/render-icons.mjs` (rendert mit Chromium).

**Startbild (iOS):** Hintergrund Paper (dunkel: Ink), Bildmarke in Deep (dunkel: Creme), 36 % der
Bildschirmbreite, darunter die Wortmarke „MESUNA“ (Ink, dunkel: Cream) in den Proportionen des Logos, Gruppe leicht
über der Mitte. Erzeugt von `ddev exec
node scripts/render-splash.mjs` nach `public/splash/`.

**Offen:** Masterdatei 1024 × 1024 px für App-Stores, Monochrom-Varianten (dunkel, weiß), Social-Preview-Bild.

---

## 5. Farbwelt (geändert)

Die Startpalette des PDFs (Forest Green, Warm Cream, Fresh Orange, Sage) ist durch eine eigene Palette ersetzt.
Sie ist ruhig und kühl mit Grün-Blau als Marke und warmem Papierweiß als Grund (bis 1.6 Cream). **(neu)** Für Streetstyle und Wärme kommt
genau eine Signalfarbe dazu: Signal-Orange.

### Markenpalette

| Name | Hex | Token | Rolle |
|---|---|---|---|
| Ink | `#01161e` | `--mesuna-ink` | Text (hell), Hintergrund (dunkel), App-Icon-Grund |
| Deep | `#124559` | `--mesuna-deep` | Primäre Markenfarbe: Links, Überschriften, Bildmarke auf hellem Grund |
| Slate | `#598392` | `--mesuna-slate` | Sekundär: Dekor, nicht für kleinen Text auf hellem Grund |
| Sage | `#aec3b0` | `--mesuna-sage` | Akzentfarbe im Dark Mode (Links, Überschriften) |
| Cream | `#eff6e0` | `--mesuna-cream` | Text (dunkel), Hinweise, Bildmarke auf dunklem Grund und auf dem App-Icon |
| Paper | `#f7f5ef` | `--mesuna-paper` | **(neu)** Hintergrund im hellen Thema: warmes Papierweiß ohne Grünstich |
| Signal-Orange | `#ff6a2b` | `--mesuna-orange` | **(neu)** Aktionen (Knöpfe, Plus, Häkchen), Schüssel der Bildmarke, Merch |

### Rollen (Tokens in der App)

| Rolle | Hell | Dunkel |
|---|---|---|
| `--color-bg` Hintergrund | Paper | Ink |
| `--color-surface` Flächen, Karten | `#ffffff` | `#072633` (zwischen Ink und Deep) |
| `--color-text` Text | Ink | Cream |
| `--color-text-muted` gedämpfter Text | `#4a6b78` (Slate abgedunkelt) | Sage |
| `--color-accent` Links, Überschriften, Fokus | Deep | Sage |
| `--color-accent-contrast` Text auf Akzent | `#ffffff` | Ink |
| `--color-action` Primärknopf, Plus, Häkchen | Signal-Orange | Signal-Orange |
| `--color-action-contrast` Schrift und Häkchen auf Aktion | Ink | Ink |
| `--color-separator` Trennlinien | Deep 16 % | Sage 18 % |
| `--color-toast-bg` / `-text` Hinweise | Ink / Cream | Cream / Ink |

### Statusfarben

Statusfarben dürfen nicht mit der Markenfarbe verwechselt werden.

| Status | Hell | Dunkel | Einsatz |
|---|---|---|---|
| Fehler, Löschen | `#c84b45` | `#ef7a73` | Fehlermeldungen, „Entfernen“, „Löschen“ |
| Warnung, ausstehend | `#b0740f` | `#e6a23c` | Punkt „noch nicht übertragen“, Status „ausstehend“ |
| Erfolg | `#32845d` | `#6fcf9a` | „heute“ am Datum eines Eintrags, Hinweis nach erfolgreichem Anlegen |
| Schrift auf getönten Etiketten | Erfolg `#256b49`, Fehler `#a63a34` | wie Statusfarbe | „heute“ / „abgelaufen“, damit 4,5:1 auch auf der Tönung erreicht wird |

### Einsatzprinzip

- Deep trägt die Marke (Links, Überschriften, Bildmarke), im Dark Mode übernimmt Sage.
- **Signal-Orange nur für Aktionen und die Schüssel**, sparsam: ein Primärknopf pro Ansicht, Plus, Häkchen.
  Darauf immer Ink (6,5:1), nie Weiß (2,9:1). Nicht für Text auf hellem Grund (2,6:1).
- Slate und Sage sind Akzente, nicht für Fließtext auf hellem Grund.
- Paper gibt einen warmen, ruhigen Grund, Weiß hebt Karten davon ab.

---

## 6. Typografie

UI-Schrift: **Manrope** (variabel, Schnitte 200–800), Alternative Inter. **(geändert 1.9)** Auch die Wortmarke ist
Manrope (600), Archivo Black ist entfallen. Selbst ausgeliefert aus `assets/fonts/` (Lizenz OFL-1.1, Quelle npm
`@fontsource`), kein Abruf bei Google: Datenschutz und offline verfügbar.

### Skala in der App (geändert: kompakter als im PDF)

| Stil | Größe | Schnitt | Einsatz |
|---|---|---|---|
| Wortmarke „MESUNA“ | 40 px | 400, Laufweite +0,28 em | Login |
| H1 Seitentitel | 28 px | 700 | „Einkauf“, „Mehr“ (PDF: 30–32 px) |
| H2 Abschnitt | 17–18 px | 700 | Hilfe (18), Neuigkeiten (17) |
| Kategorie-Überschrift | 16 px | 700, Akzentfarbe | Gruppen der Einkaufsliste |
| Body | 16 px | 400 | Listeneinträge, Fließtext, Eingaben |
| Small | 14 px | 400 | Mengen, Notizen, Hinweise |
| Caption | 13 px | 400 | Abschnittstitel (Großbuchstaben), Zähler, Abbinder |
| Button | 16 px | 600 | Aktionen |

**Regel:** Eingabefelder nie unter 16 px, sonst zoomt iOS beim Antippen. Zeilenhöhe 1,35.

---

## 7. UI-Designsystem

Ruhig, übersichtlich, hochwertig. Die Marke zeigt sich durch konsistente Typografie, Abstände und Farben, nicht durch
Dekoration.

### Prinzipien

- Mobile first, vollständig responsiv.
- Klare Hierarchie, kurze Wege, **(neu)** hohe Informationsdichte in der Einkaufsliste (möglichst viele Einträge auf
  einen Blick), ohne Trefferflächen zu verkleinern.
- Weißraum gezielt, Inhalte nicht unnötig in Karten aufteilen.
- Zurückhaltende Schatten, feine Rahmen (0,5 px Trennlinien wie bei iOS).
- Animationen nur für Orientierung und Feedback, `prefers-reduced-motion` beachten.

### Abstände (geändert)

| Token | Wert | Typischer Einsatz |
|---|---|---|
| `--space-1` | 4 px | Abstand Text zu Hinweis |
| `--space-2` | 8 px | Innenabstand klein, Lücken zwischen Elementen |
| `--space-3` | 12 px | Abstand zwischen Feldern |
| `--space-4` | 16 px | Seitenrand, Abstand zwischen Kategorien, Karteninnenabstand |
| `--space-5` | 24 px | Abstand zwischen Abschnitten |
| `--space-6` | 32 px | große Abstände (Login, Abbinder) |

Abweichung vom PDF (4 · 8 · 16 · 24 · 32 · 48 · 64): zusätzlich 12 px für die kompakte Liste, 48 und 64 px bisher
nicht benötigt.

### Größen

| Token | Wert | Regel |
|---|---|---|
| `--touch-target` | 44 px | Mindestgröße jeder Trefferfläche, Höhe einer Listenzeile |
| `--tabbar-height` | 50 px | Tab-Leiste unten |
| `--list-row` | 44 px, Touch 50 px | Mindesthöhe einer Zeile der Einkaufsliste (`pointer: coarse` = Touch) |
| `--check-column` | 44 px, Touch 52 px | Spalte mit dem Abhak-Kreis, ab ihr beginnt die Trennlinie |
| Abhak-Kreis | 22 px | mittig in der Kreisspalte |
| Bildmarke im Kopf | 30 px hoch | |

### Radien

| Token | Wert | Einsatz |
|---|---|---|
| `--radius-s` | 8 px | Eingabefelder |
| `--radius-button` | 10 px | Knöpfe |
| `--radius-m` | 14 px | Karten, Listengruppen, Hinweise |
| `--radius-l` | 20 px | große Flächen, Sheet von unten |
| – | 999 px | runde Elemente (Eingabeleiste, Plus-Knopf) |

### Komponenten (neu)

| Komponente | Gestaltung |
|---|---|
| Primärknopf | Signal-Orange, Schrift Ink, 44 px hoch, Radius 10 |
| Destruktiver Knopf | Fehlerfarbe, bei kritischen Aktionen Rückfrage (Kategorie löschen) |
| Listengruppe | weiße Karte (Radius 14), Zeilen 44 px, Trennlinie ab der Textspalte. Beschriftung links, Wert gedämpft rechtsbündig direkt vor dem Pfeil › |
| Zeile der Einkaufsliste | Kreis, Name und Menge sind eine Trefferfläche zum Abhaken (beim Drücken grau hinterlegt), rechts „›“ (52 px breit, 8 px Abstand zur Menge, gedämpft) öffnet das Bearbeiten-Fenster |
| Kategorie-Überschrift | Emoji + Name, 16 px fett in Akzentfarbe, 6 px über der Gruppe |
| Eingabeleiste | unten fixiert, über der Tastatur, Feld rund, Plus-Knopf rund in Signal-Orange (Plus in Ink) |
| Abhaken | Kreis füllt sich in Signal-Orange mit Häkchen in Ink, Name durchgestrichen in gedämpfter Farbe; die Zeile bleibt stehen, bis die Liste 2,5 s nicht berührt wurde, dann schrumpft alles Abgehakte in 0,25 s weg und wandert nach „Erledigt“ |
| Chip | 32 px hoch, Radius voll, Rahmen 35 % Akzent, Schrift Akzent halbfett; gewählt: Fläche Akzent, Schrift Kontrastfarbe. Laden-Filter über der Liste (waagrecht scrollbar, mit Anzahl), Läden im Bearbeiten-Fenster |
| Laden-Auswahl (Bearbeiten) | Eingabefeld „Laden hinzufügen …“ wie die Schnelleingabe; Vorschläge erst beim Tippen, als Karte mit Schatten wie unter „Artikel hinzufügen …“ (Zeilen 44 px). Gewählte Läden als Chips mit × darüber |
| Laden und Datum am Eintrag | Zeile unter dem Namen: Läden 13 px gedämpft (zu lange werden mit … gekürzt), direkt dahinter „gültig bis“ als Etikett (12 px halbfett, Radius voll): neutral „bis Sa., 28.11.“, grün „heute“, rot „abgelaufen 23.11.“ (Fläche jeweils 12–16 % der Farbe) |
| Hinweis Erfolg | `.notice--success`: Fläche mit 16 % Erfolgsfarbe, z. B. nach dem Anlegen im Admin-Bereich |
| Metazeile im Sheet | 13 px gedämpft über dem Löschen-Knopf („Hinzugefügt von … heute um …“) |
| Wischen zum Entfernen | Zeile nach links schieben, roter Knopf „Entfernen“ rastet ein |
| Hinweis (Toast) | dunkle Fläche (Radius 14, 15 px Text) über der Eingabeleiste, Fehler in Fehlerfarbe |
| Sheet | von unten, Radius 20 oben, für Bearbeiten |
| Schalter | in einer Listengruppen-Zeile rechts, 51 × 31 px, aus: Trennlinienfarbe, an: Signal-Orange, Knopf weiß |
| Auswahl in Zeile | Chip rechts: Wert 16 px halbfett in Akzentfarbe auf 10 % Akzent, Radius voll, Pfeil auf/ab; so breit wie der Wert (z. B. Häufigkeit) |
| Auswahlfeld im Formular | wie Eingabefeld, rechts Pfeil auf/ab in gedämpfter Farbe (Token `--icon-select`, je Thema) |

---

## 8. Produkterlebnis

Mesuna wirkt wie ein intelligenter Begleiter, der Zusammenhänge erkennt und Arbeit abnimmt. KI ist eine Fähigkeit des
Produkts, kein dekoratives Label.

1. Überblick geben. 2. Gute Vorschläge machen. 3. Entscheidungen vereinfachen. 4. Zusammenhängende Abläufe
ermöglichen. 5. Manuelle Arbeit reduzieren.

**KI-Begriffe:** „Smarte Vorschläge“, „Für dich empfohlen“, „Einkaufsliste optimieren“, „Intelligente Planung“.
Nicht jede Funktion mit „KI“ markieren.

**Responsiv:** Mobil klare Hauptaktionen und große Trefferflächen. Tablet mehr Inhalt, sinnvolle zwei Spalten.
Desktop Seitennavigation und breitere Inhalte (noch offen, siehe Kapitel 11).

---

## 9. Barrierefreiheit (neu: gemessen)

Ziel WCAG 2.2 AA: Kontrast für Text mindestens 4,5:1 (große Schrift 3:1), für grafische Elemente 3:1, sichtbarer
Tastaturfokus, klare Labels, verständliche Fehlermeldungen, Status nie nur über Farbe, reduzierte Bewegung
respektieren, Trefferflächen mindestens 44 × 44 px.

| Kombination | Kontrast | AA |
|---|---|---|
| Ink auf Paper (Text) | 17,0:1 | ✓ |
| Ink auf Weiß | 18,5:1 | ✓ |
| Gedämpft `#4a6b78` auf Paper / Weiß | 5,3:1 / 5,7:1 | ✓ |
| Slate auf Paper | 3,8:1 | nur große Schrift und Grafik |
| Deep auf Paper (Links, Überschriften) | 9,5:1 | ✓ |
| Weiß auf Deep | 10,4:1 | ✓ |
| Ink auf Signal-Orange (Primärknopf, Häkchen) | 6,5:1 | ✓ |
| Weiß auf Signal-Orange | 2,9:1 | ✗, deshalb nicht verwenden |
| Signal-Orange auf Paper (als Text) | 2,6:1 | ✗, nur Flächen und Grafik |
| Creme auf Deep | 9,4:1 | ✓ |
| Creme auf Ink (App-Icon) | 16,7:1 | ✓ |
| Fehlerfarbe auf Weiß, Weiß auf Fehlerfarbe | 4,6:1 | ✓ |
| Dunkel: Cream auf Ink / auf Surface | 16,7:1 / 14,2:1 | ✓ |
| Dunkel: Sage auf Ink / auf Surface | 9,9:1 / 8,4:1 | ✓ |
| Dunkel: Ink auf Sage | 9,9:1 | ✓ |
| Dunkel: Fehlerfarbe auf Surface | 5,8:1 | ✓ |
| Warnpunkt hell `#b0740f` auf Weiß / Paper | 3,9:1 / 3,6:1 | ✓ (Grafik) |
| Etikett „heute“ `#256b49` / „abgelaufen“ `#a63a34` auf ihrer Tönung | 5,2:1 / 5,3:1 | ✓ |
| Warnpunkt dunkel `#e6a23c` auf Surface | 7,2:1 | ✓ |

---

## 10. Design Tokens

Zentral in [`assets/styles/app.css`](../assets/styles/app.css), Abschnitt „Design-Tokens“. Komponenten verwenden nur
Tokens, keine eigenen Farben, Radien oder Abstände. Auszug (helles Thema):

```css
:root {
    --mesuna-ink: #01161e;
    --mesuna-deep: #124559;
    --mesuna-slate: #598392;
    --mesuna-sage: #aec3b0;
    --mesuna-cream: #eff6e0;
    --mesuna-paper: #f7f5ef;
    --mesuna-orange: #ff6a2b;

    --color-bg: var(--mesuna-paper);
    --color-surface: #ffffff;
    --color-text: var(--mesuna-ink);
    --color-text-muted: #4a6b78;
    --color-accent: var(--mesuna-deep);
    --color-accent-contrast: #ffffff;
    --color-action: var(--mesuna-orange);
    --color-action-contrast: var(--mesuna-ink);
    --color-danger: #c84b45;
    --color-warning: #b0740f;
    --color-success: #32845d;

    --radius-s: 8px;
    --radius-button: 10px;
    --radius-m: 14px;
    --radius-l: 20px;

    --space-1: 4px;  --space-2: 8px;  --space-3: 12px;
    --space-4: 16px; --space-5: 24px; --space-6: 32px;

    --touch-target: 44px;
    --list-row: 44px;      /* Touch: 50px */
    --check-column: 44px;  /* Touch: 52px */
    --font-system: "Manrope", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
```

Das dunkle Thema überschreibt die Rollen unter `@media (prefers-color-scheme: dark)`.

---

## 11. Offene Punkte

| Priorität | Aufgabe | Status |
|---|---|---|
| 1 | App-Icon-Master 1024 × 1024 px, Monochrom-Varianten, Social Preview | offen |
| 2 | Wortmarke als eigene Vektordatei (statt Schrift) | offen |
| 3 | Desktop-Ansicht: Seitennavigation, breitere Inhalte | offen |
| 4 | Erfolgsfarbe festlegen oder streichen (Synchron nutzt Akzentfarbe) | offen |
| 5 | Marke prüfen (DPMA, EUIPO), Domain mesuna.de, App-Stores | teilweise (1.9: US-Marken, Domains) |
| – | Bildmarke als SVG | erledigt (1.1) |
| – | Manrope eingebunden, Typografie abgestimmt | erledigt (1.1) |
| – | Design Tokens umgesetzt | erledigt (1.1) |
| – | Kontraste geprüft | erledigt (1.1) |
| – | Warnpunkt auf mindestens 3:1 (`#b0740f` hell) | erledigt (1.2) |
| – | Streetstyle: Signal-Orange, Sticker-Bildmarke, Archivo Black | erledigt (1.3), Sticker und Archivo Black in 1.9 ersetzt |
| – | Name Mesuna, Logo nach Vorlage (feine Bildmarke, Wortmarke „MESUNA“ in Manrope) | erledigt (1.9) |
