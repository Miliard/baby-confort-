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
