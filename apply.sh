#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/var/www/vmdockerwebdkatropim.westeurope.cloudapp.azure.com
WEB=atropim-web-1

exec_in() { docker exec -i "$WEB" sh -lc "$1"; }

# Vælg CLI entrypoint (foretræk console.php)
CLI="console.php"
exec_in "[ -f \"$APP_DIR/$CLI\" ] || { [ -f \"$APP_DIR/index.php\" ] && echo 'using index.php' && ln -sf index.php $APP_DIR/$CLI || true; }"

# 1) (kun når PHP-klasser ændres)
exec_in "cd \"$APP_DIR\" && [ -f composer.phar ] && php composer.phar dump-autoload -n || true"

# 2) Ryd systemcache (erstatter run-postupdate.php)
exec_in "cd \"$APP_DIR\" && php $CLI clear cache || true"

# (valgfrit) opdatér oversættelser
exec_in "cd \"$APP_DIR\" && php $CLI refresh translations || true"  # :contentReference[oaicite:4]{index=4}

# 3) Synk DB til metadata
exec_in "cd \"$APP_DIR\" && php $CLI sql diff --show || true"
exec_in "cd \"$APP_DIR\" && php $CLI sql diff --run"

# 4) (valgfrit) trig cron én gang
exec_in "cd \"$APP_DIR\" && php $CLI cron || true"
