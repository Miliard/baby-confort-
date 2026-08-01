<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Pestaña dedicada a preparar guías para Sistrack (optimizada para el teléfono).
 *
 * Flujo: pegas la "Orden de envío" de WhatsApp → el intérprete llena las columnas
 * de la plantilla de Sistrack → se agrega a la lista. Repites con varias órdenes y
 * al final descargas UN solo Excel para subirlo a Importación masiva de Sistrack
 * y crear todas las guías de una vez.
 *
 * Todo ocurre en el navegador (la lista se guarda en el teléfono), no toca la base de datos.
 */
class CrearGuia extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Crear guías';
    protected static ?string $title = 'Crear guías para Sistrack';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.crear-guia';
}
