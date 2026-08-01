<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class CrearGuia extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Crear guía';
    protected static ?string $title = 'Crear guía · Intérprete Sistrack';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.crear-guia';
}
