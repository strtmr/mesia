# Benutzerhandbuch

Mesuna (bis Version 0.10 „Mesia“) ist eine gemeinsame Einkaufsliste für euren Haushalt, später auch für die Essensplanung. Die App läuft im
Browser und lässt sich auf dem iPhone wie eine normale App installieren.

> **Stand:** Die gemeinsame Einkaufsliste funktioniert, auch ohne Verbindung, und die Kategorien lassen sich verwalten.

## Anmelden

Konten werden vom Administrator angelegt (eine Selbstregistrierung gibt es nicht). Du meldest dich mit E-Mail-Adresse
und Passwort an. Groß- und Kleinschreibung der E-Mail-Adresse spielt keine Rolle.

- Du bleibst auf dem Gerät **ein Jahr lang angemeldet**, auch wenn du die App schließt.
- Nach 5 falschen Passwörtern ist die Anmeldung für 15 Minuten gesperrt.
- **Name oder Passwort ändern:** Tab „Mehr“ → Bereich „Konto“ → „Name“ bzw. „Passwort“. Für ein neues Passwort
  gibst du das aktuelle einmal ein. Auf diesem Gerät bleibst du angemeldet, auf allen anderen Geräten musst du dich
  mit dem neuen Passwort neu anmelden.
- **Passwort vergessen?** Der Administrator setzt es neu. Danach bist du auf allen Geräten abgemeldet.
- **Abmelden:** Tab „Mehr“ → „Abmelden“.

## Auf dem iPhone installieren

1. Die Adresse der App in **Safari** öffnen und anmelden.
2. Unten auf **Teilen** tippen (Quadrat mit Pfeil nach oben).
3. **„Zum Home-Bildschirm“** wählen und bestätigen.

Danach startet die App vom Home-Bildschirm ohne Browserleiste. Wichtig: Die installierte App hat einen eigenen
Speicher, du musst dich dort **einmal neu anmelden** und die Einkaufsliste einmal mit Verbindung öffnen. Danach
startet sie auch im Funkloch. iOS bewahrt die Daten installierter Apps zuverlässiger auf als die von Safari.

Beim Start zeigt die App kurz das Mesuna-Logo auf hellem Grund (im dunklen Modus auf dunklem Grund). Siehst du
stattdessen einen weißen Bildschirm, wurde die App vor Version 0.10.1 installiert: App vom Home-Bildschirm löschen
und wie oben neu hinzufügen. Die Einkaufsliste bleibt erhalten, sie liegt auf dem Server. Genauso zeigt eine vor
Version 0.11.0 installierte App noch das alte Symbol mit dem dicken Sticker-M, bis du sie neu hinzufügst.

## Bereiche

| Tab | Inhalt |
|-----|--------|
| **Einkauf** | eure gemeinsame Einkaufsliste |
| **Mehr** | Konto (Name und Passwort ändern), Haushalt, Kategorien, Läden, Mitteilungen, Hilfe, App-Version mit **Neuigkeiten** (was sich in welcher Version geändert hat), Verbindungsstatus, Abmelden |

## Einkaufsliste

**Hinzufügen:** Unten ins Feld tippen und mit Return bestätigen. Das Feld bleibt offen, du kannst gleich den nächsten
Artikel eingeben. Mengen erkennt die App automatisch:

| Eingabe | wird zu |
|---------|---------|
| `Milch` | Milch |
| `2 l Milch`, `2l Milch`, `Milch 2l` | Milch · 2 l |
| `3 Zwiebeln`, `3 Zwiebel` | Zwiebeln · 3 Stk |
| `500g Hack` | Hackfleisch · 500 g |
| `Butter (die gute)` | Butter mit Notiz „die gute“ |
| `2x500g Mehl`, `2 x 500 g Mehl` | Mehl · 1.000 g |
| `3+2 Äpfel`, `3x Äpfel` | Äpfel · 5 Stk bzw. 3 Stk |
| `1/2 Zitrone` | Zitrone · 0,5 Stk |
| `Bier @Edeka` | Bier, nur bei Edeka |
| `Kaffee @Lidl bis Sa` | Kaffee bei Lidl, Angebot bis Samstag |

Mengen darf man ausrechnen lassen: `+`, `-`, `x` bzw. `*` und `/` bzw. `:` (Punkt- vor Strichrechnung). Erlaubt
sind Mengen bis 99.999. Ist eine Menge unplausibel, erscheint ein Hinweis und der Text bleibt zum Korrigieren stehen.

Ein Artikelname darf höchstens 100 Zeichen lang sein (eine Notiz in Klammern 200). Ab 90 Zeichen zeigt die Eingabe
„Noch … Zeichen“, darüber wird der Hinweis rot und Hinzufügen ist gesperrt.

