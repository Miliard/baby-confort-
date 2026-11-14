# Servidor de producción (FrankenPHP) para Railway.
#
# Antes el sitio arrancaba con "php -S" (Procfile), que es el servidor de
# pruebas de PHP: sin caché de código y pensado para una persona a la vez.
# FrankenPHP es un servidor de verdad, con el código ya compilado en memoria
# (OPcache) y compresión de las respuestas.
#
# Para volver al de antes: git revert de este commit (se borra este archivo y
# Railway vuelve a usar el Procfile).
FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions pdo_mysql intl zip gd exif bcmath opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Primero las librerías: esta capa se reutiliza mientras no cambie composer.lock.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && cp docker/php.ini "$PHP_INI_DIR/conf.d/zz-baby-confort.ini" \
    && chmod +x docker/arranque.sh

CMD ["sh", "/app/docker/arranque.sh"]
