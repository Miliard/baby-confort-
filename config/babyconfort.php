<?php

return [
    // Numero de WhatsApp donde llegan los pedidos (con codigo de pais, sin +).
    // Se define en .env como  WHATSAPP_NUMBER=50368601764
    'whatsapp' => env('WHATSAPP_NUMBER', '50368601764'),
];