- Artikel werden **automatisch einsortiert** (Obst & Gemüse, Milchprodukte …). Unbekanntes landet in „Sonstiges“.
  Der Katalog kennt auch laktosefreie und vegane Produkte (z. B. „Laktosefreie Milch“, „Hafersahne“, „Veggie-Hack“).
- Beim Tippen erscheinen **Vorschläge**, zuerst Dinge, die ihr schon gekauft habt. Tippfehler werden verziehen.
- Steht ein Artikel schon auf der Liste, kommt die Menge dazu statt eines zweiten Eintrags.

**Abhaken:** auf den Eintrag tippen, auf den Kreis oder den Namen (nur „›“ ganz rechts öffnet das Bearbeiten-Fenster).
Der Artikel bekommt ein Häkchen und wird durchgestrichen, bleibt aber an seinem Platz. So kannst du mehrere
hintereinander abhaken, ohne dass die Liste verrutscht. Erst wenn du die Liste etwa 2,5 Sekunden nicht berührst
(weder tippst noch scrollst), schrumpfen alle abgehakten Artikel weg und wandern gemeinsam nach **„Erledigt“**
(unten, aufklappbar). Auf dem Handy sind die Zeilen etwas höher als am Computer, damit du sicher triffst.
Versehentlich abgehakt? Solange der Artikel noch durchgestrichen dasteht, einfach noch einmal darauf tippen.
Danach in „Erledigt“ auf seinen Kreis tippen, dann kommt er wieder auf die Liste.

**„Erledigt“** ist euer Vorrat an schon Gekauftem: Jeder Artikel steht dort einmal, **oft Gekauftes oben** (bei
gleich vielen Käufen das zuletzt Gekaufte). Ein Tipp auf den Kreis setzt ihn wieder auf die Liste. Ein Tipp auf den
Namen öffnet das Bearbeiten-Fenster: Dort steht, wer ihn wann erledigt und hinzugefügt hat, und „Wieder auf die
Liste“ holt ihn ebenfalls zurück.

**Aus „Erledigt“ entfernen:** nach links wischen und „Entfernen“ tippen, gedacht für Einmalkäufe wie
„Geschenkpapier“. Der Artikel bleibt im Katalog und wird beim nächsten Eintippen wieder erkannt, er steht nur nicht
mehr in „Erledigt“.

**Von der Liste entfernen, ohne abzuhaken:** nach links wischen und „Entfernen“ tippen, z. B. wenn ihr etwas doch
nicht braucht oder es doppelt eingetragen ist. Der Artikel landet nicht in „Erledigt“ und zählt nicht als gekauft.
Ist eine Zeile aufgewischt, schiebt ein Tipp auf sie sie wieder zurück (ohne abzuhaken).

**Bearbeiten:** rechts in der Zeile auf „›“ tippen. Dort änderst du Name, Menge, Einheit, Kategorie und Notiz oder löschst den Artikel.
Unten steht, wer den Artikel wann auf die Liste gesetzt hat (z. B. „Hinzugefügt von Anna heute um 9:05“), bei
abgehakten Artikeln auch, wer ihn wann erledigt hat.
Änderst du die Kategorie, merkt sich die App das für das nächste Mal.

**Umsortieren:** einen Artikel lange gedrückt halten und ziehen, auch in eine andere Kategorie.

**Abgleich zwischen Geräten:** Oben rechts siehst du den Status:

| Anzeige | Bedeutung |
|---------|-----------|
| ● Synchron | alles übertragen |
| ● n ausstehend | Änderungen werden gleich übertragen |
| ● Offline | keine Verbindung. Du kannst normal weiterarbeiten, alles wird später übertragen |
| ● Abgemeldet | bitte neu anmelden, deine Änderungen bleiben erhalten |

Die Liste gleicht sich alle paar Sekunden ab, solange sie geöffnet ist, und sofort beim Öffnen der App.
Ein kleiner gelber Punkt neben einem Artikel heißt: Diese Änderung ist noch nicht übertragen.

**Ohne Verbindung** (Keller, Supermarkt, Flugmodus) startet die Einkaufsliste trotzdem und du kannst ganz normal
hinzufügen, abhaken und bearbeiten. Alles wird übertragen, sobald wieder Netz da ist. Der Tab „Mehr“ braucht eine
Verbindung.

**Neue Version:** Erscheint oben „Neue Version verfügbar“, tippe auf „Neu laden“. Dabei geht nichts verloren.

**Abmelden** löscht die Liste von diesem Gerät. Noch nicht übertragene Änderungen gehen dabei verloren.

