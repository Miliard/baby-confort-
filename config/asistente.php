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

    // Cada presentación sale como FOTO grande con sus datos y el enlace
    // abajo. En false sale como antes: texto con la tarjetita del enlace.
    // Solo se manda como foto si es JPG o PNG (WhatsApp no acepta otras); si
    // no, va como tarjeta.
    'presentacion_como_foto' => (bool) env('ASISTENTE_COMO_FOTO', true),

    /*
    |--------------------------------------------------------------------------
    | Tallas que ofrece, en este orden
    |--------------------------------------------------------------------------
    |
    | Tienen que escribirse igual que en el catálogo. Los pesos salen de
    | config/tallas_peso.php, la misma tabla de la tienda.
    |
    */
    'tallas' => ['RN', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4 A 7 AÑOS', '8 A 14 AÑOS'],

    // Hasta este peso (kg) se sugiere recién nacido, mientras config/tallas_peso.php
    // no tenga el rango de RN. Cuando lo pongas ahí, manda ese.
    'rn_hasta_kg' => 4.5,

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
    /*
    |--------------------------------------------------------------------------
    | Donde la entrega la hacés vos
    |--------------------------------------------------------------------------
    |
    | En estos municipios el envío no se cotiza solo: depende de la colonia
    | (hay lugares cerca donde no cobrás y lugares lejos donde sí). El
    | asistente pregunta la colonia o el lugar de entrega y te pasa el chat
    | para que vos le digas el costo. Nunca dice "gratis".
    |
    */
    'entrega_propia' => [
        'municipios' => ['San Miguel'],
        'etiqueta'   => 'San Miguel',   // se le pone al chat, si existe
        'pregunta'   => '¡Perfecto! 😊 En San Miguel la entrega la hacemos nosotros. ¿En qué colonia, barrio o lugar sería la entrega? Así le confirmamos el costo del envío 🚚',
        'cierre'     => 'Gracias 😊 En un momento le confirmamos el costo del envío a esa zona.',
    ],

    'envio' => [
        'solo_tabla' => false,
        'municipios' => [
            // 'San Salvador' => 2.50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preguntas que contesta solo
    |--------------------------------------------------------------------------
    |
    | Si el cliente pregunta algo de esto, se le contesta con este texto y se
    | sigue donde iba. Las palabras se comparan sin tildes ni mayúsculas.
    | Lo que se diga acá tiene que ser cierto: es lo que va a leer el cliente.
    |
    | Las preguntas de salud (rozaduras, alergias, irritación) NO van acá:
    | esas siempre te las pasa a vos.
    |
    */
    'respuestas' => [
        [
            'palabras' => ['calientes', 'caliente', 'calor', 'acaloran', 'sudan', 'sudor', 'sofocan', 'transpiran', 'respiran', 'frescos', 'frescas',
                           'piel sensible', 'pieles sensibles', 'delicada', 'delicado', 'hipoalergenico', 'hipoalergenicos', 'dermatest', 'certificado', 'certificacion', 'son buenos', 'son buenas', 'calidad'],
            'texto'    => 'Nuestros productos cuentan con certificación *Dermatest de Alemania*, lo que significa que han sido evaluados en pruebas relacionadas con la seguridad y compatibilidad con la piel, obteniendo una calificación de excelencia. Es un respaldo de calidad que brinda mayor confianza al elegir el cuidado para su bebé 👶✨',
        ],
        [
            'palabras' => ['absorben', 'absorbe', 'absorcion', 'se pasan', 'se rebalsan', 'derrama', 'derraman', 'toda la noche', 'aguantan', 'aguanta', 'se llenan'],
            'texto'    => 'Tienen alta absorción y protegen durante toda la noche 🌙',
        ],
        [
            'palabras' => ['que marca', 'cual marca', 'de que marca', 'son originales', 'de donde son', 'de donde vienen', 'son de el salvador', 'son de aqui', 'son importados', 'australia', 'son chinos'],
            'texto'    => 'Son pañales *Aiwibi* y vienen desde Australia 🇦🇺',
        ],
        [
            'palabras' => ['donde estan', 'donde quedan', 'donde queda', 'ubicados', 'ubicada', 'ubicacion de la tienda', 'tienda fisica', 'tienen tienda', 'tienen local', 'pasar a recoger', 'pasar a traer', 'recoger en tienda'],
            'texto'    => 'Tenemos tienda en *San Miguel, plaza Concepción* (a dos cuadras de Sertracen) 📍 Y también le enviamos a domicilio a todo El Salvador 🚚',
        ],
        [
            'palabras' => ['forma de pago', 'como pago', 'como se paga', 'como seria el pago', 'contra entrega', 'transferencia', 'tarjeta', 'pago al recibir', 'se paga al recibir', 'efectivo', 'numero de cuenta'],
            'texto'    => 'Puede pagar al recibir o por transferencia, como usted prefiera 😊',
        ],
        [
            'palabras' => ['cuanto cuesta el envio', 'cuanto es el envio', 'cuanto sale el envio', 'costo de envio', 'costo del envio', 'precio del envio', 'cobran envio', 'el envio', 'envios', 'envio nacional', 'envios nacionales', 'acen envios', 'asen envios', 'hacen entregas', 'y el envio', 'entregas en', 'entregan en', 'hacen envios', 'hace envios', 'hacen envio', 'envian a', 'llegan a', 'mandan a', 'a domicilio', 'todo el pais'],
            'texto'    => "⚡ ¡El envío más barato del mercado! 🚚\n💲 Solo \${envio} (¡lleve lo que lleve!)\n⏱️ Entrega rápida en 24 horas (en la mayoría de pedidos)\n🏠 Hasta su casa o lugar de trabajo",
        ],
        [
            'palabras' => ['cuanto tarda', 'cuanto tardan', 'cuando llega', 'cuando me llega', 'cuando llegaria', 'tiempo de entrega', 'cuantos dias', 'en cuanto tiempo', 'para hoy', 'hoy mismo', 'para manana', 'llega hoy', 'el domingo', 'los domingos'],
            'texto'    => 'La entrega es en {entrega} con Express El Salvador 🚚. Los domingos no despachamos: si lo necesita para el domingo, hay que encargarlo un día antes.',
        ],
        [
            'palabras' => ['promocion', 'promociones', 'promo', 'promos', 'oferta', 'ofertas', '3 x 25', '3x25', '3 por 25', 'combo'],
            'texto'    => '{promos}',
        ],
        [
            'palabras' => ['cuando tendran', 'cuando van a tener', 'cuando entran', 'cuando entrarian', 'cuando les llega', 'cuando vuelven', 'cuando hay', 'de nuevo disponible', 'nuevamente', 'cuando tenga me avisa', 'me avisa cuando', 'avisame cuando', 'aviseme cuando'],
            'texto'    => 'Vienen desde Australia por barco 🚢 y ya están en camino. Apenas entren le avisamos por aquí 😊',
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
