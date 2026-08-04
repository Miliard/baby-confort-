<?php

namespace App\Filament\Resources\GuiaFotoResource\Pages;

use App\Filament\Resources\GuiaFotoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGuiaFotos extends ListRecords
{
    protected static string $resource = GuiaFotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('subir')
                ->label('📷 Subir fotos')
                ->url(fn () => \App\Filament\Pages\FotosPaquetes::getUrl()),
        ];
    }
}
