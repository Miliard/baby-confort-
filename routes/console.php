<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Todas las madrugadas se sueltan del disco las fotos ya vencidas.
// El registro del pedido se queda: solo se va la imagen.
Schedule::command('fotos:limpiar')->dailyAt('03:15')->withoutOverlapping();
