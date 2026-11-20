#!/bin/sh
# Läuft AUF DEM SERVER (per SSH von deploy/release.sh übertragen). Bewusst POSIX-sh, damit es unabhängig von der
# Shell des Webhostings funktioniert.
#
#   sh remote.sh activate <basis> <release> <php>   Release einrichten, migrieren, live schalten, alte aufräumen
#   sh remote.sh rollback <basis>                   zurück auf das vorherige Release (Datenbank bleibt, wie sie ist)
#
# Aufbau auf dem Server:
#   <basis>/releases/<release>/   je Deployment ein vollständiges Verzeichnis
#   <basis>/current -> releases/… Document Root der Subdomain zeigt auf <basis>/current/public
#   <basis>/shared/.env.local     Zugangsdaten (APP_ENV=prod, APP_SECRET, DATABASE_URL), einmalig von Hand angelegt
#   <basis>/shared/log/           Logdateien, bleiben über Deployments hinweg erhalten
set -eu

KEEP_RELEASES=5

command=${1:?Befehl fehlt}
base=${2:?Basisverzeichnis fehlt}

fail() {
    echo "FEHLER: $*" >&2
    exit 1
}

# <basis> liegt evtl. im Web-Verzeichnis einer anderen Domain (Hetzner: ~/public_html/…). Alles darunter sperren,
# public/.htaccess des Releases gibt nur den eigenen Ordner wieder frei.
protect_base() {
    cat > "$1/.htaccess" <<'HTACCESS'
# Verwaltet von deploy/remote.sh: kein Zugriff auf Releases, Zugangsdaten und Logs über eine andere Domain.
# Die App selbst gibt in public/.htaccess ihren Ordner wieder frei.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
HTACCESS
}

case "$command" in
activate)
    release=${3:?Release fehlt}
    php=${4:-php}
    dir="$base/releases/$release"

    [ -d "$dir" ] || fail "$dir existiert nicht"
    [ -f "$base/shared/.env.local" ] || fail "$base/shared/.env.local fehlt (siehe docs/betrieb.md)"
    grep -q '^APP_ENV=prod' "$base/shared/.env.local" || fail "APP_ENV=prod fehlt in $base/shared/.env.local"
    mkdir -p "$base/shared/log"
    protect_base "$base"

    ln -sfn "$base/shared/.env.local" "$dir/.env.local"
    mkdir -p "$dir/var"
    ln -sfn "$base/shared/log" "$dir/var/log"

    cd "$dir"
    echo "→ PHP: $($php -r 'echo PHP_VERSION;')"
    echo "→ Cache aufwärmen"
    $php bin/console cache:warmup --no-interaction
    echo "→ Datenbank migrieren"
    $php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    echo "→ Katalog-Abgleich (neue Einträge des Startkatalogs übernehmen)"
    $php bin/console app:catalog:update --no-interaction

    echo "→ Live schalten: $release"
    cd "$base"
    # Beim Einrichten wird current/public oft als leerer Ordner angelegt (die Hetzner-Verwaltung verlangt ein
    # vorhandenes Document Root). Der Ordner muss dem Symlink weichen, aber nur wenn er leer ist.
    if [ -d current ] && [ ! -L current ]; then
        rmdir current/public 2>/dev/null || true
        rmdir current || fail "$base/current ist ein Ordner mit Inhalt, bitte prüfen und entfernen"
    fi
    [ -L current ] && ln -sfn "$(readlink current)" previous
    ln -sfn "releases/$release" current

    echo "→ Alte Releases aufräumen (behalte $KEEP_RELEASES)"
    current_release=$(basename "$(readlink current)")
    previous_release=$( [ -L previous ] && basename "$(readlink previous)" || echo "")
    ls -1 releases | sort -r | tail -n +$((KEEP_RELEASES + 1)) | while read -r old; do
        if [ "$old" != "$current_release" ] && [ "$old" != "$previous_release" ]; then
            rm -rf "releases/$old"
        fi
    done
    echo "✓ Live: $release"
    ;;
rollback)
    cd "$base"
    [ -L previous ] || fail "kein vorheriges Release vorhanden"
    target=$(readlink previous)
    ln -sfn "$(readlink current)" previous
    ln -sfn "$target" current
    echo "✓ Zurückgerollt auf $(basename "$target")"
    ;;
*)
    fail "Unbekannter Befehl: $command"
    ;;
esac
