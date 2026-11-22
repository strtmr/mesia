# MESIA Brand Book

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
| 1.2 | 2026-11-22 | Warnfarbe hell auf `#b0740f` (Kontrast 3:1) |
| 1.1 | 2026-11-22 | Eigene Farbpalette, Bildmarke als SVG nachgebaut, Manrope eingebunden, Abstände und Dichte der App, Kontrastwerte gemessen |

---

## 1. Überblick

MESIA ist als skalierbare digitale Produktmarke gedacht, nicht nur als Meal-Planning- oder Einkaufslisten-App. Der
Einstieg liegt bei Einkauf, Rezepten und Planung, die Markenidee bleibt offen für weitere Alltagsfunktionen.

- **Markenidee:** Technologie verbindet Dinge, die im Alltag zusammengehören, und nimmt unnötige Organisation aus dem Weg.
- **Positionierung:** Eine intelligente Plattform, die Planung, Essen und Einkauf zu einer einfachen,
  zusammenhängenden Erfahrung verbindet.
- **Markenversprechen:** Make life simpler. Connect everything that matters.

### Entscheidungsstand

| Element | Stand |
|---|---|
| Name | MESIA, Wortmarke klein „mesia“ |
| Claim | „Life, intelligently connected.“ (exakt so) |
| Bildmarke | M als Tisch mit Schüssel, **(geändert)** als SVG nachgebaut, siehe Kapitel 3 |
| App-Icon | Dunkler Grund (Deep), helles Symbol, Schüssel in Sage |
| Farben | **(geändert)** eigene Palette, siehe Kapitel 5, in der App umgesetzt |
| Typografie | Manrope, **(neu)** in der App umgesetzt (selbst ausgeliefert) |
| Markenrecht, Domain | noch nicht geprüft (App läuft unter mesia.strtmr.de) |

---

## 2. Markenpersönlichkeit und Ton

MESIA wirkt intelligent, ohne kühl oder kompliziert zu sein.

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

Die Bildmarke verbindet drei Ebenen: Das M bildet das Tischgestell, die Schüssel steht für Mahlzeiten, die reduzierte
Formensprache für die digitale Produktwelt.

### Bildmarke (geändert: als SVG nachgebaut)

- **Aufbau:** kräftiges M als Tischgestell (zwei Beine, V-Kerbe von oben, Tischplatte liegt auf den Schultern),
  schmale gerade Tischplatte mit runden Enden, mittige Schüssel, darüber drei Dampflinien.
- **Proportionen** (Koordinaten im 512er-Raster): Tischplatte 304 × 26, M 264 × 144, Schüssel 184 × 60,
  Dampf 3 Linien à ca. 60 hoch. Die Schüssel dominiert das M nicht.
- **Varianten:**

| Variante | Einsatz | Datei |
|---|---|---|
| Voll (mit Dampf) | Login, App-Icon, große Darstellungen | `templates/app/_brand_mark.html.twig` |
| Kompakt (ohne Dampf) | ab ca. 32 px und kleiner: Seitenkopf, Favicon | `_brand_mark.html.twig` mit `compact: true`, `public/icons/favicon.svg` |
| Auf dunklem Grund | App-Icon, Favicon | `public/icons/icon.svg`, `icon-maskable.svg` |

- **Farben der Bildmarke:** Auf hellem Grund Tisch/M in Deep, Schüssel und Dampf in Slate. Auf dunklem Grund Tisch/M
  in Cream, Schüssel und Dampf in Sage. In der App über CSS gesteuert (`.brand-mark__table`, `.brand-mark__accent`),
  der Dark Mode wechselt automatisch.
- **Im Seitenkopf (neu):** kompakte Variante, 30 px hoch, links neben dem Seitentitel. Der Fuß des M steht auf der
  Grundlinie des Titels, die Oberkante auf Höhe der Großbuchstaben.

### Wortmarke und Claim

- Immer klein: **mesia**. In der App Manrope ExtraBold (800), Laufweite −0,03 em, in Deep (hell) bzw. Sage (dunkel).
- Die PDF-Idee eines orangen i-Punkts entfällt mit der neuen Palette (siehe Kapitel 5).
- Claim exakt: „Life, intelligently connected.“, Manrope Medium, gedämpfte Textfarbe.

### Schutzraum und Verwendung

