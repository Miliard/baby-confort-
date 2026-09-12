<?php

namespace App\Filament\Resources\WaEtiquetaResource\Pages;

use App\Filament\Resources\WaEtiquetaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWaEtiquetas extends ListRecords
{
    protected static string $resource = WaEtiquetaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nueva etiqueta'),
        ];
    }
}