## Läden und Angebote

Manches kauft man nur in einem bestimmten Laden („Bier bei Edeka im Angebot“). Dafür lassen sich Artikel einem oder
mehreren Läden zuordnen und mit einem Datum „gültig bis“ versehen.

- **Schnelleingabe:** `@` vor dem Laden, `bis` vor dem Datum, z. B. `6 Bier @Edeka bis Sa`. Als Datum gehen
  `heute`, `morgen`, `übermorgen`, Wochentage (`Sa`, `Samstag`, der nächste dieses Namens) und `28.11.`. Nach dem
  `@` schlägt die App passende Läden vor, antippen übernimmt ihn. Ein eindeutiger Anfang reicht auch so (`@ross` für
  Rossmann). Gibt es den Laden nicht, erscheint ein Hinweis, und nichts wird angelegt.
- **Bearbeiten-Fenster:** Unter „Läden“ ins Feld „Laden hinzufügen …“ tippen, passende Läden erscheinen darunter.
  Antippen oder Return übernimmt, mehrere sind möglich. Ein Tipp auf einen gewählten
  Laden entfernt ihn. „Gültig bis“ per Datumsauswahl, × entfernt das Datum.
- **Auf der Liste** steht unter dem Artikel der Laden und das Datum: normal „bis Sa., 28.11.“, am letzten Tag **grün
  „heute“**, danach **rot „abgelaufen“**.
- **Filter:** Sobald Artikeln ein Laden zugeordnet ist, erscheinen oben Knöpfe mit den Läden. „Edeka“ zeigt nur noch
  Edeka-Artikel, ein Hinweis nennt, wie viele ausgeblendet sind. „Alle“ (oder Edeka noch einmal antippen) zeigt
  wieder alles. Das Gerät merkt sich den Filter.
- **Wieder auf die Liste:** Der Laden bleibt (Gewohnheit), das Datum fällt weg (das Angebot galt für das letzte Mal).
- **Läden verwalten:** Unter **Mehr → Läden** stehen rund 45 Märkte: Discounter, Supermärkte, Bio, Drogerien,
  Getränkemärkte, Lieferdienste und Allgemeines wie Wochenmarkt, Bäckerei oder Hofladen. Eigene lassen sich
  ergänzen, alle umbenennen oder löschen. Gelöschte kommen nicht wieder.

## Kategorien

Unter **Mehr → Kategorien** stellst du ein, in welcher Reihenfolge die Kategorien auf der Liste erscheinen. Am besten
so, wie du durch deinen Laden gehst.

- **Sortieren:** am Griff (☰) links ziehen.
- **Umbenennen oder Symbol ändern:** Kategorie antippen. Als Symbol eignet sich ein Emoji.
- **Neue Kategorie:** unten auf „Neue Kategorie“. Sie erscheint vor „Sonstiges“.
- **Löschen:** Kategorie antippen → „Kategorie löschen“. Ihre Artikel wandern nach „Sonstiges“. „Sonstiges“ selbst
  lässt sich nicht löschen.

Die Seite braucht eine Internetverbindung. Die Einkaufsliste übernimmt Änderungen beim nächsten Abgleich, auch auf
dem anderen Gerät.

## Mitteilungen

Unter **Mehr → Mitteilungen** schaltest du ein, dass dein Gerät Bescheid gibt, wenn **jemand anderes** etwas auf
die Liste setzt. Was du selbst einträgst, meldet Mesuna dir nicht. Beim ersten Einschalten fragt das Gerät, ob Mesuna
Mitteilungen senden darf: mit „Erlauben“ bestätigen.

- **Pro Gerät:** Die Einstellung gilt nur für das Gerät, auf dem du sie triffst. Handy und Tablet schaltest du
  getrennt ein.
- **Häufigkeit:** „Sofort“ meldet sich innerhalb weniger Minuten. „Alle 15 Minuten“ sammelt und schickt
  eine Nachricht für alles, was in der Zwischenzeit dazugekommen ist, z. B. „Anna hat Milch, Brot und 2 weitere auf
  die Liste gesetzt.“
- **Antippen** der Mitteilung öffnet die Einkaufsliste.
- **iPhone:** Mitteilungen gibt es nur in der **installierten App** (siehe „Auf dem iPhone installieren“), nicht in
  Safari. Voraussetzung ist iOS 16.4 oder neuer.
- **Ausschalten:** Schalter wieder aus. Wurden Mitteilungen einmal abgelehnt, lassen sie sich nur in den
  Einstellungen des Geräts wieder erlauben (iPhone: Einstellungen → Mitteilungen → Mesuna).
