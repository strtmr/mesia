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
| 1.3 | 2026-11-22 | Streetstyle: Signal-Orange als Aktionsfarbe, Sticker-Bildmarke mit Größenstufen, Wortmarke „MESIA“ in Archivo Black |
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
| Name | MESIA, Wortmarke in Versalien „MESIA“ **(geändert)** |
| Claim | „Life, intelligently connected.“ (exakt so) |
| Bildmarke | M als Tisch mit Schüssel, **(geändert)** kräftiges Sticker-M mit Kontur und Versatz, siehe Kapitel 3 |
| App-Icon | Deep, Sticker-M in Creme, Schüssel in Signal-Orange |
| Farben | **(geändert)** eigene Palette plus Signal-Orange, siehe Kapitel 5 |
| Typografie | Manrope für die App, **(neu)** Archivo Black für Wortmarke und Merch |
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

Die Bildmarke verbindet drei Ebenen: Das M bildet das Tischgestell, die Schüssel steht für Mahlzeiten, die
**(neu)** Sticker-Anmutung (Kontur, Versatz, leichte Drehung) bringt Streetstyle für die Zielgruppe Eltern 30–45.

### Bildmarke (geändert: Sticker-M)

- **Aufbau:** kräftiges M als Tischgestell (breite Beine, V-Kerbe von oben, die Tischplatte liegt auf den
  Schultern), dicke gerade Tischplatte mit runden Enden, darauf eine Schüssel. **Kein Dampf mehr:** Dampflinien
  erinnerten zu sehr an das Java-Logo. Die Wärme trägt jetzt die Farbe der Schüssel (Signal-Orange).
- **Sticker:** Creme mit Kontur in Ink, Versatz (Schatten) in Deep nach rechts unten, um 5° gegen den Uhrzeigersinn
  gedreht. Auf dunklem Grund ist der Versatz Ink.
- **Proportionen** (512er-Raster): Tischplatte 312 × 32, M 264 × 150, Schüssel 176 × 60, Kontur 11, Versatz 14.

### Größenstufen

| Stufe | Größe | Gestaltung | Einsatz | Datei |
|---|---|---|---|---|
| Sticker | ab ca. 80 px | Kontur, Versatz, Drehung | Login, App-Icon, Merch | `templates/app/_brand_mark.html.twig` (Standard), `public/icons/icon.svg` |
| Kontur | 32–80 px | Kontur ohne Versatz und Drehung | Favicon | `public/icons/favicon.svg` |
| Flach | bis ca. 40 px in der App | ohne Kontur: M in Akzentfarbe (Deep, dunkel: Creme), Schüssel Signal-Orange | Seitenkopf | `_brand_mark.html.twig` mit `variant: 'flat'` |

Im Seitenkopf steht die flache Bildmarke 30 px hoch links neben dem Titel, der Fuß des M auf der Grundlinie.

### Wortmarke und Claim

- **(geändert)** **MESIA** in **Archivo Black**, Versalien, Laufweite +0,01 em. Im HTML steht „mesia“, die Versalien
  kommen aus CSS (`.wordmark`), damit Screenreader den Namen normal vorlesen.
- Farbe: Ink auf hellem, Creme auf dunklem Grund. Auf Signal-Orange immer Ink.
- Claim exakt: „Life, intelligently connected.“, Manrope Medium, gedämpfte Textfarbe.

### Schutzraum und Verwendung

- Rund um das Logo mindestens die Höhe der Schüssel frei lassen.
- Nicht stauchen, mit Illustrationen kombinieren, mit zusätzlichen Effekten (Verläufe, weiche Schatten) versehen oder
  außerhalb der Palette umfärben. Die Drehung ist Teil des Zeichens, nicht weiter drehen.
- Sticker-Look nur für Marke und Merch, nicht für Bedienelemente der App.

---

## 4. App-Icon und Favicon

| Datei | Größe | Hinweis |
|---|---|---|
| `public/icons/icon.svg` | Vektor | abgerundetes Quadrat (Radius 112/512), Quelle für die PNGs |
| `public/icons/icon-maskable.svg` | Vektor | randlos, Zeichen in der sicheren Zone für Android |
| `public/icons/icon-192.png`, `icon-512.png` | 192, 512 | aus `icon.svg` |
| `public/icons/icon-maskable-512.png` | 512 | aus `icon-maskable.svg` |
| `public/icons/apple-touch-icon.png` | 180 | randlos, ohne Transparenz (iOS rundet selbst ab) |
| `public/icons/favicon.svg` | Vektor | Stufe Kontur (ohne Versatz und Drehung), Zeichen vergrößert |
| `public/favicon.ico` | 16, 32, 48 | aus `favicon.svg` |

**Regeln:** nur das Symbol, ohne Wortmarke und Claim. Hintergrund Deep. Das M muss in kleinen Größen klar
erkennbar bleiben (Größenstufen in Kapitel 3). PNGs und `favicon.ico` neu erzeugen: `ddev exec node
scripts/render-icons.mjs` (rendert mit Chromium, weil ImageMagick die Kontur nicht darstellt).

**Offen:** Masterdatei 1024 × 1024 px für App-Stores, Monochrom-Varianten (dunkel, weiß), Social-Preview-Bild.