- Rund um das Logo mindestens die Höhe der Schüssel frei lassen.
- Nicht stauchen, kippen, mit Illustrationen kombinieren, mit Effekten (Schatten, Verläufe) versehen oder umfärben
  außerhalb der Palette.

---

## 4. App-Icon und Favicon

| Datei | Größe | Hinweis |
|---|---|---|
| `public/icons/icon.svg` | Vektor | abgerundetes Quadrat (Radius 112/512), Quelle für die PNGs |
| `public/icons/icon-maskable.svg` | Vektor | randlos, Symbol auf 80 % verkleinert (sichere Zone für Android) |
| `public/icons/icon-192.png`, `icon-512.png` | 192, 512 | aus `icon.svg` |
| `public/icons/icon-maskable-512.png` | 512 | aus `icon-maskable.svg` |
| `public/icons/apple-touch-icon.png` | 180 | randlos, ohne Transparenz (iOS rundet selbst ab) |
| `public/icons/favicon.svg` | Vektor | ohne Dampf, Symbol vergrößert |
| `public/favicon.ico` | 16, 32, 48 | aus `favicon.svg` |

**Regeln:** nur das Symbol, ohne Wortmarke und Claim. Das M muss in kleinen Größen klar erkennbar bleiben, unter
ca. 32 px ohne Dampf. Neu erzeugen im DDEV-Container (ImageMagick), siehe [entwicklung.md](entwicklung.md#design-brand-book).

**Offen:** Masterdatei 1024 × 1024 px für App-Stores, Monochrom-Varianten (dunkel, weiß), Social-Preview-Bild.

---

## 5. Farbwelt (geändert)

Die Startpalette des PDFs (Forest Green, Warm Cream, Fresh Orange, Sage) ist durch eine eigene Palette ersetzt.
Sie ist ruhiger und kühler, behält aber Grün-Blau als Marke und Creme als warmen Grund.

### Markenpalette

| Name | Hex | Token | Rolle |
|---|---|---|---|
| Ink | `#01161e` | `--mesia-ink` | Text (hell), Hintergrund (dunkel) |
| Deep | `#124559` | `--mesia-deep` | Primäre Markenfarbe: Knöpfe, Links, Überschriften, App-Icon-Grund |
| Slate | `#598392` | `--mesia-slate` | Sekundär: Schüssel in der Bildmarke, Dekor, nicht für kleinen Text auf hellem Grund |
| Sage | `#aec3b0` | `--mesia-sage` | Akzent: Schüssel auf dunklem Grund, Primärfarbe im Dark Mode |
| Cream | `#eff6e0` | `--mesia-cream` | Hintergrund (hell), Text (dunkel) |

### Rollen (Tokens in der App)

| Rolle | Hell | Dunkel |
|---|---|---|
| `--color-bg` Hintergrund | Cream | Ink |
| `--color-surface` Flächen, Karten | `#ffffff` | `#072633` (zwischen Ink und Deep) |
| `--color-text` Text | Ink | Cream |
| `--color-text-muted` gedämpfter Text | `#4a6b78` (Slate abgedunkelt) | Sage |
| `--color-accent` Primäraktion, Links | Deep | Sage |
| `--color-accent-contrast` Text auf Akzent | `#ffffff` | Ink |
| `--color-separator` Trennlinien | Deep 16 % | Sage 18 % |
| `--color-toast-bg` / `-text` Hinweise | Ink / Cream | Cream / Ink |

### Statusfarben

Statusfarben dürfen nicht mit der Markenfarbe verwechselt werden.

| Status | Hell | Dunkel | Einsatz |
|---|---|---|---|
| Fehler, Löschen | `#c84b45` | `#ef7a73` | Fehlermeldungen, „Entfernen“, „Löschen“ |
| Warnung, ausstehend | `#b0740f` | `#e6a23c` | Punkt „noch nicht übertragen“, Status „ausstehend“ |
| Erfolg | `#32845d` | `#32845d` | reserviert (Synchron nutzt derzeit die Akzentfarbe) |

### Einsatzprinzip

- Deep trägt die Marke und alle primären Aktionen (im Dark Mode übernimmt Sage).
- Slate und Sage sind Akzente, nicht für Fließtext auf hellem Grund.
- Cream bringt Wärme in die Flächen, Weiß hebt Karten davon ab.
- **(geändert)** Kein Orange mehr: Die Schüssel ist Slate bzw. Sage. Orange war im PDF Food-Akzent.

---

## 6. Typografie

UI-Schrift: **Manrope** (variabel, Schnitte 200–800), Alternative Inter. **(neu)** Selbst ausgeliefert aus
`assets/fonts/` (Lizenz OFL-1.1), kein Abruf bei Google: Datenschutz und offline verfügbar.

### Skala in der App (geändert: kompakter als im PDF)

| Stil | Größe | Schnitt | Einsatz |
|---|---|---|---|
| Wortmarke | 40 px | 800 | Login |
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
| Abhak-Kreis | 22 px | in einer 44 px breiten Spalte |
| Bildmarke im Kopf | 30 px hoch | kompakte Variante |

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
| Primärknopf | Akzentfläche, Text in Akzent-Kontrast, 44 px hoch, Radius 10 |
| Destruktiver Knopf | Fehlerfarbe, bei kritischen Aktionen Rückfrage (Kategorie löschen) |
| Listengruppe | weiße Karte (Radius 14), Zeilen 44 px, Trennlinie ab der Textspalte |
| Kategorie-Überschrift | Emoji + Name, 16 px fett in Akzentfarbe, 6 px über der Gruppe |
| Eingabeleiste | unten fixiert, über der Tastatur, Feld rund, Plus-Knopf rund in Akzentfarbe |
| Abhaken | Kreis füllt sich in Akzentfarbe mit Häkchen, Zeile blendet aus, dann „Erledigt“ |
| Wischen zum Entfernen | Zeile nach links schieben, roter Knopf „Entfernen“ rastet ein |
| Hinweis (Toast) | dunkle Fläche (Radius 14, 15 px Text) über der Eingabeleiste, Fehler in Fehlerfarbe |
| Sheet | von unten, Radius 20 oben, für Bearbeiten |

---

## 8. Produkterlebnis

MESIA wirkt wie ein intelligenter Begleiter, der Zusammenhänge erkennt und Arbeit abnimmt. KI ist eine Fähigkeit des
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
| Ink auf Cream (Text) | 16,7:1 | ✓ |
| Ink auf Weiß | 18,5:1 | ✓ |
| Gedämpft `#4a6b78` auf Cream / Weiß | 5,2:1 / 5,7:1 | ✓ |
| Slate auf Cream | 3,7:1 | nur große Schrift und Grafik |
| Deep auf Cream (Links, Überschriften) | 9,4:1 | ✓ |
| Weiß auf Deep (Primärknopf) | 10,4:1 | ✓ |
| Fehlerfarbe auf Weiß, Weiß auf Fehlerfarbe | 4,6:1 | ✓ |
| Dunkel: Cream auf Ink / auf Surface | 16,7:1 / 14,2:1 | ✓ |
| Dunkel: Sage auf Ink / auf Surface | 9,9:1 / 8,4:1 | ✓ |
| Dunkel: Ink auf Sage (Primärknopf) | 9,9:1 | ✓ |
| Dunkel: Fehlerfarbe auf Surface | 5,8:1 | ✓ |
| Warnpunkt hell `#b0740f` auf Weiß / Cream | 3,9:1 / 3,5:1 | ✓ (Grafik) |
| Warnpunkt dunkel `#e6a23c` auf Surface | 7,2:1 | ✓ |

---

## 10. Design Tokens

Zentral in [`assets/styles/app.css`](../assets/styles/app.css), Abschnitt „Design-Tokens“. Komponenten verwenden nur
Tokens, keine eigenen Farben, Radien oder Abstände. Auszug (helles Thema):

```css
:root {
    --mesia-ink: #01161e;
    --mesia-deep: #124559;
    --mesia-slate: #598392;
    --mesia-sage: #aec3b0;
    --mesia-cream: #eff6e0;

    --color-bg: var(--mesia-cream);
    --color-surface: #ffffff;
    --color-text: var(--mesia-ink);
    --color-text-muted: #4a6b78;
    --color-accent: var(--mesia-deep);
    --color-accent-contrast: #ffffff;
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
| 5 | Marke, Domains und App-Stores recherchieren | offen |
| – | Bildmarke als SVG | erledigt (1.1) |
| – | Manrope eingebunden, Typografie abgestimmt | erledigt (1.1) |
| – | Design Tokens umgesetzt | erledigt (1.1) |
| – | Kontraste geprüft | erledigt (1.1) |
| – | Warnpunkt auf mindestens 3:1 (`#b0740f` hell) | erledigt (1.2) |
