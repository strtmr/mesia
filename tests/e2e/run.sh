#!/bin/bash
# Rauchtest der Einkaufsliste im Browser, gegen die laufende Dev-App.
# Legt dafür einen frischen Haushalt "E2E-Test" in der Dev-Datenbank an (vorherigen Lauf vorher entfernen).
#
# Einmalig: ddev exec 'sudo npx playwright install-deps chromium && npx playwright install chromium --only-shell'
# Aufruf:   ddev exec bash tests/e2e/run.sh     → Screenshots in var/e2e/
set -e
mkdir -p var/e2e
rm -f var/e2e/*.png
psql -h db -U db -d db -q -v ON_ERROR_STOP=1 -f tests/e2e/reset-household.sql
ID=$(bin/console app:household:create "E2E-Test" | grep -o 'ID [0-9a-f-]*' | cut -d' ' -f2)
printf 'e2e-passwort-123\ne2e-passwort-123\n' | bin/console app:user:create e2e@example.test E2E --household="$ID" >/dev/null
node tests/e2e/smoke.mjs e2e@example.test e2e-passwort-123 var/e2e
