<?php

/**
 * El asistente de ventas por WhatsApp.
 *
 * QUIÉN LO RECIBE — tres variables en Railway:
 *
 *   ASISTENTE=true                   · lo enciende. Sin esto no le contesta a nadie.
 *   ASISTENTE_SOLO_NUMEROS=79670366  · mientras se prueba: SOLO estos números
 *                                      (8 dígitos, separados por coma).
 *   ASISTENTE_PARA_TODOS=true        · recién cuando esté probado. Sin esto,
 *                                      aunque la lista quede vacía, no le
 *                                      contesta a ningún cliente.
 *
 * Todo lo demás de este archivo son las tablas que usa para contestar. Lo que
 * dice "CONFIRMAR" son números puestos de ejemplo: corregilos con los tuyos.
 */
return [

    'activo' => (bool) env('ASISTENTE', false),

    'solo_numeros' => array_values(array_filter(array_map(
        fn ($n) => substr(preg_replace('/\D/', '', $n), -8),
        explode(',', (string) env('ASISTENTE_SOLO_NUMEROS', ''))
    ))),

    'para_todos' => (bool) env('ASISTENTE_PARA_TODOS', false),

    /*
    |--------------------------------------------------------------------------
    | Frenos
    |--------------------------------------------------------------------------
    */

    // Si el cliente manda varios mensajes seguidos, se espera este rato y se
    // contestan juntos, en vez de una respuesta por mensaje.
    'espera_segundos' => 3,

    // Si Wil escribió en el chat en estas horas, el asistente no arranca.
    'silencio_si_wil_horas' => 12,

    // Máximo de respuestas del asistente por chat en una hora (un pedido
    // completo son unas 15). Si se pasa,
    // algo raro está pasando: se lo pasa a Wil.
    'max_turnos_hora' => 40,

    // Si la conversación quedó parada este rato, el siguiente mensaje empieza
    // de cero. Y si vuelve a saludar ("hola") después de este otro rato, también.
    'reiniciar_tras_minutos'      => 120,
    'reiniciar_si_saluda_minutos' => 10,

    // Más paquetes que esto en un solo producto huele a mayoreo: a Wil.
    'max_paquetes' => 10,

    // Mandar también las fotos de cómo queda puesto (las de "fotos de uso" de
    // cada talla), además de la tarjeta del producto.
    'con_fotos_uso' => (bool) env('ASISTENTE_FOTOS_USO', false),

    /*
    |--------------------------------------------------------------------------
    | Tallas que ofrece, en este orden
    |--------------------------------------------------------------------------
    |
    | Tienen que escribirse igual que en el catálogo. Los pesos salen de
    | config/tallas_peso.php, la misma tabla de la tienda.
    |
    */
    'tallas' => ['S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4 A 7 AÑOS', '8 A 14 AÑOS'],

    // Las de niño grande solo se sugieren por peso si ninguna de bebé le queda.
    'tallas_nino' => ['4 A 7 AÑOS', '8 A 14 AÑOS'],

    // Productos que NO ofrece aunque tengan talla S, M, L… (el pañal de adulto
    // también tiene talla M). Son las categorías del catálogo.
    'excluir_categorias' => ['adulto', 'mujer', 'accesorios'],

    /*
    |--------------------------------------------------------------------------
    | Cinta o calzoncito
    |--------------------------------------------------------------------------
    |
    | Se decide por el NOMBRE del producto: si tiene alguna de estas palabras es
    | calzoncito; si no, es de cinta.
    |
    */
    'palabras_calzoncito' => ['calzoncito', 'calzon', 'pants', 'pant', 'braga', 'training'],

    /*
    |--------------------------------------------------------------------------
    | Pañales al día en cada talla — CONFIRMAR
    |--------------------------------------------------------------------------
    |
    | Con esto sugiere cuántos paquetes llevar para un mes cuando el bebé está
    | entre dos tallas. Si una talla queda en null, no sugiere cantidades.
    |
    */
    'consumo_diario' => [
        'S'    => 8,
        'M'    => 7,
        'L'    => 6,
        'XL'   => 5,
        'XXL'  => 5,
        'XXXL' => 4,
        '4 A 7 AÑOS'  => 3,
        '8 A 14 AÑOS' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Envío por municipio
    |--------------------------------------------------------------------------
    |
    | 'Soyapango' => 3.00, uno por renglón, escrito como en la lista de
    | municipios. Los que no estén acá cobran el envío general de
    | Configuración (con su envío gratis si lo tenés puesto).
    |
    | Si ponés 'solo_tabla' => true, un municipio que no esté acá no se cotiza:
    | se le pasa el chat a Wil.
    |
    */
    'envio' => [
        'solo_tabla' => false,
        'municipios' => [
            // 'San Salvador' => 2.50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Textos
    |--------------------------------------------------------------------------
    */
    'textos' => [
        'saludo'     => '¡Hola! 😊 Con gusto le ayudamos.',
        'diferencia' => "La de *cinta* se pega a los lados: ideal para bebés que todavía no caminan.\n"
                      . "El *calzoncito* se sube como ropa interior: más cómodo para bebés que ya gatean o caminan.",
        'asesor'     => 'Le comunico con un asesor, en un momento le atiende 😊',
        'gracias'    => '¡Gracias por su compra! 💙 En breve le confirmamos el envío.',
        'prueba'     => '🧪 PEDIDO DE PRUEBA — no procesar',
    ],
];
