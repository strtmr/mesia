#!/usr/bin/env bash
# Überträgt ein mit deploy/build.sh gebautes Release auf den Server und schaltet es live.
#
#   deploy/release.sh <build-verzeichnis>
#
# Umgebungsvariablen (in GitHub Actions aus den Variablen der Umgebung "production"):
#   DEPLOY_HOST, DEPLOY_USER   SSH-Zugang (Schlüssel muss im ssh-agent bzw. in ~/.ssh liegen)
#   DEPLOY_PORT                SSH-Port (Hetzner Webhosting: 222)
#   DEPLOY_PATH                Basisverzeichnis auf dem Server, z. B. /usr/home/<benutzer>/mealplanner
#   DEPLOY_PHP                 PHP-CLI auf dem Server (Standard: php), Version muss zur Web-PHP-Version passen
#   APP_URL                    optional: https://… für den Test nach dem Live-Schalten (sonst Rollback)
set -euo pipefail

BUILD=${1:?Build-Verzeichnis fehlt}
: "${DEPLOY_HOST:?}" "${DEPLOY_USER:?}" "${DEPLOY_PATH:?}"
PORT=${DEPLOY_PORT:-22}
PHP=${DEPLOY_PHP:-php}
RELEASE="$(date -u +%Y%m%d%H%M%S)-$(cut -c1-7 "$BUILD/REVISION")"
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)

ssh_run() {
    ssh -p "$PORT" -o BatchMode=yes "$DEPLOY_USER@$DEPLOY_HOST" "$@"
}

echo "→ Hochladen: $RELEASE"
tar -czf - -C "$BUILD" . | ssh_run "mkdir -p '$DEPLOY_PATH/releases/$RELEASE' && tar -xzf - -C '$DEPLOY_PATH/releases/$RELEASE'"

echo "→ Aktivieren"
ssh_run "sh -s activate '$DEPLOY_PATH' '$RELEASE' '$PHP'" < "$SCRIPT_DIR/remote.sh"

if [[ -n "${APP_URL:-}" ]]; then
    echo "→ Prüfen: $APP_URL/login"
    sleep 2
    if ! curl -fsS -o /dev/null --max-time 20 "$APP_URL/login"; then
        echo "✗ Die neue Version antwortet nicht. Rollback auf die vorherige." >&2
        ssh_run "sh -s rollback '$DEPLOY_PATH'" < "$SCRIPT_DIR/remote.sh"
        exit 1
    fi
fi
echo "✓ Deployment fertig: $RELEASE"
