#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/var/www/vmdockerwebdkatropim.westeurope.cloudapp.azure.com
WEB=atropim-docker-web-1

# 1) (kun nødvendig hvis du har ændret PHP-klasser/filnavne – harmless ellers)
docker exec -it "$WEB" sh -lc "cd \"$APP_DIR\" && [ -f composer.phar ] && php composer.phar dump-autoload -n || true"

# 2) Rescan moduler + ryd Atro-cache (så metadata, layouts, i18n m.m. opdages)
docker exec -it "$WEB" sh -lc "cd \"$APP_DIR\" && php run-postupdate.php"

# 3) Synk database hvis entityDefs har ændret skema
docker exec -it "$WEB" sh -lc "cd \"$APP_DIR\" && php index.php sql diff --run"
