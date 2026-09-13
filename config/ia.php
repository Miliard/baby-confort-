<?php

/**
 * El ayudante que corrige los mensajes antes de mandarlos.
 *
 * Funciona con cualquiera de los dos proveedores grandes. Se elige uno, se
 * carga su clave en Railway y listo. Si no hay clave, el botón no aparece y el
 * panel sigue funcionando igual que siempre.
 *
 * Las claves NUNCA van en el código ni se pegan en un chat: solo en las
 * variables de Railway.
 */
return [

    // 'openai' o 'anthropic'. Wil ya tenía cuenta en OpenAI, así que va esa.
    'proveedor' => env('IA_PROVEEDOR', 'openai'),

    'anthropic' => [
        'clave'  => env('ANTHROPIC_API_KEY'),
        // Modelo chico y barato: para corregir un mensaje de WhatsApp sobra.
        'modelo' => env('ANTHROPIC_MODELO', 'claude-haiku-4-5-20251001'),
    ],

    'openai' => [
        'clave'  => env('OPENAI_API_KEY'),
        'modelo' => env('OPENAI_MODELO', 'gpt-4o-mini'),
    ],

    // El que pasa a texto las notas de voz de los clientes.
    'modelo_audio' => env('OPENAI_MODELO_AUDIO', 'whisper-1'),

    // Tope de caracteres que se manda a corregir. Es también un freno de mano
    // para el costo: nadie manda una novela por WhatsApp.
    'maximo' => 1200,

    /*
    |--------------------------------------------------------------------------
    | Lo que el corrector puede decir del producto
    |--------------------------------------------------------------------------
    |
    | Estos son los ÚNICOS datos que tiene permitido mencionar por su cuenta.
    | Todo lo que no esté en esta lista, no existe para él: no puede inventar
    | certificaciones, materiales ni beneficios.
    |
    | Agregá o quitá renglones libremente. Lo que pongas acá se lo va a poder
    | decir a un cliente, así que tiene que ser cierto y comprobable.
    |
    */
    'datos_producto' => [
        'Los pañales Aiwibi cuentan con certificación Dermatest.',
        'Son hipoalergénicos, pensados para pieles sensibles.',
        'Tienen alta absorción y protección durante toda la noche.',
        'Hay tallas desde recién nacido hasta talla especial para niños grandes.',
        'La entrega es a domicilio en todo El Salvador.',
        'Se paga al recibir el paquete.',
    ],

];
