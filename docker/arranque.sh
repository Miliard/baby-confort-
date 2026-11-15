#!/bin/sh
# Arranque del sitio en Railway. Sirve para los dos servidores:
#   · con FrankenPHP (si el proyecto se armó con el Dockerfile), el rápido;
#   · si no está, el de siempre (php -S), con los mismos límites de antes.
# Así, quitar el Dockerfile (git revert) deja el sitio andando igual que antes.

cd "$(dirname "$0")/.." || exit 1

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/app/public storage/app/private storage/logs bootstrap/cache

php artisan storage:link --force || true
php artisan migrate --force || echo "OJO: la migración falló; el sitio arranca igual."

if command -v frankenphp >/dev/null 2>&1 && [ -f docker/Caddyfile ]; then
    php artisan package:discover --ansi || true
    php artisan view:cache || true
    echo "Arrancando con FrankenPHP"
    exec frankenphp run --config docker/Caddyfile --adapter caddyfile
fi

echo "Arrancando con php -S (servidor de siempre)"
export PHP_CLI_SERVER_WORKERS=20
exec php -d upload_max_filesize=25M -d post_max_size=30M -d memory_limit=512M \
    -d max_execution_time=120 -d max_file_uploads=40 \
    -S 0.0.0.0:"${PORT:-8080}" -t public server.php
