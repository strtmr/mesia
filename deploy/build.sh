#!/usr/bin/env bash
# Baut ein Release-Verzeichnis für die Produktion (ohne Entwicklerwerkzeuge, Assets kompiliert).
#
#   deploy/build.sh <zielverzeichnis>
#
# Läuft in GitHub Actions (PHP 8.5 + Composer). Lokal: ddev exec deploy/build.sh var/build
# Gebaut wird der Stand von HEAD (git archive), nicht committete Änderungen landen also nie auf dem Server.
# Der Symfony-Cache wird erst auf dem Server erzeugt (er enthält absolute Pfade).
set -euo pipefail

TARGET=${1:?Zielverzeichnis fehlt}

rm -rf "$TARGET"
mkdir -p "$TARGET"
git archive --format=tar HEAD | tar -x -C "$TARGET"   # lässt die export-ignore-Pfade aus .gitattributes weg
git rev-parse HEAD > "$TARGET/REVISION"

cd "$TARGET"
export APP_ENV=prod APP_DEBUG=0

composer install --no-dev --no-scripts --classmap-authoritative --no-interaction --no-progress
php bin/console importmap:install
php bin/console asset-map:compile

rm -rf var
echo "Release gebaut: $TARGET ($(du -sh . | cut -f1))"
