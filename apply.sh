#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/var/www/vmdockerwebdkatropim.westeurope.cloudapp.azure.com
WEB=atropim-web-1

# [NYT] Sti til din modulmappe på værten og "global" composer.json
HOST_MODULE="${HOST_MODULE:-$HOME/dev/Atro.BEModule}"
HOST_COMPOSER="$HOST_MODULE/atro_composer.json"

exec_in() { docker exec -u www-data -i "$WEB" sh -lc "$1"; }

# [NYT] 0) Kopiér atro_composer.json ind som composer.json (hvis findes)
if [ -f "$HOST_COMPOSER" ]; then
  echo "[i] Kopierer $HOST_COMPOSER til containeren som composer.json"
  exec_in "cd \"$APP_DIR\" && [ -f composer.json ] && cp composer.json composer.json.bak || true && cat > composer.json" < "$HOST_COMPOSER"
fi
# Sørg for at cache-mappen har de rigtige rettigheder/ejerskab
exec_in "chown www-data:www-data -R \"$APP_DIR/data/cache\" && chmod -R 775 \"$APP_DIR/data/cache\" || true"

# Vælg CLI entrypoint (foretræk console.php)
CLI="console.php"
exec_in "[ -f \"$APP_DIR/$CLI\" ] || { [ -f \"$APP_DIR/index.php\" ] && echo 'using index.php' && ln -sf index.php $APP_DIR/$CLI || true; }"

# 1) (kun når PHP-klasser ændres)
exec_in "cd \"$APP_DIR\" && [ -f composer.phar ] && php composer.phar dump-autoload -n || true"

# [NYT] Kør composer update hvis composer.phar findes (så ny composer.json tages i brug)
exec_in "cd \"$APP_DIR\" && [ -f composer.phar ] && php composer.phar update -n || true"

# 2) Ryd systemcache (erstatter run-postupdate.php)
exec_in "cd \"$APP_DIR\" && php $CLI clear cache || true"

# (valgfrit) opdatér oversættelser
exec_in "cd \"$APP_DIR\" && php $CLI refresh translations || true"  # :contentReference[oaicite:4]{index=4}

# 3) Synk DB til metadata
exec_in "cd \"$APP_DIR\" && php $CLI sql diff --show || true"
exec_in "cd \"$APP_DIR\" && php $CLI sql diff --run"

# 4) (valgfrit) trig cron én gang
exec_in "cd \"$APP_DIR\" && php $CLI cron || true"

