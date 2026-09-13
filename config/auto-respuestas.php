<?php

/**
 * Respuestas que salen solas, sin esperar a un agente.
 *
 * Se edita este archivo y listo: no hace falta tocar código. Cada disparador
 * tiene sus palabras clave y su texto. El orden importa: gana el primero que
 * coincida, así que lo más específico va arriba.
 *
 * Las palabras se comparan sin tildes, sin mayúsculas y sin signos, y sueltas
 * dentro de la frase. "q talla" y "qué tallas?" caen las dos en "talla".
 */
return [

    // Interruptor general. Apagado: nadie recibe respuestas automáticas, sin
    // importar lo que digan los disparadores de abajo. Se prefiere contestar a
    // mano, que es lo que el cliente nota.
    //
    // Para volver a encenderlas, poner true acá. Los textos y las palabras
    // clave quedan guardados tal cual, no hay que rehacerlos.
    'activas' => env('AUTO_RESPUESTAS', false),

    // Se responde como mucho una vez cada tantos minutos por conversación, para
    // que un cliente que escribe tres veces seguidas no reciba tres tablas.
    'espera_minutos' => 180,

    'disparadores' => [

        [
            'nombre'   => 'tallas',
            'activo'   => true,
            'palabras' => [
                'talla', 'tallas', 'tayas', 'taya',
                'tamaño', 'tamano', 'tamaños', 'tamanos',
                'medida', 'medidas',
                'que talla', 'cual talla', 'cuál talla', 'q talla',
                'peso de mi bebe', 'cuanto pesa',
            ],

            // Si va vacío, se arma solo con config/tallas_peso.php, que ya tiene
            // los rangos reales. Poner texto acá solo si se quiere otra cosa.
            'texto' => '',

            'encabezado' => "¡Hola! 💙 Estas son nuestras tallas según el peso del bebé:",
            'cierre'     => "¿Sabés cuánto pesa tu bebé? Decímelo y te digo cuál le queda. 👶",
        ],

    ],
];
