#!/bin/sh
# Hosting-Check (Paket 0.1): zeigt, was auf dem Webhosting vorhanden ist. Ändert nichts.
#
# Von WSL aus aufrufen (Port 222 bei Hetzner Webhosting):
#   ssh -p 222 <benutzer>@<host> 'sh -s' < deploy/hosting-check.sh
#
# Liegt schon <basis>/shared/.env.local mit DATABASE_URL, wird auch PostgreSQL geprüft:
#   ssh -p 222 <benutzer>@<host> 'sh -s -- ~/public_html/projects/mesia' < deploy/hosting-check.sh
BASE=${1:-}

section() { printf '\n== %s ==\n' "$1"; }

section "System"
echo "Benutzer: $(whoami)   Home: $HOME   Shell: $SHELL"
uname -sr
for tool in tar gzip rsync git composer curl crontab psql; do
    printf '%-10s %s\n' "$tool" "$(command -v "$tool" || echo '–')"
done

section "PHP-Binaries"
for candidate in php php84 php85 php8.4 php8.5 /usr/bin/php* /usr/local/bin/php* /opt/php*/bin/php; do
    path=$(command -v "$candidate" 2>/dev/null) || continue
    [ -x "$path" ] || continue
    printf '%-28s %s\n' "$path" "$("$path" -r 'echo PHP_VERSION;' 2>/dev/null)"
done | sort -u

section "Standard-PHP (php)"
php -r '
$need = ["intl", "pdo_pgsql", "ctype", "iconv", "mbstring", "xml", "sodium", "Zend OPcache", "apcu"];
foreach ($need as $ext) { printf("%-14s %s\n", $ext, extension_loaded($ext) ? "ok" : "FEHLT"); }
foreach (["memory_limit", "max_execution_time", "realpath_cache_ttl", "date.timezone", "session.save_path"] as $key) {
    printf("%-20s %s\n", $key, ini_get($key));
}
'

if [ -n "$BASE" ] && [ -f "$BASE/shared/.env.local" ]; then
    section "PostgreSQL (aus $BASE/shared/.env.local)"
    php -r '
    foreach (file($argv[1]) as $line) {
        if (preg_match("/^DATABASE_URL=\"?([^\"\n]+)/", $line, $m)) { $url = parse_url($m[1]); }
    }
    if (!isset($url)) { exit("DATABASE_URL nicht gefunden\n"); }
    $dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s", $url["host"], $url["port"] ?? 5432, ltrim($url["path"], "/"));
    $pdo = new PDO($dsn, urldecode($url["user"]), urldecode($url["pass"] ?? ""));
    echo "Version: ", $pdo->query("SHOW server_version")->fetchColumn(), "\n";
    echo "Erweiterungen (Auswahl): ";
    $rows = $pdo->query("SELECT name FROM pg_available_extensions WHERE name IN (\x27pg_trgm\x27, \x27vector\x27, \x27unaccent\x27, \x27pgcrypto\x27) ORDER BY name");
    echo implode(", ", $rows->fetchAll(PDO::FETCH_COLUMN)) ?: "keine", "\n";
    ' "$BASE/shared/.env.local"
fi
