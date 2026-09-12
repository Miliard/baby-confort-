<?php

/**
 * Credenciales de la API de WhatsApp de Meta.
 *
 * Van acá y no sueltas en el código por una razón concreta: cuando Laravel
 * guarda la configuración en caché (cosa que pasa al desplegar), la función
 * env() deja de devolver nada fuera de los archivos de config. Leyéndolas desde
 * acá funcionan siempre, con caché o sin ella.
 *
 * Los valores reales viven en las variables de Railway. Este archivo solo dice
 * de dónde sacarlos.
 */
return [

    // Token de acceso. El de prueba dura 24 horas; el de producción se saca
    // creando un usuario del sistema en business.facebook.com.
    'token' => env('WHATSAPP_TOKEN'),

    // Identificador del número (un número largo). NO es el número de teléfono.
    'phone_id' => env('WHATSAPP_PHONE_ID'),

    // Contraseña que inventamos nosotros y que Meta repite al registrar el
    // webhook, para comprobar que la dirección es realmente nuestra.
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    // Clave secreta de la app: con ella se comprueba que cada aviso del webhook
    // viene de Meta y no de un tercero.
    'app_secret' => env('WHATSAPP_APP_SECRET'),

    // Versión de la API de Meta contra la que hablamos.
    'version' => env('WHATSAPP_VERSION', 'v21.0'),

    // ─── Para el botón de conexión (registro insertado de Meta) ───────────────
    // Identificador público de la aplicación. No es secreto: viaja en el
    // JavaScript de la página, es lo normal.
    'app_id' => env('WHATSAPP_APP_ID', '1338503986007171'),

    // Identificador del ajuste de "Inicio de sesión con Facebook para empresas",
    // variación "Registro insertado de WhatsApp". Es el que hace que el diálogo
    // de Meta ofrezca el código QR de Coexistencia en vez de pedir SMS.
    'config_id' => env('WHATSAPP_CONFIG_ID', '1414357770650054'),

];
