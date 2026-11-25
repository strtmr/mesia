# Domain-Doku

Wie die Engineering-Skills die fachliche Dokumentation dieses Repos beim Erkunden lesen.

## Vor dem Erkunden lesen

- **`CONTEXT.md`** im Wurzelverzeichnis (Glossar).
- **`docs/adr/`**: ADRs, die den Bereich betreffen, in dem gearbeitet wird.
- **Bis es ADRs gibt (und daneben):** [`docs/architektur.md`](../architektur.md). Kapitel 0 enthält die getroffenen
  Entscheidungen und hat Vorrang vor den übrigen Kapiteln. Dazu die Spezifikation der aktuellen Phase
  (`docs/spezifikation-phase-*.md`) und [`docs/entwicklung.md`](../entwicklung.md).

Fehlen `CONTEXT.md` oder `docs/adr/`, **ohne Hinweis weitermachen**. Nicht auf das Fehlen hinweisen und nicht vorab
anlegen. Der Skill `/domain-modeling` (über `/grill-with-docs` und `/improve-codebase-architecture`) legt sie an, sobald
Begriffe oder Entscheidungen tatsächlich geklärt werden.

## Dateistruktur

Single-context:

```
/
├── CONTEXT.md
├── docs/adr/
│   ├── 0001-...md
│   └── 0002-...md
└── src/
```

## Vokabular des Glossars verwenden

Wenn eine Ausgabe einen fachlichen Begriff nennt (Ticket-Titel, Refactoring-Vorschlag, Hypothese, Testname), gilt der
Begriff aus `CONTEXT.md` bzw. aus der Architektur- und Spezifikationsdoku (z. B. Artikel, Beitrag, Eintrag, Haushalt,
Gericht, Rezept, Zutatenzeile). Keine Synonyme verwenden, die das Glossar vermeidet. Fachbegriffe auf Deutsch,
Code-Bezeichner auf Englisch.

Fehlt ein Begriff im Glossar, ist das ein Signal: Entweder wird Sprache erfunden, die das Projekt nicht nutzt
(überdenken), oder es gibt eine echte Lücke (für `/domain-modeling` notieren).

## Widersprüche offen melden

Widerspricht eine Ausgabe einem ADR oder einer Entscheidung aus Kapitel 0 der Architekturdoku, das ausdrücklich
benennen statt stillschweigend abzuweichen:

> _Widerspricht ADR-0007 (bzw. Architektur 0.1 „private zuerst“), lohnt sich aber, weil …_
