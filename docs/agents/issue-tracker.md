# Issue-Tracker: GitHub

Tickets und Spezifikationen für dieses Repo liegen als GitHub Issues in `strtmr/mesuna`. Alle Operationen laufen über
die `gh`-CLI.

## Projektregeln

- Titel und Text auf Deutsch, mit dem Vokabular des Projekts (Artikel, Beitrag, Eintrag, Haushalt, Gericht, Rezept …).
- Keine Dateipfade oder Code-Schnipsel im Ticket, sie veralten schnell. Ausnahme: ein Prototyp hat eine Entscheidung
  (Zustandsmodell, Schema) genauer festgelegt, als Prosa es kann.

## Konventionen

- **Issue anlegen**: `gh issue create --title "..." --body "..."`. Mehrzeiligen Text per Heredoc übergeben.
- **Issue lesen**: `gh issue view <nummer> --comments`, Kommentare mit `jq` filtern, Labels mit abrufen.
- **Issues auflisten**: `gh issue list --state open --json number,title,body,labels,comments --jq '[.[] | {number, title, body, labels: [.labels[].name], comments: [.comments[].body]}]'` mit passenden `--label`- und `--state`-Filtern.
- **Kommentieren**: `gh issue comment <nummer> --body "..."`
- **Labels setzen / entfernen**: `gh issue edit <nummer> --add-label "..."` / `--remove-label "..."`
- **Schließen**: `gh issue close <nummer> --comment "..."`

Das Repo ergibt sich aus `git remote -v`. `gh` erkennt es automatisch, wenn es im Klon läuft.

## Pull Requests als Triage-Quelle

**PRs as a request surface: no.** _(Auf `yes` setzen, wenn externe PRs als Feature-Wünsche gelten. `/triage` liest
diesen Schalter.)_

Bei `yes` durchlaufen PRs dieselben Labels und Zustände wie Issues, mit den `gh pr`-Gegenstücken:

- **PR lesen**: `gh pr view <nummer> --comments` und `gh pr diff <nummer>` für den Diff.
- **Externe PRs zur Triage auflisten**: `gh pr list --state open --json number,title,body,labels,author,authorAssociation,comments`, dann nur `authorAssociation` `CONTRIBUTOR`, `FIRST_TIME_CONTRIBUTOR` oder `NONE` behalten (`OWNER`/`MEMBER`/`COLLABORATOR` verwerfen).
- **Kommentieren / Labels / Schließen**: `gh pr comment`, `gh pr edit --add-label`/`--remove-label`, `gh pr close`.

Issues und PRs teilen sich bei GitHub einen Nummernraum. Ein nacktes `#42` kann beides sein: mit `gh pr view 42`
auflösen, ersatzweise `gh issue view 42`.

## Wenn ein Skill „im Issue-Tracker veröffentlichen“ sagt

Ein GitHub Issue anlegen.

## Wenn ein Skill „das zugehörige Ticket holen“ sagt

`gh issue view <nummer> --comments` ausführen.

## Abhängigkeiten („Blocked by“)

GitHubs **native Issue-Abhängigkeiten** nutzen, sie sind in der Oberfläche sichtbar. Kante anlegen mit
`gh api --method POST repos/<owner>/<repo>/issues/<kind>/dependencies/blocked_by -F issue_id=<blocker-db-id>`.
`<blocker-db-id>` ist die numerische **Datenbank-ID** des blockierenden Issues
(`gh api repos/<owner>/<repo>/issues/<n> --jq .id`), _nicht_ die `#nummer` und nicht die `node_id`. Zusätzlich steht
im Issue-Text ein Abschnitt „Blocked by“ mit `#<n>`. Ein Ticket ist frei, wenn alle blockierenden Issues geschlossen sind.

## Wayfinding

Genutzt von `/wayfinder`. Die **Karte** ist ein einzelnes Issue, die Tickets sind **Kind**-Issues.

- **Karte**: ein Issue mit Label `wayfinder:map` und den Abschnitten Notes / Decisions-so-far / Fog. `gh issue create --label wayfinder:map`.
- **Kind-Ticket**: als GitHub-Sub-Issue mit der Karte verknüpft (`gh api` auf den Sub-Issues-Endpunkt). Wo Sub-Issues nicht verfügbar sind: Kind in eine Aufgabenliste im Text der Karte aufnehmen und `Part of #<karte>` oben in den Text des Kindes schreiben. Labels: `wayfinder:<typ>` (`research`/`prototype`/`grilling`/`task`). Wer ein Ticket übernimmt, wird zugewiesen.
- **Blockieren**: wie unter „Abhängigkeiten“. `issue_dependencies_summary.blocked_by` zählt nur offene Blocker. Ohne Abhängigkeiten: Zeile `Blocked by: #<n>, #<n>` oben im Text.
- **Frontier-Abfrage**: offene Kinder der Karte auflisten (`gh issue list --state open`, beschränkt auf Sub-Issues bzw. Aufgabenliste), alle mit offenem Blocker oder Zuweisung verwerfen. Das erste in Kartenreihenfolge gewinnt.
- **Übernehmen**: `gh issue edit <n> --add-assignee @me`, der erste Schreibvorgang der Sitzung.
- **Erledigen**: `gh issue comment <n> --body "<antwort>"`, dann `gh issue close <n>`, dann einen Verweis (Kern + Link) unter Decisions-so-far in der Karte ergänzen.
