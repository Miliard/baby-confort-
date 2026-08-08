<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Sube varias fotos de etiquetas de golpe. Lee el QR de cada foto para saber
 * a qué guía pertenece y la guarda, de modo que el cliente vea la foto de SU
 * paquete al abrir su enlace de seguimiento.
 *
 * Las fotos subidas quedan listadas aquí (vienen de la base de datos), así que
 * no se pierden aunque se cierre la pantalla o se cambie de aplicación.
 */
class FotosPaquetes extends Page
{
    // Ahora vive dentro de la pestaña "Guías" (sección Fotos); se deja la página
    // por si se quiere abrir sola, pero fuera del menú para no duplicar.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Fotos de paquetes';
    protected static ?string $title = 'Fotos de paquetes';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.fotos-paquetes';

}
