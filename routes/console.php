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

// Cada media hora se le pregunta al courier por los paquetes que están
// esperando entrega, y las conversaciones se mueven a Entregados solas.
//
// La media hora no es al azar: hacen falta dos revisiones seguidas diciendo
// "entregado" antes de mover nada, porque al repartidor se le ha ido marcarlo
// por error y corregirlo minutos después. Con este intervalo, la corrección
// llega antes de la segunda vuelta y el error nunca se ve acá.
//
// Igual que la limpieza de fotos: esto necesita un programador levantado. En
// Railway se agrega como servicio de cron corriendo "php artisan schedule:run"
// cada minuto, o directamente "php artisan entregas:revisar" cada media hora.
// Sin eso, el comando existe pero nadie lo llama.
Schedule::command('entregas:revisar')->everyThirtyMinutes()->withoutOverlapping();
