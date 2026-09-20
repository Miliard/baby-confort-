<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Todas las madrugadas se sueltan del disco las fotos ya vencidas.
// El registro del pedido se queda: solo se va la imagen.
//
// OJO: esto SOLO corre si hay un programador levantado. A propósito no se
// lanza junto al servidor web: en este servidor comparten el mismo proceso y
// cada arranque del programador le robaba trabajadores a las subidas, que se
// quedaban colgadas. La limpieza igual se hace sola al subir fotos, una vez
// al día (ver GuiaFotoController::limpiarSiTocaHoy).
Schedule::command('fotos:limpiar')->dailyAt('03:15')->withoutOverlapping();

// El paso a "Entregados" está APAGADO y se hace a mano.
//
// Se probó automático, preguntándole al courier cada media hora, y el problema
// no fue la consulta sino de qué guía. El panel busca la guía por teléfono, y
// un cliente que vuelve a pedir tiene dos: la vieja, entregada, y la nueva, que
// todavía no tiene número porque lo asigna Sistrack. Encontraba la vieja y daba
// el pedido nuevo por entregado el mismo día que entraba.
//
// Para que funcione de verdad hay que atar cada conversación a SU guía cuando
// se arma, no buscarla por teléfono después. Hasta entonces, a mano: equivocarse
// en esto cuesta pedidos.
//
// El comando "entregas:revisar" sigue existiendo por si se retoma.
// Schedule::command('entregas:revisar')->everyThirtyMinutes()->withoutOverlapping();
