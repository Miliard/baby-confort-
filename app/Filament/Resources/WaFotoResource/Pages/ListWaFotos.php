<?php

namespace App\Filament\Resources\WaFotoResource\Pages;

use App\Filament\Resources\WaFotoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWaFotos extends ListRecords
{
    protected static string $resource = WaFotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Subir una foto'),
        ];
    }
}
