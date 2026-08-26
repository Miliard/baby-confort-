<?php

/**
 * Enrutador para el servidor de PHP.
 *
 * Existe para poder arrancar el servidor con límites propios (-d en el
 * Procfile). El servidor de Railway venía con el tamaño máximo de subida en
 * CERO, así que ninguna foto podía entrar, del tamaño que fuera.
 *
 * La carpeta pública se pasa con -t public, así que desde afuera solo se
 * puede llegar a lo que hay ahí adentro.
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Si el archivo existe dentro de /public, que lo sirva el servidor tal cual
// (imágenes, css, js). Si no, lo atiende Laravel.
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

require_once __DIR__ . '/public/index.php';
