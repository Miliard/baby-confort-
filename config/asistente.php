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

    /*
    | Cómo conversa:
    |   conversacion · la clienta escribe como quiera y la IA lo entiende con
    |                  el catálogo. Botones solo para confirmar (total, teléfono
    |                  y la orden final). Es el que se eligió.
    |   botones      · como antes: menús y listas para tocar.
    |   ejemplos     · la IA conversa sola mirando cómo contestás VOS en tus
    |                  chats reales (el banco de ejemplos). El sistema solo
    |                  cuida precios, el envío, San Miguel y arma la orden.
    */
    'modo' => env('ASISTENTE_MODO', 'conversacion'),

    // El banco de ejemplos (modo "ejemplos"): de cuántos días de chats se
    // arma, cuántos guarda como mucho y cuántos le pasa a la IA por turno.
    'ejemplos' => [
        'dias'    => (int) env('ASISTENTE_EJEMPLOS_DIAS', 180),
        'maximo'  => 8000,
        'cuantos' => 10,
    ],

    // La IA que entiende los pedidos escritos. gpt-5-mini es barato y rápido;
    // es independiente de OPENAI_MODELO (el de "Mejorar" y las órdenes).
    // Con ASISTENTE_IA=false se apaga y quedan solo las reglas.
    'usar_ia'   => (bool) env('ASISTENTE_IA', true),
    'modelo_ia' => env('ASISTENTE_MODELO', 'gpt-5-mini'),

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

    // Cuántas presentaciones se mandan con foto por talla. Las demás no se
    // pierden: aparecen en la lista de "Comprar" con talla, tipo y precio.
    // Cada foto es un mensaje que Meta cobra (desde octubre de 2026).
    'max_fotos' => (int) env('ASISTENTE_MAX_FOTOS', 4),

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

    // La numeración que usan otras marcas ("talla 6" de Pampers) y a cuál de
    // las nuestras equivale. Si una clienta dice un número que no está acá,
    // se le pregunta el peso.
    'tallas_numericas' => [
        0 => 'RN',
        1 => 'RN',
        2 => 'S',
        3 => 'M',
        4 => 'L',
        5 => 'XL',
        6 => 'XXL',
        7 => 'XXXL',
    ],

    // Cómo le dicen a cada talla otras marcas y la gente. Cuando la clienta
    // usa uno de estos nombres, se le aclara a cuál de las nuestras equivale
    // ("La talla G es nuestra talla L, en otras marcas talla 4").
    'tallas_alias' => [
        'S'    => ['P', 'pequeña'],
        'L'    => ['G', 'grande'],
        'XL'   => ['XG', 'extra grande'],
        'XXL'  => ['XXG'],
        'XXXL' => ['XXXG'],
    ],

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
