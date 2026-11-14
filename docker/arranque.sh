#!/bin/sh
# Lo que corre al arrancar el servidor en Railway (lo mismo que hacía el Procfile).

cd /app || exit 1

# Las carpetas donde Laravel escribe, por si el disco viene vacío.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/app/public storage/app/private storage/logs bootstrap/cache

# La lista de paquetes instalados (el build no la arma).
php artisan package:discover --ansi || true

php artisan storage:link --force || true
php artisan migrate --force || echo "OJO: la migración falló; el sitio arranca igual."

# Vistas ya compiladas: el primer clic de cada pantalla no espera.
php artisan view:cache || true

exec frankenphp run --config /app/docker/Caddyfile --adapter caddyfile