---

## 5. Farbwelt (geändert)

Die Startpalette des PDFs (Forest Green, Warm Cream, Fresh Orange, Sage) ist durch eine eigene Palette ersetzt.
Sie ist ruhig und kühl mit Grün-Blau als Marke und Creme als warmem Grund. **(neu)** Für Streetstyle und Wärme kommt
genau eine Signalfarbe dazu: Signal-Orange.

### Markenpalette

| Name | Hex | Token | Rolle |
|---|---|---|---|
| Ink | `#01161e` | `--mesia-ink` | Text (hell), Hintergrund (dunkel) |
| Deep | `#124559` | `--mesia-deep` | Primäre Markenfarbe: Links, Überschriften, Bildmarke im Kopf, Versatz des Stickers, App-Icon-Grund |
| Slate | `#598392` | `--mesia-slate` | Sekundär: Dekor, nicht für kleinen Text auf hellem Grund |
| Sage | `#aec3b0` | `--mesia-sage` | Akzentfarbe im Dark Mode (Links, Überschriften) |
| Cream | `#eff6e0` | `--mesia-cream` | Hintergrund (hell), Text (dunkel), Fläche der Sticker-Bildmarke |
| Signal-Orange | `#ff6a2b` | `--mesia-orange` | **(neu)** Aktionen (Knöpfe, Plus, Häkchen), Schüssel der Bildmarke, Merch |

### Rollen (Tokens in der App)

| Rolle | Hell | Dunkel |
|---|---|---|
| `--color-bg` Hintergrund | Cream | Ink |
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
| Erfolg | `#32845d` | `#32845d` | reserviert (Synchron nutzt derzeit die Akzentfarbe) |

### Einsatzprinzip

- Deep trägt die Marke (Links, Überschriften, Bildmarke), im Dark Mode übernimmt Sage.
- **Signal-Orange nur für Aktionen und die Schüssel**, sparsam: ein Primärknopf pro Ansicht, Plus, Häkchen.
  Darauf immer Ink (6,5:1), nie Weiß (2,9:1). Nicht für Text auf hellem Grund (2,6:1).
- Slate und Sage sind Akzente, nicht für Fließtext auf hellem Grund.
- Cream bringt Wärme in die Flächen, Weiß hebt Karten davon ab.

---

## 6. Typografie

UI-Schrift: **Manrope** (variabel, Schnitte 200–800), Alternative Inter. **(neu)** Wortmarke und Merch:
**Archivo Black**. Beide selbst ausgeliefert aus `assets/fonts/` (Lizenz OFL-1.1, Quelle npm `@fontsource`), kein
Abruf bei Google: Datenschutz und offline verfügbar. Archivo Black nur für „MESIA“ und Merch, nie für Fließtext.

### Skala in der App (geändert: kompakter als im PDF)

| Stil | Größe | Schnitt | Einsatz |
|---|---|---|---|
| Wortmarke „MESIA“ | 44 px | Archivo Black | Login |
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
| Primärknopf | Signal-Orange, Schrift Ink, 44 px hoch, Radius 10 |
| Destruktiver Knopf | Fehlerfarbe, bei kritischen Aktionen Rückfrage (Kategorie löschen) |
| Listengruppe | weiße Karte (Radius 14), Zeilen 44 px, Trennlinie ab der Textspalte |
| Kategorie-Überschrift | Emoji + Name, 16 px fett in Akzentfarbe, 6 px über der Gruppe |
| Eingabeleiste | unten fixiert, über der Tastatur, Feld rund, Plus-Knopf rund in Signal-Orange (Plus in Ink) |
| Abhaken | Kreis füllt sich in Signal-Orange mit Häkchen in Ink, Zeile blendet aus, dann „Erledigt“ |
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
| Weiß auf Deep | 10,4:1 | ✓ |
| Ink auf Signal-Orange (Primärknopf, Häkchen) | 6,5:1 | ✓ |
| Weiß auf Signal-Orange | 2,9:1 | ✗, deshalb nicht verwenden |
| Signal-Orange auf Cream (als Text) | 2,6:1 | ✗, nur Flächen und Grafik |
| Creme auf Deep (App-Icon, Sticker) | 9,4:1 | ✓ |
| Fehlerfarbe auf Weiß, Weiß auf Fehlerfarbe | 4,6:1 | ✓ |
| Dunkel: Cream auf Ink / auf Surface | 16,7:1 / 14,2:1 | ✓ |
| Dunkel: Sage auf Ink / auf Surface | 9,9:1 / 8,4:1 | ✓ |
| Dunkel: Ink auf Sage | 9,9:1 | ✓ |
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
    --mesia-orange: #ff6a2b;

    --color-bg: var(--mesia-cream);
    --color-surface: #ffffff;
    --color-text: var(--mesia-ink);
    --color-text-muted: #4a6b78;
    --color-accent: var(--mesia-deep);
    --color-accent-contrast: #ffffff;
    --color-action: var(--mesia-orange);
    --color-action-contrast: var(--mesia-ink);
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
| – | Streetstyle: Signal-Orange, Sticker-Bildmarke, Archivo Black | erledigt (1.3) |
